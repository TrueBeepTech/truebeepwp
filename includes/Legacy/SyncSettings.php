<?php

namespace Truebeep\Legacy;

/**
 * Customer Sync Settings Page
 * 
 * Manages the customer synchronization interface in WordPress admin.
 * Provides UI for syncing WordPress users with Truebeep platform.
 * 
 * @package Truebeep\Legacy
 * @since 1.0.0
 */
class SyncSettings
{
    /**
     * @var SyncManager Instance of sync manager
     */
    private $sync_manager;
    
    /**
     * Initialize sync settings
     * 
     * Sets up the sync manager instance and registers WordPress hooks
     * for menu items and AJAX handlers.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->sync_manager = new SyncManager();
        
        add_action('admin_menu', [$this, 'add_sync_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_truebeep_get_sync_statistics', [$this, 'ajax_get_statistics']);
    }
    
    /**
     * Add sync menu to WordPress Users menu
     * 
     * Creates a submenu item under Users for the Truebeep sync functionality.
     * 
     * @return void
     */
    public function add_sync_menu()
    {
        add_submenu_page(
            'users.php',
            __('Sync to Truebeep', 'truebeep'),
            __('Sync to Truebeep', 'truebeep'),
            'manage_options',
            'truebeep-sync',
            [$this, 'render_sync_page']
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * Loads JavaScript and CSS files required for the sync interface.
     * Only loads on the sync page to avoid unnecessary resource loading.
     * 
     * @param string $hook_suffix The current admin page hook suffix
     * @return void
     */
    public function enqueue_scripts($hook_suffix)
    {
        if ($hook_suffix !== 'users_page_truebeep-sync') {
            return;
        }
        
        wp_enqueue_script(
            'truebeep-sync',
            plugins_url('assets/js/sync-admin.js', dirname(dirname(__FILE__))),
            ['jquery'],
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/js/sync-admin.js'),
            true
        );
        
        wp_localize_script('truebeep-sync', 'truebeep_sync', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('truebeep_sync_nonce'),
            'strings' => [
                'starting' => __('Starting sync...', 'truebeep'),
                'stopping' => __('Stopping sync...', 'truebeep'),
                'error' => __('An error occurred. Please try again.', 'truebeep'),
                'confirm_cancel' => __('Are you sure you want to cancel the sync?', 'truebeep'),
                'confirm_reset' => __('Are you sure you want to reset all sync data? This cannot be undone.', 'truebeep')
            ]
        ]);
        
        wp_enqueue_style(
            'truebeep-sync',
            plugins_url('assets/css/sync-admin.css', dirname(dirname(__FILE__))),
            [],
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/css/sync-admin.css')
        );
    }
    
    /**
     * Render the sync settings page
     * 
     * Displays the main sync interface including status, progress,
     * controls, and statistics.
     * 
     * @return void
     */
    public function render_sync_page()
    {
        $status = $this->sync_manager->get_sync_status();
        $stats = $status['statistics'] ?? [];
        
        ?>
        <div class="wrap truebeep-sync-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-update" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
                <?php _e('Sync Customers to Truebeep', 'truebeep'); ?>
            </h1>
            
            <?php if (!get_option('truebeep_api_url') || !get_option('truebeep_api_key')): ?>
                <div class="notice notice-error">
                    <p><?php _e('Please configure Truebeep API settings first.', 'truebeep'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=truebeep'); ?>" class="button button-primary"><?php _e('Configure Settings', 'truebeep'); ?></a></p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <!-- Full Width Status Card -->
            <div class="card sync-status-card">
                <h2 class="title"><?php _e('Sync Status', 'truebeep'); ?></h2>
                <div class="card-body">
                    <div class="sync-status-indicator">
                        <span class="status-badge status-<?php echo esc_attr($status['status']); ?>">
                            <?php echo $this->get_status_label($status['status']); ?>
                        </span>
                        <?php if ($status['status'] === 'processing'): ?>
                            <span class="spinner is-active" style="margin: 0 10px; float: none;"></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($status['status'] !== 'idle'): ?>
                        <?php $progress = $status['progress'] ?? []; ?>
                        <?php $isCompleted = ($status['status'] === 'completed' && ($stats['percentage'] ?? 0) == 100); ?>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill<?php echo $isCompleted ? ' completed' : ''; ?>" style="width: <?php echo esc_attr($stats['percentage'] ?? 0); ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <?php printf(
                                    __('%d of %d customers synced (%s%%)', 'truebeep'),
                                    $progress['processed'] ?? 0,
                                    $progress['total'] ?? 0,
                                    number_format($stats['percentage'] ?? 0, 1)
                                ); ?>
                            </div>
                        </div>
                        
                        <div class="sync-stats-grid">
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('Successful', 'truebeep'); ?></span>
                                <span class="stat-value success"><?php echo number_format($progress['successful'] ?? 0); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('Failed', 'truebeep'); ?></span>
                                <span class="stat-value error"><?php echo number_format($progress['failed'] ?? 0); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('Skipped', 'truebeep'); ?></span>
                                <span class="stat-value warning"><?php echo number_format($progress['skipped'] ?? 0); ?></span>
                            </div>
                        </div>
                        
                        <?php if (isset($status['rate_limit']) && !empty($status['rate_limit'])): ?>
                            <div class="rate-limit-info">
                                <p class="description">
                                    <?php printf(
                                        __('Processing %d customers per batch, with %d second intervals between batches.', 'truebeep'),
                                        $status['rate_limit']['batch_size'] ?? 20,
                                        $status['rate_limit']['interval'] ?? 60
                                    ); ?>
                                </p>
                                <?php if (isset($status['estimated_time_remaining']) && $status['estimated_time_remaining'] > 0): ?>
                                    <p class="description">
                                        <?php printf(
                                            __('Estimated time remaining: %s', 'truebeep'),
                                            human_time_diff(time(), time() + $status['estimated_time_remaining'])
                                        ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bottom Row Container -->
            <div class="truebeep-sync-container">
                <!-- Controls Card -->
                <div class="card">
                    <h2 class="title"><?php _e('Sync Controls', 'truebeep'); ?></h2>
                    <div class="card-body">
                        <div class="sync-controls">
                            <?php if ($status['status'] === 'idle' || $status['status'] === 'completed' || $status['status'] === 'cancelled'): ?>
                                <?php if ($stats['remaining'] > 0): ?>
                                    <div class="notice notice-info inline">
                                        <p>
                                            <?php printf(
                                                __('There are %s customers ready to sync. Estimated time: %s minutes.', 'truebeep'),
                                                '<strong>' . number_format($stats['remaining']) . '</strong>',
                                                '<strong>' . ceil($stats['remaining'] / CustomerSyncProcessor::BATCH_SIZE) . '</strong>'
                                            ); ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="notice notice-success inline">
                                        <p><?php _e('All customers are synchronized!', 'truebeep'); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="sync-actions">
                                    <button type="button" class="button button-primary button-hero" id="start-sync" <?php echo $stats['remaining'] === 0 ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-update"></span>
                                        <?php _e('Start Sync', 'truebeep'); ?>
                                    </button>
                                </p>
                            <?php elseif ($status['status'] === 'processing' || $status['status'] === 'running'): ?>
                                <p class="sync-actions">
                                    <button type="button" class="button button-secondary button-hero" id="cancel-sync">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Cancel Sync', 'truebeep'); ?>
                                    </button>
                                </p>
                            <?php elseif ($status['status'] === 'paused'): ?>
                                <p class="sync-actions">
                                    <button type="button" class="button button-primary button-hero" id="resume-sync">
                                        <span class="dashicons dashicons-controls-play"></span>
                                        <?php _e('Resume Sync', 'truebeep'); ?>
                                    </button>
                                    <button type="button" class="button button-secondary button-hero" id="cancel-sync">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Cancel Sync', 'truebeep'); ?>
                                    </button>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($status['status'] !== 'idle' && $status['status'] !== 'processing'): ?>
                                <hr style="margin: 20px 0;">
                                <p>
                                    <button type="button" class="button button-link-delete" id="reset-sync">
                                        <?php _e('Reset Sync Data', 'truebeep'); ?>
                                    </button>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Card -->
                <div class="card">
                    <h2 class="title"><?php _e('Sync Statistics', 'truebeep'); ?></h2>
                    <div class="card-body">
                        <table class="wp-list-table widefat fixed striped">
                            <tbody>
                                <tr>
                                    <th><?php _e('Total Customers', 'truebeep'); ?></th>
                                    <td><strong><?php echo number_format(($stats['total'] ?? 0) + ($stats['remaining'] ?? 0)); ?></strong></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Already Synced', 'truebeep'); ?></th>
                                    <td><strong><?php echo number_format($stats['total'] ?? 0); ?></strong></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Remaining to Sync', 'truebeep'); ?></th>
                                    <td><strong><?php echo number_format($stats['remaining'] ?? 0); ?></strong></td>
                                </tr>
                                <?php if ($status['started_at']): ?>
                                <tr>
                                    <th><?php _e('Started At', 'truebeep'); ?></th>
                                    <td><?php echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($status['started_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($status['completed_at']): ?>
                                <tr>
                                    <th><?php _e('Completed At', 'truebeep'); ?></th>
                                    <td><?php echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($status['completed_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sync Log Section -->
            <?php $logs = $this->sync_manager->get_sync_logs(20); ?>
            <?php if (!empty($logs)): ?>
            <div class="card sync-log-card">
                <h2 class="title"><?php _e('Sync Activity Log', 'truebeep'); ?></h2>
                <div class="card-body">
                    <div class="sync-log-container">
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry">
                                <div class="log-header">
                                    <span class="log-timestamp"><?php echo esc_html($log['formatted_timestamp']); ?></span>
                                    <span class="log-summary"><?php echo esc_html($log['summary']); ?></span>
                                </div>
                                
                                <?php if (!empty($log['errors'])): ?>
                                    <div class="log-details">
                                        <div class="log-errors">
                                            <strong><?php _e('Errors:', 'truebeep'); ?></strong>
                                            <ul class="error-list">
                                                <?php foreach ($log['errors'] as $user_id => $error): ?>
                                                    <li class="error-item">
                                                        <span class="error-user"><?php printf(__('User ID %s:', 'truebeep'), $user_id); ?></span>
                                                        <span class="error-message"><?php echo esc_html($error); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (($log['successful'] ?? 0) > 0): ?>
                                    <div class="log-success">
                                        <span class="success-count"><?php printf(__('%d customers synced successfully', 'truebeep'), $log['successful']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($logs) >= 20): ?>
                        <div class="log-footer">
                            <p class="log-note"><?php _e('Showing latest 20 log entries. Logs are automatically cleaned after 100 entries.', 'truebeep'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get human-readable status label
     * 
     * Converts internal status codes to user-friendly labels.
     * 
     * @param string $status Internal status code
     * @return string Translated status label
     */
    private function get_status_label($status)
    {
        $labels = [
            'idle' => __('Not Started', 'truebeep'),
            'preparing' => __('Preparing...', 'truebeep'),
            'processing' => __('Syncing...', 'truebeep'),
            'completed' => __('Completed', 'truebeep'),
            'cancelled' => __('Cancelled', 'truebeep'),
            'paused' => __('Paused', 'truebeep'),
            'failed' => __('Failed', 'truebeep')
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * AJAX handler for getting sync statistics
     * 
     * Returns current sync statistics in JSON format for live updates.
     * 
     * @return void
     */
    public function ajax_get_statistics()
    {
        check_ajax_referer('truebeep_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'truebeep'));
        }
        
        $status = $this->sync_manager->get_sync_status();
        wp_send_json_success($status);
    }
}