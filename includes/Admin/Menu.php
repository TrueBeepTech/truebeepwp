<?php

namespace Truebeep\Admin;

/**
 * Admin menu class
 */
class Menu
{
    /**
     * Initialize menu
     */
    function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    /**
     * Handle plugin menu
     *
     * @return void
     */
    public function admin_menu()
    {
        $parent_slug = 'truebeep-dashboard';
        $capability = 'manage_options';

        add_menu_page(__('Truebeep Dashboard', 'truebeep'), __('Truebeep', 'truebeep'), $capability, $parent_slug, [$this, 'dashboard_page'], 'dashicons-buddicons-groups');
            add_submenu_page($parent_slug, __('Settings', 'truebeep'), __('Settings', 'truebeep'), $capability, $parent_slug, [$this, 'dashboard_page']);
        add_submenu_page($parent_slug, __('Report', 'truebeep'), __('Report', 'truebeep'), $capability, 'truebeep-report', [$this, 'report_page']);
        add_submenu_page($parent_slug, __('Test Import', 'truebeep'), __('Test Import', 'truebeep'), $capability, 'truebeep-test-import', [$this, 'test_import_page']);
    }

    /**
     * Handle menu page
     *
     * @return void
     */
    public function dashboard_page()
    {
        $settings = new Settings();
        $settings->settings_page();
    }

    /**
     * ShazaboManager report page
     *
     * @return void
     */
    public function report_page()
    {
        $settings = new Settings();
        $settings->report_page();
    }

    /**
     * ShazaboManager test import page
     *
     * @return void
     */
    public function test_import_page()
    {
        $test_import = new TestImport();
        $test_import->handle_import();
    }
}
