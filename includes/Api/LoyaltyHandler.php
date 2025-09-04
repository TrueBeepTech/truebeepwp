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
        truebeep_log('LoyaltyHandler initialized', 'api_loyalty', ['timestamp' => current_time('mysql')]);
        $this->init_hooks();
    }

    /**
     * Initialize WooCommerce hooks for loyalty points
     */
    private function init_hooks()
    {
        // Get the configured order status for awarding points
        $award_status = get_option('truebeep_award_points_status', 'completed');
        truebeep_log('Initializing loyalty hooks', 'api_loyalty', ['award_status' => $award_status]);
        
        // Add hooks based on the selected status
        // Note: If 'both' is selected, points are only awarded once due to the check in award_loyalty_points()
        if ($award_status === 'processing' || $award_status === 'both') {
            add_action('woocommerce_order_status_processing', [$this, 'award_loyalty_points'], 10, 1);
        }
        if ($award_status === 'completed' || $award_status === 'both') {
            add_action('woocommerce_order_status_completed', [$this, 'award_loyalty_points'], 10, 1);
        }
        
        add_action('woocommerce_order_status_cancelled', [$this, 'revoke_loyalty_points'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'revoke_loyalty_points'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'revoke_loyalty_points'], 10, 1);
        add_action('woocommerce_order_partially_refunded', [$this, 'handle_partial_refund'], 10, 2);
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_earned_points'], 10, 1);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_admin_points_info']);
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_points_redemption'], 20, 3);
    }

    /**
     * Check if points should be earned on orders with redeemed points
     *
     * @return bool
     */
    private function should_earn_on_redeemed_orders()
    {
        return get_option('truebeep_earn_on_redeemed', 'no') === 'yes';
    }

    /**
     * Award loyalty points when order is completed/processing
     *
     * @param int $order_id Order ID
     */
    public function award_loyalty_points($order_id)
    {
        truebeep_log('Award loyalty points triggered', 'api_loyalty', ['order_id' => $order_id]);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            truebeep_log('Order not found', 'api_loyalty', ['order_id' => $order_id]);
            return;
        }

        // Prevent awarding points multiple times
        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        if ($points_awarded === 'yes') {
            truebeep_log('Points already awarded for order', 'api_loyalty', ['order_id' => $order_id]);
            return;
        }

        // Check if this order has redeemed points and if earning is allowed
        $has_redeemed_points = $order->get_meta('_truebeep_points_redeemed');
        $points_redeemed_amount = floatval($order->get_meta('_truebeep_points_redeemed_amount'));
        
        if (($has_redeemed_points === 'yes' || $points_redeemed_amount > 0) && !$this->should_earn_on_redeemed_orders()) {
            truebeep_log('Points not awarded - redeemed order with earning disabled', 'api_loyalty', [
                'order_id' => $order_id,
                'points_redeemed' => $points_redeemed_amount
            ]);
            $order->add_order_note(__('Loyalty points not awarded: Points were redeemed on this order and earning on redeemed orders is disabled.', 'truebeep'));
            return;
        }

        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            truebeep_log('No Truebeep customer ID found for order', 'api_loyalty', ['order_id' => $order_id]);
            return;
        }
        
        truebeep_log('Customer ID found for order', 'api_loyalty', [
            'order_id' => $order_id,
            'customer_id' => $customer_id
        ]);

        $order_total = $order->get_total();
        $user_id = $order->get_user_id();

        $points = $this->calculate_loyalty_points($order_total, $user_id);
        truebeep_log('Points calculated for order', 'api_loyalty', [
            'order_id' => $order_id,
            'order_total' => $order_total,
            'points' => $points,
            'user_id' => $user_id
        ]);
        
        if ($points <= 0) {
            truebeep_log('No points to award (points <= 0)', 'api_loyalty', ['order_id' => $order_id]);
            return;
        }

        $response = $this->update_loyalty_points($customer_id, $points, 'increment', 'woocommerce');
        truebeep_log('API response for awarding points', 'api_loyalty', [
            'order_id' => $order_id,
            'customer_id' => $customer_id,
            'points' => $points,
            'success' => !is_wp_error($response) && $response['success'],
            'response' => is_wp_error($response) ? $response->get_error_message() : $response
        ]);
        
        if (!is_wp_error($response) && $response['success']) {
            $order->update_meta_data('_truebeep_points_awarded', 'yes');
            $order->update_meta_data('_truebeep_points_earned', $points);
            $order->update_meta_data('_truebeep_points_award_date', current_time('mysql'));
            $order->save();

            if ($user_id) {
                $this->sync_customer_points_from_api($customer_id, $user_id);
            }

            $order->add_order_note(sprintf(__('Awarded %s loyalty points to customer via Truebeep', 'truebeep'), $points));
            truebeep_log('Points awarded successfully', 'api_loyalty', [
                'order_id' => $order_id,
                'points' => $points
            ]);
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(__('Failed to award loyalty points: %s', 'truebeep'), $error_message));
            truebeep_log('Failed to award points', 'api_loyalty', [
                'order_id' => $order_id,
                'error' => $error_message
            ]);
        }
    }

    /**
     * Revoke loyalty points when order is cancelled/refunded
     *
     * @param int $order_id Order ID
     */
    public function revoke_loyalty_points($order_id)
    {
        truebeep_log('Revoke loyalty points triggered', 'api_loyalty', ['order_id' => $order_id]);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            truebeep_log('Order not found for revocation', 'api_loyalty', ['order_id' => $order_id]);
            return;
        }

        // First, handle revoking earned points
        $this->revoke_earned_points($order);
        
        // Then, handle returning redeemed points
        $this->return_redeemed_points($order);
    }

    /**
     * Revoke earned points from cancelled order
     *
     * @param WC_Order $order
     */
    private function revoke_earned_points($order)
    {
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

        truebeep_log('Attempting to revoke earned points', 'api_loyalty', [
            'order_id' => $order->get_id(),
            'customer_id' => $customer_id,
            'points' => $points_earned
        ]);
        
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
            truebeep_log('Points revoked successfully', 'api_loyalty', [
                'order_id' => $order->get_id(),
                'points' => $points_earned
            ]);
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(__('Failed to revoke loyalty points: %s', 'truebeep'), $error_message));
            truebeep_log('Failed to revoke points', 'api_loyalty', [
                'order_id' => $order->get_id(),
                'error' => $error_message
            ]);
        }
    }

    /**
     * Return redeemed points from cancelled order
     *
     * @param WC_Order $order
     */
    private function return_redeemed_points($order)
    {
        // Check if points were redeemed for this order
        $points_redeemed = floatval($order->get_meta('_truebeep_points_redeemed_amount'));
        if ($points_redeemed <= 0) {
            return;
        }

        // Check if points have already been returned
        $points_returned = $order->get_meta('_truebeep_points_returned');
        if ($points_returned === 'yes') {
            return;
        }

        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            return;
        }

        // Return the redeemed points back to customer
        truebeep_log('Attempting to return redeemed points', 'api_loyalty', [
            'order_id' => $order->get_id(),
            'customer_id' => $customer_id,
            'points' => $points_redeemed
        ]);
        
        $response = $this->update_loyalty_points($customer_id, $points_redeemed, 'increment', 'woocommerce_refund');
        
        if (!is_wp_error($response) && $response['success']) {
            // Mark points as returned
            $order->update_meta_data('_truebeep_points_returned', 'yes');
            $order->update_meta_data('_truebeep_points_return_date', current_time('mysql'));
            $order->save();

            // Sync customer points from API to update user meta
            $user_id = $order->get_user_id();
            if ($user_id) {
                $this->sync_customer_points_from_api($customer_id, $user_id);
            }

            $order->add_order_note(sprintf(
                __('Returned %s redeemed loyalty points to customer via Truebeep', 'truebeep'), 
                $points_redeemed
            ));
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(
                __('Failed to return redeemed loyalty points: %s', 'truebeep'), 
                $error_message
            ));
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
        truebeep_log('Handling partial refund', 'api_loyalty', [
            'order_id' => $order_id,
            'refund_id' => $refund_id
        ]);
        
        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (!$order || !$refund) {
            return;
        }

        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            return;
        }

        $refund_amount = abs($refund->get_total());
        $order_total = $order->get_total();
        $user_id = $order->get_user_id();

        // Handle deducting earned points for partial refund
        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        if ($points_awarded === 'yes') {
            $points_to_deduct = $this->calculate_loyalty_points($refund_amount, $user_id);

            if ($points_to_deduct > 0) {
                $response = $this->update_loyalty_points($customer_id, $points_to_deduct, 'decrement', 'woocommerce');
                if (!is_wp_error($response) && $response['success']) {
                    $total_refunded_points = floatval($order->get_meta('_truebeep_points_refunded')) + $points_to_deduct;
                    $order->update_meta_data('_truebeep_points_refunded', $total_refunded_points);
                    $order->save();

                    $order->add_order_note(sprintf(__('Deducted %s loyalty points due to partial refund', 'truebeep'), $points_to_deduct));
                }
            }
        }

        // Handle returning redeemed points for partial refund
        $points_redeemed = floatval($order->get_meta('_truebeep_points_redeemed_amount'));
        if ($points_redeemed > 0 && $order_total > 0) {
            // Calculate proportional points to return based on refund percentage
            $refund_percentage = $refund_amount / $order_total;
            $points_to_return = round($points_redeemed * $refund_percentage);
            
            // Check if we've already returned some points
            $points_already_returned = floatval($order->get_meta('_truebeep_points_partial_returned'));
            
            // Only return if we haven't exceeded the original redeemed amount
            if (($points_already_returned + $points_to_return) <= $points_redeemed && $points_to_return > 0) {
                $response = $this->update_loyalty_points($customer_id, $points_to_return, 'increment', 'woocommerce_partial_refund');
                
                if (!is_wp_error($response) && $response['success']) {
                    $order->update_meta_data('_truebeep_points_partial_returned', $points_already_returned + $points_to_return);
                    $order->save();

                    $order->add_order_note(sprintf(
                        __('Returned %s redeemed loyalty points due to partial refund (%.1f%% of order)', 'truebeep'), 
                        $points_to_return,
                        $refund_percentage * 100
                    ));
                } else {
                    $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                    $order->add_order_note(sprintf(__('Failed to return redeemed points for partial refund: %s', 'truebeep'), $error_message));
                }
            }
        }

        // Sync customer points from API
        if ($user_id) {
            $this->sync_customer_points_from_api($customer_id, $user_id);
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
        
        truebeep_log('Handling points redemption at checkout', 'api_loyalty', [
            'order_id' => $order_id,
            'points_redeemed' => $points_redeemed
        ]);

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
                    truebeep_log('Points deducted successfully at checkout', 'api_loyalty', [
                        'order_id' => $order_id,
                        'points' => $points_redeemed
                    ]);
                } else {
                    $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                    $order->add_order_note(sprintf(__('Failed to deduct loyalty points: %s', 'truebeep'), $error_message));
                    truebeep_log('Failed to deduct points at checkout', 'api_loyalty', [
                        'order_id' => $order_id,
                        'error' => $error_message
                    ]);
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

        truebeep_log('Syncing customer points from API', 'api_loyalty', [
            'customer_id' => $truebeep_customer_id,
            'user_id' => $user_id
        ]);

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
            
            truebeep_log('Customer points synced successfully', 'api_loyalty', [
                'user_id' => $user_id,
                'points' => $customer_points,
                'total_earned' => $total_earned_points,
                'total_spent' => $total_spent_points
            ]);

            $tier_info = $this->get_customer_tier($truebeep_customer_id);
            if ($tier_info['tier_tag']) {
                update_user_meta($user_id, '_truebeep_tier_name', $tier_info['tier_name']);
                update_user_meta($user_id, '_truebeep_tier_data', $tier_info['full_tier']);
            } else {
                delete_user_meta($user_id, '_truebeep_tier_name');
                delete_user_meta($user_id, '_truebeep_tier_data');
            }
        } catch (\Exception $e) {
            truebeep_log('Error syncing customer points', 'api_loyalty', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
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
        $points_returned = $order->get_meta('_truebeep_points_returned');
        $points_partial_returned = $order->get_meta('_truebeep_points_partial_returned');
        $points_refunded = $order->get_meta('_truebeep_points_refunded');

        if ($points_earned || $points_redeemed) {
?>
            <div class="truebeep-loyalty-points-info" style="margin-top: 20px;">
                <h3><?php _e('Truebeep Loyalty Points', 'truebeep'); ?></h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <?php if ($points_earned): ?>
                    <tr>
                        <td style="padding: 5px 0;"><strong><?php _e('Points Earned:', 'truebeep'); ?></strong></td>
                        <td style="padding: 5px 0;">
                            <?php echo number_format($points_earned); ?>
                            <?php if ($points_awarded === 'yes'): ?>
                                <span style="color: green; font-size: 12px;">(<?php _e('Awarded', 'truebeep'); ?>)</span>
                            <?php endif; ?>
                            <?php if ($points_revoked === 'yes'): ?>
                                <span style="color: red; font-size: 12px;">(<?php _e('Revoked', 'truebeep'); ?>)</span>
                            <?php endif; ?>
                            <?php if ($points_refunded > 0): ?>
                                <span style="color: orange; font-size: 12px;">
                                    (<?php printf(__('%s points deducted for refunds', 'truebeep'), number_format($points_refunded)); ?>)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($points_redeemed): ?>
                    <tr>
                        <td style="padding: 5px 0;"><strong><?php _e('Points Redeemed:', 'truebeep'); ?></strong></td>
                        <td style="padding: 5px 0;">
                            <?php echo number_format($points_redeemed); ?>
                            <?php if ($points_returned === 'yes'): ?>
                                <span style="color: green; font-size: 12px;">(<?php _e('Fully Returned', 'truebeep'); ?>)</span>
                            <?php elseif ($points_partial_returned > 0): ?>
                                <span style="color: orange; font-size: 12px;">
                                    (<?php printf(__('%s points returned', 'truebeep'), number_format($points_partial_returned)); ?>)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
<?php
        }
    }
}
