<?php

namespace Truebeep;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin
{
    /**
     * Class initialize
     */
    function __construct()
    {
        new Admin\UserHandler();
        new Admin\NetworkDiagnostics();
        
        // Initialize sync functionality
        new Legacy\SyncSettings();
        Legacy\LegacyIntegration::init();
    }
}
