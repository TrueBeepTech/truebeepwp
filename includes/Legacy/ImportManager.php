<?php

namespace Truebeep\Legacy;

/**
 * Import Manager
 */
class ImportManager
{
    private $processor;
    private $importer;

    /**
     * Initialize manager
     */
    public function __construct()
    {
        $this->importer = new CustomerImporter();
        
        CustomerImportProcessor::init();
        $this->processor = new CustomerImportProcessor();
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('wp_ajax_truebeep_start_import', [$this, 'ajax_start_import']);
        add_action('wp_ajax_truebeep_get_import_status', [$this, 'ajax_get_import_status']);
        add_action('wp_ajax_truebeep_cancel_import', [$this, 'ajax_cancel_import']);
        add_action('wp_ajax_truebeep_reset_import', [$this, 'ajax_reset_import']);
        
        add_action('truebeep_import_healthcheck', [$this->processor, 'handle_cron_healthcheck']);
        
        if (!wp_next_scheduled('truebeep_import_healthcheck')) {
            wp_schedule_event(time(), 'hourly', 'truebeep_import_healthcheck');
        }
    }

    /**
     * Start import process
     */
    public function start_import()
    {
        if ($this->is_import_running()) {
            return [
                'success' => false,
                'message' => __('Import is already running', 'truebeep')
            ];
        }

        update_option('truebeep_import_lock', time());
        update_option('truebeep_import_status', 'preparing');
        
        delete_option('truebeep_import_progress');
        delete_option('truebeep_import_log');
        
        $customer_ids = $this->importer->get_customers_to_import();
        _log('customer_ids');
        _log($customer_ids);
        
        if (empty($customer_ids)) {
            update_option('truebeep_import_status', 'completed');
            delete_option('truebeep_import_lock');
            
            return [
                'success' => false,
                'message' => __('No customers to import', 'truebeep')
            ];
        }

        update_option('truebeep_import_progress', [
            'total' => count($customer_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ]);

        $batches = array_chunk($customer_ids, CustomerImporter::BATCH_SIZE);
        
        $this->processor->schedule_import($batches);
        
        update_option('truebeep_import_status', 'running');
        update_option('truebeep_import_started_at', current_time('mysql'));

        return [
            'success' => true,
            'message' => sprintf(
                __('Import started. Processing %d customers in %d batches.', 'truebeep'),
                count($customer_ids),
                count($batches)
            ),
            'total' => count($customer_ids),
            'batches' => count($batches)
        ];
    }

    /**
     * Get import status
     */
    public function get_import_status()
    {
        $status = get_option('truebeep_import_status', 'idle');
        $progress = get_option('truebeep_import_progress', []);
        $statistics = $this->importer->get_import_statistics();
        
        if ($status === 'running' && !$this->processor->has_pending_actions()) {
            if ($statistics['remaining'] === 0) {
                update_option('truebeep_import_status', 'completed');
                $status = 'completed';
            }
        }

        return [
            'status' => $status,
            'progress' => $progress,
            'statistics' => $statistics,
            'is_running' => $this->is_import_running(),
            'started_at' => get_option('truebeep_import_started_at'),
            'completed_at' => get_option('truebeep_import_completed_at'),
            'last_update' => get_option('truebeep_import_last_update', 0)
        ];
    }

    /**
     * Check if import is running
     */
    public function is_import_running()
    {
        $status = get_option('truebeep_import_status') === 'running';
        return $status || $this->processor->has_pending_actions();
    }

    /**
     * Cancel import
     */
    public function cancel_import()
    {
        $this->processor->clear_scheduled_actions();
        
        update_option('truebeep_import_status', 'cancelled');
        delete_option('truebeep_import_lock');
        
        return true;
    }

    /**
     * Reset import data
     */
    public function reset_import()
    {
        $this->cancel_import();
        
        delete_option('truebeep_import_status');
        delete_option('truebeep_import_progress');
        delete_option('truebeep_import_log');
        delete_option('truebeep_import_lock');
        delete_option('truebeep_import_started_at');
        delete_option('truebeep_import_completed_at');
        delete_option('truebeep_import_last_update');
        
        return true;
    }

    /**
     * AJAX start import
     */
    public function ajax_start_import()
    {
        check_ajax_referer('truebeep_import_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $result = $this->start_import();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX get status
     */
    public function ajax_get_import_status()
    {
        check_ajax_referer('truebeep_import_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $status = $this->get_import_status();
        wp_send_json_success($status);
    }

    /**
     * AJAX cancel import
     */
    public function ajax_cancel_import()
    {
        check_ajax_referer('truebeep_import_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $this->cancel_import();
        wp_send_json_success(__('Import cancelled', 'truebeep'));
    }

    /**
     * AJAX reset import
     */
    public function ajax_reset_import()
    {
        check_ajax_referer('truebeep_import_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }

        $this->reset_import();
        wp_send_json_success(__('Import data reset', 'truebeep'));
    }
}