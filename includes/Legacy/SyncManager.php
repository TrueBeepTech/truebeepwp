<?php

namespace Truebeep\Legacy;

/**
 * Customer Sync Manager
 * 
 * Coordinates the bulk synchronization process between WordPress users
 * and Truebeep platform. Manages sync state, progress tracking, and
 * provides AJAX endpoints for sync operations.
 * 
 * @package Truebeep\Legacy
 * @since 1.0.0
 */
class SyncManager
{
    /**
     * @var CustomerSyncProcessor Processor instance for handling sync operations
     */
    private $processor;
    
    /**
     * @var CustomerSyncer Syncer instance for customer data synchronization
     */
    private $syncer;

    /**
     * Initialize sync manager
     * 
     * Sets up processor and syncer instances, registers WordPress hooks
     * for AJAX handlers and scheduled tasks.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->syncer = new CustomerSyncer();
        
        CustomerSyncProcessor::init();
        $this->processor = new CustomerSyncProcessor();
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     * 
     * Registers AJAX handlers for sync operations and sets up
     * periodic health check for stuck sync processes.
     * 
     * @return void
     */
    private function init_hooks()
    {
        add_action('wp_ajax_truebeep_start_sync', [$this, 'ajax_start_sync']);
        add_action('wp_ajax_truebeep_get_sync_status', [$this, 'ajax_get_sync_status']);
        add_action('wp_ajax_truebeep_cancel_sync', [$this, 'ajax_cancel_sync']);
        add_action('wp_ajax_truebeep_reset_sync', [$this, 'ajax_reset_sync']);
        
        add_action('truebeep_sync_healthcheck', [$this->processor, 'handle_cron_healthcheck']);
        
        if (!wp_next_scheduled('truebeep_sync_healthcheck')) {
            wp_schedule_event(time(), 'hourly', 'truebeep_sync_healthcheck');
        }
    }

    /**
     * Start the synchronization process
     * 
     * Initiates bulk customer sync, creating batches and scheduling
     * them for processing with rate limiting.
     * 
     * @return array {
     *     Sync start result
     *     
     *     @type bool   $success Whether sync started successfully
     *     @type string $message User-friendly status message
     *     @type int    $total   Total number of customers to sync
     *     @type int    $batches Number of batches created
     * }
     */
    public function start_sync()
    {
        if ($this->is_sync_running()) {
            return [
                'success' => false,
                'message' => __('Sync is already running', 'truebeep')
            ];
        }

        update_option('truebeep_sync_lock', time());
        update_option('truebeep_sync_status', 'preparing');
        
        delete_option('truebeep_sync_progress');
        delete_option('truebeep_sync_log');
        
        $customer_ids = $this->syncer->get_customers_to_sync();
        
        if (empty($customer_ids)) {
            update_option('truebeep_sync_status', 'completed');
            delete_option('truebeep_sync_lock');
            
            return [
                'success' => false,
                'message' => __('No customers to sync', 'truebeep')
            ];
        }

        update_option('truebeep_sync_progress', [
            'total' => count($customer_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ]);

        $batches = array_chunk($customer_ids, CustomerSyncProcessor::BATCH_SIZE);
        
        $this->processor->schedule_sync($batches);
        
        update_option('truebeep_sync_status', 'running');
        update_option('truebeep_sync_started_at', current_time('mysql'));

        $result = [
            'success' => true,
            'message' => sprintf(
                __('Sync started. Processing %d customers in %d batches.', 'truebeep'),
                count($customer_ids),
                count($batches)
            ),
            'total' => count($customer_ids),
            'batches' => count($batches)
        ];
        
        truebeep_log('Customer sync started: ' . count($customer_ids) . ' customers in ' . count($batches) . ' batches', 'SyncManager');
        return $result;
    }

    /**
     * Get current sync status and statistics
     * 
     * Retrieves comprehensive sync status including progress,
     * statistics, and timing information.
     * 
     * @return array {
     *     Sync status information
     *     
     *     @type string $status      Current sync status
     *     @type array  $progress    Progress metrics
     *     @type array  $statistics  Sync statistics
     *     @type bool   $is_running  Whether sync is active
     *     @type string $started_at  Sync start timestamp
     *     @type string $completed_at Sync completion timestamp
     *     @type int    $last_update Last activity timestamp
     * }
     */
    public function get_sync_status()
    {
        $status = get_option('truebeep_sync_status', 'idle');
        $progress = get_option('truebeep_sync_progress', []);
        $statistics = $this->syncer->get_sync_statistics();
        
        if ($status === 'running') {
            // Auto-complete if no customers remaining to sync
            if ($statistics['remaining'] === 0) {
                $this->processor->clear_scheduled_actions();
                update_option('truebeep_sync_status', 'completed');
                update_option('truebeep_sync_completed_at', current_time('mysql'));
                delete_option('truebeep_sync_lock');
                truebeep_log('Customer sync completed', 'SyncManager');
                $status = 'completed';
            }
            // Also check if no pending actions and should be completed
            elseif (!$this->processor->has_pending_actions()) {
                // Double-check remaining count in case of race condition
                $fresh_stats = $this->syncer->get_sync_statistics();
                if ($fresh_stats['remaining'] === 0) {
                    update_option('truebeep_sync_status', 'completed');
                    update_option('truebeep_sync_completed_at', current_time('mysql'));
                    delete_option('truebeep_sync_lock');
                    $status = 'completed';
                }
            }
        }

        return [
            'status' => $status,
            'progress' => $progress,
            'statistics' => $statistics,
            'is_running' => $this->is_sync_running(),
            'started_at' => get_option('truebeep_sync_started_at'),
            'completed_at' => get_option('truebeep_sync_completed_at'),
            'last_update' => get_option('truebeep_sync_last_update', 0),
            'rate_limit' => get_option('truebeep_sync_rate_limit', [])
        ];
    }

    /**
     * Check if sync is currently running
     * 
     * Determines if a sync operation is in progress by checking
     * both the status flag and pending actions.
     * 
     * @return bool True if sync is running, false otherwise
     */
    public function is_sync_running()
    {
        $status = get_option('truebeep_sync_status') === 'running';
        return $status || $this->processor->has_pending_actions();
    }

    /**
     * Cancel active sync operation
     * 
     * Stops the current sync process and clears scheduled actions.
     * 
     * @return bool Always returns true
     */
    public function cancel_sync()
    {
        $this->processor->clear_scheduled_actions();
        
        update_option('truebeep_sync_status', 'cancelled');
        delete_option('truebeep_sync_lock');
        
        truebeep_log('Customer sync cancelled', 'SyncManager');
        return true;
    }

    /**
     * Reset all sync data
     * 
     * Clears all sync-related options and resets to initial state.
     * 
     * @return bool Always returns true
     */
    public function reset_sync()
    {
        $this->cancel_sync();
        
        delete_option('truebeep_sync_status');
        delete_option('truebeep_sync_progress');
        delete_option('truebeep_sync_log');
        delete_option('truebeep_sync_lock');
        delete_option('truebeep_sync_started_at');
        delete_option('truebeep_sync_completed_at');
        delete_option('truebeep_sync_last_update');
        delete_option('truebeep_sync_rate_limit');
        
        return true;
    }

    /**
     * AJAX handler for starting sync
     * 
     * Processes AJAX request to initiate customer synchronization.
     * Validates permissions and nonce before starting.
     * 
     * @return void Sends JSON response
     */
    public function ajax_start_sync()
    {
        check_ajax_referer('truebeep_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $result = $this->start_sync();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for getting sync status
     * 
     * Returns current sync status and progress information.
     * 
     * @return void Sends JSON response
     */
    public function ajax_get_sync_status()
    {
        check_ajax_referer('truebeep_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $status = $this->get_sync_status();
        wp_send_json_success($status);
    }

    /**
     * AJAX handler for cancelling sync
     * 
     * Stops the current sync operation via AJAX request.
     * 
     * @return void Sends JSON response
     */
    public function ajax_cancel_sync()
    {
        check_ajax_referer('truebeep_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $this->cancel_sync();
        wp_send_json_success(__('Sync cancelled', 'truebeep'));
    }

    /**
     * AJAX handler for resetting sync
     * 
     * Clears all sync data and resets to initial state.
     * 
     * @return void Sends JSON response
     */
    public function ajax_reset_sync()
    {
        check_ajax_referer('truebeep_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $this->reset_sync();
        wp_send_json_success(__('Sync data reset', 'truebeep'));
    }

    /**
     * Get sync log entries
     * 
     * Retrieves recent sync log entries with detailed information
     * about each batch processing result.
     * 
     * @param int $limit Maximum number of log entries to return
     * @return array Array of log entries with timestamps, results, and errors
     */
    public function get_sync_logs($limit = 50)
    {
        $logs = get_option('truebeep_sync_log', []);
        
        if ($limit > 0) {
            $logs = array_slice($logs, 0, $limit);
        }
        
        // Format logs for display
        foreach ($logs as &$log) {
            // Add formatted timestamp
            $log['formatted_timestamp'] = wp_date(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($log['timestamp'])
            );
            
            // Add summary
            $log['summary'] = sprintf(
                __('%d processed (%d successful, %d failed, %d skipped)', 'truebeep'),
                count($log['processed'] ?? []),
                $log['successful'] ?? 0,
                $log['failed'] ?? 0,
                $log['skipped'] ?? 0
            );
        }
        
        return $logs;
    }
}