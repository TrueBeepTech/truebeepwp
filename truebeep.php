<?php

/**
 * Plugin Name:       Truebeep: Smart Wallet Loyalty
 * Plugin URI:        https://truebeep.com
 * Description:       Reward customers with points they can track and redeem via Wallet. Retain them with smart tools.
 * Version:           2.0.5
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
    const version = '2.0.5';

    /**
     * contractor
     */
    private function __construct()
    {
        $this->define_constants();

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_early_hooks'], 1); // Register hooks early
        add_action('plugins_loaded', [$this, 'init_plugin']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);

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
     * Register hooks that need to be available early
     * 
     * This method registers Action Scheduler hooks that need to be available
     * when Action Scheduler tries to execute scheduled actions.
     *
     * @return void
     */
    public function register_early_hooks()
    {
        // Register Action Scheduler hooks for customer sync
        if (class_exists('Truebeep\\Legacy\\CustomerSyncProcessor')) {
            Truebeep\Legacy\CustomerSyncProcessor::register_action_hooks();
        }
    }

    /**
     * Plugin activation
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
        
        // Send connection status if API credentials exist
        $this->update_connection_status_on_activation();
    }
    
    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate()
    {
        // Send disconnected status to TrueBeep
        $this->update_connection_status_on_deactivation();
        
        // Clear scheduled update checks
        $hook_name = 'truebeep_daily_update_check';
        $timestamp = wp_next_scheduled($hook_name);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook_name);
        }
        wp_clear_scheduled_hook($hook_name);
        
        // Clear update caches
        delete_transient('truebeep_github_release_' . md5('TruebeepTech' . 'TruebeepWp'));
        delete_site_transient('update_plugins');
    }
    
    /**
     * Update connection status when plugin is activated
     *
     * @return void
     */
    private function update_connection_status_on_activation()
    {
        $api_url = get_option('truebeep_api_url', '');
        $api_key = get_option('truebeep_api_key', '');
        
        // Only send connected status if both URL and key are configured
        if (!empty($api_url) && !empty($api_key)) {
            $connection_helper = new Truebeep\Admin\ConnectionHelper();
            $response = $connection_helper->send_connection_status('connected');
            
            if ($response['success']) {
                update_option('truebeep_connection_status', 'connected');
            }
        }
    }
    
    /**
     * Update connection status when plugin is deactivated
     *
     * @return void
     */
    private function update_connection_status_on_deactivation()
    {
        $api_url = get_option('truebeep_api_url', '');
        $api_key = get_option('truebeep_api_key', '');
        
        // Send disconnected status if credentials exist
        if (!empty($api_url) && !empty($api_key)) {
            $connection_helper = new Truebeep\Admin\ConnectionHelper();
            $connection_helper->send_connection_status('disconnected');
        }
        
        // Always update local status to disconnected
        update_option('truebeep_connection_status', 'disconnected');
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
     * Add plugin action links
     *
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_plugin_action_links($links)
    {
        $settings_url = $this->get_settings_url();
        
        // Allow customization of documentation and support URLs
        $docs_url = apply_filters('truebeep_docs_url', 'https://docs.truebeep.com');
        $support_url = apply_filters('truebeep_support_url', 'https://truebeep.com/support');
        
        $action_links = [
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($settings_url),
                __('Settings', 'truebeep')
            ),
            'docs' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($docs_url),
                __('Docs', 'truebeep')
            ),
            'support' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($support_url),
                __('Support', 'truebeep')
            )
        ];

        // Allow developers to add custom action links
        $action_links = apply_filters('truebeep_plugin_action_links', $action_links);

        return array_merge($action_links, $links);
    }
    
    /**
     * Get settings URL based on WooCommerce availability
     *
     * @return string Settings URL
     */
    private function get_settings_url()
    {
        if ($this->is_woocommerce_active()) {
            return admin_url('admin.php?page=wc-settings&tab=truebeep');
        }
        
        // Fallback to network diagnostics if WooCommerce is not active
        return admin_url('tools.php?page=truebeep-diagnostics');
    }

    /**
     * Initialize GitHub updater using the modular update system
     *
     * @return void
     */
    private function init_updater()
    {
        // Try new modular config first, fallback to legacy config
        $config_file = TRUEBEEP_PATH . '/Update/update-config.php';
        if (!file_exists($config_file)) {
            $config_file = TRUEBEEP_PATH . '/github-config.php'; // Legacy fallback
        }
        
        if (!file_exists($config_file)) {
            return; // Skip updater if no config file exists
        }

        // Initialize the modular update manager
        $update_manager = \Truebeep\Update\UpdateManager::from_config_file(
            TRUEBEEP_FILE,
            $config_file,
            [
                'text_domain' => 'truebeep',
                'cache_prefix' => 'truebeep'
            ]
        );
        
        // Store reference for potential future use
        $this->update_manager = $update_manager;
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
