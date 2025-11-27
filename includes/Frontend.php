<?php

namespace Truebeep;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

/**
 * Frontend class
 */
class Frontend
{
    /**
     * Initialize class
     */
    public function __construct()
    {
        new Frontend\Shortcode();
        new Frontend\LoyaltyPanel();
        new Frontend\MyAccountLoyalty();
    }
}
