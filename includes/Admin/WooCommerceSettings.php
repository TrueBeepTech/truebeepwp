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
        add_action('wp_ajax_truebeep_save_coupons', [$this, 'ajax_save_coupons']);
        add_action('woocommerce_admin_field_truebeep_coupons', [$this, 'output_loyalty_field']);
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
                'type' => 'truebeep_coupons',
                'id' => 'truebeep_coupons_settings'
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
                'desc' => __('How many points equal $1 (for customers with no tier)', 'truebeep'),
                'id' => 'truebeep_redeeming_value',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'default' => '100',
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
                'title' => __('Award Points on Order Status', 'truebeep'),
                'desc' => __('Select when customers earn loyalty points. Processing = immediately after payment, Completed = after order fulfillment', 'truebeep'),
                'id' => 'truebeep_award_points_status',
                'type' => 'select',
                'default' => 'completed',
                'options' => [
                    'processing' => __('Processing (Immediate)', 'truebeep'),
                    'completed' => __('Completed (After Fulfillment)', 'truebeep'),
                    'both' => __('Both Processing and Completed', 'truebeep'),
                ],
                'desc_tip' => true,
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
                'title' => __('Show Loyalty Panel', 'truebeep'),
                'desc' => __('Display floating loyalty panel on frontend', 'truebeep'),
                'id' => 'truebeep_show_loyalty_panel',
                'type' => 'checkbox',
                'default' => 'yes',
                'desc_tip' => false,
            ],

            [
                'title' => __('Wallet Base URL', 'truebeep'),
                'desc' => __('Enter the base URL for your wallet API', 'truebeep'),
                'id' => 'truebeep_wallet_base_url',
                'type' => 'text',
                'css' => 'min-width:400px;',
                'placeholder' => 'e.g. https://processor.truebeep.com',
                'desc_tip' => true,
            ],

            [
                'title' => __('Panel Position', 'truebeep'),
                'desc' => __('Choose where to display the loyalty panel', 'truebeep'),
                'id' => 'truebeep_panel_position',
                'type' => 'select',
                'options' => [
                    'bottom-right' => __('Bottom Right', 'truebeep'),
                    'bottom-left' => __('Bottom Left', 'truebeep'),
                    // 'top-right' => __('Top Right', 'truebeep'),
                    // 'top-left' => __('Top Left', 'truebeep'),
                ],
                'default' => 'bottom-right',
                'desc_tip' => true,
            ],
            // [
            //     'title' => __('Apple Wallet Template ID', 'truebeep'),
            //     'desc' => __('Enter your Apple Wallet pass template ID', 'truebeep'),
            //     'id' => 'truebeep_apple_wallet_template_id',
            //     'type' => 'text',
            //     'css' => 'min-width:400px;',
            //     'placeholder' => 'e.g. kdgydz9zs90mdgr6hcdce4d8',
            //     'desc_tip' => true,
            // ],
            // [
            //     'title' => __('Google Wallet Template ID', 'truebeep'),
            //     'desc' => __('Enter your Google Wallet pass template ID', 'truebeep'),
            //     'id' => 'truebeep_google_wallet_template_id',
            //     'type' => 'text',
            //     'css' => 'min-width:400px;',
            //     'placeholder' => 'e.g. kdgydz9zs90mdgr6hcdce4d8',
            //     'desc_tip' => true,
            // ],
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

    public function output_loyalty_field($value)
    {
        // This is a custom field type handler for loyalty fields
        if ($value['type'] == 'truebeep_coupons') {
            $coupons = get_option('truebeep_coupons', $this->get_default_coupons());
            ?>
            <tr valign="top" id="coupon-settings-section" style="display:none;">
                <th scope="row" class="titledesc">
                    <label><?php _e('Coupons', 'truebeep'); ?></label>
                </th>
                <td class="forminp">
                    <div id="truebeep-coupons-container">
                        <table class="wp-list-table widefat fixed striped" id="truebeep-coupons-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Coupon Name', 'truebeep'); ?></th>
                                    <th><?php _e('Value', 'truebeep'); ?></th>
                                    <th><?php _e('Actions', 'truebeep'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="truebeep-coupons-list">
                                <?php foreach ($coupons as $index => $coupon) : ?>
                                    <tr class="coupon-row" data-index="<?php echo $index; ?>">
                                        <td><?php echo esc_html($coupon['name']); ?></td>
                                        <td>$<?php echo esc_html($coupon['value']); ?></td>
                                        <td>
                                            <button type="button" class="button edit-coupon" data-coupon='<?php echo json_encode($coupon); ?>' data-index="<?php echo $index; ?>"><?php _e('Edit', 'truebeep'); ?></button>
                                            <button type="button" class="button remove-coupon" data-index="<?php echo $index; ?>"><?php _e('Remove', 'truebeep'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="button" class="button button-secondary" id="add-coupon-button"><?php _e('Add New Coupon', 'truebeep'); ?></button>
                            <button type="button" class="button button-primary" id="save-coupons-button"><?php _e('Save Coupons', 'truebeep'); ?></button>
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

        // Save coupons
        $coupons = isset($_POST['coupons']) ? $_POST['coupons'] : [];
        $sanitized_coupons = [];

        foreach ($coupons as $coupon) {
            $sanitized_coupons[] = [
                'name' => sanitize_text_field($coupon['name']),
                'value' => floatval($coupon['value'])
            ];
        }

        update_option('truebeep_coupons', $sanitized_coupons);

        wp_send_json_success(['message' => __('Loyalty settings saved successfully!', 'truebeep')]);
    }

    public function ajax_save_coupons()
    {
        check_ajax_referer('truebeep_save_coupons', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'truebeep'));
        }

        // Save coupons only
        $coupons = isset($_POST['coupons']) ? $_POST['coupons'] : [];
        $sanitized_coupons = [];

        foreach ($coupons as $coupon) {
            $sanitized_coupons[] = [
                'name' => sanitize_text_field($coupon['name']),
                'value' => floatval($coupon['value'])
            ];
        }

        update_option('truebeep_coupons', $sanitized_coupons);

        wp_send_json_success(['message' => __('Coupons saved successfully!', 'truebeep')]);
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
                'coupons_nonce' => wp_create_nonce('truebeep_save_coupons'),
                'strings' => [
                    'tier_name' => __('Tier Name', 'truebeep'),
                    'remove' => __('Remove', 'truebeep'),
                    'saving' => __('Saving...', 'truebeep'),
                    'save_tiers' => __('Save Changes', 'truebeep'),
                    'save_coupons' => __('Save Coupons', 'truebeep'),
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