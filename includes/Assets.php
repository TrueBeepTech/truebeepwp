<?php

namespace Truebeep;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Assets class handler
 */
class Assets
{
    /**
     * Initialize assets
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);
    }

    /**
     * ShazaboManager scripts
     *
     * @return array
     */
    public function get_scripts()
    {
        return [
            'truebeep' => [
                'src'     => TRUEBEEP_ASSETS . '/js/truebeep.js',
                'version' => filemtime(TRUEBEEP_PATH . '/assets/js/truebeep.js'),
                'deps'    => ['jquery']
            ],
            'truebeep-smwl-loyalty-panel' => [
                'src'     => TRUEBEEP_ASSETS . '/js/frontend/loyalty-panel.js',
                'version' => filemtime(TRUEBEEP_PATH . '/assets/js/frontend/loyalty-panel.js'),
                'deps'    => ['jquery']
            ]
        ];
    }

    /**
     * ShazaboManager styles
     *
     * @return array
     */
    public function get_styles()
    {
        return [
            'truebeep' => [
                'src'     => TRUEBEEP_ASSETS . '/css/truebeep.css',
                'version' => filemtime(TRUEBEEP_PATH . '/assets/css/truebeep.css'),
            ],
            'truebeep-smwl-loyalty-panel' => [
                'src'     => TRUEBEEP_ASSETS . '/css/frontend/loyalty-panel.css',
                'version' => filemtime(TRUEBEEP_PATH . '/assets/css/frontend/loyalty-panel.css'),
            ]
        ];
    }

    /**
     * Register assets
     */
    public function register_assets()
    {
        $scripts = $this->get_scripts();
        foreach ($scripts as $handle => $script) {
            $deps = isset($script['deps']) ? $script['deps'] : false;
            $version = isset($script['version']) ? $script['version'] : TRUEBEEP_VERSION;

            wp_register_script($handle, $script['src'], $deps, $version, true);
        }

        $styles = $this->get_styles();
        foreach ($styles as $handle => $style) {
            $deps = isset($style['deps']) ? $style['deps'] : false;
            $version = isset($style['version']) ? $style['version'] : TRUEBEEP_VERSION;

            wp_register_style($handle, $style['src'], $deps, $version);
        }
    }
    
    /**
     * Register admin assets
     */
    public function register_admin_assets($hook)
    {
        // Admin assets can be added here if needed
    }
}
