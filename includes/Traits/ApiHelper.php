<?php

namespace Truebeep\Traits;

use Truebeep\Config\Constants;

trait ApiHelper
{
    /**
     * Get API URL
     */
    protected function get_api_url()
    {
        return Constants::API_URL;
    }

    /**
     * Get API Key
     */
    protected function get_api_key()
    {
        return get_option('truebeep_api_key', '');
    }

    /**
     * Get Wallet Base URL
     */
    protected function get_wallet_base_url()
    {
        return Constants::WALLET_BASE_URL;
    }

    /**
     * Get Wallet ID
     */
    protected function get_wallet_id()
    {
        return get_option('truebeep_wallet_id', '');
    }

    /**
     * Make API request
     */
    protected function make_api_request($endpoint, $method = 'GET', $data = [], $additional_headers = [])
    {
        $api_url = $this->get_api_url();
        $api_key = $this->get_api_key();

        if (empty($api_url) || empty($api_key)) {
            truebeep_log('API credentials missing', 'ApiHelper', ['endpoint' => $endpoint]);
            return new \WP_Error('missing_credentials', __('API URL or API Key is not configured', 'truebeep'));
        }

        $url = rtrim($api_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Log API request
        truebeep_log('API Request: ' . $method . ' ' . $endpoint, 'ApiHelper', $data);

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
            truebeep_log('API Request Error: ' . $response->get_error_message(), 'ApiHelper', ['endpoint' => $endpoint]);
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code >= 200 && $response_code < 300) {
            truebeep_log('API Response: ' . $response_code . ' ' . $endpoint, 'ApiHelper');
            return [
                'success' => true,
                'data' => $response_data,
                'code' => $response_code,
            ];
        } else {
            $error_message = $response_data['message'] ?? __('API request failed', 'truebeep');
            truebeep_log('API Response Error: ' . $response_code . ' ' . $endpoint, 'ApiHelper', ['error' => $error_message]);
            return [
                'success' => false,
                'error' => $error_message,
                'data' => $response_data,
                'code' => $response_code,
            ];
        }
    }

    /**
     * Create customer in Truebeep
     */
    public function create_truebeep_customer($customer_data)
    {
        $required_fields = ['firstName'];

        foreach ($required_fields as $field) {
            if (empty($customer_data[$field])) {
                /* translators: %s: field name */
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
            $customer_data['phone'] = preg_replace('/\s+/', '', $customer_data['phone']);
            if (substr($customer_data['phone'], 0, 1) === "'") {
                $customer_data['phone'] = ltrim($customer_data['phone'], "'");
            }
            $formatted_data['phone'] = sanitize_text_field($customer_data['phone']);
        }

        if (!empty($customer_data['source'])) {
            $formatted_data['source'] = sanitize_text_field($customer_data['source']);
        }
        
        if (!empty($customer_data['metadata'])) {
            $formatted_data['metadata'] = $customer_data['metadata'];
        }
        
        // Add address fields if present
        if (!empty($customer_data['city'])) {
            $formatted_data['city'] = sanitize_text_field($customer_data['city']);
        }
        
        if (!empty($customer_data['state'])) {
            $formatted_data['state'] = sanitize_text_field($customer_data['state']);
        }
        
        if (!empty($customer_data['country'])) {
            $formatted_data['country'] = sanitize_text_field($customer_data['country']);
        }
        
        if (!empty($customer_data['zipCode'])) {
            $formatted_data['zipCode'] = sanitize_text_field($customer_data['zipCode']);
        }

        return $this->make_api_request('customer', 'POST', $formatted_data);
    }

    /**
     * Create multiple customers in bulk
     */
    public function create_truebeep_customers_bulk($customers_data)
    {
        if (empty($customers_data) || !is_array($customers_data)) {
            return new \WP_Error('invalid_data', __('Invalid customer data array', 'truebeep'));
        }

        $formatted_customers = [];
        
        foreach ($customers_data as $index => $customer_data) {
            if (empty($customer_data['firstName'])) {
                continue;
            }

            $formatted_customer = [
                'firstName' => sanitize_text_field($customer_data['firstName']),
            ];

            if (!empty($customer_data['lastName'])) {
                $formatted_customer['lastName'] = sanitize_text_field($customer_data['lastName']);
            }

            if (!empty($customer_data['email'])) {
                $formatted_customer['email'] = sanitize_email($customer_data['email']);
            }

            if (!empty($customer_data['phone'])) {
                $phone = preg_replace('/\s+/', '', $customer_data['phone']);
                if (substr($phone, 0, 1) === "'") {
                    $phone = ltrim($phone, "'");
                }
                $formatted_customer['phone'] = sanitize_text_field($phone);
            }

            if (!empty($customer_data['source'])) {
                $formatted_customer['source'] = sanitize_text_field($customer_data['source']);
            }
            
            if (!empty($customer_data['metadata'])) {
                $formatted_customer['metadata'] = $customer_data['metadata'];
            }
            
            // Add address fields if present
            if (!empty($customer_data['city'])) {
                $formatted_customer['city'] = sanitize_text_field($customer_data['city']);
            }
            
            if (!empty($customer_data['state'])) {
                $formatted_customer['state'] = sanitize_text_field($customer_data['state']);
            }
            
            if (!empty($customer_data['country'])) {
                $formatted_customer['country'] = sanitize_text_field($customer_data['country']);
            }
            
            if (!empty($customer_data['zipCode'])) {
                $formatted_customer['zipCode'] = sanitize_text_field($customer_data['zipCode']);
            }

            if (!empty($customer_data['wordpress_user_id'])) {
                $formatted_customer['wordpress_user_id'] = $customer_data['wordpress_user_id'];
            }

            $formatted_customers[] = $formatted_customer;
        }

        if (empty($formatted_customers)) {
            return new \WP_Error('no_valid_customers', __('No valid customers to sync', 'truebeep'));
        }

        $api_url = $this->get_api_url();
        $api_key = $this->get_api_key();

        if (empty($api_url) || empty($api_key)) {
            return new \WP_Error('missing_credentials', __('API URL or API Key is not configured', 'truebeep'));
        }

        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($formatted_customers),
            'timeout' => 30,
            'sslverify' => true,
        ];

        $response = wp_remote_post(rtrim($api_url, '/') . '/customers', $args);
        
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
                'error' => $response_data['message'] ?? __('Bulk API request failed', 'truebeep'),
                'data' => $response_data,
                'code' => $response_code,
            ];
        }
    }

    /**
     * Get customer by ID
     */
    public function get_truebeep_customer($customer_id)
    {
        if (empty($customer_id)) {
            return new \WP_Error('missing_id', __('Customer ID is required', 'truebeep'));
        }

        return $this->make_api_request('customer/' . $customer_id, 'GET');
    }

    /**
     * Update customer
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
        
        // Add address fields if present
        if (isset($customer_data['city'])) {
            $formatted_data['city'] = sanitize_text_field($customer_data['city']);
        }
        
        if (isset($customer_data['state'])) {
            $formatted_data['state'] = sanitize_text_field($customer_data['state']);
        }
        
        if (isset($customer_data['country'])) {
            $formatted_data['country'] = sanitize_text_field($customer_data['country']);
        }
        
        if (isset($customer_data['zipCode'])) {
            $formatted_data['zipCode'] = sanitize_text_field($customer_data['zipCode']);
        }
        
        if (isset($customer_data['metadata'])) {
            $formatted_data['metadata'] = $customer_data['metadata'];
        }

        return $this->make_api_request('customer/' . $customer_id, 'PUT', $formatted_data);
    }

    /**
     * Delete customer
     */
    public function delete_truebeep_customer($customer_id)
    {
        if (empty($customer_id)) {
            return new \WP_Error('missing_id', __('Customer ID is required', 'truebeep'));
        }

        return $this->make_api_request('customer/' . $customer_id, 'DELETE');
    }

    /**
     * List customers
     */
    public function list_truebeep_customers($params = [])
    {
        $query_string = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->make_api_request('customer' . $query_string, 'GET');
    }

    /**
     * Update loyalty points
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
     * Get customer tier
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
     * Calculate loyalty points
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
     * Get total earned points
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
     * Get customer points data
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
     * Get customer balance
     */
    public function get_customer_balance($customer_id)
    {
        $customer = $this->get_customer_points($customer_id);
        return !empty($customer['points']) ? floatval($customer['points']) : 0;
    }

    /**
     * Check earn on redeemed orders
     */
    public function should_earn_on_redeemed_orders()
    {
        return get_option('truebeep_earn_on_redeemed', 'no') === 'yes';
    }

    /**
     * Test API connection
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

    /**
     * Send customer interaction
     */
    protected function send_customer_interaction($customer_id, $type, $data)
    {
        $api_url = $this->get_api_url();
        if (empty($api_url)) {
            return new \WP_Error('missing_api_url', __('API URL is not configured', 'truebeep'));
        }
        
        // Build the interaction endpoint URL
        $endpoint = sprintf(
            'customer-interactions?subscriber_id=%s&channel=woocommerce&type=%s',
            urlencode($customer_id),
            urlencode($type)
        );
        
        // Send the interaction data
        return $this->make_api_request($endpoint, 'POST', $data);
    }

    /**
     * Build order payload
     */
    protected function build_order_payload($order)
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
     * Build structured order payload
     */
    protected function build_structured_order_payload($order)
    {
        // Build product list with just names and quantities
        $products = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $products[] = [
                    'name' => $product->get_name(),
                    'quantity' => $item->get_quantity(),
                    'price' => $item->get_total()
                ];
            }
        }
        
        // Build minimal structured payload
        return [
            'order' => [
                'id' => $order->get_order_number(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'status' => $order->get_status(),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s')
            ],
            'customer' => [
                'email' => $order->get_billing_email(),
                'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'phone' => $order->get_billing_phone()
            ],
            'products' => $products,
            'summary' => [
                'items_count' => count($order->get_items()),
                'subtotal' => $order->get_subtotal(),
                'shipping' => $order->get_shipping_total(),
                'tax' => $order->get_total_tax(),
                'discount' => $order->get_discount_total()
            ],
            'location' => [
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'country' => $order->get_billing_country()
            ]
        ];
    }

    /**
     * Build refund payload
     */
    protected function build_refund_payload($order)
    {
        // Get the base order payload
        $payload = $this->build_order_payload($order);
        
        // Add refund-specific data
        $payload['refund_reason'] = $order->get_status();
        $payload['refund_date'] = current_time('Y-m-d H:i:s');
        
        // Get customer note if available
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $payload['customer_note'] = $customer_note;
        }
        
        // Get all order notes for additional context
        $order_notes = wc_get_order_notes([
            'order_id' => $order->get_id(),
            'type' => 'customer',
            'limit' => 10
        ]);
        
        if (!empty($order_notes)) {
            $notes = [];
            foreach ($order_notes as $note) {
                $notes[] = [
                    'date' => $note->date_created->format('Y-m-d H:i:s'),
                    'content' => $note->content,
                    'added_by' => $note->added_by
                ];
            }
            $payload['order_notes'] = $notes;
        }
        
        // Add refund status flags
        $payload['is_refunded'] = true;
        $payload['is_cancelled'] = ($order->get_status() === 'cancelled');
        
        return $payload;
    }

    /**
     * Build structured refund payload
     */
    protected function build_structured_refund_payload($order)
    {
        // Get base structured payload
        $payload = $this->build_structured_order_payload($order);
        
        // Add refund-specific structured data
        $payload['refund'] = [
            'status' => $order->get_status(),
            'date' => current_time('Y-m-d H:i:s'),
            'type' => 'full'
        ];
        
        // Add customer note if available
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $payload['refund']['customer_note'] = $customer_note;
        }
        
        return $payload;
    }

    /**
     * Build partial refund payload
     */
    protected function build_partial_refund_payload($order, $refund)
    {
        // Get the base order payload
        $payload = $this->build_order_payload($order);
        
        // Add partial refund specific data
        $payload['partial_refund'] = [
            'refund_id' => $refund->get_id(),
            'refund_amount' => abs($refund->get_total()),
            'refund_reason' => $refund->get_reason(),
            'refund_date' => $refund->get_date_created()->format('Y-m-d H:i:s'),
            'refunded_by' => $refund->get_refunded_by()
        ];
        
        // Get refunded items
        $refunded_items = [];
        foreach ($refund->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $refunded_items[] = [
                    'product_name' => $product->get_name(),
                    'quantity' => abs($item->get_quantity()),
                    'refund_total' => abs($item->get_total())
                ];
            }
        }
        
        if (!empty($refunded_items)) {
            $payload['partial_refund']['refunded_items'] = $refunded_items;
        }
        
        // Add customer note if available
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $payload['customer_note'] = $customer_note;
        }
        
        // Calculate totals after refund
        $total_refunded = $order->get_total_refunded();
        $payload['totals_after_refund'] = [
            'total_refunded' => $total_refunded,
            'remaining_total' => $order->get_total() - $total_refunded
        ];
        
        return $payload;
    }

    /**
     * Build structured partial refund payload
     */
    protected function build_structured_partial_refund_payload($order, $refund)
    {
        // Get base structured payload
        $payload = $this->build_structured_order_payload($order);
        
        // Add partial refund structured data
        $payload['refund'] = [
            'status' => 'partial_refund',
            'date' => $refund->get_date_created()->format('Y-m-d H:i:s'),
            'type' => 'partial',
            'amount' => abs($refund->get_total()),
            'reason' => $refund->get_reason()
        ];
        
        // Add customer note if available
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $payload['refund']['customer_note'] = $customer_note;
        }
        
        return $payload;
    }
}
