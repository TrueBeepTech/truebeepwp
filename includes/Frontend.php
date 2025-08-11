<?php

namespace Truebeep; 

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
    }
}
