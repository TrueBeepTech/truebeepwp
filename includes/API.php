<?php

namespace ShazaboManager;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Ajax class
 */
class API
{

    /**
     * Initialize ajax class
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'outlet_cars_api_init']);
    }

    public function outlet_cars_api_init()
    {
        register_rest_route('truebeep/v1', '/test', array(
            'methods' => 'GET',
            'callback' => [$this, 'truebeep_test'],
        ));
    }

    public function truebeep_test(WP_REST_Request $request)
    {
        $response = new WP_REST_Response([
            'message' => 'Hello World',
        ]);

        return $response;
    }
}
