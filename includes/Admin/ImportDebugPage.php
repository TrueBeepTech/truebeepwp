<?php

namespace Truebeep\Admin;

use Truebeep\Legacy\ImportDebugger;
use Truebeep\Legacy\ImportManager;

/**
 * Import Debug Admin Page
 */
class ImportDebugPage
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_debug_menu'], 100);
        add_action('wp_ajax_truebeep_debug_action', [$this, 'handle_debug_action']);
    }
    
    /**
     * Add debug menu
     */
    public function add_debug_menu()
    {
        add_submenu_page(
            'woocommerce',
            'Truebeep Import Debug',
            'Truebeep Debug',
            'manage_woocommerce',
            'truebeep-import-debug',
            [$this, 'render_page']
        );
    }
    
    /**
     * Render the debug page
     */
    public function render_page()
    {
        $status = ImportDebugger::get_detailed_status();
        $api_status = ImportDebugger::check_api_connection();
        $duplicates = ImportDebugger::check_duplicates();
        
        ?>
        <div class="wrap">
            <h1>Truebeep Import Debug</h1>
            
            <div class="notice notice-warning">
                <p><strong>Debug Mode:</strong> This page helps diagnose and fix import issues.</p>
            </div>
            
            <!-- API Connection Status -->
            <div class="card">
                <h2>API Connection</h2>
                <table class="widefat">
                    <tr>
                        <th>API URL</th>
                        <td><?php echo esc_html($api_status['api_url']); ?></td>
                    </tr>
                    <tr>
                        <th>API Key Set</th>
                        <td><?php echo $api_status['api_key_set'] ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <th>Connection Test</th>
                        <td>
                            <?php 
                            if (is_wp_error($api_status['connection_test'])) {
                                echo '❌ ' . esc_html($api_status['connection_test']->get_error_message());
                            } else {
                                echo $api_status['connection_test']['success'] ? '✅ Connected' : '❌ Failed';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php if ($api_status['response_structure_info']): ?>
                    <tr>
                        <th>Response Structure</th>
                        <td>
                            <pre><?php echo esc_html(json_encode($api_status['response_structure_info'], JSON_PRETTY_PRINT)); ?></pre>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Import Status -->
            <div class="card">
                <h2>Import Status</h2>
                <table class="widefat">
                    <tr>
                        <th>Status</th>
                        <td><?php echo esc_html($status['status']['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Progress</th>
                        <td>
                            <?php 
                            $progress = $status['status']['progress'];
                            echo sprintf(
                                'Processed: %d / %d (%s%%)',
                                $progress['processed'] ?? 0,
                                $progress['total'] ?? 0,
                                $progress['percentage'] ?? 0
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Successful</th>
                        <td><?php echo intval($progress['successful'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th>Failed</th>
                        <td><?php echo intval($progress['failed'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th>Skipped</th>
                        <td><?php echo intval($progress['skipped'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th>Remaining Customers</th>
                        <td><?php echo intval($status['remaining_customers']); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Action Scheduler Status -->
            <div class="card">
                <h2>Action Scheduler Status</h2>
                <table class="widefat">
                    <tr>
                        <th>Pending Actions</th>
                        <td><?php echo intval($status['action_scheduler']['pending']); ?></td>
                    </tr>
                    <tr>
                        <th>Failed Actions</th>
                        <td><?php echo intval($status['action_scheduler']['failed']); ?></td>
                    </tr>
                    <tr>
                        <th>Running Actions</th>
                        <td><?php echo intval($status['action_scheduler']['running']); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Duplicate Check -->
            <div class="card">
                <h2>Import Statistics</h2>
                <table class="widefat">
                    <tr>
                        <th>Total Imported</th>
                        <td><?php echo intval($duplicates['total_imported']); ?></td>
                    </tr>
                    <tr>
                        <th>Unique Truebeep IDs</th>
                        <td><?php echo intval($duplicates['unique_truebeep_ids']); ?></td>
                    </tr>
                    <tr>
                        <th>Has Duplicates</th>
                        <td><?php echo $duplicates['has_duplicates'] ? '⚠️ Yes' : '✅ No'; ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Recent Errors -->
            <?php if (!empty($status['recent_errors'])): ?>
            <div class="card">
                <h2>Recent Errors</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Error</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status['recent_errors'] as $error): ?>
                        <tr>
                            <td><?php echo esc_html($error['user_id']); ?></td>
                            <td><?php echo esc_html($error['error']); ?></td>
                            <td><?php echo esc_html($error['timestamp']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Debug Actions -->
            <div class="card">
                <h2>Debug Actions</h2>
                <p>
                    <button class="button" onclick="truebeepDebugAction('test_batch')">
                        Test Process 5 Customers
                    </button>
                    <button class="button" onclick="truebeepDebugAction('retry_failed')">
                        Retry Failed Actions
                    </button>
                    <button class="button button-primary" onclick="truebeepDebugAction('clear_restart')">
                        Clear & Restart Import
                    </button>
                </p>
                <div id="debug-result"></div>
            </div>
        </div>
        
        <script>
        function truebeepDebugAction(action) {
            jQuery('#debug-result').html('Processing...');
            
            jQuery.post(ajaxurl, {
                action: 'truebeep_debug_action',
                debug_action: action,
                nonce: '<?php echo wp_create_nonce('truebeep_debug'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#debug-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    if (response.data.details) {
                        jQuery('#debug-result').append('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    }
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    jQuery('#debug-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Handle debug AJAX actions
     */
    public function handle_debug_action()
    {
        check_ajax_referer('truebeep_debug', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }
        
        $action = $_POST['debug_action'] ?? '';
        
        switch ($action) {
            case 'test_batch':
                $result = ImportDebugger::fix_stuck_batch();
                break;
                
            case 'retry_failed':
                $result = ImportDebugger::retry_failed_actions();
                break;
                
            case 'clear_restart':
                $result = ImportDebugger::clear_and_restart();
                break;
                
            default:
                wp_send_json_error('Invalid action');
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'] ?? 'Action completed',
                'details' => $result
            ]);
        } else {
            wp_send_json_error($result['message'] ?? 'Action failed');
        }
    }
}