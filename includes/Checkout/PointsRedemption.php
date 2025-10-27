<?php

namespace Truebeep\Checkout;

use Truebeep\Loyalty\PointsManager;
use Truebeep\Traits\ApiHelper;
use Truebeep\Security\RateLimiter;

if (!defined('ABSPATH')) {
    exit;
}

class PointsRedemption
{
    use ApiHelper;
    private $user_points = 0;
    private $user_tier = null;
    private $redemption_method;
    private $earning_value;
    private $redeeming_value;
    private $tiers;
    private $coupons;

    public function __construct()
    {
        // Hook into WooCommerce checkout
        add_action('woocommerce_review_order_before_payment', [$this, 'display_points_redemption_field']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);

        // AJAX handlers - only for logged in users
        add_action('wp_ajax_apply_points_discount', [$this, 'ajax_apply_points_discount']);
        add_action('wp_ajax_remove_points_discount', [$this, 'ajax_remove_points_discount']);
        add_action('wp_ajax_validate_points', [$this, 'ajax_validate_points']);

        // Apply discount to cart
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount_to_cart']);

        // Save redemption data to order
        add_action('woocommerce_checkout_create_order', [$this, 'save_redemption_to_order'], 10, 2);

        // Initialize settings
        $this->init_settings();
    }

    private function init_settings()
    {
        $this->redemption_method = get_option('truebeep_redeem_method', 'dynamic_coupon');
        $this->earning_value = floatval(get_option('truebeep_earning_value', 1));
        $this->redeeming_value = floatval(get_option('truebeep_redeeming_value', 1));
        $this->tiers = get_option('truebeep_tiers', []);
        $this->coupons = get_option('truebeep_coupons', []);

        // Get current user's points and tier
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $this->user_points = $this->get_user_points($user_id);
            $this->user_tier = $this->get_user_tier($user_id);
        }
    }

    public function display_points_redemption_field()
    {
        // if (!is_user_logged_in() || $this->user_points <= 0) {
        //     return;
        // }

        $max_discount = $this->calculate_max_discount();
        $cart_total = WC()->cart->get_subtotal();

?>
        <div id="truebeep-points-redemption" class="truebeep-checkout-section">
            <h3><?php esc_html_e('Redeem Loyalty Points', 'truebeep'); ?></h3>

            <div class="points-balance">
                <p><?php 
                /* translators: %d: number of available points */
                printf(wp_kses_post(__('Available Points: <strong>%d</strong>', 'truebeep')), esc_html($this->user_points)); 
                ?></p>
                <?php if ($this->user_tier): ?>
                    <p><?php 
                    /* translators: %s: tier name */
                    printf(wp_kses_post(__('Your Tier: <strong>%s</strong>', 'truebeep')), esc_html($this->user_tier['name'])); 
                    ?></p>
                <?php endif; ?>
            </div>

            <?php if ($this->redemption_method === 'dynamic_coupon'): ?>
                <div class="dynamic-coupon-redemption">
                    <label for="points-to-redeem"><?php esc_html_e('Points to Redeem:', 'truebeep'); ?></label>
                    <div class="points-input-wrapper">
                        <input type="number"
                            id="points-to-redeem"
                            name="points_to_redeem"
                            min="0"
                            max="<?php echo esc_attr($this->user_points); ?>"
                            step="1"
                            placeholder="<?php esc_attr_e('Enter points', 'truebeep'); ?>" />
                        <span class="points-value-preview" data-rate="<?php echo esc_attr($this->get_redemption_rate()); ?>">
                            = $<span id="discount-preview">0.00</span>
                        </span>
                    </div>
                    <div class="points-controls">
                        <button type="button" class="button apply-points-btn" id="apply-points">
                            <?php esc_html_e('Apply Points', 'truebeep'); ?>
                        </button>
                        <button type="button" class="button remove-points-btn" id="remove-points" style="display:none;">
                            <?php esc_html_e('Remove Points', 'truebeep'); ?>
                        </button>
                    </div>
                    <div class="points-message" id="points-message"></div>
                    <p class="max-discount-info">
                        <?php 
                        /* translators: %s: maximum discount amount */
                        printf(esc_html__('Maximum discount available: $%s', 'truebeep'), esc_html(number_format($max_discount, 2))); 
                        ?>
                    </p>
                </div>
            <?php else: // Predefined coupons 
            ?>
                <div class="coupon-redemption">
                    <label for="coupon-select"><?php esc_html_e('Select Coupon:', 'truebeep'); ?></label>
                    <select id="coupon-select" name="selected_coupon">
                        <option value=""><?php esc_html_e('-- Select a coupon --', 'truebeep'); ?></option>
                        <?php foreach ($this->coupons as $index => $coupon):
                            $points_required = $this->calculate_points_for_coupon($coupon['value']);
                            $is_available = $this->user_points >= $points_required;
                        ?>
                            <option value="<?php echo esc_attr($index); ?>"
                                data-points="<?php echo esc_attr($points_required); ?>"
                                data-value="<?php echo esc_attr($coupon['value']); ?>"
                                <?php echo !$is_available ? 'disabled' : ''; ?>>
                                <?php
                                echo esc_html($coupon['name']) . ' - ' .
                                    /* translators: %d: number of points required */
                                    sprintf(esc_html__('%d points', 'truebeep'), esc_html($points_required));
                                if (!$is_available) {
                                    echo ' ' . esc_html__('(Insufficient points)', 'truebeep');
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="points-controls">
                        <button type="button" class="button apply-coupon-btn" id="apply-coupon">
                            <?php esc_html_e('Apply Coupon', 'truebeep'); ?>
                        </button>
                        <button type="button" class="button remove-coupon-btn" id="remove-coupon" style="display:none;">
                            <?php esc_html_e('Remove Coupon', 'truebeep'); ?>
                        </button>
                    </div>
                    <div class="coupon-message" id="coupon-message"></div>
                </div>
            <?php endif; ?>
        </div>
<?php
    }

    public function enqueue_checkout_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'truebeep-checkout-redemption',
            TRUEBEEP_URL . '/assets/js/frontend/checkout-redemption.js',
            ['jquery'],
            TRUEBEEP_VERSION,
            true
        );

        wp_localize_script('truebeep-checkout-redemption', 'truebeep_checkout', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('truebeep_checkout_nonce'),
            'redemption_method' => $this->redemption_method,
            'user_points' => $this->user_points,
            'redemption_rate' => $this->get_redemption_rate(),
            'strings' => [
                'applying' => __('Applying...', 'truebeep'),
                'removing' => __('Removing...', 'truebeep'),
                'applied' => __('Points applied successfully!', 'truebeep'),
                'removed' => __('Points removed successfully!', 'truebeep'),
                'error' => __('An error occurred. Please try again.', 'truebeep'),
                'invalid_points' => __('Please enter a valid number of points.', 'truebeep'),
                'select_coupon' => __('Please select a coupon.', 'truebeep'),
                'insufficient_points' => __('You don\'t have enough points.', 'truebeep'),
            ]
        ]);

        wp_enqueue_style(
            'truebeep-checkout-redemption',
            TRUEBEEP_URL . '/assets/css/frontend/checkout-redemption.css',
            [],
            TRUEBEEP_VERSION
        );
    }

    public function ajax_apply_points_discount()
    {
        check_ajax_referer('truebeep_checkout_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to redeem points.', 'truebeep')]);
        }
        
        // Rate limiting - max 5 attempts per minute
        if (RateLimiter::is_rate_limited('apply_points', RateLimiter::get_identifier(), 5, 60)) {
            wp_send_json_error(['message' => __('Too many attempts. Please try again later.', 'truebeep')]);
        }

        $user_id = get_current_user_id();

        if ($this->redemption_method === 'dynamic_coupon') {
            $points = isset($_POST['points']) ? intval($_POST['points']) : 0;

            if ($points <= 0 || $points > $this->user_points) {
                wp_send_json_error(['message' => __('Invalid points amount.', 'truebeep')]);
            }

            $discount = $this->calculate_discount_from_points($points);
            $cart_total = WC()->cart->get_subtotal();

            // Ensure discount doesn't exceed cart total
            if ($discount > $cart_total) {
                $discount = $cart_total;
                $points = $this->calculate_points_from_discount($discount);
            }

            // Store in session
            WC()->session->set('truebeep_points_redeemed', $points);
            WC()->session->set('truebeep_discount_amount', $discount);
        } else { // Predefined coupon
            $coupon_index = isset($_POST['coupon_index']) ? intval($_POST['coupon_index']) : -1;

            if (!isset($this->coupons[$coupon_index])) {
                wp_send_json_error(['message' => __('Invalid coupon selected.', 'truebeep')]);
            }

            $coupon = $this->coupons[$coupon_index];
            $points_required = $this->calculate_points_for_coupon($coupon['value']);

            if ($points_required > $this->user_points) {
                wp_send_json_error(['message' => __('Insufficient points for this coupon.', 'truebeep')]);
            }

            // Store in session
            WC()->session->set('truebeep_points_redeemed', $points_required);
            WC()->session->set('truebeep_discount_amount', $coupon['value']);
            WC()->session->set('truebeep_coupon_used', $coupon['name']);
        }

        // Trigger cart recalculation
        WC()->cart->calculate_totals();

        $points_used = WC()->session->get('truebeep_points_redeemed');
        $discount_amount = WC()->session->get('truebeep_discount_amount');
        
        truebeep_log('Points applied: ' . $points_used . ' points for $' . number_format($discount_amount, 2) . ' discount', 'PointsRedemption', ['user_id' => $user_id]);

        wp_send_json_success([
            'message' => __('Points applied successfully!', 'truebeep'),
            'points_used' => $points_used,
            'discount' => $discount_amount,
            'cart_html' => $this->get_updated_cart_totals()
        ]);
    }

    public function ajax_remove_points_discount()
    {
        check_ajax_referer('truebeep_checkout_nonce', 'nonce');

        // Clear session data
        WC()->session->set('truebeep_points_redeemed', null);
        WC()->session->set('truebeep_discount_amount', null);
        WC()->session->set('truebeep_coupon_used', null);

        // Trigger cart recalculation
        WC()->cart->calculate_totals();

        wp_send_json_success([
            'message' => __('Points removed successfully!', 'truebeep'),
            'cart_html' => $this->get_updated_cart_totals()
        ]);
    }

    public function ajax_validate_points()
    {
        check_ajax_referer('truebeep_checkout_nonce', 'nonce');

        $points = isset($_POST['points']) ? intval($_POST['points']) : 0;

        if ($points <= 0) {
            wp_send_json_error(['message' => __('Please enter a valid number of points.', 'truebeep')]);
        }

        if ($points > $this->user_points) {
            wp_send_json_error(['message' => __('You don\'t have enough points.', 'truebeep')]);
        }

        $discount = $this->calculate_discount_from_points($points);
        $cart_total = WC()->cart->get_subtotal();

        if ($discount > $cart_total) {
            $max_points = $this->calculate_points_from_discount($cart_total);
            wp_send_json_error([
                /* translators: %d: maximum number of points that can be used */
                'message' => sprintf(__('Maximum points you can use: %d', 'truebeep'), $max_points),
                'max_points' => $max_points
            ]);
        }

        wp_send_json_success([
            'discount' => $discount,
            'formatted_discount' => wc_price($discount)
        ]);
    }

    public function apply_points_discount_to_cart($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $points_redeemed = WC()->session->get('truebeep_points_redeemed');
        $discount_amount = WC()->session->get('truebeep_discount_amount');

        if ($points_redeemed && $discount_amount > 0) {
            /* translators: %d: number of points redeemed */
            $label = sprintf(__('Loyalty Points Redemption (-%d points)', 'truebeep'), $points_redeemed);

            if (WC()->session->get('truebeep_coupon_used')) {
                $label .= ' - ' . WC()->session->get('truebeep_coupon_used');
            }

            $cart->add_fee($label, -$discount_amount);
        }
    }

    public function save_redemption_to_order($order, $data)
    {
        $points_redeemed = WC()->session->get('truebeep_points_redeemed');
        $discount_amount = WC()->session->get('truebeep_discount_amount');

        if ($points_redeemed && $discount_amount > 0) {
            $order->update_meta_data('_truebeep_points_redeemed_amount', $points_redeemed);
            $order->update_meta_data('_truebeep_discount_amount', $discount_amount);

            if (WC()->session->get('truebeep_coupon_used')) {
                $order->update_meta_data('_truebeep_coupon_used', WC()->session->get('truebeep_coupon_used'));
            }

            $order->add_order_note(sprintf(
                /* translators: %1$d: number of points redeemed, %2$s: discount amount */
                __('Customer redeemed %1$d loyalty points for a discount of %2$s', 'truebeep'),
                $points_redeemed,
                wc_price($discount_amount)
            ));

            // Clear session
            WC()->session->set('truebeep_points_redeemed', null);
            WC()->session->set('truebeep_discount_amount', null);
            WC()->session->set('truebeep_coupon_used', null);
        }
    }

    // Helper methods

    private function get_user_points($user_id)
    {
        $points_manager = PointsManager::get_instance();
        return $points_manager->get_user_points($user_id);
    }

    private function get_user_tier($user_id)
    {
        $points_manager = PointsManager::get_instance();
        return $points_manager->get_user_tier($user_id);
    }

    private function get_redemption_rate()
    {
        // Get the points_to_amount value from tier or default setting
        // This value represents how many points equal $1 (e.g., 100 points = $1)
        if ($this->user_tier && isset($this->user_tier['points_to_amount'])) {
            return floatval($this->user_tier['points_to_amount']);
        }
        return $this->redeeming_value;
    }

    private function calculate_discount_from_points($points)
    {
        $rate = $this->get_redemption_rate();
        // If rate represents points per dollar (e.g., 100 points = $1)
        // Then discount = points / rate
        if ($rate > 0) {
            return $points / $rate;
        }
        return 0;
    }

    private function calculate_points_from_discount($discount)
    {
        $rate = $this->get_redemption_rate();

        // If rate represents points per dollar (e.g., 100 points = $1)
        // Then points = discount * rate
        if ($rate > 0) {
            return ceil($discount * $rate);
        }
        return 0;
    }

    private function calculate_points_for_coupon($coupon_value)
    {
        // For predefined coupons, use the same calculation as calculate_points_from_discount
        // This ensures consistency between dynamic and predefined coupon modes
        return $this->calculate_points_from_discount($coupon_value);
    }

    private function calculate_max_discount()
    {
        $cart_total = WC()->cart->get_subtotal();
        $max_points_discount = $this->calculate_discount_from_points($this->user_points);
        return min($cart_total, $max_points_discount);
    }

    private function deduct_user_points($user_id, $points)
    {
        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (!$truebeep_customer_id) {
            return false;
        }
        
        return $this->update_loyalty_points($truebeep_customer_id, $points, 'decrement', 'woocommerce');
    }

    private function get_updated_cart_totals()
    {
        ob_start();
        woocommerce_cart_totals();
        return ob_get_clean();
    }
}
