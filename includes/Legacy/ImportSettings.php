<?php

namespace Truebeep\Legacy;

/**
 * Import Settings Page
 * Handles the admin UI for bulk customer import
 */
class ImportSettings
{
    /**
     * @var ImportManager
     */
    private $import_manager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->import_manager = new ImportManager();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Add submenu to WooCommerce settings
        add_action('admin_menu', [$this, 'add_admin_menu'], 60);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Truebeep Import', 'truebeep'),
            __('Truebeep Import', 'truebeep'),
            'manage_woocommerce',
            'truebeep-import',
            [$this, 'render_import_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our import page
        if ($hook !== 'woocommerce_page_truebeep-import') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'truebeep-import-admin',
            TRUEBEEP_URL . '/assets/css/import-admin.css',
            [],
            TRUEBEEP_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'truebeep-import-admin',
            TRUEBEEP_URL . '/assets/js/import-admin.js',
            ['jquery'],
            TRUEBEEP_VERSION,
            true
        );

        // Localize script
        wp_localize_script('truebeep-import-admin', 'truebeepImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('truebeep_import_nonce'),
            'strings' => [
                'startImport' => __('Start Import', 'truebeep'),
                'importRunning' => __('Import Running...', 'truebeep'),
                'importCompleted' => __('Import Completed', 'truebeep'),
                'importCancelled' => __('Import Cancelled', 'truebeep'),
                'importFailed' => __('Import Failed', 'truebeep'),
                'cancelImport' => __('Cancel Import', 'truebeep'),
                'resetImport' => __('Reset', 'truebeep'),
                'confirmCancel' => __('Are you sure you want to cancel the import?', 'truebeep'),
                'confirmReset' => __('Are you sure you want to reset the import data?', 'truebeep'),
            ]
        ]);
    }

    /**
     * Render the import page
     */
    public function render_import_page()
    {
        _log('render_import_page');

        $status = $this->import_manager->get_import_status();
        $importer = new CustomerImporter();
        $stats = $importer->get_import_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Truebeep Customer Import', 'truebeep'); ?></h1>
            
            <div class="truebeep-import-container">
                <!-- Import Status Card -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Import Status', 'truebeep'); ?></h2>
                    <div class="inside">
                        <div class="truebeep-status-grid">
                            <div class="status-item">
                                <span class="label"><?php _e('Current Status:', 'truebeep'); ?></span>
                                <span class="value status-<?php echo esc_attr($status['status']); ?>">
                                    <?php echo $this->get_status_label($status['status']); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="label"><?php _e('Total Customers:', 'truebeep'); ?></span>
                                <span class="value"><?php echo number_format($stats['total']); ?></span>
                            </div>
                            
                            <div class="status-item">
                                <span class="label"><?php _e('Remaining:', 'truebeep'); ?></span>
                                <span class="value"><?php echo number_format($stats['remaining']); ?></span>
                            </div>
                            
                            <div class="status-item">
                                <span class="label"><?php _e('Processed:', 'truebeep'); ?></span>
                                <span class="value"><?php echo number_format($status['progress']['processed'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Card -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Import Progress', 'truebeep'); ?></h2>
                    <div class="inside">
                        <div class="progress-container">
                            <div class="progress-bar-container">
                                <div class="progress-bar" id="import-progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $stats['percentage']; ?>%"></div>
                                </div>
                                <span class="progress-text" id="progress-text">
                                    <?php echo $stats['percentage']; ?>%
                                </span>
                            </div>
                            
                            <div class="progress-stats">
                                <div class="stat successful">
                                    <span class="number"><?php echo number_format($status['progress']['successful'] ?? 0); ?></span>
                                    <span class="label"><?php _e('Successful', 'truebeep'); ?></span>
                                </div>
                                
                                <div class="stat failed">
                                    <span class="number"><?php echo number_format($status['progress']['failed'] ?? 0); ?></span>
                                    <span class="label"><?php _e('Failed', 'truebeep'); ?></span>
                                </div>
                                
                                <div class="stat skipped">
                                    <span class="number"><?php echo number_format($status['progress']['skipped'] ?? 0); ?></span>
                                    <span class="label"><?php _e('Skipped', 'truebeep'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls Card -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Import Controls', 'truebeep'); ?></h2>
                    <div class="inside">
                        <div class="import-controls">
                            <?php if ($status['status'] === 'idle' || $status['status'] === 'completed' || $status['status'] === 'cancelled'): ?>
                                <button id="start-import-btn" class="button button-primary button-large">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php _e('Start Import', 'truebeep'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($status['is_running']): ?>
                                <button id="cancel-import-btn" class="button button-secondary">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php _e('Cancel Import', 'truebeep'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($status['status'] !== 'running' && $status['status'] !== 'preparing'): ?>
                                <button id="reset-import-btn" class="button">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php _e('Reset Data', 'truebeep'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="import-info">
                            <p class="description">
                                <?php _e('This will import all existing WordPress customers to Truebeep. The process runs in the background and processes customers in batches of 20.', 'truebeep'); ?>
                            </p>
                            
                            <?php if ($stats['remaining'] > 0): ?>
                                <div class="notice notice-info inline">
                                    <p>
                                        <?php 
                                        printf(
                                            __('There are %s customers ready to be imported. This will take approximately %s minutes.', 'truebeep'),
                                            '<strong>' . number_format($stats['remaining']) . '</strong>',
                                            '<strong>' . ceil($stats['remaining'] / (CustomerImporter::BATCH_SIZE * 2)) . '</strong>'
                                        ); 
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Import Log Card -->
                <?php if ($status['status'] !== 'idle'): ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Import Log', 'truebeep'); ?></h2>
                    <div class="inside">
                        <div id="import-log" class="import-log">
                            <?php $this->render_import_log(); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Loading overlay -->
            <div id="import-loading-overlay" class="loading-overlay" style="display: none;">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p><?php _e('Processing...', 'truebeep'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get status label
     * 
     * @param string $status Status key
     * @return string Status label
     */
    private function get_status_label($status)
    {
        $labels = [
            'idle' => __('Ready to Import', 'truebeep'),
            'preparing' => __('Preparing...', 'truebeep'),
            'running' => __('Import Running', 'truebeep'),
            'completed' => __('Completed', 'truebeep'),
            'cancelled' => __('Cancelled', 'truebeep'),
            'failed' => __('Failed', 'truebeep')
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Render import log
     */
    private function render_import_log()
    {
        $log = get_option('truebeep_import_log', []);
        
        if (empty($log)) {
            echo '<p class="description">' . __('No log entries yet.', 'truebeep') . '</p>';
            return;
        }

        echo '<div class="log-entries">';
        foreach (array_slice($log, 0, 10) as $entry) {
            printf(
                '<div class="log-entry">
                    <span class="timestamp">%s</span>
                    <span class="stats">Processed: %d | Success: %d | Failed: %d | Skipped: %d</span>
                </div>',
                $entry['timestamp'],
                count($entry['processed']),
                $entry['successful'],
                $entry['failed'],
                $entry['skipped']
            );
        }
        echo '</div>';
    }
}