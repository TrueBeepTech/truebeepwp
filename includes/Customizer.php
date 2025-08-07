<?php

namespace Truebeep;

/**
 * Frontend class
 */
class Customizer
{
    /**
     * Initialize class
     */
    public function __construct()
    {
        if (class_exists('Kirki')) {
            new Customizer\Init_Customizer();
            new Customizer\General_Settings();
            new Customizer\Header_Settings();
        }
    }
}
