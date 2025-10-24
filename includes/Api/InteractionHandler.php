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
        
        // Hooks for refund/cancellation interactions
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_refund_interaction'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'handle_refund_interaction'], 10, 1);
        add_action('woocommerce_order_partially_refunded', [$this, 'handle_partial_refund_interaction'], 10, 2);
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
            $order->add_order_note(sprintf(
                /* translators: %s: error message */
                __('Failed to send order interaction to Truebeep: %s', 'truebeep'), 
                $error_message
            ));
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
     * Handle refund/cancellation interaction
     *
     * @param int $order_id Order ID
     */
    public function handle_refund_interaction($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get customer Truebeep ID
        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            $order->add_order_note(__('Refund interaction not sent to Truebeep: Customer ID not found', 'truebeep'));
            return;
        }

        // Check if we already sent a refund interaction for this order
        $refund_interaction_sent = $order->get_meta('_truebeep_refund_interaction_sent');
        if ($refund_interaction_sent === 'yes') {
            return;
        }

        // Build refund interaction data
        $interaction_data = $this->prepare_refund_interaction($order);

        // Send interaction to API
        $response = $this->send_customer_interaction($customer_id, 'refund', $interaction_data);
        
        if (!is_wp_error($response) && $response['success']) {
            $order->update_meta_data('_truebeep_refund_interaction_sent', 'yes');
            $order->update_meta_data('_truebeep_refund_interaction_date', current_time('mysql'));
            $order->save();
            
            $order->add_order_note(__('Refund interaction successfully sent to Truebeep', 'truebeep'));
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(
                /* translators: %s: error message */
                __('Failed to send refund interaction to Truebeep: %s', 'truebeep'), 
                $error_message
            ));
        }
    }

    /**
     * Handle partial refund interaction
     *
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     */
    public function handle_partial_refund_interaction($order_id, $refund_id)
    {
        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);
        
        if (!$order || !$refund) {
            return;
        }

        // Get customer Truebeep ID
        $customer_id = $this->get_customer_truebeep_id($order);
        if (!$customer_id) {
            $order->add_order_note(__('Partial refund interaction not sent to Truebeep: Customer ID not found', 'truebeep'));
            return;
        }

        // Build partial refund interaction data
        $interaction_data = $this->prepare_partial_refund_interaction($order, $refund);

        // Send interaction to API
        $response = $this->send_customer_interaction($customer_id, 'refund', $interaction_data);
        
        if (!is_wp_error($response) && $response['success']) {
            // Track partial refund interactions
            $partial_refunds = $order->get_meta('_truebeep_partial_refund_interactions');
            if (!is_array($partial_refunds)) {
                $partial_refunds = [];
            }
            $partial_refunds[] = [
                'refund_id' => $refund_id,
                'date' => current_time('mysql'),
                'amount' => abs($refund->get_total())
            ];
            $order->update_meta_data('_truebeep_partial_refund_interactions', $partial_refunds);
            $order->save();
            
            $order->add_order_note(sprintf(
                /* translators: %s: refund ID */
                __('Partial refund interaction sent to Truebeep (Refund #%s)', 'truebeep'),
                $refund_id
            ));
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : $response['error'];
            $order->add_order_note(sprintf(
                /* translators: %s: error message */
                __('Failed to send partial refund interaction to Truebeep: %s', 'truebeep'), 
                $error_message
            ));
        }
    }

    /**
     * Prepare refund interaction data with both full payload and structured payload
     *
     * @param WC_Order $order
     * @return array
     */
    private function prepare_refund_interaction($order)
    {
        // Build the full payload with refund-specific data
        $full_payload = $this->build_refund_payload($order);
        
        // Build the structured minimal payload
        $structured_payload = $this->build_structured_refund_payload($order);
        
        return [
            'payload' => $full_payload,
            'structuredPayload' => $structured_payload
        ];
    }

    /**
     * Prepare partial refund interaction data
     *
     * @param WC_Order $order
     * @param WC_Order_Refund $refund
     * @return array
     */
    private function prepare_partial_refund_interaction($order, $refund)
    {
        // Build the full payload with partial refund data
        $full_payload = $this->build_partial_refund_payload($order, $refund);
        
        // Build the structured minimal payload
        $structured_payload = $this->build_structured_partial_refund_payload($order, $refund);
        
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