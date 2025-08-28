<?php

namespace Truebeep\Legacy;

/**
 * Customer Sync Processor
 * 
 * Background processor for customer synchronization using Action Scheduler.
 * Handles rate-limited batch processing and progress tracking.
 * 
 * @package Truebeep\Legacy
 * @since 1.0.0
 */
class CustomerSyncProcessor
{
    /**
     * @var string Action hook name for sync processing
     */
    protected $action = 'truebeep_customer_sync';
    
    /**
     * @var string Unique identifier for this processor
     */
    public $identifier = 'truebeep_customer_sync';
    
    /**
     * @var string Action Scheduler group name
     */
    protected $group = 'truebeep_sync';
    
    /**
     * Rate limit configuration
     */
    const RATE_LIMIT_INTERVAL = 60; // 60 seconds between batches
    const BATCH_SIZE = 20; // Process 20 customers per batch

    /**
     * @var CustomerSyncProcessor Single instance for hooks registration
     */
    private static $instance = null;

    /**
     * Initialize processor
     * 
     * Registers action hooks for batch processing and completion.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress action hooks
     * 
     * Registers the action hooks needed for background processing.
     * This method is public so it can be called early in WordPress loading.
     * 
     * @return void
     */
    public function register_hooks()
    {
        add_action($this->action, [$this, 'process_batch'], 10, 1);
        add_action($this->action . '_complete', [$this, 'complete']);
    }

    /**
     * Static initializer
     * 
     * Creates a new instance of the processor and ensures hooks are registered.
     * 
     * @return void
     */
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register hooks early in WordPress loading process
     * 
     * This method should be called during WordPress init to ensure
     * Action Scheduler can find the callback functions.
     * 
     * @return void
     */
    public static function register_action_hooks()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        } else {
            // Ensure hooks are registered even if instance already exists
            self::$instance->register_hooks();
        }
    }

    /**
     * Schedule batch processing with rate limiting
     * 
     * Schedules customer batches for processing with specified intervals
     * between each batch to prevent API overload.
     * 
     * @param array $batches Array of customer ID batches
     * @return bool True if scheduling successful, false otherwise
     */
    public function schedule_sync($batches)
    {
        if (empty($batches) || !is_array($batches)) {
            return false;
        }

        $this->clear_scheduled_actions();
        
        update_option('truebeep_sync_status', 'processing');
        update_option('truebeep_sync_started_at', current_time('mysql'));
        delete_option('truebeep_sync_completed_at');
        
        update_option('truebeep_sync_progress', [
            'total' => array_sum(array_map('count', $batches)),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_batches' => count($batches),
            'completed_batches' => 0
        ]);

        foreach ($batches as $index => $batch) {
            $scheduled_time = time() + ($index * self::RATE_LIMIT_INTERVAL);
            
            as_schedule_single_action(
                $scheduled_time,
                $this->action,
                [$batch],
                $this->group
            );
        }

        $completion_time = time() + (count($batches) * self::RATE_LIMIT_INTERVAL) + 120;
        as_schedule_single_action(
            $completion_time,
            $this->action . '_complete',
            [],
            $this->group
        );

        update_option('truebeep_sync_rate_limit', [
            'interval' => self::RATE_LIMIT_INTERVAL,
            'batch_size' => self::BATCH_SIZE,
            'total_batches' => count($batches),
            'estimated_time' => count($batches) * self::RATE_LIMIT_INTERVAL
        ]);

        return true;
    }

    /**
     * Process a single batch of customers
     * 
     * Handles the synchronization of a batch of customers with rate limiting.
     * 
     * @param array $batch Array of customer IDs to process
     * @return void
     */
    public function process_batch($batch)
    {
        if (empty($batch) || !is_array($batch)) {
            return;
        }

        update_option('truebeep_sync_last_update', time());

        $syncer = new CustomerSyncer();
        
        try {
            $result = $syncer->process_batch($batch);

            if ($result['success']) {
                $this->update_progress($result);
            } else {
                error_log('Truebeep Sync Error: ' . json_encode($result));
                
                $progress = get_option('truebeep_sync_progress', []);
                $progress['failed'] += count($batch);
                $progress['completed_batches'] = ($progress['completed_batches'] ?? 0) + 1;
                update_option('truebeep_sync_progress', $progress);
            }
        } catch (\Exception $e) {
            error_log('Truebeep Sync Exception: ' . $e->getMessage());
            
            $progress = get_option('truebeep_sync_progress', []);
            $progress['failed'] += count($batch);
            $progress['completed_batches'] = ($progress['completed_batches'] ?? 0) + 1;
            update_option('truebeep_sync_progress', $progress);
        }
    }

    /**
     * Complete sync process
     * 
     * Handles completion of the sync process, sends notifications,
     * and cleans up temporary data.
     * 
     * @return void
     */
    public function complete()
    {
        $progress = get_option('truebeep_sync_progress', []);
        
        // Check if there are still customers to sync
        $syncer = new CustomerSyncer();
        $remaining_customers = count($syncer->get_customers_to_sync());
        
        if ($remaining_customers > 0) {
            // If there are customers remaining but no pending actions, something went wrong
            $pending = as_get_scheduled_actions([
                'group' => $this->group,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1
            ]);
            
            if (empty($pending)) {
                // Reschedule remaining customers
                $customer_ids = $syncer->get_customers_to_sync();
                $batches = array_chunk($customer_ids, self::BATCH_SIZE);
                
                error_log('Truebeep Sync: Rescheduling ' . count($customer_ids) . ' remaining customers in ' . count($batches) . ' batches');
                
                foreach ($batches as $index => $batch) {
                    $scheduled_time = time() + ($index * self::RATE_LIMIT_INTERVAL);
                    
                    as_schedule_single_action(
                        $scheduled_time,
                        $this->action,
                        [$batch],
                        $this->group
                    );
                }
                
                // Reschedule completion check
                $completion_time = time() + (count($batches) * self::RATE_LIMIT_INTERVAL) + 120;
                as_schedule_single_action(
                    $completion_time,
                    $this->action . '_complete',
                    [],
                    $this->group
                );
                return;
            }
        }
        
        if (($progress['completed_batches'] ?? 0) < ($progress['total_batches'] ?? 0)) {
            $pending = as_get_scheduled_actions([
                'group' => $this->group,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1
            ]);
            
            if (!empty($pending)) {
                as_schedule_single_action(
                    time() + 60,
                    $this->action . '_complete',
                    [],
                    $this->group
                );
                return;
            }
        }
        
        update_option('truebeep_sync_status', 'completed');
        update_option('truebeep_sync_completed_at', current_time('mysql'));
        
        delete_option('truebeep_sync_lock');
        delete_option('truebeep_sync_rate_limit');
        
        $this->send_completion_notification();
    }

    /**
     * Update sync progress
     * 
     * Updates the progress statistics after processing a batch.
     * 
     * @param array $result Batch processing result
     * @return void
     */
    private function update_progress($result)
    {
        $progress = get_option('truebeep_sync_progress', [
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_batches' => 0,
            'completed_batches' => 0
        ]);
        
        $progress['processed'] += count($result['processed'] ?? []);
        $progress['successful'] += $result['successful'] ?? 0;
        $progress['failed'] += $result['failed'] ?? 0;
        $progress['skipped'] += $result['skipped'] ?? 0;
        $progress['completed_batches'] = ($progress['completed_batches'] ?? 0) + 1;
        
        update_option('truebeep_sync_progress', $progress);
    }

    /**
     * Send completion notification
     * 
     * Sends an email notification to admin when sync completes.
     * 
     * @return void
     */
    private function send_completion_notification()
    {
        $progress = get_option('truebeep_sync_progress', []);
        
        $admin_email = get_option('admin_email');
        
        $subject = __('Truebeep Customer Sync Completed', 'truebeep');
        $message = sprintf(
            __("The Truebeep customer sync has been completed.\n\nResults:\n- Total Processed: %d\n- Successful: %d\n- Failed: %d\n- Skipped: %d\n\nYou can view the full report in your WordPress admin panel.", 'truebeep'),
            $progress['processed'] ?? 0,
            $progress['successful'] ?? 0,
            $progress['failed'] ?? 0,
            $progress['skipped'] ?? 0
        );
        
        if (apply_filters('truebeep_send_sync_notification', true)) {
            wp_mail($admin_email, $subject, $message);
        }
    }

    /**
     * Check for pending actions
     * 
     * Determines if there are any pending sync actions in the queue.
     * 
     * @return bool True if pending actions exist, false otherwise
     */
    public function has_pending_actions()
    {
        $actions = as_get_scheduled_actions([
            'group' => $this->group,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
            'per_page' => 1
        ]);
        
        return !empty($actions);
    }
    
    /**
     * Clear all scheduled actions
     * 
     * Removes all scheduled sync actions from the queue.
     * 
     * @return bool Always returns true
     */
    public function clear_scheduled_actions()
    {
        as_unschedule_all_actions($this->action, [], $this->group);
        as_unschedule_all_actions($this->action . '_complete', [], $this->group);
        
        return true;
    }
    
    /**
     * Handle cron healthcheck
     * 
     * Monitors sync process health and restarts stuck batches.
     * 
     * @return void
     */
    public function handle_cron_healthcheck()
    {
        if ($this->has_pending_actions()) {
            $last_update = get_option('truebeep_sync_last_update', 0);
            if ((time() - $last_update) > 300) {
                $failed_actions = as_get_scheduled_actions([
                    'group' => $this->group,
                    'status' => \ActionScheduler_Store::STATUS_FAILED,
                    'per_page' => 10
                ]);
                
                foreach ($failed_actions as $index => $action) {
                    as_schedule_single_action(
                        time() + ($index * self::RATE_LIMIT_INTERVAL),
                        $action->get_hook(),
                        $action->get_args(),
                        $this->group
                    );
                }
            }
        }
    }
    
    /**
     * Get sync status with rate limit info
     * 
     * Retrieves comprehensive sync status including rate limiting details.
     * 
     * @return array {
     *     Sync status information
     *     
     *     @type string $status                Current sync status
     *     @type array  $progress              Progress metrics
     *     @type array  $rate_limit            Rate limit configuration
     *     @type bool   $has_running           Whether actions are running
     *     @type bool   $has_pending           Whether actions are pending
     *     @type int    $last_update           Last update timestamp
     *     @type string $started_at            Sync start time
     *     @type string $completed_at          Sync completion time
     *     @type int    $estimated_time_remaining Seconds until completion
     *     @type int    $next_batch_time       Next batch execution time
     * }
     */
    public function get_status()
    {
        $status = get_option('truebeep_sync_status', 'idle');
        $progress = get_option('truebeep_sync_progress', []);
        $rate_limit = get_option('truebeep_sync_rate_limit', []);
        
        $running_actions = as_get_scheduled_actions([
            'group' => $this->group,
            'status' => \ActionScheduler_Store::STATUS_RUNNING,
            'per_page' => 1
        ]);
        
        $pending_actions = as_get_scheduled_actions([
            'group' => $this->group,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
            'per_page' => 1
        ]);
        
        $remaining_batches = ($progress['total_batches'] ?? 0) - ($progress['completed_batches'] ?? 0);
        $estimated_time_remaining = $remaining_batches * self::RATE_LIMIT_INTERVAL;
        
        return [
            'status' => $status,
            'progress' => $progress,
            'rate_limit' => $rate_limit,
            'has_running' => !empty($running_actions),
            'has_pending' => !empty($pending_actions),
            'last_update' => get_option('truebeep_sync_last_update', 0),
            'started_at' => get_option('truebeep_sync_started_at'),
            'completed_at' => get_option('truebeep_sync_completed_at'),
            'estimated_time_remaining' => $estimated_time_remaining,
            'next_batch_time' => !empty($pending_actions) ? array_values($pending_actions)[0]->get_schedule()->getTimestamp() : null
        ];
    }
    
    /**
     * Pause sync operation
     * 
     * Pauses the current sync by clearing scheduled actions.
     * 
     * @return bool Always returns true
     */
    public function pause()
    {
        $this->clear_scheduled_actions();
        update_option('truebeep_sync_status', 'paused');
        return true;
    }
    
    /**
     * Resume sync with rate limiting
     * 
     * Resumes a paused sync operation with rate limiting applied.
     * 
     * @return bool True if resumed successfully, false if no actions to resume
     */
    public function resume()
    {
        $actions = as_get_scheduled_actions([
            'group' => $this->group,
            'status' => \ActionScheduler_Store::STATUS_CANCELED,
            'per_page' => 100
        ]);
        
        if (!empty($actions)) {
            $index = 0;
            foreach ($actions as $action_id => $action) {
                as_schedule_single_action(
                    time() + ($index * self::RATE_LIMIT_INTERVAL),
                    $action->get_hook(),
                    $action->get_args(),
                    $this->group
                );
                $index++;
            }
            
            update_option('truebeep_sync_status', 'processing');
            return true;
        }
        
        return false;
    }
}