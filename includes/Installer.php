<?php

namespace Truebeep;

class Installer
{
    /**
     * Initialize class functions
     *
     * @return void
     */
    public function run()
    {
        $this->add_version();
        // $this->create_tables();
    }

    /**
     * Store plugin information
     *
     * @return void
     */
    public function add_version()
    {
        $installed = get_option('truebeep_installed');

        if (!$installed) {
            update_option('truebeep_installed', time());
        }

        update_option('truebeep_version', TRUEBEEP_VERSION);
    }

    /**
     * Create custom tables
     *
     * @return void
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $checkout_scheme = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}truebeep_boilerplate` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(250) DEFAULT NULL,
            `value` varchar(250) DEFAULT NULL,
            `create_at` datetime NOT NULL,  
            PRIMARY KEY (`id`)
          ) $charset_collate";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($truebeep_scheme);
    }
}
