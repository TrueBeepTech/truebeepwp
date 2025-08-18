<?php

namespace Truebeep;

/**
 * GitHub Plugin Updater
 * 
 * Handles automatic updates from GitHub repository
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
        $this->plugin_slug = plugin_basename(dirname($plugin_file));
        
        $this->initialize();
    }
    
    /**
     * Initialize the updater
     */
    private function initialize() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
        add_action('admin_init', [$this, 'set_plugin_data']);
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
     * Check for updates
     * 
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        
        _log('check_for_update');   
        _log($transient);
        
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get GitHub release info
        $this->get_github_release_info();
        
        if (!$this->github_response) {
            return $transient;
        }
        
        // Check if update is available
        $do_update = version_compare(
            $this->github_response->tag_name, 
            $this->plugin_data['Version'], 
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
     * Get GitHub release information
     * 
     * @return mixed GitHub API response or false
     */
    private function get_github_release_info() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }
        
        // Build API URL
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );
        
        // Build headers
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        ];
        
        // Add authorization header if access token is set
        if (!empty($this->access_token)) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }
        
        // Get API response
        $response = wp_remote_get($api_url, [
            'headers' => $headers
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $this->github_response = json_decode($body);
        
        // If rate limited or no release found, try tags endpoint
        if (empty($this->github_response->tag_name)) {
            $this->github_response = $this->get_latest_tag();
        }
        
        return $this->github_response;
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
        
        // Build headers
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        ];
        
        // Add authorization header if access token is set
        if (!empty($this->access_token)) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }
        
        $response = wp_remote_get($api_url, [
            'headers' => $headers
        ]);
        
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
        $release->zipball_url = $latest_tag->zipball_url;
        $release->published_at = date('Y-m-d\TH:i:s\Z');
        
        return $release;
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
        $plugin_info->new_version = $this->github_response->tag_name;
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
        if (!empty($this->github_response->assets)) {
            // Look for a ZIP asset
            foreach ($this->github_response->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    return $asset->browser_download_url;
                }
            }
        }
        
        // Fallback to zipball URL
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
        
        $this->get_github_release_info();
        
        if (!$this->github_response) {
            return $result;
        }
        
        $plugin_info = new \stdClass();
        
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $this->github_response->tag_name;
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
        
        // GitHub delivers the plugin in a folder named with the branch/tag
        // We need to rename it to match our plugin folder name
        $plugin_folder = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;
        
        // Reactivate plugin if it was active
        if (is_plugin_active(plugin_basename($this->plugin_file))) {
            activate_plugin(plugin_basename($this->plugin_file));
        }
        
        return $result;
    }
}