<?php

namespace Truebeep\Customizer;

use Kirki;

if (!defined('ABSPATH')) {
    exit;
}

class InitCustomizer
{
    public function __construct()
    {
        $this->init_panel();
    }

    /**
     * Init panel
     *
     * @return void
     */
    public function init_panel()
    {
        Kirki::add_config('truebeep_config', [
            'capability'  => 'edit_theme_options',
            'option_type' => 'theme_mod',
        ]);

        Kirki::add_panel('truebeep_config_panel', [
            'priority'    => 10,
            'title'       => esc_html__('Truebeep Options', 'truebeep'),
            'description' => esc_html__('Truebeep Options description', 'truebeep'),
        ]);
    }
}
