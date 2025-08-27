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
        
        // Initialize import functionality
        new Legacy\ImportSettings();
        Legacy\LegacyIntegration::init();
        
        // Initialize debug page (only in admin)
        if (is_admin()) {
            new Admin\ImportDebugPage();
        }
    }
}
