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
            new Customizer\InitCustomizer();
            new Customizer\GeneralSettings();
            new Customizer\HeaderSettings();
        }
    }
}
