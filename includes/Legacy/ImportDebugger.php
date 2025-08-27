<?php

namespace Truebeep\Legacy;

/**
 * Import Debugger
 * Helps diagnose and fix import issues
 */
class ImportDebugger
{
    /**
     * Get detailed import status
     * 
     * @return array
     */
    public static function get_detailed_status()
    {
        $processor = new CustomerImportProcessor();
        $importer = new CustomerImporter();
        
        // Get basic status
        $status = $processor->get_status();
        
        // Get Action Scheduler status
        $pending_actions = as_get_scheduled_actions([
            'group' => 'truebeep_import',
            'status' => \ActionScheduler_Store::STATUS_PENDING,
            'per_page' => 100
        ]);
        
        $failed_actions = as_get_scheduled_actions([
            'group' => 'truebeep_import',
            'status' => \ActionScheduler_Store::STATUS_FAILED,
            'per_page' => 100
        ]);
        
        $running_actions = as_get_scheduled_actions([
            'group' => 'truebeep_import',
            'status' => \ActionScheduler_Store::STATUS_RUNNING,
            'per_page' => 100
        ]);
        
        // Get import log
        $import_log = get_option('truebeep_import_log', []);
        $recent_errors = [];
        
        foreach ($import_log as $entry) {
            if (!empty($entry['errors'])) {
                foreach ($entry['errors'] as $user_id => $error) {
                    $recent_errors[] = [
                        'user_id' => $user_id,
                        'error' => $error,
                        'timestamp' => $entry['timestamp']
                    ];
                }
            }
        }
        
        // Get customers still to import
        $remaining_customers = $importer->get_customers_to_import();
        
        return [
            'status' => $status,
            'action_scheduler' => [
                'pending' => count($pending_actions),
                'failed' => count($failed_actions),
                'running' => count($running_actions),
                'pending_details' => array_slice($pending_actions, 0, 5),
                'failed_details' => array_slice($failed_actions, 0, 5)
            ],
            'remaining_customers' => count($remaining_customers),
            'recent_errors' => array_slice($recent_errors, 0, 10),
            'import_log_entries' => count($import_log),
            'last_5_log_entries' => array_slice($import_log, 0, 5)
        ];
    }
    
    /**
     * Retry failed actions
     * 
     * @return array
     */
    public static function retry_failed_actions()
    {
        $processor = new CustomerImportProcessor();
        
        $failed_actions = as_get_scheduled_actions([
            'group' => 'truebeep_import',
            'status' => \ActionScheduler_Store::STATUS_FAILED,
            'per_page' => 100
        ]);
        
        $retried = 0;
        foreach ($failed_actions as $action_id => $action) {
            // Mark the failed action as complete
            as_mark_complete($action_id);
            
            // Reschedule it
            as_schedule_single_action(
                time() + ($retried * 10),
                $action->get_hook(),
                $action->get_args(),
                'truebeep_import'
            );
            $retried++;
        }
        
        // Update status
        if ($retried > 0) {
            update_option('truebeep_import_status', 'processing');
            update_option('truebeep_import_last_update', time());
        }
        
        return [
            'success' => true,
            'retried' => $retried,
            'message' => sprintf('Retried %d failed actions', $retried)
        ];
    }
    
    /**
     * Clear stuck import and restart
     * 
     * @return array
     */
    public static function clear_and_restart()
    {
        $processor = new CustomerImportProcessor();
        $importer = new CustomerImporter();
        $manager = new ImportManager();
        
        // First cancel the current import
        $manager->cancel_import();
        
        // Clear all scheduled actions
        $processor->clear_scheduled_actions();
        
        // Reset import status
        delete_option('truebeep_import_status');
        delete_option('truebeep_import_progress');
        delete_option('truebeep_import_lock');
        delete_option('truebeep_import_last_update');
        
        // Get remaining customers
        $customer_ids = $importer->get_customers_to_import();
        
        if (empty($customer_ids)) {
            return [
                'success' => false,
                'message' => 'No customers left to import'
            ];
        }
        
        // Restart the import
        $result = $manager->start_import();
        
        return $result;
    }
    
    /**
     * Check for duplicate customer IDs
     * 
     * @return array
     */
    public static function check_duplicates()
    {
        global $wpdb;
        
        // Find users with Truebeep customer IDs
        $query = "
            SELECT user_id, meta_value as truebeep_id
            FROM {$wpdb->usermeta}
            WHERE meta_key = '_truebeep_customer_id'
            AND meta_value != ''
            ORDER BY user_id
        ";
        
        $results = $wpdb->get_results($query);
        
        $duplicates = [];
        $id_map = [];
        
        foreach ($results as $row) {
            if (isset($id_map[$row->truebeep_id])) {
                if (!isset($duplicates[$row->truebeep_id])) {
                    $duplicates[$row->truebeep_id] = [$id_map[$row->truebeep_id]];
                }
                $duplicates[$row->truebeep_id][] = $row->user_id;
            } else {
                $id_map[$row->truebeep_id] = $row->user_id;
            }
        }
        
        return [
            'total_imported' => count($results),
            'unique_truebeep_ids' => count($id_map),
            'duplicates' => $duplicates,
            'has_duplicates' => !empty($duplicates)
        ];
    }
    
    /**
     * Fix a single stuck batch
     * 
     * @param int $batch_index
     * @return array
     */
    public static function fix_stuck_batch($batch_index = null)
    {
        $processor = new CustomerImportProcessor();
        $importer = new CustomerImporter();
        
        // Get remaining customers
        $customer_ids = $importer->get_customers_to_import();
        
        if (empty($customer_ids)) {
            return [
                'success' => false,
                'message' => 'No customers left to import'
            ];
        }
        
        // Create a small test batch
        $test_batch = array_slice($customer_ids, 0, 5);
        
        // Process the batch directly (synchronously)
        $result = $importer->process_batch($test_batch);
        
        return [
            'success' => true,
            'batch_result' => $result,
            'message' => sprintf(
                'Processed test batch: %d successful, %d failed, %d skipped',
                $result['successful'],
                $result['failed'],
                $result['skipped']
            )
        ];
    }
    
    /**
     * Get API connection status
     * 
     * @return array
     */
    public static function check_api_connection()
    {
        $importer = new CustomerImporter();
        
        // Test API connection
        $api_test = $importer->test_api_connection();
        
        // Try to fetch a test customer
        $test_customer_response = null;
        $test_create_response = null;
        
        if (!is_wp_error($api_test) && $api_test['success']) {
            // Try to create a test customer
            $test_data = [
                'firstName' => 'Test',
                'lastName' => 'Import Debug',
                'email' => 'test-import-debug-' . time() . '@example.com',
                'source' => 'wordpress_import_debug'
            ];
            
            $test_create_response = $importer->create_truebeep_customer($test_data);
            
            // If successful, try to delete it
            if (!is_wp_error($test_create_response) && $test_create_response['success']) {
                // Extract the ID from various possible locations
                $test_id = null;
                if (!empty($test_create_response['data']['data']['_id'])) {
                    $test_id = $test_create_response['data']['data']['_id'];
                } elseif (!empty($test_create_response['data']['_id'])) {
                    $test_id = $test_create_response['data']['_id'];
                } elseif (!empty($test_create_response['data']['id'])) {
                    $test_id = $test_create_response['data']['id'];
                }
                
                if ($test_id) {
                    // Try to delete the test customer
                    $importer->delete_truebeep_customer($test_id);
                }
            }
        }
        
        return [
            'api_url' => get_option('truebeep_api_url', ''),
            'api_key_set' => !empty(get_option('truebeep_api_key', '')),
            'connection_test' => $api_test,
            'test_create_response' => $test_create_response,
            'response_structure_info' => $test_create_response ? [
                'has_data' => isset($test_create_response['data']),
                'has_data_data' => isset($test_create_response['data']['data']),
                'has_id_fields' => [
                    '_id_in_data' => isset($test_create_response['data']['_id']),
                    '_id_in_data_data' => isset($test_create_response['data']['data']['_id']),
                    'id_in_data' => isset($test_create_response['data']['id']),
                    'id_in_data_data' => isset($test_create_response['data']['data']['id'])
                ]
            ] : null
        ];
    }
}