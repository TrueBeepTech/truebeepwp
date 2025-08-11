<?php

namespace Truebeep\Frontend;

use Truebeep\Traits\ApiHelper;
use Truebeep\Loyalty\PointsManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Account Dashboard Loyalty section handler
 */
class MyAccountLoyalty
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
        // Add loyalty section to My Account Dashboard
        add_action('woocommerce_account_dashboard', [$this, 'dashboard_loyalty_section'], 5);
        
        // Enqueue styles for My Account page
        add_action('wp_enqueue_scripts', [$this, 'enqueue_myaccount_assets']);
    }

    /**
     * Display loyalty section on dashboard
     */
    public function dashboard_loyalty_section()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        
        if (!$truebeep_customer_id) {
            return;
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
        
        // Load the dashboard view
        include __DIR__ . '/views/dashboard-loyalty.php';
    }

    /**
     * Enqueue assets for My Account page
     */
    public function enqueue_myaccount_assets()
    {
        if (!is_account_page()) {
            return;
        }
        
        // Enqueue the loyalty panel CSS for consistent styling
        wp_enqueue_style(
            'truebeep-myaccount-loyalty',
            TRUEBEEP_URL . '/assets/css/frontend/myaccount-loyalty.css',
            [],
            TRUEBEEP_VERSION
        );
    }
}