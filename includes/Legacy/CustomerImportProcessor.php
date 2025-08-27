<?php

namespace Truebeep\Legacy;

/**
 * Background processor for customer import using Action Scheduler
 */
class CustomerImportProcessor
{
    protected $action = 'truebeep_customer_import';
    public $identifier = 'truebeep_customer_import';
    protected $group = 'truebeep_import';

    /**
     * Initialize processor
     */
    public function __construct()
    {
        add_action($this->action, [$this, 'process_batch'], 10, 1);
        add_action($this->action . '_complete', [$this, 'complete']);
    }

    /**
     * Static initializer
     */
    public static function init()
    {
        new self();
    }

    /**
     * Schedule batch processing
     */
    public function schedule_import($batches)
    {
        _log('schedule_import');
        _log($batches);

        if (empty($batches) || !is_array($batches)) {
            return false;
        }

        $this->clear_scheduled_actions();
        
        update_option('truebeep_import_status', 'processing');
        update_option('truebeep_import_started_at', current_time('mysql'));
        delete_option('truebeep_import_completed_at');
        
        update_option('truebeep_import_progress', [
            'total' => array_sum(array_map('count', $batches)),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_batches' => count($batches),
            'completed_batches' => 0
        ]);

        foreach ($batches as $index => $batch) {
            as_schedule_single_action(
                time() + ($index * 10),
                $this->action,
                [$batch],
                $this->group
            );
        }

        $completion_time = time() + (count($batches) * 10) + 60;
        as_schedule_single_action(
            $completion_time,
            $this->action . '_complete',
            [],
            $this->group
        );

        return true;
    }

    /**
     * Process batch
     */
    public function process_batch($batch)
    {
        _log('process_batch');
        _log($batch);

        if (empty($batch) || !is_array($batch)) {
            return;
        }

        update_option('truebeep_import_last_update', time());

        $importer = new CustomerImporter();
        
        try {
            $result = $importer->process_batch($batch);

            if ($result['success']) {
                $this->update_progress($result);
            } else {
                error_log('Truebeep Import Error: ' . json_encode($result));
                
                $progress = get_option('truebeep_import_progress', []);
                $progress['failed'] += count($batch);
                $progress['completed_batches'] = ($progress['completed_batches'] ?? 0) + 1;
                update_option('truebeep_import_progress', $progress);
            }
        } catch (\Exception $e) {
            error_log('Truebeep Import Exception: ' . $e->getMessage());
            
            $progress = get_option('truebeep_import_progress', []);
            $progress['failed'] += count($batch);
            $progress['completed_batches'] = ($progress['completed_batches'] ?? 0) + 1;
            update_option('truebeep_import_progress', $progress);
        }
    }

    /**
     * Complete import
     */
    public function complete()
    {
        $progress = get_option('truebeep_import_progress', []);
        
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
        
        update_option('truebeep_import_status', 'completed');
        update_option('truebeep_import_completed_at', current_time('mysql'));
        
        delete_option('truebeep_import_lock');
        
        $this->send_completion_notification();
    }

    /**
     * Update progress
     */
    private function update_progress($result)
    {
        $progress = get_option('truebeep_import_progress', [
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
        
        update_option('truebeep_import_progress', $progress);
    }

    /**
     * Send completion notification
     */
    private function send_completion_notification()
    {
        $progress = get_option('truebeep_import_progress', []);
        
        $admin_email = get_option('admin_email');
        
        $subject = __('Truebeep Customer Import Completed', 'truebeep');
        $message = sprintf(
            __("The Truebeep customer import has been completed.\n\nResults:\n- Total Processed: %d\n- Successful: %d\n- Failed: %d\n- Skipped: %d\n\nYou can view the full report in your WordPress admin panel.", 'truebeep'),
            $progress['processed'] ?? 0,
            $progress['successful'] ?? 0,
            $progress['failed'] ?? 0,
            $progress['skipped'] ?? 0
        );
        
        if (apply_filters('truebeep_send_import_notification', true)) {
            wp_mail($admin_email, $subject, $message);
        }
    }

    /**
     * Check pending actions
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
     * Clear scheduled actions
     */
    public function clear_scheduled_actions()
    {
        as_unschedule_all_actions($this->action, [], $this->group);
        as_unschedule_all_actions($this->action . '_complete', [], $this->group);
        
        return true;
    }
    
    /**
     * Handle cron healthcheck
     */
    public function handle_cron_healthcheck()
    {
        if ($this->has_pending_actions()) {
            $last_update = get_option('truebeep_import_last_update', 0);
            if ((time() - $last_update) > 300) {
                $failed_actions = as_get_scheduled_actions([
                    'group' => $this->group,
                    'status' => \ActionScheduler_Store::STATUS_FAILED,
                    'per_page' => 10
                ]);
                
                foreach ($failed_actions as $action_id => $action) {
                    as_schedule_single_action(
                        time() + 10,
                        $action->get_hook(),
                        $action->get_args(),
                        $this->group
                    );
                }
            }
        }
    }
    
    /**
     * Get status
     */
    public function get_status()
    {
        $status = get_option('truebeep_import_status', 'idle');
        $progress = get_option('truebeep_import_progress', []);
        
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
        
        return [
            'status' => $status,
            'progress' => $progress,
            'has_running' => !empty($running_actions),
            'has_pending' => !empty($pending_actions),
            'last_update' => get_option('truebeep_import_last_update', 0),
            'started_at' => get_option('truebeep_import_started_at'),
            'completed_at' => get_option('truebeep_import_completed_at')
        ];
    }
    
    /**
     * Pause import
     */
    public function pause()
    {
        $this->clear_scheduled_actions();
        update_option('truebeep_import_status', 'paused');
        return true;
    }
    
    /**
     * Resume import
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
                    time() + ($index * 10),
                    $action->get_hook(),
                    $action->get_args(),
                    $this->group
                );
                $index++;
            }
            
            update_option('truebeep_import_status', 'processing');
            return true;
        }
        
        return false;
    }
}