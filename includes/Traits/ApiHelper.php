<?php

namespace Truebeep\Traits;

trait ApiHelper
{
    /**
     * Get API URL from WooCommerce settings
     *
     * @return string
     */
    protected function get_api_url()
    {
        return get_option('truebeep_api_url', '');
    }

    /**
     * Get API Key from WooCommerce settings
     *
     * @return string
     */
    protected function get_api_key()
    {
        return get_option('truebeep_api_key', '');
    }

    /**
     * Make API request
     *
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @param array $additional_headers
     * @return array|WP_Error
     */
    protected function make_api_request($endpoint, $method = 'GET', $data = [], $additional_headers = [])
    {
        $api_url = $this->get_api_url();
        $api_key = $this->get_api_key();

        if (empty($api_url) || empty($api_key)) {
            return new \WP_Error('missing_credentials', __('API URL or API Key is not configured', 'truebeep'));
        }

        $url = rtrim($api_url, '/') . '/' . ltrim($endpoint, '/');

        $headers = array_merge([
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ], $additional_headers);

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'data' => $response_data,
                'code' => $response_code,
            ];
        } else {
            return [
                'success' => false,
                'error' => $response_data['message'] ?? __('API request failed', 'truebeep'),
                'data' => $response_data,
                'code' => $response_code,
            ];
        }
    }

    /**
     * Create Truebeep customer
     *
     * @param array $customer_data
     * @return array|WP_Error
     */
    public function create_truebeep_customer($customer_data)
    {
        $required_fields = ['firstName'];

        foreach ($required_fields as $field) {
            if (empty($customer_data[$field])) {
                return new \WP_Error('missing_field', sprintf(__('Required field %s is missing', 'truebeep'), $field));
            }
        }

        $formatted_data = [
            'firstName' => sanitize_text_field($customer_data['firstName']),
        ];

        if (!empty($customer_data['lastName'])) {
            $formatted_data['lastName'] = sanitize_text_field($customer_data['lastName']);
        }

        if (!empty($customer_data['email'])) {
            $formatted_data['email'] = sanitize_email($customer_data['email']);
        }

        if (!empty($customer_data['phone'])) {
            $formatted_data['phone'] = sanitize_text_field($customer_data['phone']);
        }

        if (!empty($customer_data['source'])) {
            $formatted_data['source'] = sanitize_text_field($customer_data['source']);
        }

        return $this->make_api_request('customer', 'POST', $formatted_data);
    }

    /**
     * Get Truebeep customer by ID
     *
     * @param string $customer_id
     * @return array|WP_Error
     */
    public function get_truebeep_customer($customer_id)
    {
        if (empty($customer_id)) {
            return new \WP_Error('missing_id', __('Customer ID is required', 'truebeep'));
        }

        return $this->make_api_request('customer/' . $customer_id, 'GET');
    }

    /**
     * Update Truebeep customer
     *
     * @param string $customer_id
     * @param array $customer_data
     * @return array|WP_Error
     */
    public function update_truebeep_customer($customer_id, $customer_data)
    {
        if (empty($customer_id)) {
            return new \WP_Error('missing_id', __('Customer ID is required', 'truebeep'));
        }

        $formatted_data = [];

        if (isset($customer_data['firstName'])) {
            $formatted_data['firstName'] = sanitize_text_field($customer_data['firstName']);
        }

        if (isset($customer_data['lastName'])) {
            $formatted_data['lastName'] = sanitize_text_field($customer_data['lastName']);
        }

        if (isset($customer_data['email'])) {
            $formatted_data['email'] = sanitize_email($customer_data['email']);
        }

        if (isset($customer_data['phone'])) {
            $formatted_data['phone'] = sanitize_text_field($customer_data['phone']);
        }

        if (isset($customer_data['source'])) {
            $formatted_data['source'] = sanitize_text_field($customer_data['source']);
        }

        return $this->make_api_request('customer/' . $customer_id, 'PUT', $formatted_data);
    }

    /**
     * Delete Truebeep customer
     *
     * @param string $customer_id
     * @return array|WP_Error
     */
    public function delete_truebeep_customer($customer_id)
    {
        if (empty($customer_id)) {
            return new \WP_Error('missing_id', __('Customer ID is required', 'truebeep'));
        }

        return $this->make_api_request('customer/' . $customer_id, 'DELETE');
    }

    /**
     * List Truebeep customers
     *
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public function list_truebeep_customers($params = [])
    {
        $query_string = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->make_api_request('customer' . $query_string, 'GET');
    }

    /**
     * Update customer loyalty points
     *
     * @param string $customer_id Truebeep customer ID
     * @param float $points Points to add/subtract
     * @param string $type 'increment' or 'decrement'
     * @param string $channel Channel source (default: 'woocommerce')
     * @return array|WP_Error
     */
    public function update_loyalty_points($customer_id, $points, $type = 'increment', $channel = 'woocommerce')
    {
        if (empty($customer_id)) {
            return new \WP_Error('missing_id', __('Customer ID is required', 'truebeep'));
        }

        if (!in_array($type, ['increment', 'decrement'])) {
            return new \WP_Error('invalid_type', __('Type must be either increment or decrement', 'truebeep'));
        }

        $payload = [
            'points' => floatval($points),
            'type' => $type,
            'channel' => $channel
        ];

        return $this->make_api_request('customer/' . $customer_id . '/loyalty', 'POST', $payload);
    }

    /**
     * Get customer tier information based on total earned points
     *
     * @param string $customer_id Truebeep customer ID
     * @return array Contains tier_tag, tier_name, and full_tier
     */
    public function get_customer_tier($customer_id)
    {
        if (empty($customer_id)) {
            return [
                'tier_tag' => false,
                'tier_name' => '',
                'full_tier' => null
            ];
        }

        $customer = $this->get_customer_points($customer_id);
        if (empty($customer)) {
            return [
                'tier_tag' => false,
                'tier_name' => '',
                'full_tier' => null
            ];
        }

        $total_earned_points = isset($customer['totalEarnedPoints']) ? floatval($customer['totalEarnedPoints']) : 0;
        $tiers = get_option('truebeep_tiers', []);

        if (empty($tiers)) {
            return [
                'tier_tag' => false,
                'tier_name' => '',
                'full_tier' => null
            ];
        }

        usort($tiers, function ($a, $b) {
            return floatval($b['threshold']) - floatval($a['threshold']);
        });

        $tier_name = '';
        $full_tier = null;

        foreach ($tiers as $tier) {
            if ($total_earned_points >= floatval($tier['threshold'])) {
                $tier_name = strtolower($tier['name']);
                $full_tier = $tier;
                break;
            }
        }

        return [
            'tier_tag' => !empty($tier_name),
            'tier_name' => $tier_name,
            'full_tier' => $full_tier
        ];
    }

    /**
     * Calculate loyalty points based on order and settings
     *
     * @param float $order_total Order total amount
     * @param int $user_id WordPress user ID (optional)
     * @return float Calculated points
     */
    public function calculate_loyalty_points($order_total, $user_id = null)
    {
        $default_earning_value = floatval(get_option('truebeep_earning_value', 1));
        if (!$user_id) {
            return $order_total * $default_earning_value;
        }

        $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
        if (empty($truebeep_customer_id)) {
            return $order_total * $default_earning_value;
        }

        $tier_info = $this->get_customer_tier($truebeep_customer_id);
        if ($tier_info['tier_tag'] && $tier_info['full_tier']) {
            $tier_earning_value = floatval($tier_info['full_tier']['order_to_points']);
            return $order_total * $tier_earning_value;
        }

        return $order_total * $default_earning_value;
    }

    /**
     * Get customer's total earned points
     *
     * @param string $customer_id Truebeep customer ID
     * @return float Total earned points
     */
    public function get_customer_total_earned_points($customer_id)
    {
        if (empty($customer_id)) {
            return 0;
        }

        $customer = $this->get_customer_points($customer_id);
        if (empty($customer)) {
            return 0;
        }

        return isset($customer['totalEarnedPoints']) ? floatval($customer['totalEarnedPoints']) : 0;
    }

    /**
     * Get customer's complete data including points and tier from API
     *
     * @param string $customer_id Truebeep customer ID
     * @return array Customer data including points, totalEarnedPoints, totalSpentPoints
     */
    public function get_customer_points($customer_id)
    {
        if (empty($customer_id)) {
            return [];
        }

        $response = $this->make_api_request('customer/' . $customer_id, 'GET');
        if (is_wp_error($response) || !$response['success']) {
            return [];
        }

        $customer = isset($response['data']['data']) ? $response['data']['data'] : $response['data'];
        return $customer;
    }

    /**
     * Get customer's current balance only
     *
     * @param string $customer_id Truebeep customer ID
     * @return float Current balance points
     */
    public function get_customer_balance($customer_id)
    {
        $customer = $this->get_customer_points($customer_id);
        return !empty($customer['points']) ? floatval($customer['points']) : 0;
    }

    /**
     * Check if points should be earned on redeemed orders
     *
     * @return bool
     */
    public function should_earn_on_redeemed_orders()
    {
        return get_option('truebeep_earn_on_redeemed', 'no') === 'yes';
    }

    /**
     * Test API connection
     *
     * @return array|WP_Error
     */
    public function test_api_connection()
    {
        $response = $this->make_api_request('health', 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return [
                'success' => true,
                'message' => __('API connection successful', 'truebeep'),
            ];
        } else {
            return [
                'success' => false,
                'message' => $response['error'] ?? __('API connection failed', 'truebeep'),
            ];
        }
    }
}
