<?php

namespace Truebeep\Api;

use Truebeep\Traits\ApiHelper;

/**
 * Interaction Handler class - Manages customer interactions with Truebeep API
 */
class InteractionHandler
{
    use ApiHelper;

    /**
     * Initialize interaction handler
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WooCommerce hooks for interactions
     */
    private function init_hooks()
    {
        // Hook for order interactions
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_order_interaction'], 15, 3);
    }

    /**
     * Handle order interaction when order is placed
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param object $order Order object
     */
    public function handle_order_interaction($order_id, $posted_data, $order)
    {
        // Get customer Truebeep ID
        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            $order->add_order_note(__('Order interaction not sent to Truebeep: Customer ID not found', 'truebeep'));
            return;
        }

        // Build order interaction data
        $interaction_data = $this->prepare_order_interaction($order);

        // Send interaction to API
        $response = $this->send_customer_interaction($customer_id, 'order', $interaction_data);
        
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
     * Prepare order interaction data with both full payload and structured payload
     *
     * @param WC_Order $order
     * @return array
     */
    private function prepare_order_interaction($order)
    {
        // Build the full payload
        $full_payload = $this->build_order_payload($order);
        
        // Build the structured minimal payload
        $structured_payload = $this->build_structured_order_payload($order);
        
        return [
            'payload' => $full_payload,
            'structuredPayload' => $structured_payload
        ];
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
}