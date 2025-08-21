<?php

namespace Truebeep\Admin;

use Truebeep\Traits\ApiHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper class for managing connection status
 */
class ConnectionHelper
{
    use ApiHelper;
    
    /**
     * Send connection status to TrueBeep
     *
     * @param string $status 'connected' or 'disconnected'
     * @return array
     */
    public function send_connection_status($status)
    {
        $api_url = $this->get_api_url();
        $api_key = $this->get_api_key();
        
        if (empty($api_url) || empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API credentials not configured', 'truebeep')
            ];
        }
        
        $response = $this->make_api_request('connection-status', 'POST', [
            'type' => 'wordpress',
            'status' => $status
        ]);
        
        if (!is_wp_error($response) && $response['success']) {
            return [
                'success' => true,
                'message' => sprintf(__('Connection status updated to %s', 'truebeep'), $status)
            ];
        }
        
        return [
            'success' => false,
            'message' => is_wp_error($response) ? $response->get_error_message() : ($response['error'] ?? __('Failed to update connection status', 'truebeep'))
        ];
    }
}