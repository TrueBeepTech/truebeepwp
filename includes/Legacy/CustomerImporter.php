<?php

namespace Truebeep\Legacy;

use Truebeep\Traits\ApiHelper;

/**
 * Handles customer import to Truebeep API
 */
class CustomerImporter
{
    use ApiHelper;

    const BATCH_SIZE = 20;

    /**
     * Process batch of customers using bulk API
     */
    public function process_batch($customer_ids)
    {
        $result = [
            'success' => true,
            'processed' => [],
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        // Filter out already imported customers and prepare data
        $customers_to_import = [];
        $user_id_map = [];
        
        foreach ($customer_ids as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                $result['skipped']++;
                $result['errors'][$user_id] = 'User not found';
                continue;
            }

            // Check if already imported
            $existing_truebeep_id = get_user_meta($user_id, '_truebeep_customer_id', true);
            if (!empty($existing_truebeep_id)) {
                $verify_response = $this->get_truebeep_customer($existing_truebeep_id);
                if (!is_wp_error($verify_response) && $verify_response['success']) {
                    $result['skipped']++;
                    $result['processed'][] = $user_id;
                    continue;
                }
            }

            // Prepare customer data
            $customer_data = $this->prepare_customer_data($user);
            $customer_data['wordpress_user_id'] = $user_id;
            $customers_to_import[] = $customer_data;
            $user_id_map[] = $user_id;
        }

        // If no customers to import, return early
        if (empty($customers_to_import)) {
            return $result;
        }

        // Make bulk API call
        $response = $this->create_truebeep_customers_bulk($customers_to_import);
        
        if (is_wp_error($response)) {
            error_log('Truebeep Bulk API Error: ' . $response->get_error_message());
            foreach ($user_id_map as $user_id) {
                $result['failed']++;
                $result['errors'][$user_id] = $response->get_error_message();
                $result['processed'][] = $user_id;
            }
            return $result;
        }

        if (!$response['success']) {
            error_log('Truebeep Bulk API Failed: ' . ($response['error'] ?? 'Unknown error'));
            foreach ($user_id_map as $user_id) {
                $result['failed']++;
                $result['errors'][$user_id] = $response['error'] ?? 'Failed to create customer';
                $result['processed'][] = $user_id;
            }
            return $result;
        }

        // Process the bulk response
        $this->process_bulk_response($response, $user_id_map, $customers_to_import, $result);

        update_option('truebeep_import_last_update', time());
        
        $this->log_batch_result($result);

        return $result;
    }

    /**
     * Process bulk API response
     */
    private function process_bulk_response($response, $user_id_map, $customers_data, &$result)
    {
        $response_data = $response['data'];
        
        // Handle different response structures
        if (isset($response_data['data']) && is_array($response_data['data'])) {
            $created_customers = $response_data['data'];
        } elseif (isset($response_data['customers']) && is_array($response_data['customers'])) {
            $created_customers = $response_data['customers'];
        } elseif (is_array($response_data) && isset($response_data[0])) {
            $created_customers = $response_data;
        } else {
            // If structure is unclear, try to extract customer IDs
            $created_customers = [$response_data];
        }

        // Match created customers with WordPress user IDs
        foreach ($created_customers as $index => $customer) {
            if (!isset($user_id_map[$index])) {
                continue;
            }
            
            $user_id = $user_id_map[$index];
            $result['processed'][] = $user_id;
            
            // Extract customer ID from response
            $truebeep_customer_id = null;
            if (isset($customer['_id'])) {
                $truebeep_customer_id = $customer['_id'];
            } elseif (isset($customer['id'])) {
                $truebeep_customer_id = $customer['id'];
            } elseif (isset($customer['customer_id'])) {
                $truebeep_customer_id = $customer['customer_id'];
            }
            
            if (!empty($truebeep_customer_id)) {
                // Store the Truebeep customer ID
                update_user_meta($user_id, '_truebeep_customer_id', $truebeep_customer_id);
                update_user_meta($user_id, '_truebeep_import_date', current_time('mysql'));
                
                // Import customer statistics
                $this->import_customer_statistics($user_id, $truebeep_customer_id);
                
                $result['successful']++;
            } else {
                $result['failed']++;
                $result['errors'][$user_id] = 'No customer ID in response';
                error_log('No customer ID found for user ' . $user_id . ' in response: ' . json_encode($customer));
            }
        }

        // Handle any customers that weren't in the response
        $processed_count = count($result['processed']);
        $expected_count = count($user_id_map);
        
        if ($processed_count < $expected_count) {
            for ($i = $processed_count; $i < $expected_count; $i++) {
                if (isset($user_id_map[$i])) {
                    $user_id = $user_id_map[$i];
                    $result['processed'][] = $user_id;
                    $result['failed']++;
                    $result['errors'][$user_id] = 'Not found in API response';
                }
            }
        }
    }

    /**
     * Prepare customer data
     */
    private function prepare_customer_data($user)
    {
        $customer_data = [
            'firstName' => get_user_meta($user->ID, 'billing_first_name', true) ?: $user->display_name,
            'lastName' => get_user_meta($user->ID, 'billing_last_name', true) ?: $user->last_name,
            'email' => $user->user_email,
            'source' => 'WordPress'
        ];

        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (!empty($phone)) {
            $customer_data['phone'] = $phone;
        }

        $customer_data['metadata'] = [
            'wordpress_user_id' => $user->ID,
            'import_date' => current_time('mysql'),
            'user_registered' => $user->user_registered
        ];

        return $customer_data;
    }

    /**
     * Import customer order statistics
     */
    private function import_customer_statistics($user_id, $truebeep_customer_id)
    {
        $customer_orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => -1,
            'return' => 'ids'
        ]);

        if (!empty($customer_orders)) {
            $total_spent = 0;
            $order_count = count($customer_orders);
            
            foreach ($customer_orders as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $total_spent += $order->get_total();
                }
            }

            update_user_meta($user_id, '_truebeep_imported_stats', [
                'order_count' => $order_count,
                'total_spent' => $total_spent,
                'import_date' => current_time('mysql')
            ]);

            if ($total_spent > 0) {
                $this->award_historical_points($user_id, $truebeep_customer_id, $total_spent);
            }
        }
    }

    /**
     * Award historical points
     */
    private function award_historical_points($user_id, $truebeep_customer_id, $total_spent)
    {
        $points = $this->calculate_loyalty_points($total_spent, $user_id);
        
        if ($points > 0) {
            $response = $this->update_loyalty_points(
                $truebeep_customer_id, 
                $points, 
                'increment', 
                'wordpress_import'
            );

            if (!is_wp_error($response) && $response['success']) {
                update_user_meta($user_id, '_truebeep_historical_points_awarded', $points);
            }
        }
    }

    /**
     * Get customers to import
     */
    public function get_customers_to_import()
    {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT u.ID 
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->usermeta} tb ON u.ID = tb.user_id AND tb.meta_key = '_truebeep_customer_id'
            WHERE um.meta_key = %s
            AND um.meta_value LIKE %s
            AND (tb.meta_value IS NULL OR tb.meta_value = '')
            ORDER BY u.ID ASC
        ", $wpdb->prefix . 'capabilities', '%customer%');

        $user_ids = $wpdb->get_col($query);
        $order_customer_ids = $this->get_order_customer_ids();
        
        $all_customer_ids = array_unique(array_merge($user_ids, $order_customer_ids));
        
        return array_values($all_customer_ids);
    }

    /**
     * Get customer IDs from orders
     */
    private function get_order_customer_ids()
    {
        global $wpdb;

        $query = "
            SELECT DISTINCT pm.meta_value as customer_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->usermeta} tb 
                ON pm.meta_value = tb.user_id AND tb.meta_key = '_truebeep_customer_id'
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key = '_customer_user'
            AND pm.meta_value > 0
            AND (tb.meta_value IS NULL OR tb.meta_value = '')
        ";

        return $wpdb->get_col($query);
    }

    /**
     * Log batch result
     */
    private function log_batch_result($result)
    {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'processed' => $result['processed'],
            'successful' => $result['successful'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors']
        ];

        $import_log = get_option('truebeep_import_log', []);
        array_unshift($import_log, $log_entry);
        $import_log = array_slice($import_log, 0, 100);
        
        update_option('truebeep_import_log', $import_log);
    }

    /**
     * Get import statistics
     */
    public function get_import_statistics()
    {
        $total_customers = count($this->get_customers_to_import());
        $progress = get_option('truebeep_import_progress', [
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_batches' => 0,
            'completed_batches' => 0
        ]);
        
        if (empty($progress['total']) || $progress['total'] == 0) {
            $progress['total'] = $total_customers;
        }
        
        $progress['remaining'] = $total_customers;
        $progress['percentage'] = $progress['total'] > 0 
            ? round(($progress['processed'] / $progress['total']) * 100, 2) 
            : 0;

        return $progress;
    }
}