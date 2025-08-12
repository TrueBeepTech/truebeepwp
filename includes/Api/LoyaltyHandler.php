<?php

namespace Truebeep\Api;

use Truebeep\Traits\ApiHelper;

/**
 * Loyalty Handler class - Manages loyalty points for orders
 */
class LoyaltyHandler
{
    use ApiHelper;

    /**
     * Initialize loyalty handler
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WooCommerce hooks for loyalty points
     */
    private function init_hooks()
    {
        add_action('woocommerce_order_status_completed', [$this, 'award_loyalty_points'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'revoke_loyalty_points'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'revoke_loyalty_points'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'revoke_loyalty_points'], 10, 1);
        add_action('woocommerce_order_partially_refunded', [$this, 'handle_partial_refund'], 10, 2);
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_earned_points'], 10, 1);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_admin_points_info']);
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_points_redemption'], 20, 3);
    }

    /**
     * Award loyalty points when order is completed/processing
     *
     * @param int $order_id Order ID
     */
    public function award_loyalty_points($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        if ($points_awarded === 'yes') {
            return;
        }

        $has_redeemed_points = $order->get_meta('_truebeep_points_redeemed');
        if ($has_redeemed_points && !$this->should_earn_on_redeemed_orders()) {
            return;
        }

        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            return;
        }

        $order_total = $order->get_total();
        $user_id = $order->get_user_id();

        $points = $this->calculate_loyalty_points($order_total, $user_id);
        if ($points <= 0) {
            return;
        }

        $response = $this->update_loyalty_points($customer_id, $points, 'increment', 'woocommerce');
        if (!is_wp_error($response) && $response['success']) {
            $order->update_meta_data('_truebeep_points_awarded', 'yes');
            $order->update_meta_data('_truebeep_points_earned', $points);
            $order->update_meta_data('_truebeep_points_award_date', current_time('mysql'));
            $order->save();

            if ($user_id) {
                $this->sync_customer_points_from_api($customer_id, $user_id);
            }

            $order->add_order_note(sprintf(__('Awarded %s loyalty points to customer via Truebeep', 'truebeep'), $points));
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(__('Failed to award loyalty points: %s', 'truebeep'), $error_message));
        }
    }

    /**
     * Revoke loyalty points when order is cancelled/refunded
     *
     * @param int $order_id Order ID
     */
    public function revoke_loyalty_points($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        if ($points_awarded !== 'yes') {
            return;
        }

        $points_revoked = $order->get_meta('_truebeep_points_revoked');
        if ($points_revoked === 'yes') {
            return;
        }

        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            return;
        }

        $points_earned = floatval($order->get_meta('_truebeep_points_earned'));
        if ($points_earned <= 0) {
            return;
        }

        $response = $this->update_loyalty_points($customer_id, $points_earned, 'decrement', 'woocommerce');
        if (!is_wp_error($response) && $response['success']) {
            $order->update_meta_data('_truebeep_points_revoked', 'yes');
            $order->update_meta_data('_truebeep_points_revoke_date', current_time('mysql'));
            $order->save();

            $user_id = $order->get_user_id();
            if ($user_id) {
                $this->sync_customer_points_from_api($customer_id, $user_id);
            }

            $order->add_order_note(sprintf(__('Revoked %s loyalty points from customer via Truebeep', 'truebeep'), $points_earned));
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(__('Failed to revoke loyalty points: %s', 'truebeep'), $error_message));
        }
    }

    /**
     * Handle partial refund - adjust points accordingly
     *
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     */
    public function handle_partial_refund($order_id, $refund_id)
    {
        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (!$order || !$refund) {
            return;
        }

        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        if ($points_awarded !== 'yes') {
            return;
        }

        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            return;
        }

        $refund_amount = abs($refund->get_total());
        $user_id = $order->get_user_id();
        $points_to_deduct = $this->calculate_loyalty_points($refund_amount, $user_id);

        if ($points_to_deduct <= 0) {
            return;
        }

        $response = $this->update_loyalty_points($customer_id, $points_to_deduct, 'decrement', 'woocommerce');
        if (!is_wp_error($response) && $response['success']) {
            $total_refunded_points = floatval($order->get_meta('_truebeep_points_refunded')) + $points_to_deduct;
            $order->update_meta_data('_truebeep_points_refunded', $total_refunded_points);
            $order->save();

            if ($user_id) {
                $this->sync_customer_points_from_api($customer_id, $user_id);
            }

            $order->add_order_note(sprintf(__('Deducted %s loyalty points due to partial refund', 'truebeep'), $points_to_deduct));
        }
    }

    /**
     * Handle points redemption during checkout
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param object $order Order object
     */
    public function handle_points_redemption($order_id, $posted_data, $order)
    {
        $points_redeemed = floatval($order->get_meta('_truebeep_points_redeemed_amount'));

        if ($points_redeemed > 0) {
            $order->update_meta_data('_truebeep_points_redeemed', 'yes');

            $customer_id = $this->get_customer_truebeep_id($order);
            if ($customer_id) {
                $response = $this->update_loyalty_points($customer_id, $points_redeemed, 'decrement', 'woocommerce');

                if (!is_wp_error($response) && $response['success']) {
                    $user_id = $order->get_user_id();
                    if ($user_id) {
                        $this->sync_customer_points_from_api($customer_id, $user_id);
                    }

                    $order->add_order_note(sprintf(__('Deducted %s loyalty points via Truebeep API', 'truebeep'), $points_redeemed));
                } else {
                    $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                    $order->add_order_note(sprintf(__('Failed to deduct loyalty points: %s', 'truebeep'), $error_message));
                }
            }
        }
    }

    /**
     * Get Truebeep customer ID from order
     *
     * @param object $order WooCommerce order object
     * @return string|null Truebeep customer ID or null
     */
    private function get_customer_truebeep_id($order)
    {
        // First check if order has a Truebeep customer ID (for guest orders)
        $customer_id = $order->get_meta('_truebeep_customer_id');
        if ($customer_id) {
            return $customer_id;
        }

        // Check if order has a user
        $user_id = $order->get_user_id();
        if ($user_id) {
            return get_user_meta($user_id, '_truebeep_customer_id', true);
        }

        return null;
    }

    /**
     * Sync customer points from API to user meta
     *
     * @param string $truebeep_customer_id Truebeep customer ID
     * @param int $user_id WordPress user ID
     */
    private function sync_customer_points_from_api($truebeep_customer_id, $user_id)
    {
        if (!$truebeep_customer_id || !$user_id) {
            return;
        }

        try {
            $customer = $this->get_customer_points($truebeep_customer_id);
            if (empty($customer)) {
                return;
            }

            $customer_points = $customer['points'];
            $total_earned_points = $customer['totalEarnedPoints'];
            $total_spent_points = $customer['totalSpentPoints'];

            update_user_meta($user_id, '_truebeep_loyalty_points', $customer_points);
            update_user_meta($user_id, '_truebeep_total_earned_points', $total_earned_points);
            update_user_meta($user_id, '_truebeep_total_spent_points', $total_spent_points);

            $tier_info = $this->get_customer_tier($truebeep_customer_id);
            if ($tier_info['tier_tag']) {
                update_user_meta($user_id, '_truebeep_tier_name', $tier_info['tier_name']);
                update_user_meta($user_id, '_truebeep_tier_data', $tier_info['full_tier']);
            } else {
                delete_user_meta($user_id, '_truebeep_tier_name');
                delete_user_meta($user_id, '_truebeep_tier_data');
            }
        } catch (\Exception $e) {
            // Handle exception if needed
        }
    }

    /**
     * Display earned points on order details page
     *
     * @param object $order Order object
     */
    public function display_earned_points($order)
    {
        $points_earned = $order->get_meta('_truebeep_points_earned');
        $points_revoked = $order->get_meta('_truebeep_points_revoked');
        $points_redeemed = $order->get_meta('_truebeep_points_redeemed_amount');

        if ($points_earned || $points_redeemed) {
            echo '<h2>' . __('Loyalty Points', 'truebeep') . '</h2>';
            echo '<table class="woocommerce-table woocommerce-table--loyalty-points">';

            if ($points_earned) {
                echo '<tr>';
                echo '<th>' . __('Points Earned:', 'truebeep') . '</th>';
                echo '<td>' . $points_earned;
                if ($points_revoked === 'yes') {
                    echo ' <span style="color: red;">(' . __('Revoked', 'truebeep') . ')</span>';
                }
                echo '</td>';
                echo '</tr>';
            }

            if ($points_redeemed) {
                echo '<tr>';
                echo '<th>' . __('Points Redeemed:', 'truebeep') . '</th>';
                echo '<td>' . $points_redeemed . '</td>';
                echo '</tr>';
            }

            echo '</table>';
        }
    }

    /**
     * Display points info in admin order page
     */
    public function display_admin_points_info($order)
    {
        $points_earned = $order->get_meta('_truebeep_points_earned');
        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        $points_revoked = $order->get_meta('_truebeep_points_revoked');
        $points_redeemed = $order->get_meta('_truebeep_points_redeemed_amount');

        if ($points_earned || $points_redeemed) {
?>
            <div class="truebeep-loyalty-points-info" style="margin-top: 20px;">
                <h3><?php _e('Truebeep Loyalty Points', 'truebeep'); ?></h3>
                <p>
                    <?php if ($points_earned): ?>
                        <strong><?php _e('Points Earned:', 'truebeep'); ?></strong> <?php echo $points_earned; ?>
                        <?php if ($points_awarded === 'yes'): ?>
                            <span style="color: green;">(<?php _e('Awarded', 'truebeep'); ?>)</span>
                        <?php endif; ?>
                        <?php if ($points_revoked === 'yes'): ?>
                            <span style="color: red;">(<?php _e('Revoked', 'truebeep'); ?>)</span>
                        <?php endif; ?>
                        <br>
                    <?php endif; ?>

                    <?php if ($points_redeemed): ?>
                        <strong><?php _e('Points Redeemed:', 'truebeep'); ?></strong> <?php echo $points_redeemed; ?>
                    <?php endif; ?>
                </p>
            </div>
<?php
        }
    }
}
