<?php

namespace Truebeep\Customizer;

use Kirki;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class General_Settings
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->init_general_settings();
        $this->general_settings();
    }

    /**
     * init_general_settings
     *
     * @return void
     */
    public function init_general_settings()
    {
        Kirki::add_section('truebeep_general_section', [
            'title'       => esc_html__('General', 'truebeep'),
            'description' => esc_html__('General theme settings', 'truebeep'),
            'panel'       => 'truebeep_config_panel',
            'priority'    => 160,
        ]);
    }

    /**
     * general_settings
     *
     * @return void
     */
    public function general_settings()
    {
        // section choosing key : shazabo_manager_general_section
        Kirki::add_field('shazabo_manager_config', [
            'type'        => 'select',
            'settings'    => 'site_loader',
            'label'       => esc_html__('Site loader', 'truebeep'),
            'description' => esc_html__('Choose either site loader is On/Off through out the site', 'truebeep'),
            'section'     => 'truebeep_general_section',
            'default'     => 'off',
            'priority'    => 10,
            'multiple'    => 1,
            'choices'     => [
                'on' => esc_html__('On', 'shazabo-manager'),
                'off' => esc_html__('Off', 'shazabo-manager'),
            ],
        ]);
    }
}
