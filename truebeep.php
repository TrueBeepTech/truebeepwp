<?php

/**
 * Plugin Name:       Truebeep: Smart Wallet Loyalty
 * Plugin URI:        https://truebeep.com
 * Description:       Reward customers with points they can track and redeem via Wallet. Retain them with smart tools.
 * Version:           1.0.1
 * Author:            Truebeep
 * Author URI:        https://truebeep.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       truebeep
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Main plugin class
 */
final class Truebeep
{
    /**
     * Plugin version
     * 
     * @var string
     */
    const version = '1.0.0';

    /**
     * contractor
     */
    private function __construct()
    {
        $this->define_constants();

        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'init_plugin']);
        
        // Initialize GitHub updater
        $this->init_updater();
    }

    /**
     * Initialize singleton instance
     *
     * @return \Truebeep
     */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('TRUEBEEP_VERSION', self::version);
        define('TRUEBEEP_FILE', __FILE__);
        define('TRUEBEEP_PATH', __DIR__);
        define('TRUEBEEP_URL', plugins_url('', TRUEBEEP_FILE));
        define('TRUEBEEP_ASSETS', TRUEBEEP_URL . '/assets');
        define('TRUEBEEP_DIR_PATH', plugin_dir_path(__FILE__));
        define('TRUEBEEP_ELEMENTOR', TRUEBEEP_DIR_PATH . 'includes/Elementor/');
    }
    
    /**
     * Load plugin text domain
     *
     * @return void
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'truebeep',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Plugin information
     *
     * @return void
     */
    public function activate()
    {
        // Check if WooCommerce is active when activating the plugin
        if (!$this->is_woocommerce_active()) {
            // Load text domain for activation error
            load_plugin_textdomain('truebeep', false, dirname(plugin_basename(__FILE__)) . '/languages');
            
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Truebeep requires WooCommerce to be installed and activated.', 'truebeep'));
        }

        $installer = new Truebeep\Installer();
        $installer->run();
    }

    /**
     * Load plugin files
     *
     * @return void
     */
    public function init_plugin()
    {
        // Check if WooCommerce is active before initializing features
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        new Truebeep\Assets();
        new Truebeep\Ajax();
        new Truebeep\API();
        new Truebeep\LoadElementor();
        new Truebeep\Customizer();
        new Truebeep\Admin\WooCommerceSettings();
        
        // Initialize Loyalty components
        Truebeep\Loyalty\PointsManager::get_instance();
        new Truebeep\Checkout\PointsRedemption();
        
        if (is_admin()) {
            new Truebeep\Admin();
        } else {
            new Truebeep\Frontend();
        }
    }
    
    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active()
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || 
               (is_multisite() && array_key_exists('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', [])));
    }
    
    /**
     * Show admin notice when WooCommerce is missing
     *
     * @return void
     */
    public function woocommerce_missing_notice()
    {
        ?>
        <div class="error">
            <p><?php _e('Truebeep requires WooCommerce to be installed and activated.', 'truebeep'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Initialize GitHub updater
     *
     * @return void
     */
    private function init_updater()
    {
        // Load GitHub configuration
        $config_file = TRUEBEEP_PATH . '/github-config.php';
        if (!file_exists($config_file)) {
            return; // Skip updater if config file doesn't exist
        }
        
        $config = include $config_file;
        
        // Parse repository URL if provided
        if (!empty($config['repository_url'])) {
            $parsed = $this->parse_github_url($config['repository_url']);
            if ($parsed) {
                $config['username'] = $parsed['username'];
                $config['repository'] = $parsed['repository'];
            }
        }
        
        // Validate configuration
        if (empty($config['username']) || empty($config['repository'])) {
            return; // Skip if not properly configured
        }
        
        // Initialize the updater
        $updater = new \Truebeep\GitHubUpdater(
            TRUEBEEP_FILE,
            $config['username'],
            $config['repository']
        );
        
        // Set access token if provided (for private repos)
        if (!empty($config['access_token'])) {
            $updater->set_access_token($config['access_token']);
        }
    }
    
    /**
     * Parse GitHub URL to extract username and repository
     *
     * @param string $url GitHub repository URL
     * @return array|false Array with username and repository or false on failure
     */
    private function parse_github_url($url)
    {
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
}

/**
 * Initialize main plugin
 *
 * @return \Truebeep
 */
function truebeep()
{
    return Truebeep::init();
}

truebeep();
