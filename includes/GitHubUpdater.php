<?php

namespace Truebeep;

/**
 * GitHub Plugin Updater
 * 
 * Handles automatic updates from GitHub repository with improved network handling
 */
class GitHubUpdater {
    
    /**
     * GitHub username
     * @var string
     */
    private $username;
    
    /**
     * GitHub repository name
     * @var string
     */
    private $repository;
    
    /**
     * Plugin slug (folder name)
     * @var string
     */
    private $plugin_slug;
    
    /**
     * Plugin file path
     * @var string
     */
    private $plugin_file;
    
    /**
     * GitHub API response
     * @var mixed
     */
    private $github_response;
    
    /**
     * Plugin data from WordPress
     * @var array
     */
    private $plugin_data;
    
    /**
     * GitHub access token for private repositories
     * @var string
     */
    private $access_token;
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Main plugin file path
     * @param string $username GitHub username or organization
     * @param string $repository GitHub repository name
     */
    public function __construct($plugin_file, $username, $repository) {
        $this->plugin_file = $plugin_file;
        $this->username = $username;
        $this->repository = $repository;
        $this->plugin_slug = basename(dirname($plugin_file));
        
        $this->initialize();
    }
    
    /**
     * Initialize the updater
     */
    private function initialize() {
        // Set plugin data immediately
        $this->set_plugin_data();
        
        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update'], 10);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
        
        // Also check on these hooks to ensure updates are detected
        add_filter('site_transient_update_plugins', [$this, 'modify_transient'], 10);
        add_filter('transient_update_plugins', [$this, 'modify_transient'], 10);
        
        // Override download URL to use direct GitHub link
        add_filter('upgrader_package_options', [$this, 'maybe_bypass_github_api'], 10);
        
        // Clear cache on admin pages
        add_action('admin_init', [$this, 'maybe_clear_cache']);
    }
    
    /**
     * Set GitHub access token
     * 
     * @param string $token Personal access token
     */
    public function set_access_token($token) {
        $this->access_token = $token;
    }
    
    /**
     * Set plugin data from WordPress
     */
    public function set_plugin_data() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
    }
    
    /**
     * Maybe clear cache based on user actions
     */
    public function maybe_clear_cache() {
                
        // Clear cache if force-check is requested
        if (isset($_GET['force-check']) && $_GET['force-check'] == 1) {
            delete_transient('truebeep_github_release_' . md5($this->username . $this->repository));
            $this->github_response = null;
        }
        
        // Clear cache on plugins page
        global $pagenow;
        if ($pagenow === 'plugins.php' && isset($_GET['force-check'])) {
            delete_transient('truebeep_github_release_' . md5($this->username . $this->repository));
        }
    }
    
    /**
     * Modify transient to include our plugin update
     * 
     * @param object $transient
     * @return object
     */
    public function modify_transient($transient) {
        if (empty($transient) || !is_object($transient)) {
            $transient = new \stdClass();
        }
        
        // Initialize arrays if they don't exist
        if (!isset($transient->response)) {
            $transient->response = [];
        }
        if (!isset($transient->checked)) {
            $transient->checked = [];
        }
        
        // Ensure plugin data is loaded
        if (empty($this->plugin_data)) {
            $this->set_plugin_data();
        }
        
        // Get GitHub release info
        $this->get_github_release_info();
        
        if ($this->github_response) {
            // Check if update is available
            $github_version = ltrim($this->github_response->tag_name, 'v');
            $current_version = $this->plugin_data['Version'];
            
            if (version_compare($github_version, $current_version, '>')) {
                $plugin_info = $this->generate_plugin_info();
                $transient->response[plugin_basename($this->plugin_file)] = $plugin_info;
            }
            
            // Always add to checked
            $transient->checked[plugin_basename($this->plugin_file)] = $current_version;
        }
        
        return $transient;
    }
    
    /**
     * Check for updates
     * 
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        // Initialize checked array if it doesn't exist
        if (empty($transient->checked)) {
            $transient->checked = [];
        }
        
        // Ensure plugin data is loaded
        if (empty($this->plugin_data)) {
            $this->set_plugin_data();
        }
        
        // Get GitHub release info
        $force = (isset($_GET['force-check']) && $_GET['force-check'] == 1);
        $this->get_github_release_info($force);
        
        if (!$this->github_response) {
            return $transient;
        }
        
        // Store current version in checked array
        $transient->checked[plugin_basename($this->plugin_file)] = $this->plugin_data['Version'];
        
        // Check if update is available
        $github_version = ltrim($this->github_response->tag_name, 'v');
        $current_version = $this->plugin_data['Version'];
        
        $do_update = version_compare(
            $github_version, 
            $current_version, 
            '>'
        );
        
        if ($do_update) {
            $plugin_info = $this->generate_plugin_info();
            
            // Add update info to transient
            $transient->response[plugin_basename($this->plugin_file)] = $plugin_info;
        }
        
        return $transient;
    }
    
    /**
     * Get GitHub release information with improved error handling
     * 
     * @param bool $force_check Force a fresh API call
     * @return mixed GitHub API response or false
     */
    private function get_github_release_info($force_check = false) {
        // Check cache first
        $cache_key = 'truebeep_github_release_' . md5($this->username . $this->repository);
        
        if (!$force_check) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                $this->github_response = $cached;
                return $this->github_response;
            }
            
            if (!empty($this->github_response)) {
                return $this->github_response;
            }
        }
        
        // Try to get release info with fallback methods
        $this->github_response = $this->fetch_github_release();
        
        if (!$this->github_response) {
            // Try alternative method using tags
            $this->github_response = $this->get_latest_tag();
        }
        
        if (!$this->github_response) {
            // Final fallback: create a mock response from known data
            $this->github_response = $this->create_fallback_response();
        }
        
        // Cache the response
        if ($this->github_response && !empty($this->github_response->tag_name)) {
            set_transient($cache_key, $this->github_response, 2 * HOUR_IN_SECONDS);
        }
        
        return $this->github_response;
    }
    
    /**
     * Fetch GitHub release with improved timeout handling
     * 
     * @return mixed
     */
    private function fetch_github_release() {
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );
        
        // Build headers
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
        ];
        
        if (!empty($this->access_token)) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }
        
        // Configure request with improved settings
        $args = [
            'headers' => $headers,
            'timeout' => 45, // Increase timeout to 45 seconds
            'httpversion' => '1.1',
            'sslverify' => false, // Disable SSL verification for local environments
            'blocking' => true,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; Truebeep Updater',
            'compress' => true
        ];
        
        // Add proxy settings if defined in WordPress
        if (defined('WP_PROXY_HOST') && defined('WP_PROXY_PORT')) {
            $args['proxy'] = WP_PROXY_HOST . ':' . WP_PROXY_PORT;
            if (defined('WP_PROXY_USERNAME') && defined('WP_PROXY_PASSWORD')) {
                $args['proxy'] = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@' . $args['proxy'];
            }
        }
        
        // Try the request
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('Truebeep GitHub API Error: ' . $response->get_error_message());
            
            // Try alternative URL without API
            return $this->try_direct_github_access();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (empty($data) || !isset($data->tag_name)) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Try direct GitHub access without API
     * 
     * @return mixed
     */
    private function try_direct_github_access() {
        // Try to fetch the releases page directly
        $releases_url = sprintf(
            'https://github.com/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );
        
        $args = [
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => false,
            'user-agent' => 'WordPress/' . get_bloginfo('version')
        ];
        
        $response = wp_remote_get($releases_url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        // Extract version from the redirect URL or page content
        $final_url = wp_remote_retrieve_header($response, 'location');
        if (!$final_url) {
            $final_url = $releases_url;
        }
        
        // Extract tag from URL
        if (preg_match('/\/tag\/([^\/]+)/', $final_url, $matches)) {
            $tag = $matches[1];
            
            // Create a release object
            $release = new \stdClass();
            $release->tag_name = $tag;
            $release->name = $tag;
            $release->body = 'Release ' . $tag;
            $release->zipball_url = sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                $this->username,
                $this->repository,
                $tag
            );
            $release->published_at = date('Y-m-d\TH:i:s\Z');
            
            return $release;
        }
        
        return false;
    }
    
    /**
     * Get latest tag as fallback
     * 
     * @return mixed GitHub API response or false
     */
    private function get_latest_tag() {
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/tags',
            $this->username,
            $this->repository
        );
        
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
        ];
        
        if (!empty($this->access_token)) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }
        
        $args = [
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'WordPress/' . get_bloginfo('version')
        ];
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $tags = json_decode($body);
        
        if (empty($tags) || !is_array($tags)) {
            return false;
        }
        
        // Get the first (latest) tag
        $latest_tag = $tags[0];
        
        // Create a release-like object
        $release = new \stdClass();
        $release->tag_name = $latest_tag->name;
        $release->name = $latest_tag->name;
        $release->body = 'Release ' . $latest_tag->name;
        $release->zipball_url = sprintf(
            'https://github.com/%s/%s/archive/refs/tags/%s.zip',
            $this->username,
            $this->repository,
            $latest_tag->name
        );
        $release->published_at = date('Y-m-d\TH:i:s\Z');
        
        return $release;
    }
    
    /**
     * Create fallback response when API is unreachable
     * 
     * @return object|false
     */
    private function create_fallback_response() {
        // Check if we have a known version to compare against
        // This allows manual version checking via direct download
        $fallback_version = get_option('truebeep_latest_version', false);
        
        if ($fallback_version) {
            $release = new \stdClass();
            $release->tag_name = $fallback_version;
            $release->name = $fallback_version;
            $release->body = 'Release ' . $fallback_version;
            $release->zipball_url = sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                $this->username,
                $this->repository,
                $fallback_version
            );
            $release->published_at = date('Y-m-d\TH:i:s\Z');
            
            return $release;
        }
        
        return false;
    }
    
    /**
     * Generate plugin info object for WordPress
     * 
     * @return object Plugin update information
     */
    private function generate_plugin_info() {
        $plugin_info = new \stdClass();
        
        $plugin_info->id = $this->plugin_slug;
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->plugin = plugin_basename($this->plugin_file);
        $plugin_info->new_version = ltrim($this->github_response->tag_name, 'v');
        $plugin_info->url = 'https://github.com/' . $this->username . '/' . $this->repository;
        $plugin_info->package = $this->get_download_url();
        $plugin_info->icons = $this->get_plugin_icons();
        $plugin_info->banners = [];
        $plugin_info->banners_rtl = [];
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->requires_php = '7.4';
        $plugin_info->compatibility = new \stdClass();
        
        return $plugin_info;
    }
    
    /**
     * Get download URL for the plugin ZIP
     * 
     * @return string Download URL
     */
    private function get_download_url() {
        // Check if there's a specific ZIP asset in the release
        if (!empty($this->github_response->assets) && is_array($this->github_response->assets)) {
            foreach ($this->github_response->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    return $asset->browser_download_url;
                }
            }
        }
        
        // Use direct GitHub archive URL (bypasses API)
        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/%s.zip',
            $this->username,
            $this->repository,
            $this->github_response->tag_name
        );
    }
    
    /**
     * Get plugin icons
     * 
     * @return array Icon URLs
     */
    private function get_plugin_icons() {
        return [
            '1x' => TRUEBEEP_URL . '/assets/images/icon-128x128.png',
            '2x' => TRUEBEEP_URL . '/assets/images/icon-256x256.png'
        ];
    }
    
    /**
     * Plugin information for modal
     * 
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $this->get_github_release_info(true);
        
        if (!$this->github_response) {
            return $result;
        }
        
        $plugin_info = new \stdClass();
        
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = ltrim($this->github_response->tag_name, 'v');
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->author_profile = $this->plugin_data['AuthorURI'];
        $plugin_info->last_updated = $this->github_response->published_at;
        $plugin_info->homepage = $this->plugin_data['PluginURI'];
        $plugin_info->short_description = $this->plugin_data['Description'];
        $plugin_info->sections = [
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->parse_changelog()
        ];
        $plugin_info->download_link = $this->get_download_url();
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->requires = '5.0';
        $plugin_info->requires_php = '7.4';
        $plugin_info->icons = $this->get_plugin_icons();
        $plugin_info->banners = [];
        
        return $plugin_info;
    }
    
    /**
     * Parse changelog from GitHub release
     * 
     * @return string Formatted changelog
     */
    private function parse_changelog() {
        if (empty($this->github_response->body)) {
            return 'Version ' . $this->github_response->tag_name;
        }
        
        // Convert markdown to HTML
        $changelog = $this->github_response->body;
        
        // Basic markdown to HTML conversion
        $changelog = str_replace('###', '<h3>', $changelog);
        $changelog = str_replace('##', '<h2>', $changelog);
        $changelog = str_replace('#', '<h1>', $changelog);
        $changelog = nl2br($changelog);
        
        return $changelog;
    }
    
    /**
     * Maybe bypass GitHub API for package downloads
     * 
     * @param array $options
     * @return array
     */
    public function maybe_bypass_github_api($options) {
        if (isset($options['package']) && strpos($options['package'], 'github.com') !== false) {
            // Ensure we're using direct download URL
            $options['timeout'] = 300; // 5 minutes for download
        }
        return $options;
    }
    
    /**
     * After plugin install/update
     * 
     * @param mixed $response
     * @param array $hook_extra
     * @param mixed $result
     * @return mixed
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== plugin_basename($this->plugin_file)) {
            return $response;
        }
        
        // Get the installed plugin folder
        $installed_dir = $result['destination'];
        $plugin_dir = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        
        // GitHub delivers the plugin in a folder named repo-name-tag
        // We need to handle the directory structure properly
        if ($installed_dir !== $plugin_dir) {
            // Check if the GitHub archive structure needs adjustment
            $temp_dir = $installed_dir;
            
            // Find the actual plugin files (they might be in a subdirectory)
            $possible_dirs = glob($temp_dir . '/*', GLOB_ONLYDIR);
            if (count($possible_dirs) === 1 && $wp_filesystem->exists($possible_dirs[0] . '/truebeep.php')) {
                // Plugin files are in a subdirectory
                $temp_dir = $possible_dirs[0];
            }
            
            // Remove existing plugin directory if it exists
            if ($wp_filesystem->exists($plugin_dir)) {
                $wp_filesystem->delete($plugin_dir, true);
            }
            
            // Move the new files to the correct location
            $wp_filesystem->move($temp_dir, $plugin_dir);
            $result['destination'] = $plugin_dir;
            
            // Clean up any remaining temporary directories
            if ($temp_dir !== $installed_dir && $wp_filesystem->exists($installed_dir)) {
                $wp_filesystem->delete($installed_dir, true);
            }
        }
        
        return $result;
    }
    
    /**
     * Add manual version check option
     * 
     * @param string $version
     */
    public static function set_fallback_version($version) {
        update_option('truebeep_latest_version', $version);
    }
}