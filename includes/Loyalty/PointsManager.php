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
            truebeep_log('PointsManager instance created', 'loyalty_points', ['timestamp' => current_time('mysql')]);
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
            truebeep_log('Get user points - no user ID provided', 'loyalty_points');
            return 0;
        }
        
        truebeep_log('Getting user points', 'loyalty_points', ['user_id' => $user_id]);

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            $cached_points = intval(get_user_meta($user_id, '_truebeep_loyalty_points', true));
            truebeep_log('No Truebeep ID - returning cached points', 'loyalty_points', [
                'user_id' => $user_id,
                'cached_points' => $cached_points
            ]);
            return $cached_points;
        }

        $customer = $this->get_customer_points($truebeep_customer_id);
        if (!empty($customer['points'])) {
            $points = intval($customer['points']);
            update_user_meta($user_id, '_truebeep_loyalty_points', $points);
            truebeep_log('Points fetched from API', 'loyalty_points', [
                'user_id' => $user_id,
                'truebeep_id' => $truebeep_customer_id,
                'points' => $points
            ]);
            return $points;
        }

        $fallback_points = intval(get_user_meta($user_id, '_truebeep_loyalty_points', true));
        truebeep_log('API fetch failed - returning cached points', 'loyalty_points', [
            'user_id' => $user_id,
            'fallback_points' => $fallback_points
        ]);
        return $fallback_points;
    }


    /**
     * Get user's current tier from API
     */
    public function get_user_tier($user_id)
    {
        if (!$user_id) {
            truebeep_log('Get user tier - no user ID provided', 'loyalty_points');
            return null;
        }
        
        truebeep_log('Getting user tier', 'loyalty_points', ['user_id' => $user_id]);

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            return get_user_meta($user_id, '_truebeep_tier_data', true);
        }

        $tier_info = $this->get_customer_tier($truebeep_customer_id);
        if ($tier_info['tier_tag'] && $tier_info['full_tier']) {
            update_user_meta($user_id, '_truebeep_tier_name', $tier_info['tier_name']);
            update_user_meta($user_id, '_truebeep_tier_data', $tier_info['full_tier']);
            truebeep_log('Tier fetched from API', 'loyalty_points', [
                'user_id' => $user_id,
                'tier_name' => $tier_info['tier_name'],
                'tier_tag' => $tier_info['tier_tag']
            ]);
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
            truebeep_log('Get user stats - no user ID provided', 'loyalty_points');
            return null;
        }
        
        truebeep_log('Getting user stats', 'loyalty_points', ['user_id' => $user_id]);

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

        $stats = [
            'current_balance' => isset($customer['points']) ? intval($customer['points']) : 0,
            'lifetime_earned' => isset($customer['totalEarnedPoints']) ? intval($customer['totalEarnedPoints']) : 0,
            'lifetime_redeemed' => isset($customer['totalSpentPoints']) ? intval($customer['totalSpentPoints']) : 0,
            'current_tier' => $this->get_user_tier($user_id)
        ];
        
        truebeep_log('User stats retrieved', 'loyalty_points', [
            'user_id' => $user_id,
            'stats' => $stats
        ]);
        
        return $stats;
    }
}
