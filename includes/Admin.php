<?php

namespace Truebeep;

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
