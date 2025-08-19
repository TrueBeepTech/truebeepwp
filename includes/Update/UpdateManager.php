<?php

namespace Truebeep\Update;

/**
 * Update Manager
 * 
 * Manages plugin updates from GitHub repositories. 
 * This class provides a simple interface to initialize GitHub-based updates
 * and can be easily moved between different plugins.
 */
class UpdateManager {
    
    /**
     * Plugin file path
     * @var string
     */
    private $plugin_file;
    
    /**
     * Plugin configuration
     * @var array
     */
    private $config;
    
    /**
     * GitHubUpdater instance
     * @var GitHubUpdater
     */
    private $updater;
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Main plugin file path
     * @param array $config Configuration array
     */
    public function __construct($plugin_file, $config = []) {
        $this->plugin_file = $plugin_file;
        $this->config = $this->parse_config($config);
        
        // Only initialize if we have valid configuration
        if ($this->is_config_valid()) {
            $this->initialize_updater();
        }
    }
    
    /**
     * Parse and validate configuration
     * 
     * @param array $config Raw configuration
     * @return array Parsed configuration
     */
    private function parse_config($config) {
        // Set defaults
        $defaults = [
            'repository_url' => '',
            'username' => '',
            'repository' => '',
            'access_token' => '',
            'text_domain' => 'default',
            'cache_prefix' => '',
            'config_file' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        // If config file is provided, load it
        if (!empty($config['config_file']) && file_exists($config['config_file'])) {
            $file_config = include $config['config_file'];
            if (is_array($file_config)) {
                $config = array_merge($config, $file_config);
            }
        }
        
        // Parse repository URL if provided
        if (!empty($config['repository_url'])) {
            $parsed = $this->parse_github_url($config['repository_url']);
            if ($parsed) {
                $config['username'] = $parsed['username'];
                $config['repository'] = $parsed['repository'];
            }
        }
        
        // Set cache prefix if not provided
        if (empty($config['cache_prefix'])) {
            $config['cache_prefix'] = sanitize_title(basename(dirname($this->plugin_file)));
        }
        
        return $config;
    }
    
    /**
     * Parse GitHub URL to extract username and repository
     *
     * @param string $url GitHub repository URL
     * @return array|false Array with username and repository or false on failure
     */
    private function parse_github_url($url) {
        // Remove trailing .git if present
        $url = rtrim($url, '/');
        $url = preg_replace('/\.git$/', '', $url);
        
        // Parse URL for github.com format
        if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/]+)/', $url, $matches)) {
            return [
                'username' => $matches[1],
                'repository' => $matches[2]
            ];
        }
        
        return false;
    }
    
    /**
     * Check if configuration is valid for updates
     * 
     * @return bool
     */
    private function is_config_valid() {
        return !empty($this->config['username']) && !empty($this->config['repository']);
    }
    
    /**
     * Initialize the GitHub updater
     */
    private function initialize_updater() {
        $args = [
            'text_domain' => $this->config['text_domain'],
            'cache_prefix' => $this->config['cache_prefix']
        ];
        
        $this->updater = new GitHubUpdater(
            $this->plugin_file,
            $this->config['username'],
            $this->config['repository'],
            $args
        );
        
        // Set access token if provided
        if (!empty($this->config['access_token'])) {
            $this->updater->set_access_token($this->config['access_token']);
        }
    }
    
    /**
     * Get the updater instance
     * 
     * @return GitHubUpdater|null
     */
    public function get_updater() {
        return $this->updater;
    }
    
    /**
     * Get configuration
     * 
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Create update manager from config file
     * 
     * @param string $plugin_file Main plugin file
     * @param string $config_file Path to config file
     * @param array $additional_config Additional config to merge
     * @return UpdateManager
     */
    public static function from_config_file($plugin_file, $config_file, $additional_config = []) {
        $config = $additional_config;
        $config['config_file'] = $config_file;
        
        return new self($plugin_file, $config);
    }
    
    /**
     * Create update manager from repository URL
     * 
     * @param string $plugin_file Main plugin file
     * @param string $repository_url GitHub repository URL
     * @param array $additional_config Additional config to merge
     * @return UpdateManager
     */
    public static function from_repository_url($plugin_file, $repository_url, $additional_config = []) {
        $config = $additional_config;
        $config['repository_url'] = $repository_url;
        
        return new self($plugin_file, $config);
    }
    
    /**
     * Create update manager with manual configuration
     * 
     * @param string $plugin_file Main plugin file
     * @param string $username GitHub username
     * @param string $repository GitHub repository name
     * @param array $additional_config Additional config to merge
     * @return UpdateManager
     */
    public static function create($plugin_file, $username, $repository, $additional_config = []) {
        $config = $additional_config;
        $config['username'] = $username;
        $config['repository'] = $repository;
        
        return new self($plugin_file, $config);
    }
}