<?php

namespace Truebeep\Customizer;

use Kirki;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class HeaderSettings
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->initHeaderSettings();
        $this->HeaderSettings();
    }

    /**
     * initHeaderSettings
     *
     * @return void
     */
    public function initHeaderSettings()
    {
        Kirki::add_section('truebeep_header_section', [
            'title'       => esc_html__('Header', 'truebeep'),
            'description' => esc_html__('Global settings for header located here', 'truebeep'),
            'panel'       => 'truebeep_config_panel',
            'priority'    => 160,
        ]);
    }

    /**
     * HeaderSettings
     *
     * @return void
     */
    public function HeaderSettings()
    { // section choosing key : chawkbazar_header_section

        Kirki::add_field('GON_config', [
            'type'        => 'image',
            'settings'    => 'GON_header_logo',
            'label'       => esc_html__('Main Logo', 'truebeep'),
            'section'     => 'truebeep_header_section',
            'default'     => '',
        ]);
    }
}
