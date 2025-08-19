<?php

namespace Truebeep;

class Admin
{
    /**
     * Class initialize test 123
     */
    function __construct()
    {
        new Admin\Menu();
        new Admin\Handler();
        new Admin\CMB2();
        new Admin\UserHandler();
        new Admin\NetworkDiagnostics();
    }
}
