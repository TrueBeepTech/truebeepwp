<?php

namespace Truebeep\Admin;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Network Diagnostics for Truebeep API
 */
class NetworkDiagnostics {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_diagnostics_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_truebeep_smwl_test_network_connection', [$this, 'test_network_connection']);
    }
    
    /**
     * Enqueue scripts and styles for diagnostics page
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        // Only load on our diagnostics page
        if ('tools_page_truebeep-diagnostics' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'truebeep-smwl-network-diagnostics',
            TRUEBEEP_URL . '/assets/js/admin/network-diagnostics.js',
            [],
            TRUEBEEP_VERSION,
            true
        );
        
        wp_localize_script('truebeep-smwl-network-diagnostics', 'truebeep_smwl_diagnostics', [
            'nonce' => wp_create_nonce('truebeep_smwl_diagnostics'),
            'strings' => [
                'running' => __('Running...', 'truebeep-smart-wallet-loyalty'),
                'runDiagnostics' => __('Run Diagnostics', 'truebeep-smart-wallet-loyalty'),
                'runningDiagnostics' => __('Running diagnostics...', 'truebeep-smart-wallet-loyalty'),
                /* translators: %s: error message */
                'errorRunning' => __('Error running diagnostics: %s', 'truebeep-smart-wallet-loyalty'),
            ],
        ]);
    }
    
    /**
     * Add diagnostics page to admin menu
     */
    public function add_diagnostics_page() {
        add_submenu_page(
            'tools.php',
            esc_html__('Truebeep Network Diagnostics', 'truebeep-smart-wallet-loyalty'),
            esc_html__('Truebeep Diagnostics', 'truebeep-smart-wallet-loyalty'),
            'manage_options',
            'truebeep-diagnostics',
            [$this, 'diagnostics_page']
        );
    }
    
    /**
     * Render diagnostics page
     */
    public function diagnostics_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Truebeep Network Diagnostics', 'truebeep-smart-wallet-loyalty'); ?></h1>
            <p><?php esc_html_e('Use this tool to diagnose network connectivity issues with the Truebeep API service.', 'truebeep-smart-wallet-loyalty'); ?></p>
            
            <div id="truebeep-diagnostics-results">
                <button type="button" class="button button-primary" id="run-diagnostics">
                    <?php esc_html_e('Run Diagnostics', 'truebeep-smart-wallet-loyalty'); ?>
                </button>
            </div>
            
            <h2><?php esc_html_e('Troubleshooting', 'truebeep-smart-wallet-loyalty'); ?></h2>
            <div class="card">
                <h3><?php esc_html_e('If Truebeep API is blocked:', 'truebeep-smart-wallet-loyalty'); ?></h3>
                <ol>
                    <li><strong><?php esc_html_e('Check your firewall:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('Ensure api.truebeep.com is allowed', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><strong><?php esc_html_e('Proxy settings:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('If behind a corporate proxy, configure WordPress proxy constants', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><strong><?php esc_html_e('Local environment:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('Some local environments block external connections', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><strong><?php esc_html_e('SSL verification:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('Ensure your server has valid SSL certificates', 'truebeep-smart-wallet-loyalty'); ?></li>
                </ol>
                
                <h3><?php esc_html_e('WordPress Proxy Configuration:', 'truebeep-smart-wallet-loyalty'); ?></h3>
                <p><?php esc_html_e('Add these constants to your wp-config.php if you\'re behind a proxy:', 'truebeep-smart-wallet-loyalty'); ?></p>
                <pre><code>define('WP_PROXY_HOST', 'your-proxy-host');
define('WP_PROXY_PORT', 'your-proxy-port');
define('WP_PROXY_USERNAME', 'username'); // Optional
define('WP_PROXY_PASSWORD', 'password'); // Optional</code></pre>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test network connection via AJAX
     */
    public function test_network_connection() {
        check_ajax_referer('truebeep_smwl_diagnostics');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'truebeep-smart-wallet-loyalty'));
        }
        
        $results = [];
        
        $html = $this->format_results($results);
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Format test results as HTML
     */
    private function format_results($results) {
        $html = '<h2>' . esc_html__('Diagnostic Results', 'truebeep-smart-wallet-loyalty') . '</h2>';
        
        foreach ($results as $result) {
            $class = '';
            $icon = '';
            
            switch ($result['status']) {
                case 'passed':
                    $class = 'notice-success';
                    $icon = '✓';
                    break;
                case 'warning':
                    $class = 'notice-warning';
                    $icon = '⚠';
                    break;
                case 'failed':
                    $class = 'notice-error';
                    $icon = '✗';
                    break;
                case 'info':
                    $class = 'notice-info';
                    $icon = 'ℹ';
                    break;
            }
            
            $html .= sprintf(
                '<div class="notice %s"><p><strong>%s %s</strong> (%s)<br>%s</p></div>',
                esc_attr($class),
                esc_html($icon),
                esc_html($result['test']),
                esc_html($result['duration']),
                $result['message'] // Already escaped in test methods
            );
        }
        
        // Add system info
        $html .= '<h3>' . esc_html__('System Information', 'truebeep-smart-wallet-loyalty') . '</h3>';
        $html .= '<div class="notice notice-info"><p>';
        $html .= '<strong>' . esc_html__('WordPress Version:', 'truebeep-smart-wallet-loyalty') . '</strong> ' . esc_html(get_bloginfo('version')) . '<br>';
        $html .= '<strong>' . esc_html__('PHP Version:', 'truebeep-smart-wallet-loyalty') . '</strong> ' . esc_html(PHP_VERSION) . '<br>';
        
        $curl_version = function_exists('curl_version') ? curl_version()['version'] : '';
        $curl_display = !empty($curl_version) ? esc_html($curl_version) : esc_html__('Not available', 'truebeep-smart-wallet-loyalty');
        $html .= '<strong>' . esc_html__('cURL Version:', 'truebeep-smart-wallet-loyalty') . '</strong> ' . $curl_display . '<br>';
        
        $openssl_display = defined('OPENSSL_VERSION_TEXT') ? esc_html(OPENSSL_VERSION_TEXT) : esc_html__('Not available', 'truebeep-smart-wallet-loyalty');
        $html .= '<strong>' . esc_html__('OpenSSL Version:', 'truebeep-smart-wallet-loyalty') . '</strong> ' . $openssl_display . '<br>';
        
        $html .= '<strong>' . esc_html__('User Agent:', 'truebeep-smart-wallet-loyalty') . '</strong> ' . esc_html($_SERVER['HTTP_USER_AGENT'] ?? esc_html__('Not available', 'truebeep-smart-wallet-loyalty')) . '<br>';
        $html .= '</p></div>';
        
        return $html;
    }
}