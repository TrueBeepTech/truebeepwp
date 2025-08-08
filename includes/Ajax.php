<?php

namespace Truebeep;

/**
 * Ajax class
 */
class Ajax
{
    /**
     * Initialize ajax class
     */
    public function __construct()
    {
        add_action('wp_ajax_truebeep_enquiry', [$this, 'truebeep_enquiry']);
        add_action('wp_ajax_nopriv_truebeep_enquiry', [$this, 'truebeep_enquiry']);
    }

    /**
     * Perform enquiry operation
     *
     * @return array
     */
    public function truebeep_enquiry()
    {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'truebeep-enquiry-form')) {
            wp_send_json_error([
                'message' => __('Nonce verification failed!', 'truebeep')
            ]);
        }

        wp_send_json_success([
            'message' => __('Perform your operation', 'truebeep'),
            'data'    => $_REQUEST,
        ]);
    }
}
