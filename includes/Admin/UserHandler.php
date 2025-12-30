<?php

namespace Truebeep\Admin;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UserHandler
{
    /**
     * Class initialize
     */
    function __construct()
    {
        add_action('show_user_profile', [$this, 'add_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_user_profile_fields']);
        add_action('personal_options_update', [$this, 'save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);
        
        // Add custom column to users list
        add_filter('manage_users_columns', [$this, 'add_truebeep_synced_column']);
        add_filter('manage_users_custom_column', [$this, 'show_truebeep_synced_column_content'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets($hook)
    {
        // Load styles on users list page
        if ('users' === $hook) {
            wp_enqueue_style(
                'truebeep-user-columns',
                TRUEBEEP_URL . '/assets/css/admin/user-columns.css',
                [],
                TRUEBEEP_VERSION
            );
        }
        
        // Load scripts on user profile/edit pages
        if (in_array($hook, ['user-edit.php', 'profile.php'], true)) {
            wp_enqueue_script(
                'truebeep-smwl-user-profile-fields',
                TRUEBEEP_URL . '/assets/js/admin/user-profile-fields.js',
                ['jquery'],
                TRUEBEEP_VERSION,
                true
            );
            
            wp_localize_script('truebeep-smwl-user-profile-fields', 'truebeep_smwl_user_profile', [
                'nonceSync' => wp_create_nonce('truebeep_smwl_sync_user'),
                'nonceRemove' => wp_create_nonce('truebeep_smwl_remove_sync'),
                'strings' => [
                    'syncing' => __('Syncing...', 'truebeep'),
                    'syncFailed' => __('Sync failed', 'truebeep'),
                    'syncWithTruebeep' => __('Sync with Truebeep', 'truebeep'),
                    'confirmRemove' => __('Are you sure you want to remove the Truebeep link?', 'truebeep'),
                ]
            ]);
        }
    }

    /**
     * Add custom fields to user profile
     *
     * @param object $user User object
     */
    public function add_user_profile_fields($user)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ob_start();

        $template = __DIR__ . '/views/user-profile-fields.php';
        if (file_exists($template)) {
            include $template;
        }

        echo wp_kses_post(ob_get_clean());
    }

    /**
     * Save user profile fields
     *
     * @param int $user_id User ID
     */
    public function save_user_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        // Fields are read-only, managed through API sync
    }
    
    /**
     * Add Truebeep Synced column to users list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_truebeep_synced_column($columns)
    {
        $columns['truebeep_synced'] = __('Truebeep Synced', 'truebeep');
        return $columns;
    }
    
    /**
     * Display content for Truebeep Synced column
     *
     * @param string $value Column value
     * @param string $column_name Column name
     * @param int $user_id User ID
     * @return string Column content
     */
    public function show_truebeep_synced_column_content($value, $column_name, $user_id)
    {
        if ('truebeep_synced' === $column_name) {
            $truebeep_customer_id = get_user_meta($user_id, '_truebeep_customer_id', true);
            
            if (!empty($truebeep_customer_id)) {
                // User is synced - show checkmark icon
                $value = '<span class="truebeep-sync-status synced" title="' . esc_attr__('Synced with Truebeep', 'truebeep') . '">';
                $value .= '<span class="dashicons dashicons-yes-alt"></span>';
                $value .= '<span class="screen-reader-text">' . __('Synced', 'truebeep') . '</span>';
                $value .= '</span>';
            } else {
                // User is not synced - show X icon
                $value = '<span class="truebeep-sync-status not-synced" title="' . esc_attr__('Not synced with Truebeep', 'truebeep') . '">';
                $value .= '<span class="dashicons dashicons-dismiss"></span>';
                $value .= '<span class="screen-reader-text">' . __('Not synced', 'truebeep') . '</span>';
                $value .= '</span>';
            }
        }
        
        return $value;
    }
    
}
