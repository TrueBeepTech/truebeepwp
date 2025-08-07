<?php

namespace Truebeep\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceSettings
{
    public function __construct()
    {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_truebeep', [$this, 'settings_tab']);
        add_action('woocommerce_update_options_truebeep', [$this, 'update_settings']);
        add_action('woocommerce_update_options_truebeep_loyalty', [$this, 'update_settings']);
        add_action('woocommerce_sections_truebeep', [$this, 'output_sections']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_truebeep_save_loyalty', [$this, 'ajax_save_loyalty']);
    }

    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs['truebeep'] = __('Truebeep', 'truebeep');
        return $settings_tabs;
    }

    public function get_sections()
    {
        $sections = [
            '' => __('Credentials', 'truebeep'),
            'loyalty' => __('Loyalty', 'truebeep'),
            'wallet' => __('Wallet', 'truebeep'),
        ];
        return apply_filters('woocommerce_get_sections_truebeep', $sections);
    }

    public function output_sections()
    {
        global $current_section;
        $sections = $this->get_sections();

        if (empty($sections) || 1 === count($sections)) {
            return;
        }

        echo '<ul class="subsubsub">';
        $array_keys = array_keys($sections);
        foreach ($sections as $id => $label) {
            echo '<li><a href="' . admin_url('admin.php?page=wc-settings&tab=truebeep&section=' . sanitize_title($id)) . '" class="' . ($current_section == $id ? 'current' : '') . '">' . $label . '</a> ' . (end($array_keys) == $id ? '' : '|') . ' </li>';
        }
        echo '</ul><br class="clear" />';
    }

    public function get_settings($current_section = '')
    {
        if ('loyalty' == $current_section) {
            $settings = $this->get_loyalty_settings();
        } elseif ('wallet' == $current_section) {
            $settings = $this->get_wallet_settings();
        } else {
            $settings = $this->get_credentials_settings();
        }

        return apply_filters('woocommerce_get_settings_truebeep', $settings, $current_section);
    }

    private function get_credentials_settings()
    {
        $settings = [
            [
                'title' => __('Truebeep Credentials', 'truebeep'),
                'type' => 'title',
                'desc' => __('Configure your Truebeep API credentials', 'truebeep'),
                'id' => 'truebeep_credentials_section'
            ],
            [
                'title' => __('API URL', 'truebeep'),
                'desc' => __('Enter your Truebeep API URL', 'truebeep'),
                'id' => 'truebeep_api_url',
                'type' => 'text',
                'css' => 'min-width:400px;',
                'desc_tip' => true,
            ],
            [
                'title' => __('API Key', 'truebeep'),
                'desc' => __('Enter your Truebeep API Key', 'truebeep'),
                'id' => 'truebeep_api_key',
                'type' => 'password',
                'css' => 'min-width:400px;',
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id' => 'truebeep_credentials_section'
            ],
        ];

        return $settings;
    }

    private function get_loyalty_settings()
    {
        $settings = [
            [
                'title' => __('Loyalty Configuration', 'truebeep'),
                'type' => 'title',
                'desc' => __('Configure loyalty points and tier settings', 'truebeep'),
                'id' => 'truebeep_loyalty_section'
            ],
            [
                'title' => __('Ways to Redeem', 'truebeep'),
                'desc' => __('Select how customers can redeem their points', 'truebeep'),
                'id' => 'truebeep_redeem_method',
                'type' => 'radio',
                'default' => 'dynamic_coupon',
                'options' => [
                    'dynamic_coupon' => __('Dynamic Coupon', 'truebeep'),
                    'coupon' => __('Coupon', 'truebeep')
                ],
                'desc_tip' => true,
            ],
            [
                'title' => __('Earning Value', 'truebeep'),
                'desc' => __('Order Amount to Points Conversion rate (for customers with no tier)', 'truebeep'),
                'id' => 'truebeep_earning_value',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'default' => '1',
                'css' => 'width: 100px;',
                'desc_tip' => true,
            ],
            [
                'title' => __('Redeeming Value', 'truebeep'),
                'desc' => __('Points to Amount Conversion (for customers with no tier)', 'truebeep'),
                'id' => 'truebeep_redeeming_value',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'default' => '1',
                'css' => 'width: 100px;',
                'desc_tip' => true,
            ],
            [
                'title' => __('Earn Points on Redeemed Orders', 'truebeep'),
                'desc' => __('Allow customers to earn points on orders where points were redeemed', 'truebeep'),
                'id' => 'truebeep_earn_on_redeemed',
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => false,
            ],
            [
                'type' => 'truebeep_tiers',
                'id' => 'truebeep_tiers_settings'
            ],
            [
                'type' => 'sectionend',
                'id' => 'truebeep_loyalty_section'
            ],
        ];

        return $settings;
    }

    private function get_wallet_settings()
    {
        $settings = [
            [
                'title' => __('Wallet Configuration', 'truebeep'),
                'type' => 'title',
                'desc' => __('Configure your wallet settings', 'truebeep'),
                'id' => 'truebeep_wallet_section'
            ],
            [
                'title' => __('Wallet ID', 'truebeep'),
                'desc' => __('Enter your wallet ID', 'truebeep'),
                'id' => 'truebeep_wallet_id',
                'type' => 'text',
                'css' => 'min-width:400px;',
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id' => 'truebeep_wallet_section'
            ],
        ];

        return $settings;
    }

    public function settings_tab()
    {
        global $current_section;
        
        if ($current_section == 'loyalty') {
            $this->render_loyalty_section();
        } else {
            woocommerce_admin_fields($this->get_settings($current_section));
        }
    }

    private function render_loyalty_section()
    {
        $tiers = get_option('truebeep_tiers', $this->get_default_tiers());
        woocommerce_admin_fields($this->get_loyalty_settings());
        
        // Include the loyalty settings view
        include TRUEBEEP_PATH . '/includes/Admin/views/loyalty-settings.php';
    }

    private function get_default_tiers()
    {
        return [
            ['name' => 'Free', 'order_to_points' => 1.0, 'points_to_amount' => 1.0, 'threshold' => 0],
            ['name' => 'Bronze', 'order_to_points' => 1.5, 'points_to_amount' => 1.5, 'threshold' => 100],
            ['name' => 'Silver', 'order_to_points' => 2.0, 'points_to_amount' => 2.0, 'threshold' => 500],
            ['name' => 'Gold', 'order_to_points' => 3.0, 'points_to_amount' => 3.0, 'threshold' => 1000],
        ];
    }

    public function update_settings()
    {
        global $current_section;
        
        woocommerce_update_options($this->get_settings($current_section));
    }

    public function ajax_save_loyalty()
    {
        check_ajax_referer('truebeep_save_loyalty', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'truebeep'));
        }

        // Save loyalty fields
        if (isset($_POST['redeem_method'])) {
            update_option('truebeep_redeem_method', sanitize_text_field($_POST['redeem_method']));
        }
        if (isset($_POST['earning_value'])) {
            update_option('truebeep_earning_value', floatval($_POST['earning_value']));
        }
        if (isset($_POST['redeeming_value'])) {
            update_option('truebeep_redeeming_value', floatval($_POST['redeeming_value']));
        }
        if (isset($_POST['earn_on_redeemed'])) {
            update_option('truebeep_earn_on_redeemed', $_POST['earn_on_redeemed'] === 'true' ? 'yes' : 'no');
        }

        // Save tiers
        $tiers = isset($_POST['tiers']) ? $_POST['tiers'] : [];
        $sanitized_tiers = [];

        foreach ($tiers as $tier) {
            $sanitized_tiers[] = [
                'name' => sanitize_text_field($tier['name']),
                'order_to_points' => floatval($tier['order_to_points']),
                'points_to_amount' => floatval($tier['points_to_amount']),
                'threshold' => intval($tier['threshold'])
            ];
        }

        update_option('truebeep_tiers', $sanitized_tiers);

        wp_send_json_success(['message' => __('Loyalty settings saved successfully!', 'truebeep')]);
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }

        if (isset($_GET['tab']) && $_GET['tab'] === 'truebeep') {
            wp_enqueue_script(
                'truebeep-woocommerce-settings',
                TRUEBEEP_URL . '/assets/js/admin/woocommerce-settings.js',
                ['jquery'],
                TRUEBEEP_VERSION,
                true
            );

            wp_localize_script('truebeep-woocommerce-settings', 'truebeep_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('truebeep_save_loyalty'),
                'strings' => [
                    'tier_name' => __('Tier Name', 'truebeep'),
                    'remove' => __('Remove', 'truebeep'),
                    'saving' => __('Saving...', 'truebeep'),
                    'save_tiers' => __('Save Changes', 'truebeep'),
                ]
            ]);

            wp_enqueue_style(
                'truebeep-woocommerce-settings',
                TRUEBEEP_URL . '/assets/css/admin/woocommerce-settings.css',
                [],
                TRUEBEEP_VERSION
            );
        }
    }
}