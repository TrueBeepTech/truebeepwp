<?php

namespace Truebeep;

class Admin
{
    /**
     * Class initialize test 123
     */
    function __construct()
    {
        new Admin\UserHandler();
        new Admin\NetworkDiagnostics();
    }
}
