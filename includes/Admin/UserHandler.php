<?php

namespace Truebeep\Admin;

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

        echo ob_get_clean();
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
}
