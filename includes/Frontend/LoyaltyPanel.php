<?php

namespace Truebeep\Frontend;

use Truebeep\Traits\ApiHelper;
use Truebeep\Loyalty\PointsManager;
use Truebeep\Security\RateLimiter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Floating Loyalty Panel for displaying user points and wallet downloads
 */
class LoyaltyPanel
{
    use ApiHelper;

    private $points_manager;

    public function __construct()
    {
        $this->points_manager = PointsManager::get_instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('wp_footer', [$this, 'render_loyalty_panel']);
    }

    /**
     * Render the loyalty panel HTML
     */
    public function render_loyalty_panel()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $show_panel = get_option('truebeep_show_loyalty_panel', 'yes');
        if ($show_panel !== 'yes') {
            return;
        }

        $user_id = get_current_user_id();
        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            return;
        }

        $panel_position = get_option('truebeep_panel_position', 'bottom-right');

        wp_enqueue_script('truebeep-loyalty-panel');
        wp_enqueue_style('truebeep-loyalty-panel');

        // Get wallet template IDs
        $wallet_base_url = $this->get_wallet_base_url();
        $wallet_id = $this->get_wallet_id();
        $user_id = get_current_user_id();

        // Localize script with necessary data
        wp_localize_script('truebeep-loyalty-panel', 'truebeep_panel', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('truebeep_panel_nonce'),
            'apple_wallet_url' => $wallet_base_url . '/api/apple/v1/generate-pass',
            'google_wallet_url' => $wallet_base_url . '/api/google/v1/generate-pass',
            'apple_template_id' => $wallet_id,
            'google_template_id' => $wallet_id,
            'user_id' => $user_id,
            'strings' => [
                'loading' => __('Loading...', 'truebeep'),
                'error' => __('Error loading data', 'truebeep'),
                'no_tier' => __('Bronze', 'truebeep'),
            ]
        ]);

        ob_start();
        include __DIR__ . '/views/loyalty-panel.php';
        $panel_html = ob_get_clean();

        echo $panel_html;
    }


    /**
     * AJAX handler to get loyalty data
     */
    public function ajax_get_loyalty_data()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'truebeep_panel_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'truebeep')]);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Not logged in', 'truebeep')]);
        }
        
        // Rate limiting - max 10 requests per minute
        if (RateLimiter::is_rate_limited('loyalty_data', RateLimiter::get_identifier(), 10, 60)) {
            wp_send_json_error(['message' => __('Too many requests. Please try again later.', 'truebeep')]);
        }

        $user_id = get_current_user_id();
        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            wp_send_json_error(['message' => __('No customer ID found', 'truebeep')]);
        }

        $customer_data = $this->get_customer_points($truebeep_customer_id);
        if (empty($customer_data)) {
            wp_send_json_error(['message' => __('Failed to fetch customer data', 'truebeep')]);
        }

        $tier_info = $this->get_customer_tier($truebeep_customer_id);
        $response = [
            'points' => isset($customer_data['points']) ? intval($customer_data['points']) : 0,
            'total_earned' => isset($customer_data['totalEarnedPoints']) ? intval($customer_data['totalEarnedPoints']) : 0,
            'total_spent' => isset($customer_data['totalSpentPoints']) ? intval($customer_data['totalSpentPoints']) : 0,
            'tier' => $tier_info['tier_name'] ?: __('Bronze', 'truebeep'),
            'tier_data' => $tier_info['full_tier']
        ];

        wp_send_json_success($response);
    }
}
