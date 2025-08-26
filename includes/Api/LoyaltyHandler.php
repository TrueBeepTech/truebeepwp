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
        // Get the configured order status for awarding points
        $award_status = get_option('truebeep_award_points_status', 'completed');
        
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
        
        // Add hook for customer interaction API when order is placed
        add_action('woocommerce_checkout_order_processed', [$this, 'send_order_interaction'], 15, 3);
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
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Prevent awarding points multiple times
        $points_awarded = $order->get_meta('_truebeep_points_awarded');
        if ($points_awarded === 'yes') {
            return;
        }

        // Check if this order has redeemed points and if earning is allowed
        $has_redeemed_points = $order->get_meta('_truebeep_points_redeemed');
        $points_redeemed_amount = floatval($order->get_meta('_truebeep_points_redeemed_amount'));
        
        if (($has_redeemed_points === 'yes' || $points_redeemed_amount > 0) && !$this->should_earn_on_redeemed_orders()) {
            $order->add_order_note(__('Loyalty points not awarded: Points were redeemed on this order and earning on redeemed orders is disabled.', 'truebeep'));
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
     * Send order interaction to Truebeep API
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param object $order Order object
     */
    public function send_order_interaction($order_id, $posted_data, $order)
    {
        // Get customer Truebeep ID
        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            $order->add_order_note(__('Order interaction not sent to Truebeep: Customer ID not found', 'truebeep'));
            return;
        }

        // Build order data payload
        $order_data = $this->build_order_interaction_payload($order);
        
        // Send interaction to API
        $response = $this->send_customer_interaction($customer_id, 'order', $order_data);
        
        if (!is_wp_error($response) && $response['success']) {
            $order->update_meta_data('_truebeep_interaction_sent', 'yes');
            $order->update_meta_data('_truebeep_interaction_date', current_time('mysql'));
            $order->save();
            
            $order->add_order_note(__('Order interaction successfully sent to Truebeep', 'truebeep'));
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(__('Failed to send order interaction to Truebeep: %s', 'truebeep'), $error_message));
        }
    }
    
    /**
     * Build order interaction payload
     *
     * @param WC_Order $order
     * @return array
     */
    private function build_order_interaction_payload($order)
    {
        // Extract order items and build keywords
        $items = [];
        $keywords = [];
        $categories = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if ($product) {
                // Add product name to keywords
                $keywords[] = $product->get_name();
                
                // Add SKU if available
                if ($product->get_sku()) {
                    $keywords[] = $product->get_sku();
                }
                
                // Get product categories
                $product_categories = get_the_terms($product->get_id(), 'product_cat');
                if ($product_categories && !is_wp_error($product_categories)) {
                    foreach ($product_categories as $category) {
                        $categories[] = $category->name;
                        $keywords[] = $category->name;
                    }
                }
                
                // Build item data
                $items[] = [
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'quantity' => $item->get_quantity(),
                    'price' => $item->get_total(),
                    'variation_id' => $product->is_type('variation') ? $product->get_id() : null,
                ];
            }
        }
        
        // Remove duplicate keywords and categories
        $keywords = array_unique($keywords);
        $categories = array_unique($categories);
        
        // Build the complete payload
        $payload = [
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'order_total' => $order->get_total(),
            'order_subtotal' => $order->get_subtotal(),
            'order_tax' => $order->get_total_tax(),
            'order_shipping' => $order->get_shipping_total(),
            'order_discount' => $order->get_discount_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            
            // Customer information
            'customer_id' => $order->get_user_id(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            
            // Billing details
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            
            // Shipping details
            'shipping' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ],
            
            // Order items
            'items' => $items,
            
            // Keywords for the order
            'keywords' => implode(', ', $keywords),
            'categories' => $categories,
            
            // Loyalty points information if available
            'points_earned' => floatval($order->get_meta('_truebeep_points_earned')),
            'points_redeemed' => floatval($order->get_meta('_truebeep_points_redeemed_amount')),
            
            // Store information
            'store_url' => get_site_url(),
            'store_name' => get_bloginfo('name'),
        ];
        
        return $payload;
    }
    
    /**
     * Send customer interaction to API
     *
     * @param string $customer_id Truebeep customer ID
     * @param string $type Interaction type
     * @param array $data Interaction data
     * @return array|WP_Error
     */
    private function send_customer_interaction($customer_id, $type, $data)
    {
        $api_url = $this->get_api_url();
        if (empty($api_url)) {
            return new \WP_Error('missing_api_url', __('API URL is not configured', 'truebeep'));
        }
        
        // Build the interaction endpoint URL
        $endpoint = sprintf(
            'customer-interactions?subscriber_id=%s&channel=wordpress&type=%s',
            urlencode($customer_id),
            urlencode($type)
        );
        
        // Send the interaction data
        return $this->make_api_request($endpoint, 'POST', $data);
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
