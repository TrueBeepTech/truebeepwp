<?php

namespace Truebeep\Admin;

use Truebeep\Traits\ApiHelper;
use Truebeep\Config\Constants;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceSettings
{
    use ApiHelper;
    
    public function __construct()
    {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_truebeep_smwl', [$this, 'settings_tab']);
        add_action('woocommerce_update_options_truebeep_smwl', [$this, 'update_settings']);
        add_action('woocommerce_update_options_truebeep_smwl_loyalty', [$this, 'update_settings']);
        add_action('woocommerce_sections_truebeep_smwl', [$this, 'output_sections']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_truebeep_smwl_save_loyalty', [$this, 'ajax_save_loyalty']);
        add_action('wp_ajax_truebeep_smwl_save_coupons', [$this, 'ajax_save_coupons']);
        add_action('wp_ajax_truebeep_smwl_update_connection', [$this, 'ajax_update_connection']);
        add_action('woocommerce_admin_field_truebeep_smwl_coupons', [$this, 'output_loyalty_field']);
        add_action('woocommerce_admin_field_truebeep_smwl_tiers', [$this, 'output_loyalty_field']);
    }

    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs['truebeep_smwl'] = __('Truebeep', 'truebeep-smart-wallet-loyalty');
        return $settings_tabs;
    }

    public function get_sections()
    {
        $sections = [
            '' => __('Credentials', 'truebeep-smart-wallet-loyalty'),
            'loyalty' => __('Loyalty', 'truebeep-smart-wallet-loyalty'),
            'wallet' => __('Wallet', 'truebeep-smart-wallet-loyalty'),
        ];
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook pattern
        return apply_filters('woocommerce_get_sections_truebeep_smwl', $sections);
    }

    public function output_sections()
    {
        global $current_section;
        $sections = $this->get_sections();

        if (empty($sections) || 1 === count($sections)) {
            return;
        }

        $array_keys = array_keys($sections);
        $last_key = end($array_keys);
        
        printf('<ul class="subsubsub">%s', "\n");
        foreach ($sections as $id => $label) {
            $section_url = admin_url('admin.php?page=wc-settings&tab=truebeep_smwl&section=' . sanitize_title($id));
            $section_class = ($current_section == $id) ? 'current' : '';
            $separator = ($last_key == $id) ? '' : '|';
            
            printf(
                '<li><a href="%s" class="%s">%s</a> %s </li>%s',
                esc_url($section_url),
                esc_attr($section_class),
                esc_html($label),
                esc_html($separator),
                "\n"
            );
        }
        printf('</ul><br class="clear" />%s', "\n");
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

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook pattern
        return apply_filters('woocommerce_get_settings_truebeep_smwl', $settings, $current_section);
    }

    private function get_credentials_settings()
    {
        // Get connection status
        $connection_status = get_option('truebeep_connection_status', 'disconnected');
        $status_text = ($connection_status === 'connected') ? __('Connected', 'truebeep-smart-wallet-loyalty') : __('Disconnected', 'truebeep-smart-wallet-loyalty');
        $status_color = ($connection_status === 'connected') ? 'green' : 'red';
        $button_text = ($connection_status === 'connected') ? __('Disconnect', 'truebeep-smart-wallet-loyalty') : __('Connect', 'truebeep-smart-wallet-loyalty');
        
        $settings = [
            [
                'title' => __('Truebeep Credentials', 'truebeep-smart-wallet-loyalty'),
                'type' => 'title',
                'desc' => __('Configure your Truebeep API credentials', 'truebeep-smart-wallet-loyalty') . '<br/>Status: <span id="truebeep-smwl-status" style="color: ' . $status_color . '; font-weight: bold;">' . $status_text . '</span>',
                'id' => 'truebeep_credentials_section'
            ],
            [
                'title' => __('API URL', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Truebeep API URL (read-only)', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_api_url',
                'type' => 'text',
                'default' => Constants::API_URL,
                'value' => Constants::API_URL,
                'css' => 'min-width:400px; background-color: #f0f0f0; cursor: not-allowed; opacity: 0.6;',
                'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
                'desc_tip' => true,
            ],
            [
                'title' => __('API Key', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Enter your Truebeep API Key', 'truebeep-smart-wallet-loyalty') . '<br/><br/><button type="button" class="button button-primary" id="truebeep-smwl-connection-btn" data-status="' . $connection_status . '">' . $button_text . '</button><span id="truebeep-smwl-connection-message" style="margin-left: 10px;"></span>',
                'id' => 'truebeep_api_key',
                'type' => 'password',
                'css' => 'min-width:400px;',
                'desc_tip' => false,
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
                'title' => __('Loyalty Configuration', 'truebeep-smart-wallet-loyalty'),
                'type' => 'title',
                'desc' => __('Configure loyalty points and tier settings', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_loyalty_section'
            ],
            [
                'title' => __('Redemption Method', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Choose how customers will convert their points into discounts.', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_redeem_method',
                'type' => 'radio',
                'default' => 'dynamic_coupon',
                'options' => [
                    'dynamic_coupon' => __('Flexible Redemption (Customers can redeem any amount of points for instant discounts.))', 'truebeep-smart-wallet-loyalty'),
                    'coupon' => __('Fixed Rewards (Customers choose from preset point-to-discount combinations.))', 'truebeep-smart-wallet-loyalty')
                ],
                'desc_tip' => true,
            ],
            [
                'type' => 'truebeep_smwl_coupons',
                'id' => 'truebeep_coupons_settings'
            ],
            [
                'title' => __('Point Earning Rate', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('How many points customers earn for each $1 spent.', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_earning_value',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'default' => '1',
                'css' => 'width: 100px;',
                'desc_tip' => true,
            ],
            [
                'title' => __('Point Value', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('The $ value of each point when customers redeem them.', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_redeeming_value',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'default' => '100',
                'css' => 'width: 100px;',
                'desc_tip' => true,
            ],
            [
                'title' => __('Points on Discounted Orders', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Allow customers to earn new points even when using points for discounts. Great for encouraging repeat purchases!', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_earn_on_redeemed',
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => false,
            ],
            [
                'title' => __('Award Points on Order Status', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Select when customers earn loyalty points. Processing = immediately after payment, Completed = after order fulfillment', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_award_points_status',
                'type' => 'select',
                'default' => 'completed',
                'options' => [
                    'processing' => __('Processing (Immediate)', 'truebeep-smart-wallet-loyalty'),
                    'completed' => __('Completed (After Fulfillment)', 'truebeep-smart-wallet-loyalty'),
                    'both' => __('Both Processing and Completed', 'truebeep-smart-wallet-loyalty'),
                ],
                'desc_tip' => true,
            ],
            [
                'type' => 'truebeep_smwl_tiers',
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
                'title' => __('Wallet Configuration', 'truebeep-smart-wallet-loyalty'),
                'type' => 'title',
                'desc' => __('Configure your wallet settings', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_wallet_section'
            ],
            [
                'title' => __('Show Loyalty Panel', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Display floating loyalty panel on frontend', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_show_loyalty_panel',
                'type' => 'checkbox',
                'default' => 'yes',
                'desc_tip' => false,
            ],

            [
                'title' => __('Wallet Base URL', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Base URL for wallet API (read-only)', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_wallet_base_url',
                'type' => 'hidden',
                'default' => Constants::WALLET_BASE_URL,
                'css' => 'min-width:400px; background-color: #f0f0f0;',
                'custom_attributes' => array('readonly' => 'readonly'),
                'desc_tip' => true,
            ],

            [
                'title' => __('Panel Position', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Choose where to display the loyalty panel', 'truebeep-smart-wallet-loyalty'),
                'id' => 'truebeep_panel_position',
                'type' => 'select',
                'options' => [
                    'bottom-right' => __('Bottom Right', 'truebeep-smart-wallet-loyalty'),
                    'bottom-left' => __('Bottom Left', 'truebeep-smart-wallet-loyalty'),
                    // 'top-right' => __('Top Right', 'truebeep-smart-wallet-loyalty'),
                    // 'top-left' => __('Top Left', 'truebeep-smart-wallet-loyalty'),
                ],
                'default' => 'bottom-right',
                'desc_tip' => true,
            ],
            // [
            //     'title' => __('Apple Wallet Template ID', 'truebeep-smart-wallet-loyalty'),
            //     'desc' => __('Enter your Apple Wallet pass template ID', 'truebeep-smart-wallet-loyalty'),
            //     'id' => 'truebeep_apple_wallet_template_id',
            //     'type' => 'text',
            //     'css' => 'min-width:400px;',
            //     'placeholder' => 'e.g. kdgydz9zs90mdgr6hcdce4d8',
            //     'desc_tip' => true,
            // ],
            // [
            //     'title' => __('Google Wallet Template ID', 'truebeep-smart-wallet-loyalty'),
            //     'desc' => __('Enter your Google Wallet pass template ID', 'truebeep-smart-wallet-loyalty'),
            //     'id' => 'truebeep_google_wallet_template_id',
            //     'type' => 'text',
            //     'css' => 'min-width:400px;',
            //     'placeholder' => 'e.g. kdgydz9zs90mdgr6hcdce4d8',
            //     'desc_tip' => true,
            // ],
            [
                'title' => __('Wallet ID', 'truebeep-smart-wallet-loyalty'),
                'desc' => __('Enter your wallet ID', 'truebeep-smart-wallet-loyalty'),
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

    public function output_loyalty_field($value)
    {
        // This is a custom field type handler for loyalty fields
        if ($value['type'] == 'truebeep_smwl_coupons') {
            $coupons = get_option('truebeep_coupons', $this->get_default_coupons());
            ?>
            <tr valign="top" id="coupon-settings-section" style="display:none;">
                <th scope="row" class="titledesc">
                    <label><?php esc_html_e('Coupons', 'truebeep-smart-wallet-loyalty'); ?></label>
                </th>
                <td class="forminp">
                    <div id="truebeep-coupons-container">
                        <table class="wp-list-table widefat fixed striped" id="truebeep-coupons-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Coupon Name', 'truebeep-smart-wallet-loyalty'); ?></th>
                                    <th><?php esc_html_e('Value', 'truebeep-smart-wallet-loyalty'); ?></th>
                                    <th><?php esc_html_e('Actions', 'truebeep-smart-wallet-loyalty'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="truebeep-coupons-list">
                                <?php foreach ($coupons as $index => $coupon) : ?>
                                    <tr class="coupon-row" data-index="<?php echo esc_attr($index); ?>">
                                        <td><?php echo esc_html($coupon['name']); ?></td>
                                        <td>$<?php echo esc_html($coupon['value']); ?></td>
                                        <td>
                                            <button type="button" class="button edit-coupon" data-coupon='<?php echo esc_attr(json_encode($coupon)); ?>' data-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Edit', 'truebeep-smart-wallet-loyalty'); ?></button>
                                            <button type="button" class="button remove-coupon" data-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Remove', 'truebeep-smart-wallet-loyalty'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="button" class="button button-secondary" id="add-coupon-button"><?php esc_html_e('Add New Coupon', 'truebeep-smart-wallet-loyalty'); ?></button>
                            <button type="button" class="button button-primary" id="save-coupons-button"><?php esc_html_e('Save Coupons', 'truebeep-smart-wallet-loyalty'); ?></button>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
        }
    }

    private function render_loyalty_section()
    {
        $tiers = get_option('truebeep_tiers', $this->get_default_tiers());
        woocommerce_admin_fields($this->get_loyalty_settings());
        
        // Include the tiers settings view
        include TRUEBEEP_PATH . '/includes/Admin/views/loyalty-settings.php';
    }

    private function get_default_tiers()
    {
        return [
            // points_to_amount: how many points equal $1 (e.g., 100 = 100 points per $1)
            ['name' => 'Free', 'order_to_points' => 1.0, 'points_to_amount' => 100, 'threshold' => 0],
            ['name' => 'Bronze', 'order_to_points' => 1.5, 'points_to_amount' => 90, 'threshold' => 100],
            ['name' => 'Silver', 'order_to_points' => 2.0, 'points_to_amount' => 80, 'threshold' => 500],
            ['name' => 'Gold', 'order_to_points' => 3.0, 'points_to_amount' => 50, 'threshold' => 1000],
        ];
    }

    private function get_default_coupons()
    {
        return [
            ['name' => '$1 Off Coupon', 'value' => 1],
            ['name' => '$2 Off Coupon', 'value' => 2],
            ['name' => '$3 Off Coupon', 'value' => 3],
        ];
    }

    public function update_settings()
    {
        global $current_section;
        
        // Get all settings
        $settings = $this->get_settings($current_section);
        
        // Filter out the read-only fields (API URL and Wallet Base URL)
        $filtered_settings = array_filter($settings, function($setting) {
            return !isset($setting['id']) || 
                   ($setting['id'] !== 'truebeep_api_url' && $setting['id'] !== 'truebeep_wallet_base_url');
        });
        
        woocommerce_update_options($filtered_settings);
    }

    public function ajax_save_loyalty()
    {
        check_ajax_referer('truebeep_smwl_save_loyalty', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'truebeep-smart-wallet-loyalty'));
        }

        // Save loyalty fields
        if (isset($_POST['redeem_method'])) {
            update_option('truebeep_redeem_method', sanitize_text_field(wp_unslash($_POST['redeem_method'])));
        }
        if (isset($_POST['earning_value'])) {
            update_option('truebeep_earning_value', floatval($_POST['earning_value']));
        }
        if (isset($_POST['redeeming_value'])) {
            update_option('truebeep_redeeming_value', floatval($_POST['redeeming_value']));
        }
        if (isset($_POST['earn_on_redeemed'])) {
            update_option('truebeep_earn_on_redeemed', sanitize_text_field(wp_unslash($_POST['earn_on_redeemed'])) === 'true' ? 'yes' : 'no');
        }

        // Save tiers
        $sanitized_tiers = [];
        if (isset($_POST['tiers']) && is_array($_POST['tiers'])) {
            $tiers_input = map_deep(wp_unslash($_POST['tiers']), 'sanitize_text_field');
            foreach ($tiers_input as $tier) {
                if (!is_array($tier)) continue;
                $sanitized_tiers[] = [
                    'name' => sanitize_text_field($tier['name'] ?? ''),
                    'order_to_points' => floatval($tier['order_to_points'] ?? 1),
                    'points_to_amount' => floatval($tier['points_to_amount'] ?? 1),
                    'threshold' => intval($tier['threshold'] ?? 0)
                ];
            }
        }

        update_option('truebeep_tiers', $sanitized_tiers);

        // Save coupons
        $sanitized_coupons = [];
        if (isset($_POST['coupons']) && is_array($_POST['coupons'])) {
            $coupons_input = map_deep(wp_unslash($_POST['coupons']), 'sanitize_text_field');
            foreach ($coupons_input as $coupon) {
                if (!is_array($coupon)) continue;
                $sanitized_coupons[] = [
                    'name' => sanitize_text_field($coupon['name'] ?? ''),
                    'value' => floatval($coupon['value'] ?? 0)
                ];
            }
        }

        update_option('truebeep_coupons', $sanitized_coupons);

        wp_send_json_success(['message' => __('Loyalty settings saved successfully!', 'truebeep-smart-wallet-loyalty')]);
    }

    public function ajax_save_coupons()
    {
        check_ajax_referer('truebeep_smwl_save_coupons', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'truebeep-smart-wallet-loyalty'));
        }

        // Save coupons only
        $sanitized_coupons = [];
        if (isset($_POST['coupons']) && is_array($_POST['coupons'])) {
            $coupons_input = map_deep(wp_unslash($_POST['coupons']), 'sanitize_text_field');
            foreach ($coupons_input as $coupon) {
                if (!is_array($coupon)) continue;
                $sanitized_coupons[] = [
                    'name' => sanitize_text_field($coupon['name'] ?? ''),
                    'value' => floatval($coupon['value'] ?? 0)
                ];
            }
        }

        update_option('truebeep_coupons', $sanitized_coupons);
        
        truebeep_log('Coupons saved via AJAX', 'WooCommerceSettings', ['count' => count($sanitized_coupons)]);

        wp_send_json_success(['message' => __('Coupons saved successfully!', 'truebeep-smart-wallet-loyalty')]);
    }
    
    /**
     * AJAX handler for updating connection status
     */
    public function ajax_update_connection()
    {
        check_ajax_referer('truebeep_smwl_connection', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'truebeep-smart-wallet-loyalty')]);
        }
        
        $action = isset($_POST['connection_action']) ? sanitize_text_field(wp_unslash($_POST['connection_action'])) : '';
        
        if ($action === 'connect') {
            // Send connect status to TrueBeep
            $response = $this->make_api_request('connection-status', 'POST', [
                'type' => 'wordpress',
                'status' => 'connected',
                'url' => get_site_url()
            ]);
            
            if (!is_wp_error($response) && $response['success']) {
                update_option('truebeep_connection_status', 'connected');
                truebeep_log('API Connection established', 'WooCommerceSettings');
                wp_send_json_success([
                    'message' => __('Connected successfully!', 'truebeep-smart-wallet-loyalty'),
                    'status' => 'connected'
                ]);
            } else {
                $error_message = is_wp_error($response) ? $response->get_error_message() : ($response['error'] ?? __('Connection failed', 'truebeep-smart-wallet-loyalty'));
                wp_send_json_error(['message' => $error_message]);
            }
        } elseif ($action === 'disconnect') {
            // Send disconnect status to TrueBeep
            $response = $this->make_api_request('connection-status', 'POST', [
                'type' => 'wordpress',
                'status' => 'disconnected',
                'url' => get_site_url()
            ]);
            
            if (!is_wp_error($response) && $response['success']) {
                update_option('truebeep_connection_status', 'disconnected');
                wp_send_json_success([
                    'message' => __('Disconnected successfully!', 'truebeep-smart-wallet-loyalty'),
                    'status' => 'disconnected'
                ]);
            } else {
                // Even if API fails, update local status
                update_option('truebeep_connection_status', 'disconnected');
                wp_send_json_success([
                    'message' => __('Disconnected locally', 'truebeep-smart-wallet-loyalty'),
                    'status' => 'disconnected'
                ]);
            }
        } else {
            wp_send_json_error(['message' => __('Invalid action', 'truebeep-smart-wallet-loyalty')]);
        }
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }

        // Get current tab safely without nonce verification (read-only check for script loading)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for conditional script loading
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        if ($current_tab === 'truebeep_smwl') {
            wp_enqueue_script(
                'truebeep-smwl-woocommerce-settings',
                TRUEBEEP_URL . '/assets/js/admin/woocommerce-settings.js',
                ['jquery'],
                TRUEBEEP_VERSION,
                true
            );

            wp_localize_script('truebeep-smwl-woocommerce-settings', 'truebeep_smwl_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('truebeep_smwl_save_loyalty'),
                'coupons_nonce' => wp_create_nonce('truebeep_smwl_save_coupons'),
                'connection_nonce' => wp_create_nonce('truebeep_smwl_connection'),
                'strings' => [
                    'tier_name' => __('Tier Name', 'truebeep-smart-wallet-loyalty'),
                    'remove' => __('Remove', 'truebeep-smart-wallet-loyalty'),
                    'saving' => __('Saving...', 'truebeep-smart-wallet-loyalty'),
                    'save_tiers' => __('Save Changes', 'truebeep-smart-wallet-loyalty'),
                    'save_coupons' => __('Save Coupons', 'truebeep-smart-wallet-loyalty'),
                    'processing' => __('Processing...', 'truebeep-smart-wallet-loyalty'),
                    'connect' => __('Connect', 'truebeep-smart-wallet-loyalty'),
                    'disconnect' => __('Disconnect', 'truebeep-smart-wallet-loyalty'),
                    'connected' => __('Connected', 'truebeep-smart-wallet-loyalty'),
                    'disconnected' => __('Disconnected', 'truebeep-smart-wallet-loyalty'),
                    'connectionFailed' => __('Connection failed. Please try again.', 'truebeep-smart-wallet-loyalty'),
                    'confirmRemoveTier' => __('Are you sure you want to remove this tier?', 'truebeep-smart-wallet-loyalty'),
                    'confirmRemoveCoupon' => __('Are you sure you want to remove this coupon?', 'truebeep-smart-wallet-loyalty'),
                    'newCoupon' => __('New Coupon', 'truebeep-smart-wallet-loyalty'),
                    'edit' => __('Edit', 'truebeep-smart-wallet-loyalty'),
                ]
            ]);

            wp_enqueue_style(
                'truebeep-smwl-woocommerce-settings',
                TRUEBEEP_URL . '/assets/css/admin/woocommerce-settings.css',
                [],
                TRUEBEEP_VERSION
            );
        }
    }
}