<?php

/**
 * Plugin Name:       Truebeep: Smart Wallet Loyalty
 * Plugin URI:        https://truebeep.com
 * Description:       Reward customers with points they can track and redeem via Wallet. Retain them with smart tools.
 * Version:           1.0.0
 * Author:            Truebeep
 * Author URI:        https://truebeep.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       truebeep
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
?>
        <div class="error">
            <p><?php _e('Truebeep requires WooCommerce to be installed and activated.', 'truebeep'); ?></p>
        </div>
<?php
    });
    return;
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
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
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
     * Plugin information
     *
     * @return void
     */
    public function activate()
    {
        // Check if WooCommerce is active when activating the plugin
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
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
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=truebeep') . '">' . __('Settings', 'truebeep') . '</a>';
        $docs_link = '<a href="https://docs.truebeep.com" target="_blank">' . __('Documentation', 'truebeep') . '</a>';
        
        array_unshift($links, $settings_link, $docs_link);
        
        return $links;
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
