<?php

namespace Truebeep\Api;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Truebeep\Traits\ApiHelper;

/**
 * API class - Handles all Truebeep API integrations and WordPress hooks
 */
class CustomerHandler
{
    use ApiHelper;

    /**
     * Initialize API class and register hooks
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize all WordPress hooks for user synchronization
     */
    private function init_hooks()
    {
        add_action('user_register', [$this, 'handle_user_registration'], 10, 1);
        add_action('woocommerce_created_customer', [$this, 'handle_woocommerce_customer_registration'], 10, 3);
        add_action('woocommerce_new_customer', [$this, 'handle_woocommerce_new_customer'], 10, 2);
        add_action('profile_update', [$this, 'handle_user_profile_update'], 10, 2);
        add_action('edit_user_profile_update', [$this, 'handle_admin_user_update'], 10, 1);
        add_action('delete_user', [$this, 'handle_user_deletion'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_guest_checkout'], 10, 3);
        add_action('woocommerce_checkout_update_user_meta', [$this, 'handle_checkout_user_update'], 10, 2);
        add_action('woocommerce_customer_save_address', [$this, 'handle_address_update'], 10, 2);
        add_action('admin_notices', [$this, 'show_api_notices']);
        add_action('wp_ajax_truebeep_smwl_sync_user', [$this, 'ajax_sync_user']);
        add_action('wp_ajax_truebeep_smwl_remove_sync', [$this, 'ajax_remove_sync']);
    }

    /**
     * Handle WordPress user registration
     *
     * @param int $user_id User ID
     */
    public function handle_user_registration($user_id)
    {
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');
    }

    /**
     * Handle WooCommerce customer registration
     *
     * @param int $customer_id Customer ID
     * @param array $new_customer_data New customer data
     * @param bool $password_generated Whether password was auto-generated
     */
    public function handle_woocommerce_customer_registration($customer_id, $new_customer_data, $password_generated)
    {
        $this->create_or_update_truebeep_customer($customer_id, 'WordPress');
    }

    /**
     * Handle WooCommerce new customer (alternative hook)
     *
     * @param int $customer_id Customer ID
     * @param array $customer_data Customer data
     */
    public function handle_woocommerce_new_customer($customer_id, $customer_data = [])
    {
        $truebeep_id = get_user_meta($customer_id, '_truebeep_customer_id', true);
        if (empty($truebeep_id)) {
            $this->create_or_update_truebeep_customer($customer_id, 'WordPress');
        }
    }

    /**
     * Handle user profile update
     *
     * @param int $user_id User ID
     * @param object $old_user_data Old user data
     */
    public function handle_user_profile_update($user_id, $old_user_data)
    {
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');
    }

    /**
     * Handle admin user update
     *
     * @param int $user_id User ID
     */
    public function handle_admin_user_update($user_id)
    {
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');
    }

    /**
     * Handle guest checkout - create Truebeep customer for guests
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param object $order Order object
     */
    public function handle_guest_checkout($order_id, $posted_data, $order)
    {
        // Check if this is a guest order
        if (!$order->get_user_id()) {
            $customer_data = [
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'source' => 'WordPress'
            ];
            
            // Add address fields
            if ($order->get_billing_city()) {
                $customer_data['city'] = $order->get_billing_city();
            }
            
            if ($order->get_billing_state()) {
                $customer_data['state'] = $order->get_billing_state();
            }
            
            if ($order->get_billing_country()) {
                $customer_data['country'] = $order->get_billing_country();
            }
            
            if ($order->get_billing_postcode()) {
                $customer_data['zipCode'] = $order->get_billing_postcode();
            }
            
            // Add additional metadata
            $metadata = [];
            
            if ($order->get_billing_address_1()) {
                $metadata['billing_address_1'] = $order->get_billing_address_1();
            }
            
            if ($order->get_billing_address_2()) {
                $metadata['billing_address_2'] = $order->get_billing_address_2();
            }
            
            if ($order->get_billing_company()) {
                $metadata['company'] = $order->get_billing_company();
            }
            
            // Add shipping information if present
            if ($order->get_shipping_city()) {
                $metadata['shipping_city'] = $order->get_shipping_city();
                $metadata['shipping_state'] = $order->get_shipping_state();
                $metadata['shipping_country'] = $order->get_shipping_country();
                $metadata['shipping_postcode'] = $order->get_shipping_postcode();
            }
            
            if (!empty($metadata)) {
                $customer_data['metadata'] = $metadata;
            }

            $response = $this->create_truebeep_customer($customer_data);

            if (!is_wp_error($response) && $response['success']) {
                // Store Truebeep customer ID with the order
                $order->update_meta_data('_truebeep_customer_id', $response['data']['id']);
                $order->save();

                // Log success
                $this->log_api_activity('Guest customer created in Truebeep', $response['data']['id']);
                truebeep_log('Guest customer created for order #' . $order_id, 'CustomerHandler', ['customer_id' => $response['data']['id']]);
            } else {
                // Log error
                $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                $this->log_api_activity('Failed to create guest customer in Truebeep', $error_message, 'error');
                truebeep_log('Failed to create guest customer for order #' . $order_id, 'CustomerHandler', ['error' => $error_message]);
            }
        }
    }

    /**
     * Create or update Truebeep customer
     *
     * @param int $user_id WordPress user ID
     * @param string $source Registration source
     */
    private function create_or_update_truebeep_customer($user_id, $source = 'WordPress')
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        $customer_data = [
            'firstName' => get_user_meta($user_id, 'first_name', true) ?: $user->display_name,
            'lastName' => get_user_meta($user_id, 'last_name', true) ?: '',
            'email' => $user->user_email,
            'source' => $source
        ];

        // Add phone number
        $billing_phone = get_user_meta($user_id, 'billing_phone', true);
        if ($billing_phone) {
            $customer_data['phone'] = $billing_phone;
        }
        
        // Add address fields from billing information
        $billing_city = get_user_meta($user_id, 'billing_city', true);
        if ($billing_city) {
            $customer_data['city'] = $billing_city;
        }
        
        $billing_state = get_user_meta($user_id, 'billing_state', true);
        if ($billing_state) {
            $customer_data['state'] = $billing_state;
        }
        
        $billing_country = get_user_meta($user_id, 'billing_country', true);
        if ($billing_country) {
            $customer_data['country'] = $billing_country;
        }
        
        $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
        if ($billing_postcode) {
            $customer_data['zipCode'] = $billing_postcode;
        }
        
        // Collect all other user metadata as additional metadata
        $metadata = [];
        
        // Add billing address line 1 and 2 if present
        $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
        if ($billing_address_1) {
            $metadata['billing_address_1'] = $billing_address_1;
        }
        
        $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
        if ($billing_address_2) {
            $metadata['billing_address_2'] = $billing_address_2;
        }
        
        // Add company if present
        $billing_company = get_user_meta($user_id, 'billing_company', true);
        if ($billing_company) {
            $metadata['company'] = $billing_company;
        }
        
        // Add shipping information if different from billing
        $shipping_city = get_user_meta($user_id, 'shipping_city', true);
        if ($shipping_city && $shipping_city !== $billing_city) {
            $metadata['shipping_city'] = $shipping_city;
            $metadata['shipping_state'] = get_user_meta($user_id, 'shipping_state', true);
            $metadata['shipping_country'] = get_user_meta($user_id, 'shipping_country', true);
            $metadata['shipping_postcode'] = get_user_meta($user_id, 'shipping_postcode', true);
        }
        
        // Add metadata if not empty
        if (!empty($metadata)) {
            $customer_data['metadata'] = $metadata;
        }

        $response = !empty($truebeep_customer_id)
            ? $this->update_truebeep_customer($truebeep_customer_id, $customer_data)
            : $this->create_truebeep_customer($customer_data);

        if (!is_wp_error($response) && $response['success']) {
            $response_data = !empty($response['data']['data']) ? $response['data']['data'] : $response['data'];
            if (empty($truebeep_customer_id) && isset($response_data['id'])) {
                update_user_meta($user_id, '_truebeep_customer_id', $response_data['id']);
                update_user_meta($user_id, '_truebeep_sync_status', 'synced');
                update_user_meta($user_id, '_truebeep_last_sync', current_time('mysql'));
                $this->log_api_activity("User created in Truebeep", $user_id);
                truebeep_log('Customer created: User #' . $user_id, 'CustomerHandler', ['truebeep_id' => $response_data['id']]);
            }
        } else {
            update_user_meta($user_id, '_truebeep_sync_status', 'error');
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            update_user_meta($user_id, '_truebeep_sync_error', $error_message);
            $this->log_api_activity("Failed to create or update user in Truebeep", $error_message, 'error');
            truebeep_log('Customer sync failed: User #' . $user_id, 'CustomerHandler', ['error' => $error_message]);
        }
    }

    /**
     * Handle checkout user update
     *
     * @param int $user_id User ID
     * @param array $data Checkout data
     */
    public function handle_checkout_user_update($user_id, $data)
    {
        // Sync user data after checkout updates their billing/shipping information
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');
    }
    
    /**
     * Handle address update from My Account page
     *
     * @param int $user_id User ID
     * @param string $address_type Type of address (billing or shipping)
     */
    public function handle_address_update($user_id, $address_type)
    {
        // Sync user data after they update their address in My Account
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');
    }

    /**
     * Handle user deletion
     *
     * @param int $user_id User ID
     */
    public function handle_user_deletion($user_id)
    {
        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);

        if (!empty($truebeep_customer_id)) {
            $response = $this->delete_truebeep_customer($truebeep_customer_id);

            if (!is_wp_error($response) && $response['success']) {
                $this->log_api_activity('User deleted from Truebeep', $user_id);
                truebeep_log('Customer deleted: User #' . $user_id, 'CustomerHandler');
            } else {
                $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                $this->log_api_activity('Failed to delete user from Truebeep', $error_message, 'error');
                truebeep_log('Failed to delete customer: User #' . $user_id, 'CustomerHandler', ['error' => $error_message]);
            }
        }
    }



    /**
     * Log API activity
     *
     * @param string $message Log message
     * @param mixed $data Additional data
     * @param string $level Log level (info, error, warning)
     */
    private function log_api_activity($message, $data = null, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            truebeep_log($message, 'CustomerHandler-' . $level, $data);
        }

        // Store in option for admin display
        $logs = get_option('truebeep_api_logs', []);

        $logs[] = [
            'time' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'data' => $data
        ];

        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option('truebeep_api_logs', $logs);
    }

    /**
     * Show API notices in admin
     */
    public function show_api_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if API credentials are configured
        $api_url = $this->get_api_url();
        $api_key = $this->get_api_key();

        if (empty($api_url) || empty($api_key)) {
?>
            <div class="notice notice-warning">
                <p><?php esc_html_e('Truebeep API credentials are not configured. Please configure them in WooCommerce > Settings > Truebeep.', 'truebeep'); ?></p>
            </div>
<?php
        }
    }

    /**
     * AJAX handler for manual user sync
     */
    public function ajax_sync_user()
    {
        check_ajax_referer('truebeep_smwl_sync_user', 'nonce');

        if (!current_user_can('edit_users')) {
            wp_send_json_error(['message' => __('Permission denied', 'truebeep')]);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');

        $sync_status = get_user_meta($user_id, '_truebeep_sync_status', true);

        if ($sync_status === 'synced') {
            wp_send_json_success(['message' => __('User synced successfully', 'truebeep')]);
        } else {
            $error = get_user_meta($user_id, '_truebeep_sync_error', true);
            wp_send_json_error(['message' => $error ?: __('Sync failed', 'truebeep')]);
        }
    }

    /**
     * AJAX handler for removing user sync
     */
    public function ajax_remove_sync()
    {
        check_ajax_referer('truebeep_smwl_remove_sync', 'nonce');

        if (!current_user_can('edit_users')) {
            wp_send_json_error(['message' => __('Permission denied', 'truebeep')]);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        delete_user_meta($user_id, '_truebeep_customer_id');
        delete_user_meta($user_id, '_truebeep_sync_status');
        delete_user_meta($user_id, '_truebeep_last_sync');
        delete_user_meta($user_id, '_truebeep_sync_error');
        
        wp_send_json_success(['message' => __('Truebeep link removed', 'truebeep')]);
    }
}
