<?php

namespace Truebeep;

class Admin
{
    /**
     * Class initialize
     */
    function __construct()
    {
        new Admin\Menu();
        new Admin\Handler();
        new Admin\CMB2();
        new Admin\TestBgJob();
        new Admin\UserHandler();
        new Admin\NetworkDiagnostics();
    }
}
