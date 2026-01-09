<?php

namespace Truebeep\Legacy;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Truebeep\Traits\ApiHelper;

/**
 * Customer Syncer
 * 
 * Handles synchronization of WordPress users with Truebeep platform.
 * Manages bulk customer creation, data mapping, and sync statistics.
 * 
 * @package Truebeep\Legacy
 * @since 1.0.0
 */
class CustomerSyncer
{
    use ApiHelper;

    /**
     * Process batch of customers using bulk API
     * 
     * Synchronizes a batch of WordPress users with Truebeep platform
     * using the bulk customer creation API endpoint.
     * 
     * @param array $customer_ids Array of WordPress user IDs to sync
     * @return array {
     *     Processing result
     *     
     *     @type bool  $success    Whether batch processed successfully
     *     @type array $processed  Array of processed user IDs
     *     @type int   $successful Count of successfully synced customers
     *     @type int   $failed     Count of failed sync attempts
     *     @type int   $skipped    Count of skipped customers
     *     @type array $errors     Array of error messages keyed by user ID
     * }
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

        $customers_to_sync = [];
        $user_id_map = [];
        
        foreach ($customer_ids as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                $result['skipped']++;
                $result['errors'][$user_id] = 'User not found';
                continue;
            }

            $existing_truebeep_id = get_user_meta($user_id, '_truebeep_customer_id', true);
            if (!empty($existing_truebeep_id)) {
                $verify_response = $this->get_truebeep_customer($existing_truebeep_id);
                if (!is_wp_error($verify_response) && $verify_response['success']) {
                    $result['skipped']++;
                    $result['processed'][] = $user_id;
                    continue;
                }
            }

            $customer_data = $this->prepare_customer_data($user);
            $customer_data['wordpress_user_id'] = $user_id;
            $customers_to_sync[] = $customer_data;
            $user_id_map[] = $user_id;
        }

        if (empty($customers_to_sync)) {
            return $result;
        }

        $response = $this->create_truebeep_customers_bulk($customers_to_sync);
        
        if (is_wp_error($response)) {
            truebeep_log('Bulk Sync Error: ' . $response->get_error_message(), 'CustomerSyncer');
            foreach ($user_id_map as $user_id) {
                $result['failed']++;
                $result['errors'][$user_id] = $response->get_error_message();
                $result['processed'][] = $user_id;
            }
            return $result;
        }

        if (!$response['success']) {
            truebeep_log('Bulk Sync Failed: ' . ($response['error'] ?? 'Unknown error'), 'CustomerSyncer');
            foreach ($user_id_map as $user_id) {
                $result['failed']++;
                $result['errors'][$user_id] = $response['error'] ?? 'Failed to sync customer';
                $result['processed'][] = $user_id;
            }
            return $result;
        }

        $this->process_bulk_response($response, $user_id_map, $customers_to_sync, $result);

        update_option('truebeep_sync_last_update', time());
        
        $this->log_batch_result($result);

        return $result;
    }

    /**
     * Process bulk API response
     * 
     * Maps API response data back to WordPress users and updates
     * their metadata with Truebeep customer IDs.
     * 
     * @param array $response       API response data
     * @param array $user_id_map    Array mapping response index to user IDs
     * @param array $customers_data Original customer data sent to API
     * @param array &$result        Result array to update (passed by reference)
     * @return void
     */
    private function process_bulk_response($response, $user_id_map, $customers_data, &$result)
    {
        $response_data = $response['data'];
        
        if (isset($response_data['data']) && is_array($response_data['data'])) {
            $created_customers = $response_data['data'];
        } elseif (isset($response_data['customers']) && is_array($response_data['customers'])) {
            $created_customers = $response_data['customers'];
        } elseif (is_array($response_data) && isset($response_data[0])) {
            $created_customers = $response_data;
        } else {
            $created_customers = [$response_data];
        }

        foreach ($created_customers as $index => $customer) {
            if (!isset($user_id_map[$index])) {
                continue;
            }
            
            $user_id = $user_id_map[$index];
            $result['processed'][] = $user_id;
            
            $truebeep_customer_id = null;
            if (isset($customer['_id'])) {
                $truebeep_customer_id = $customer['_id'];
            } elseif (isset($customer['id'])) {
                $truebeep_customer_id = $customer['id'];
            } elseif (isset($customer['customer_id'])) {
                $truebeep_customer_id = $customer['customer_id'];
            }
            
            if (!empty($truebeep_customer_id)) {
                update_user_meta($user_id, '_truebeep_customer_id', $truebeep_customer_id);
                update_user_meta($user_id, '_truebeep_sync_date', current_time('mysql'));
                
                $this->sync_customer_statistics($user_id, $truebeep_customer_id);
                
                $result['successful']++;
            } else {
                $result['failed']++;
                $result['errors'][$user_id] = 'No customer ID in response';
                truebeep_log('No customer ID found for user ' . $user_id . ' in response', 'CustomerSyncer', ['response' => $customer]);
            }
        }

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
     * Prepare customer data for API
     * 
     * Formats WordPress user data for Truebeep API requirements.
     * 
     * @param \WP_User $user WordPress user object
     * @return array Formatted customer data for API
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
            'sync_date' => current_time('mysql'),
            'user_registered' => $user->user_registered
        ];

        return $customer_data;
    }

    /**
     * Sync customer order statistics
     * 
     * Synchronizes historical order data and awards loyalty points
     * based on past purchases.
     * 
     * @param int    $user_id             WordPress user ID
     * @param string $truebeep_customer_id Truebeep customer ID
     * @return void
     */
    private function sync_customer_statistics($user_id, $truebeep_customer_id)
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

            update_user_meta($user_id, '_truebeep_synced_stats', [
                'order_count' => $order_count,
                'total_spent' => $total_spent,
                'sync_date' => current_time('mysql')
            ]);

            if ($total_spent > 0) {
                $this->award_historical_points($user_id, $truebeep_customer_id, $total_spent);
            }
        }
    }

    /**
     * Award historical loyalty points
     * 
     * Awards points based on historical purchase data during sync.
     * 
     * @param int    $user_id             WordPress user ID
     * @param string $truebeep_customer_id Truebeep customer ID
     * @param float  $total_spent         Total amount spent historically
     * @return void
     */
    private function award_historical_points($user_id, $truebeep_customer_id, $total_spent)
    {
        $points = $this->calculate_loyalty_points($total_spent, $user_id);
        
        if ($points > 0) {
            $response = $this->update_loyalty_points(
                $truebeep_customer_id, 
                $points, 
                'increment', 
                'wordpress_sync'
            );

            if (!is_wp_error($response) && $response['success']) {
                update_user_meta($user_id, '_truebeep_historical_points_awarded', $points);
            }
        }
    }

    /**
     * Get customers to sync
     * 
     * Retrieves list of WordPress users that need to be synced
     * with Truebeep platform.
     * 
     * @return array Array of WordPress user IDs to sync
     */
    public function get_customers_to_sync()
    {
        // Try to get from cache first
        $cache_key = 'truebeep_customers_to_sync';
        $cached_result = wp_cache_get($cache_key, 'truebeep_sync');
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Get users without valid Truebeep customer ID using WordPress functions
        $users_without_truebeep = get_users([
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => '_truebeep_customer_id',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => '_truebeep_customer_id',
                    'value'   => '',
                    'compare' => '='
                ],
                [
                    'key'     => '_truebeep_customer_id',
                    'value'   => '0',
                    'compare' => '='
                ]
            ],
            'fields' => 'ID',
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        
        $user_ids = is_array($users_without_truebeep) ? $users_without_truebeep : [];
        $order_customer_ids = $this->get_order_customer_ids();
        
        $all_customer_ids = array_unique(array_merge($user_ids, $order_customer_ids));
        $result = array_values($all_customer_ids);
        
        // Cache for 5 minutes since this data changes frequently during sync
        wp_cache_set($cache_key, $result, 'truebeep_sync', 300);
        
        return $result;
    }

    /**
     * Get customer IDs from orders
     * 
     * Retrieves customer IDs from WooCommerce orders for users
     * who may not have customer role but have placed orders.
     * 
     * @return array Array of customer IDs from orders
     */
    private function get_order_customer_ids()
    {
        // Try to get from cache first
        $cache_key = 'truebeep_order_customer_ids';
        $cached_result = wp_cache_get($cache_key, 'truebeep_sync');
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Get customer IDs from orders using WordPress functions
        $orders = get_posts([
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_customer_user',
                    'value' => 0,
                    'compare' => '>'
                ]
            ]
        ]);
        
        $customer_ids = [];
        foreach ($orders as $order_id) {
            $customer_id = get_post_meta($order_id, '_customer_user', true);
            if ($customer_id && $customer_id > 0) {
                // Check if customer doesn't have Truebeep ID
                $truebeep_id = get_user_meta($customer_id, '_truebeep_customer_id', true);
                if (empty($truebeep_id) || $truebeep_id === '0') {
                    $customer_ids[] = $customer_id;
                }
            }
        }
        
        $result = array_unique($customer_ids);
        
        // Cache for 10 minutes since order data is relatively stable
        wp_cache_set($cache_key, $result, 'truebeep_sync', 600);
        
        return $result;
    }

    /**
     * Log batch result
     * 
     * Records batch processing results for debugging and reporting.
     * 
     * @param array $result Batch processing result
     * @return void
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

        $sync_log = get_option('truebeep_sync_log', []);
        array_unshift($sync_log, $log_entry);
        $sync_log = array_slice($sync_log, 0, 100);
        
        update_option('truebeep_sync_log', $sync_log);
    }

    /**
     * Get sync statistics
     * 
     * Calculates and returns current sync statistics including
     * progress percentage and remaining customers.
     * 
     * @return array {
     *     Sync statistics
     *     
     *     @type int   $total      Total customers to sync
     *     @type int   $processed  Number of processed customers
     *     @type int   $successful Successfully synced count
     *     @type int   $failed     Failed sync count
     *     @type int   $skipped    Skipped customers count
     *     @type int   $remaining  Remaining customers to sync
     *     @type float $percentage Completion percentage
     * }
     */
    public function get_sync_statistics()
    {
        $current_remaining = count($this->get_customers_to_sync());
        $progress = get_option('truebeep_sync_progress', [
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_batches' => 0,
            'completed_batches' => 0
        ]);
        
        // If sync hasn't started yet, set initial total
        if (empty($progress['total']) || $progress['total'] == 0) {
            $progress['total'] = $current_remaining;
        }
        
        // Calculate remaining as current unsynced customers
        $progress['remaining'] = $current_remaining;
        
        // Calculate percentage based on actual remaining vs initial total
        if ($progress['total'] > 0) {
            $synced_count = $progress['total'] - $current_remaining;
            $progress['percentage'] = round(($synced_count / $progress['total']) * 100, 2);
        } else {
            $progress['percentage'] = 0;
        }

        return $progress;
    }
    
    /**
     * Clear sync-related caches
     * 
     * Clears all cached database query results to ensure fresh data
     * is retrieved after sync operations or user changes.
     * 
     * @return void
     */
    public function clear_sync_caches()
    {
        wp_cache_delete('truebeep_customers_to_sync', 'truebeep_sync');
        wp_cache_delete('truebeep_order_customer_ids', 'truebeep_sync');
    }
}