<?php

namespace Truebeep;

use Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load elementor class
 */
class LoadElementor
{
    /**
     * Init elementor class
     *
     * @since 1.0.0
     * @return null
     */
    public function __construct()
    {
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'custom_elementor_scripts']);
    }


    /**
     * custom_elementor_scripts
     * 
     * @since 1.0.0
     */
    public function custom_elementor_scripts()
    {
        $scripts     = $this->get_scripts();

        foreach ($scripts as $handle => $script) {
            $deps    = isset($script['deps']) ? $script['deps'] : false;
            $version = isset($script['version']) ? $script['version'] : TRUEBEEP_VERSION;
            wp_register_script($handle, $script['src'], $deps, $version, true);
            wp_enqueue_script($handle);
        }
    }

    /**
     * Register elementor category
     *
     * @param object $elementor
     *
     * @since 1.0.0
     * @return object
     */
    public function register_category($elementor)
    {
        $elementor->add_category(
            'gon-helper-widgets',
            [
                'title' =>  __('Truebeep Widgets', 'truebeep'),
                'icon'  => 'eicon-font',
            ]
        );

        return $elementor;
    }

    /**
     * Register elementor widgets
     *
     * @since 1.0.0
     * @return void
     */
    public function register_widgets()
    {
        $this->includeWidgetsFiles();

        Plugin::instance()->widgets_manager->register(new Elementor\HelloWorld());
    }

    /**
     * Widget Scripts
     *
     * @since 1.0.0
     * @return array
     */
    public static function getWidgetScript()
    {
        return [];
    }

    /**
     * Lily scripts
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
        ];
    }

    /**
     * CHH Elementor styles
     *
     * @since 1.0.0
     * @return array
     */
    public function getStyles()
    {
        return [

            'truebeep' => [
                'src'     => TRUEBEEP_ASSETS . '/css/truebeep.css',
                'version' => filemtime(TRUEBEEP_PATH . '/assets/css/truebeep.css'),
            ]
        ];
    }

    /**
     * Widget list
     *
     * @since 1.0.0
     * @return array
     */
    public static function getWidgetList()
    {
        return [
            'HelloWorld',
        ];
    }

    /**
     * Widget files
     *
     * @since 1.0.0
     * @return void
     */
    public function includeWidgetsFiles()
    {
        $scripts     = $this->get_scripts();
        $styles      = $this->getStyles();
        $widget_list = $this->getWidgetList();

        if (!count($widget_list)) {
            return;
        }

        foreach ($widget_list as $handle => $widget) {
            $file = TRUEBEEP_ELEMENTOR . $widget . '.php';
            if (file_exists($file)) {
                continue;
            }
            require_once $file;
        }

        foreach ($scripts as $handle => $script) {
            $deps    = isset($script['deps']) ? $script['deps'] : false;
            $version = isset($script['version']) ? $script['version'] : TRUEBEEP_VERSION;
            wp_register_script($handle, $script['src'], $deps, $version, true);
            // wp_enqueue_script($handle);
        }

        foreach ($styles as $handle => $style) {
            $deps = isset($style['deps']) ? $style['deps'] : false;
            $version = isset($style['version']) ? $style['version'] : TRUEBEEP_VERSION;
            wp_register_style($handle, $style['src'], $deps, $version);
            // wp_enqueue_style($handle);
        }
    }
}
