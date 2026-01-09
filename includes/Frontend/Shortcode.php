<?php

namespace Truebeep\Frontend;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Truebeep\Traits\ApiHelper;

/**
 * Shortcode class Test
 */
class Shortcode
{
    use ApiHelper;

    /**
     * Initialize class
     */
    public function __construct()
    {
        add_shortcode('truebeep_loyalty', [$this, 'truebeep_loyalty']);
    }

    /**
     * Loyalty Points and Wallet Shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    public function truebeep_loyalty($atts, $content = null)
    {
        // Parse shortcode attributes
        $attributes = shortcode_atts([
            'show_points' => 'true',
            'show_tier' => 'true',
            'show_wallet' => 'true',
            'layout' => 'horizontal', // horizontal, vertical, compact
            'style' => 'default' // default, card, minimal
        ], $atts);

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="truebeep-loyalty-shortcode error">' . __('Please log in to view your loyalty information.', 'truebeep-smart-wallet-loyalty') . '</div>';
        }

        $user_id = get_current_user_id();
        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);

        if (!$truebeep_customer_id) {
            return '<div class="truebeep-loyalty-shortcode error">' . __('Loyalty account not found.', 'truebeep-smart-wallet-loyalty') . '</div>';
        }

        // Get customer data from API
        $customer_data = $this->get_customer_points($truebeep_customer_id);
        $tier_info = $this->get_customer_tier($truebeep_customer_id);

        // Prepare data for the view
        $user = wp_get_current_user();
        $user_name = $user->display_name ?: $user->user_login;
        $points = isset($customer_data['points']) ? intval($customer_data['points']) : 0;
        $tier_name = $tier_info['tier_name'] ?: 'bronze';

        // Get wallet URLs
        $wallet_base_url = $this->get_wallet_base_url();
        $wallet_id = $this->get_wallet_id();

        // Build wallet URLs
        $apple_wallet_url = '';
        $google_wallet_url = '';

        if ($wallet_base_url && $wallet_id && $truebeep_customer_id) {
            $apple_wallet_url = $wallet_base_url . '/api/apple/v1/generate-pass?templateId=' . urlencode($wallet_id) . '&customerId=' . urlencode($truebeep_customer_id);
            $google_wallet_url = $wallet_base_url . '/api/google/v1/generate-pass?templateId=' . urlencode($wallet_id) . '&customerId=' . urlencode($truebeep_customer_id);
        }

        // Enqueue shortcode styles
        wp_enqueue_style(
            'truebeep-loyalty-shortcode',
            TRUEBEEP_URL . '/assets/css/frontend/loyalty-shortcode.css',
            [],
            TRUEBEEP_VERSION
        );

        ob_start();
        include __DIR__ . '/views/loyalty-shortcode.php';
        return ob_get_clean();
    }
}
