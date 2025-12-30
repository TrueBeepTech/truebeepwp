<?php

namespace Truebeep\Admin;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Network Diagnostics for GitHub Updater
 */
class NetworkDiagnostics {
    
    /**
     * GitHub repository configuration
     * @var array
     */
    private $github_config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_github_config();
        add_action('admin_menu', [$this, 'add_diagnostics_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_truebeep_smwl_test_github_connection', [$this, 'test_github_connection']);
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
     * Load GitHub configuration
     */
    private function load_github_config() {
        $config_file = TRUEBEEP_PATH . '/github-config.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
            
            // Parse repository URL if provided
            if (!empty($config['repository_url'])) {
                $parsed = $this->parse_github_url($config['repository_url']);
                if ($parsed) {
                    $config['username'] = $parsed['username'];
                    $config['repository'] = $parsed['repository'];
                }
            }
            
            $this->github_config = $config;
        } else {
            // Default fallback - empty config, updater won't run without proper config
            $this->github_config = [
                'username' => '',
                'repository' => '',
                'repository_url' => ''
            ];
        }
    }
    
    /**
     * Parse GitHub URL to extract username and repository
     */
    private function parse_github_url($url) {
        $url = rtrim($url, '/');
        $url = preg_replace('/\.git$/', '', $url);
        
        if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/]+)/', $url, $matches)) {
            return [
                'username' => $matches[1],
                'repository' => $matches[2]
            ];
        }
        
        return false;
    }
    
    /**
     * Get releases URL
     */
    private function get_releases_url() {
        return $this->github_config['repository_url'] . '/releases';
    }
    
    /**
     * Get API URL
     */
    private function get_api_url($endpoint = '') {
        return sprintf(
            'https://api.github.com/repos/%s/%s/%s',
            $this->github_config['username'],
            $this->github_config['repository'],
            $endpoint
        );
    }
    
    /**
     * Get download URL
     */
    private function get_download_url() {
        return sprintf(
            'https://github.com/%s/%s/archive/refs/heads/master.zip',
            $this->github_config['username'],
            $this->github_config['repository']
        );
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
            <p><?php esc_html_e('Use this tool to diagnose GitHub connectivity issues for plugin updates.', 'truebeep-smart-wallet-loyalty'); ?></p>
            
            <?php if (empty($this->github_config['repository_url'])): ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e('GitHub repository not configured!', 'truebeep-smart-wallet-loyalty'); ?></strong></p>
                <p><?php esc_html_e('Please configure your repository URL in', 'truebeep-smart-wallet-loyalty'); ?> <code>github-config.php</code>:</p>
                <pre><code>'repository_url' => 'https://github.com/YOUR_USERNAME/YOUR_REPOSITORY'</code></pre>
            </div>
            <?php else: ?>
            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Configured Repository:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php echo esc_html($this->github_config['repository_url']); ?></p>
            </div>
            <?php endif; ?>
            
            <div id="truebeep-diagnostics-results">
                <button type="button" class="button button-primary" id="run-diagnostics">
                    <?php esc_html_e('Run Diagnostics', 'truebeep-smart-wallet-loyalty'); ?>
                </button>
            </div>
            
            <h2><?php esc_html_e('Manual Fixes', 'truebeep-smart-wallet-loyalty'); ?></h2>
            <div class="card">
                <h3><?php esc_html_e('If GitHub API is blocked:', 'truebeep-smart-wallet-loyalty'); ?></h3>
                <ol>
                    <li><strong><?php esc_html_e('Check your firewall:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('Ensure api.github.com and github.com are allowed', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><strong><?php esc_html_e('Proxy settings:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('If behind a corporate proxy, configure WordPress proxy constants', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><strong><?php esc_html_e('Local environment:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('Some local environments block external connections', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><strong><?php esc_html_e('Manual update:', 'truebeep-smart-wallet-loyalty'); ?></strong> <?php esc_html_e('Download the latest release ZIP from GitHub and upload manually', 'truebeep-smart-wallet-loyalty'); ?></li>
                </ol>
                
                <h3><?php esc_html_e('WordPress Proxy Configuration:', 'truebeep-smart-wallet-loyalty'); ?></h3>
                <p><?php esc_html_e('Add these constants to your wp-config.php if you\'re behind a proxy:', 'truebeep-smart-wallet-loyalty'); ?></p>
                <pre><code>define('WP_PROXY_HOST', 'your-proxy-host');
define('WP_PROXY_PORT', 'your-proxy-port');
define('WP_PROXY_USERNAME', 'username'); // Optional
define('WP_PROXY_PASSWORD', 'password'); // Optional</code></pre>
                
                <h3><?php esc_html_e('Manual Update Process:', 'truebeep-smart-wallet-loyalty'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Go to:', 'truebeep-smart-wallet-loyalty'); ?> <a href="<?php echo esc_url($this->get_releases_url()); ?>" target="_blank"><?php echo esc_html($this->get_releases_url()); ?></a></li>
                    <li><?php esc_html_e('Download the latest release ZIP file', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><?php esc_html_e('In WordPress admin, go to Plugins > Add New > Upload Plugin', 'truebeep-smart-wallet-loyalty'); ?></li>
                    <li><?php esc_html_e('Upload the ZIP file and activate', 'truebeep-smart-wallet-loyalty'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test GitHub connection via AJAX
     */
    public function test_github_connection() {
        check_ajax_referer('truebeep_smwl_diagnostics');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'truebeep-smart-wallet-loyalty'));
        }
        
        // Check if repository is configured
        if (empty($this->github_config['repository_url'])) {
            $error_html = '<div class="notice notice-error"><p>' . esc_html__('GitHub repository not configured! Please update github-config.php', 'truebeep-smart-wallet-loyalty') . '</p></div>';
            wp_send_json_error(['html' => $error_html]);
            return;
        }
        
        $results = [];
        
        // Test 1: Basic connectivity to GitHub
        $results[] = $this->test_basic_connectivity();
        
        // Test 2: GitHub API access
        $results[] = $this->test_github_api();
        
        // Test 3: Download capability
        $results[] = $this->test_download_capability();
        
        // Test 4: DNS resolution
        $results[] = $this->test_dns_resolution();
        
        // Test 5: SSL/TLS
        $results[] = $this->test_ssl_connection();
        
        $html = $this->format_results($results);
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Test basic connectivity to GitHub
     */
    private function test_basic_connectivity() {
        $start_time = microtime(true);
        
        $response = wp_remote_get('https://github.com', [
            'timeout' => 10,
            'sslverify' => false
        ]);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            return [
                'test' => __('Basic GitHub Connectivity', 'truebeep-smart-wallet-loyalty'),
                'status' => 'failed',
                'message' => sprintf(
                    /* translators: %s: error message */
                    esc_html__('Failed: %s', 'truebeep-smart-wallet-loyalty'),
                    esc_html($response->get_error_message())
                ),
                'duration' => $duration . 'ms'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        return [
            'test' => __('Basic GitHub Connectivity', 'truebeep-smart-wallet-loyalty'),
            'status' => $status_code == 200 ? 'passed' : 'warning',
            'message' => sprintf(
                /* translators: %d: HTTP status code */
                esc_html__('HTTP Status: %d', 'truebeep-smart-wallet-loyalty'),
                $status_code
            ),
            'duration' => $duration . 'ms'
        ];
    }
    
    /**
     * Test GitHub API access
     */
    private function test_github_api() {
        $start_time = microtime(true);
        
        $api_url = $this->get_api_url('releases/latest');
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ],
            'timeout' => 15,
            'sslverify' => false
        ]);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            return [
                'test' => __('GitHub API Access', 'truebeep-smart-wallet-loyalty'),
                'status' => 'failed',
                'message' => sprintf(
                    /* translators: %s: error message */
                    esc_html__('Failed: %s', 'truebeep-smart-wallet-loyalty'),
                    esc_html($response->get_error_message())
                ),
                'duration' => $duration . 'ms'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if ($status_code == 200 && !empty($data->tag_name)) {
            return [
                'test' => __('GitHub API Access', 'truebeep-smart-wallet-loyalty'),
                'status' => 'passed',
                'message' => sprintf(
                    /* translators: %s: version tag name */
                    esc_html__('Success! Latest version: %s', 'truebeep-smart-wallet-loyalty'),
                    esc_html($data->tag_name)
                ),
                'duration' => $duration . 'ms'
            ];
        } elseif ($status_code == 403) {
            return [
                'test' => __('GitHub API Access', 'truebeep-smart-wallet-loyalty'),
                'status' => 'warning',
                'message' => esc_html__('Rate limited (403). This is normal for public repos without authentication.', 'truebeep-smart-wallet-loyalty'),
                'duration' => $duration . 'ms'
            ];
        } else {
            return [
                'test' => __('GitHub API Access', 'truebeep-smart-wallet-loyalty'),
                'status' => 'failed',
                'message' => sprintf(
                    /* translators: %d: HTTP status code */
                    esc_html__('HTTP Status: %d', 'truebeep-smart-wallet-loyalty'),
                    $status_code
                ),
                'duration' => $duration . 'ms'
            ];
        }
    }
    
    /**
     * Test download capability
     */
    private function test_download_capability() {
        $start_time = microtime(true);
        
        // Try to download a small file from GitHub
        $download_url = $this->get_download_url();
        $response = wp_remote_get($download_url, [
            'timeout' => 20,
            'sslverify' => false,
            'headers' => [
                'Range' => 'bytes=0-1023' // Only download first 1KB
            ]
        ]);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            return [
                'test' => __('Download Capability', 'truebeep-smart-wallet-loyalty'),
                'status' => 'failed',
                'message' => sprintf(
                    /* translators: %s: error message */
                    esc_html__('Failed: %s', 'truebeep-smart-wallet-loyalty'),
                    esc_html($response->get_error_message())
                ),
                'duration' => $duration . 'ms'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        return [
            'test' => __('Download Capability', 'truebeep-smart-wallet-loyalty'),
            'status' => in_array($status_code, [200, 206]) ? 'passed' : 'failed',
            'message' => sprintf(
                /* translators: %d: HTTP status code */
                esc_html__('HTTP Status: %d', 'truebeep-smart-wallet-loyalty'),
                $status_code
            ),
            'duration' => $duration . 'ms'
        ];
    }
    
    /**
     * Test DNS resolution
     */
    private function test_dns_resolution() {
        $domains = ['github.com', 'api.github.com'];
        $results = [];
        
        foreach ($domains as $domain) {
            $ip = gethostbyname($domain);
            if ($ip !== $domain) {
                /* translators: %1$s: domain name, %2$s: IP address */
                $results[] = sprintf(esc_html__('✓ %1$s → %2$s', 'truebeep-smart-wallet-loyalty'), esc_html($domain), esc_html($ip));
            } else {
                /* translators: %s: domain name */
                $results[] = sprintf(esc_html__('✗ %s (failed)', 'truebeep-smart-wallet-loyalty'), esc_html($domain));
            }
        }
        
        return [
            'test' => __('DNS Resolution', 'truebeep-smart-wallet-loyalty'),
            'status' => 'info',
            'message' => implode('<br>', $results),
            'duration' => esc_html__('N/A', 'truebeep-smart-wallet-loyalty')
        ];
    }
    
    /**
     * Test SSL connection
     */
    private function test_ssl_connection() {
        $start_time = microtime(true);
        
        $response = wp_remote_get('https://api.github.com', [
            'timeout' => 10,
            'sslverify' => true // Test with SSL verification enabled
        ]);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (strpos($error_message, 'SSL') !== false) {
                return [
                    'test' => __('SSL/TLS Connection', 'truebeep-smart-wallet-loyalty'),
                    'status' => 'warning',
                    'message' => esc_html__('SSL verification failed. Plugin uses sslverify=false as fallback.', 'truebeep-smart-wallet-loyalty'),
                    'duration' => $duration . 'ms'
                ];
            } else {
                return [
                    'test' => __('SSL/TLS Connection', 'truebeep-smart-wallet-loyalty'),
                    'status' => 'failed',
                    'message' => sprintf(
                        /* translators: %s: error message */
                        esc_html__('Failed: %s', 'truebeep-smart-wallet-loyalty'),
                        esc_html($error_message)
                    ),
                    'duration' => $duration . 'ms'
                ];
            }
        }
        
        return [
            'test' => __('SSL/TLS Connection', 'truebeep-smart-wallet-loyalty'),
            'status' => 'passed',
            'message' => esc_html__('SSL connection successful', 'truebeep-smart-wallet-loyalty'),
            'duration' => $duration . 'ms'
        ];
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
        
        $user_agent_response = wp_remote_get('https://httpbin.org/user-agent');
        $user_agent = is_wp_error($user_agent_response) ? esc_html__('Not available', 'truebeep-smart-wallet-loyalty') : esc_html(wp_remote_retrieve_header($user_agent_response, 'User-Agent'));
        $html .= '<strong>' . esc_html__('User Agent:', 'truebeep-smart-wallet-loyalty') . '</strong> ' . $user_agent . '<br>';
        $html .= '</p></div>';
        
        return $html;
    }
}