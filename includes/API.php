<?php

namespace Truebeep;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * API class - Handles all Truebeep API integrations and WordPress hooks
 */
class API
{
    /**
     * Initialize API class and register hooks
     */
    public function __construct()
    {
        new Api\CustomerHandler();
        new Api\LoyaltyHandler();
        new Api\InteractionHandler();
    }
}
