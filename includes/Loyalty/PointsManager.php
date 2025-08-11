<?php

namespace Truebeep\Loyalty;

use Truebeep\Traits\ApiHelper;

if (!defined('ABSPATH')) {
    exit;
}

class PointsManager
{
    use ApiHelper;
    
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Points operations are now handled by the API via LoyaltyHandler
    }

    /**
     * Get user's current points balance from API
     */
    public function get_user_points($user_id)
    {
        if (!$user_id) {
            return 0;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            return intval(get_user_meta($user_id, '_truebeep_loyalty_points', true));
        }

        $customer = $this->get_customer_points($truebeep_customer_id);
        if (!empty($customer['points'])) {
            $points = intval($customer['points']);
            update_user_meta($user_id, '_truebeep_loyalty_points', $points);
            return $points;
        }

        return intval(get_user_meta($user_id, '_truebeep_loyalty_points', true));
    }


    /**
     * Get user's current tier from API
     */
    public function get_user_tier($user_id)
    {
        if (!$user_id) {
            return null;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            return get_user_meta($user_id, '_truebeep_tier_data', true);
        }

        $tier_info = $this->get_customer_tier($truebeep_customer_id);
        if ($tier_info['tier_tag'] && $tier_info['full_tier']) {
            update_user_meta($user_id, '_truebeep_tier_name', $tier_info['tier_name']);
            update_user_meta($user_id, '_truebeep_tier_data', $tier_info['full_tier']);
            return $tier_info['full_tier'];
        }

        return get_user_meta($user_id, '_truebeep_tier_data', true);
    }


    /**
     * Get points statistics for a user from API
     */
    public function get_user_stats($user_id)
    {
        if (!$user_id) {
            return null;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            return [
                'current_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
                'current_tier' => null
            ];
        }

        $customer = $this->get_customer_points($truebeep_customer_id);
        if (empty($customer)) {
            return [
                'current_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
                'current_tier' => null
            ];
        }

        return [
            'current_balance' => isset($customer['points']) ? intval($customer['points']) : 0,
            'lifetime_earned' => isset($customer['totalEarnedPoints']) ? intval($customer['totalEarnedPoints']) : 0,
            'lifetime_redeemed' => isset($customer['totalSpentPoints']) ? intval($customer['totalSpentPoints']) : 0,
            'current_tier' => $this->get_user_tier($user_id)
        ];
    }
}
