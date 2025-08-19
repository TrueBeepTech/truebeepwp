<?php

namespace Truebeep\Admin;

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
        add_action('wp_ajax_truebeep_test_github_connection', [$this, 'test_github_connection']);
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
            // Default fallback
            $this->github_config = [
                'username' => 'wildrain',
                'repository' => 'tbpublic',
                'repository_url' => 'https://github.com/wildrain/tbpublic'
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
            'Truebeep Network Diagnostics',
            'Truebeep Diagnostics',
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
            <h1>Truebeep Network Diagnostics</h1>
            <p>Use this tool to diagnose GitHub connectivity issues for plugin updates.</p>
            
            <div id="truebeep-diagnostics-results">
                <button type="button" class="button button-primary" id="run-diagnostics">
                    Run Diagnostics
                </button>
            </div>
            
            <h2>Manual Fixes</h2>
            <div class="card">
                <h3>If GitHub API is blocked:</h3>
                <ol>
                    <li><strong>Check your firewall:</strong> Ensure api.github.com and github.com are allowed</li>
                    <li><strong>Proxy settings:</strong> If behind a corporate proxy, configure WordPress proxy constants</li>
                    <li><strong>Local environment:</strong> Some local environments block external connections</li>
                    <li><strong>Manual update:</strong> Download the latest release ZIP from GitHub and upload manually</li>
                </ol>
                
                <h3>WordPress Proxy Configuration:</h3>
                <p>Add these constants to your wp-config.php if you're behind a proxy:</p>
                <pre><code>define('WP_PROXY_HOST', 'your-proxy-host');
define('WP_PROXY_PORT', 'your-proxy-port');
define('WP_PROXY_USERNAME', 'username'); // Optional
define('WP_PROXY_PASSWORD', 'password'); // Optional</code></pre>
                
                <h3>Manual Update Process:</h3>
                <ol>
                    <li>Go to: <a href="<?php echo esc_url($this->get_releases_url()); ?>" target="_blank"><?php echo esc_html($this->get_releases_url()); ?></a></li>
                    <li>Download the latest release ZIP file</li>
                    <li>In WordPress admin, go to Plugins > Add New > Upload Plugin</li>
                    <li>Upload the ZIP file and activate</li>
                </ol>
            </div>
        </div>
        
        <script>
        document.getElementById('run-diagnostics').addEventListener('click', function() {
            var button = this;
            var resultsDiv = document.getElementById('truebeep-diagnostics-results');
            
            button.disabled = true;
            button.textContent = 'Running...';
            
            resultsDiv.innerHTML = '<div class="notice notice-info"><p>Running diagnostics...</p></div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=truebeep_test_github_connection&_wpnonce=' + '<?php echo wp_create_nonce('truebeep_diagnostics'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                resultsDiv.innerHTML = data.data.html;
                button.disabled = false;
                button.textContent = 'Run Diagnostics';
            })
            .catch(error => {
                resultsDiv.innerHTML = '<div class="notice notice-error"><p>Error running diagnostics: ' + error.message + '</p></div>';
                button.disabled = false;
                button.textContent = 'Run Diagnostics';
            });
        });
        </script>
        <?php
    }
    
    /**
     * Test GitHub connection via AJAX
     */
    public function test_github_connection() {
        check_ajax_referer('truebeep_diagnostics');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
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
                'test' => 'Basic GitHub Connectivity',
                'status' => 'failed',
                'message' => 'Failed: ' . $response->get_error_message(),
                'duration' => $duration . 'ms'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        return [
            'test' => 'Basic GitHub Connectivity',
            'status' => $status_code == 200 ? 'passed' : 'warning',
            'message' => "HTTP Status: $status_code",
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
                'test' => 'GitHub API Access',
                'status' => 'failed',
                'message' => 'Failed: ' . $response->get_error_message(),
                'duration' => $duration . 'ms'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if ($status_code == 200 && !empty($data->tag_name)) {
            return [
                'test' => 'GitHub API Access',
                'status' => 'passed',
                'message' => "Success! Latest version: {$data->tag_name}",
                'duration' => $duration . 'ms'
            ];
        } elseif ($status_code == 403) {
            return [
                'test' => 'GitHub API Access',
                'status' => 'warning',
                'message' => 'Rate limited (403). This is normal for public repos without authentication.',
                'duration' => $duration . 'ms'
            ];
        } else {
            return [
                'test' => 'GitHub API Access',
                'status' => 'failed',
                'message' => "HTTP Status: $status_code",
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
                'test' => 'Download Capability',
                'status' => 'failed',
                'message' => 'Failed: ' . $response->get_error_message(),
                'duration' => $duration . 'ms'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        return [
            'test' => 'Download Capability',
            'status' => in_array($status_code, [200, 206]) ? 'passed' : 'failed',
            'message' => "HTTP Status: $status_code",
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
            $results[] = $ip !== $domain ? "✓ $domain → $ip" : "✗ $domain (failed)";
        }
        
        return [
            'test' => 'DNS Resolution',
            'status' => 'info',
            'message' => implode('<br>', $results),
            'duration' => 'N/A'
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
                    'test' => 'SSL/TLS Connection',
                    'status' => 'warning',
                    'message' => 'SSL verification failed. Plugin uses sslverify=false as fallback.',
                    'duration' => $duration . 'ms'
                ];
            } else {
                return [
                    'test' => 'SSL/TLS Connection',
                    'status' => 'failed',
                    'message' => 'Failed: ' . $error_message,
                    'duration' => $duration . 'ms'
                ];
            }
        }
        
        return [
            'test' => 'SSL/TLS Connection',
            'status' => 'passed',
            'message' => 'SSL connection successful',
            'duration' => $duration . 'ms'
        ];
    }
    
    /**
     * Format test results as HTML
     */
    private function format_results($results) {
        $html = '<h2>Diagnostic Results</h2>';
        
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
                $class,
                $icon,
                $result['test'],
                $result['duration'],
                $result['message']
            );
        }
        
        // Add system info
        $html .= '<h3>System Information</h3>';
        $html .= '<div class="notice notice-info"><p>';
        $html .= '<strong>WordPress Version:</strong> ' . get_bloginfo('version') . '<br>';
        $html .= '<strong>PHP Version:</strong> ' . PHP_VERSION . '<br>';
        $html .= '<strong>cURL Version:</strong> ' . (function_exists('curl_version') ? curl_version()['version'] : 'Not available') . '<br>';
        $html .= '<strong>OpenSSL Version:</strong> ' . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'Not available') . '<br>';
        $html .= '<strong>User Agent:</strong> ' . wp_remote_retrieve_header(wp_remote_get('https://httpbin.org/user-agent'), 'User-Agent') . '<br>';
        $html .= '</p></div>';
        
        return $html;
    }
}