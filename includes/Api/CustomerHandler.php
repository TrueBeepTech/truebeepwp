<?php

namespace Truebeep\Api;

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
        // WordPress user registration hooks
        add_action('user_register', [$this, 'handle_user_registration'], 10, 1);

        // WooCommerce customer registration hooks
        add_action('woocommerce_created_customer', [$this, 'handle_woocommerce_customer_registration'], 10, 3);
        add_action('woocommerce_new_customer', [$this, 'handle_woocommerce_new_customer'], 10, 2);

        // User profile update hooks
        add_action('profile_update', [$this, 'handle_user_profile_update'], 10, 2);
        add_action('edit_user_profile_update', [$this, 'handle_admin_user_update'], 10, 1);

        // User deletion hook
        add_action('delete_user', [$this, 'handle_user_deletion'], 10, 1);

        // WooCommerce checkout hooks for guest customers
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_guest_checkout'], 10, 3);

        // Admin notices
        add_action('admin_notices', [$this, 'show_api_notices']);

        // AJAX handlers for manual sync
        add_action('wp_ajax_truebeep_sync_user', [$this, 'ajax_sync_user']);
        add_action('wp_ajax_truebeep_remove_sync', [$this, 'ajax_remove_sync']);
    }

    /**
     * Handle WordPress user registration
     *
     * @param int $user_id User ID
     */
    public function handle_user_registration($user_id)
    {
        _log('handle_user_registration');
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
        _log('handle_woocommerce_customer_registration');
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
        _log('handle_woocommerce_new_customer');
        // Check if customer already has Truebeep ID to avoid duplicate creation
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
        _log('handle_user_profile_update');
        $this->create_or_update_truebeep_customer($user_id, 'WordPress');
    }

    /**
     * Handle admin user update
     *
     * @param int $user_id User ID
     */
    public function handle_admin_user_update($user_id)
    {
        _log('handle_admin_user_update');
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
        _log('handle_guest_checkout');
        // Check if this is a guest order
        if (!$order->get_user_id()) {
            $customer_data = [
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'source' => 'WordPress'
            ];

            $response = $this->create_truebeep_customer($customer_data);

            if (!is_wp_error($response) && $response['success']) {
                // Store Truebeep customer ID with the order
                $order->update_meta_data('_truebeep_customer_id', $response['data']['id']);
                $order->save();

                // Log success
                $this->log_api_activity('Guest customer created in Truebeep', $response['data']['id']);
            } else {
                // Log error
                $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                $this->log_api_activity('Failed to create guest customer in Truebeep', $error_message, 'error');
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

        _log('create_or_update_truebeep_customer');

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        $customer_data = [
            'firstName' => get_user_meta($user_id, 'first_name', true) ?: $user->display_name,
            'lastName' => get_user_meta($user_id, 'last_name', true),
            'email' => $user->user_email,
            'source' => $source
        ];

        $billing_phone = get_user_meta($user_id, 'billing_phone', true);
        if ($billing_phone) {
            $customer_data['phone'] = $billing_phone;
        }

        _log($customer_data);

        $response = !empty($truebeep_customer_id)
            ? $this->update_truebeep_customer($truebeep_customer_id, $customer_data)
            : $this->create_truebeep_customer($customer_data);

        _log($response);

        if (!is_wp_error($response) && $response['success']) {
            $response_data = !empty($response['data']['data']) ? $response['data']['data'] : $response['data'];
            if (empty($truebeep_customer_id) && isset($response_data['id'])) {
                _log('User created in Truebeep');
                update_user_meta($user_id, '_truebeep_customer_id', $response_data['id']);
                update_user_meta($user_id, '_truebeep_sync_status', 'synced');
                update_user_meta($user_id, '_truebeep_last_sync', current_time('mysql'));
                $this->log_api_activity("User created in Truebeep", $user_id);
            }
        } else {
            update_user_meta($user_id, '_truebeep_sync_status', 'error');
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            update_user_meta($user_id, '_truebeep_sync_error', $error_message);
            $this->log_api_activity("Failed to create or update user in Truebeep", $error_message, 'error');
        }
    }

    /**
     * Handle user deletion
     *
     * @param int $user_id User ID
     */
    public function handle_user_deletion($user_id)
    {
        _log('handle_user_deletion');
        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);

        if (!empty($truebeep_customer_id)) {
            $response = $this->delete_truebeep_customer($truebeep_customer_id);

            if (!is_wp_error($response) && $response['success']) {
                $this->log_api_activity('User deleted from Truebeep', $user_id);
            } else {
                $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
                $this->log_api_activity('Failed to delete user from Truebeep', $error_message, 'error');
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
            error_log('[Truebeep API] [' . $level . '] ' . $message . ($data ? ' - Data: ' . print_r($data, true) : ''));
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
                <p><?php _e('Truebeep API credentials are not configured. Please configure them in WooCommerce > Settings > Truebeep.', 'truebeep'); ?></p>
            </div>
<?php
        }
    }

    /**
     * AJAX handler for manual user sync
     */
    public function ajax_sync_user()
    {
        check_ajax_referer('truebeep_sync_user', 'nonce');

        if (!current_user_can('edit_users')) {
            wp_send_json_error(['message' => __('Permission denied', 'truebeep')]);
        }

        $user_id = intval($_POST['user_id']);
        $this->create_or_update_truebeep_customer($user_id, 'manual_sync');

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
        check_ajax_referer('truebeep_remove_sync', 'nonce');

        if (!current_user_can('edit_users')) {
            wp_send_json_error(['message' => __('Permission denied', 'truebeep')]);
        }

        $user_id = intval($_POST['user_id']);

        delete_user_meta($user_id, '_truebeep_customer_id');
        delete_user_meta($user_id, '_truebeep_sync_status');
        delete_user_meta($user_id, '_truebeep_last_sync');
        delete_user_meta($user_id, '_truebeep_sync_error');

        wp_send_json_success(['message' => __('Truebeep link removed', 'truebeep')]);
    }
}
