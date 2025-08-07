<?php

namespace ShazaboManager\Admin;

/**
 * CMB2 is a developer's toolkit for building metaboxes, custom fields, and forms for WordPress that will blow your mind. 
 * Easily manage meta for posts, terms, users, comments, or create custom option pages.
 * @author Roman <roman.ul.ferdosh@gmail.com>
 * @api https://github.com/CMB2/CMB2
 */
class CMB2_Sample
{
    /**
     * Initialize CMB2
     */
    function __construct()
    {
        add_action('cmb2_admin_init', [$this, 'cmb2_sample_metaboxes']);
        add_action('cmb2_admin_init', [$this, 'cmb2_register_taxonomy_metabox']);
        add_action('cmb2_admin_init', [$this, 'cmb2_register_user_profile_metabox']);
        add_action('cmb2_admin_init', [$this, 'cmb2_register_theme_options_metabox']);
        add_action('cmb2_admin_init', [$this, 'cmb2_register_comment_metabox']);
    }

    /**
     * Define the metabox and field configurations.
     */
    public function cmb2_sample_metaboxes()
    {
        /**
         * Initiate the metabox
         */
        $cmb = new_cmb2_box(array(
            'id'            => 'test_metabox',
            'title'         => __('Test Metabox', 'cmb2'),
            'object_types'  => array('page',), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true, // Show field names on the left
            // 'cmb_styles' => false, // false to disable the CMB stylesheet
            // 'closed'     => true, // Keep the metabox closed by default
        ));

        $cmb->add_field(array(
            'name' => __('Related Publications', 'textdomain'),
            'desc' => __('Add or remove BibTeX-keys.', 'textdomain'),
            'id'   =>  'related_publications',
            'type' => 'tags_sortable',
        ));

        $cmb->add_field(array(
            'name'           => esc_html__('Dynamically Load', 'text-domain'),
            'id'             => 'sample_metabox_id',
            'desc'           => esc_html__('goes here', 'text-domain'),
            'type'           => 'switch',
            'default'        => true, //If it's checked by default 
            // 'active_value'   => true,
            // 'inactive_value' => false
        ));

        $cmb->add_field( array(
            'name'    => 'Ingredients',
            'id'      => '_roma_ingredients',
            'desc'    => 'Select ingredients. Drag to reorder.',
            'type'    => 'pw_multiselect',
            'options' => array(
                'flour'  => 'Flour',
                'salt'   => 'Salt',
                'eggs'   => 'Eggs',
                'milk'   => 'Milk',
                'butter' => 'Butter',
            ),
        ) );

        // Regular text field
        $cmb->add_field(array(
            'name'       => __('Test Text', 'cmb2'),
            'desc'       => __('field description (optional)', 'cmb2'),
            'id'         => 'yourprefix_text',
            'type'       => 'text',
            'show_on_cb' => 'cmb2_hide_if_no_cats', // function should return a bool value
            // 'sanitization_cb' => 'my_custom_sanitization', // custom sanitization callback parameter
            // 'escape_cb'       => 'my_custom_escaping',  // custom escaping callback parameter
            // 'on_front'        => false, // Optionally designate a field to wp-admin only
            // 'repeatable'      => true,
        ));

        // URL text field
        $cmb->add_field(array(
            'name' => __('Website URL', 'cmb2'),
            'desc' => __('field description (optional)', 'cmb2'),
            'id'   => 'yourprefix_url',
            'type' => 'text_url',
            // 'protocols' => array('http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet'), // Array of allowed protocols
            // 'repeatable' => true,
        ));

        // Email text field
        $cmb->add_field(array(
            'name' => __('Test Text Email', 'cmb2'),
            'desc' => __('field description (optional)', 'cmb2'),
            'id'   => 'yourprefix_email',
            'type' => 'text_email',
            // 'repeatable' => true,
        ));

        // Add other metaboxes as needed

        $group_field_id = $cmb->add_field(array(
            'id'          => 'wiki_test_repeat_group',
            'type'        => 'group',
            'description' => __('Generates reusable form entries', 'cmb2'),
            // 'repeatable'  => false, // use false if you want non-repeatable group
            'options'     => array(
                'group_title'       => __('Entry {#}', 'cmb2'), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __('Add Another Entry', 'cmb2'),
                'remove_button'     => __('Remove Entry', 'cmb2'),
                'sortable'          => true,
                // 'closed'         => true, // true to have the groups closed by default
                // 'remove_confirm' => esc_html__( 'Are you sure you want to remove?', 'cmb2' ), // Performs confirmation before removing group.
            ),
        ));

        // Id's for group's fields only need to be unique for the group. Prefix is not needed.
        $cmb->add_group_field($group_field_id, array(
            'name' => 'Entry Title',
            'id'   => 'title',
            'type' => 'text',
            // 'repeatable' => true, // Repeatable fields are supported w/in repeatable groups (for most types)
        ));

        $cmb->add_group_field($group_field_id, array(
            'name' => 'Description',
            'description' => 'Write a short description for this entry',
            'id'   => 'description',
            'type' => 'textarea_small',
        ));

        $cmb->add_group_field($group_field_id, array(
            'name' => 'Entry Image',
            'id'   => 'image',
            'type' => 'file',
        ));

        $cmb->add_group_field($group_field_id, array(
            'name' => 'Image Caption',
            'id'   => 'image_caption',
            'type' => 'text',
        ));

        $cmb->add_group_field($group_field_id, array(
            'name'             => 'Test Select',
            'desc'             => 'Select an option',
            'id'               => 'wiki_test_select',
            'type'             => 'select',
            'show_option_none' => true,
            'default'          => 'custom',
            'options'          => array(
                'standard' => __('Option One', 'cmb2'),
                'custom'   => __('Option Two', 'cmb2'),
                'none'     => __('Option Three', 'cmb2'),
            ),
        ));

        $cmb->add_group_field($group_field_id, array(
            'name'    => 'Test File',
            'desc'    => 'Upload an image or enter an URL.',
            'id'      => 'wiki_test_image',
            'type'    => 'file',
            // Optional:
            'options' => array(
                'url' => false, // Hide the text input for the url
            ),
            'text'    => array(
                'add_upload_file_text' => 'Add File' // Change upload button text. Default: "Add or Upload File"
            ),
            // query_args are passed to wp.media's library query.
            'query_args' => array(
                'type' => 'application/pdf', // Make library only display PDFs.
                // Or only allow gif, jpg, or png images
                // 'type' => array(
                // 	'image/gif',
                // 	'image/jpeg',
                // 	'image/png',
                // ),
            ),
            'preview_size' => 'large', // Image size to use when previewing in the admin.
        ));
    }

    /**
     * Hook in and add a metabox to add fields to taxonomy terms
     */
    public function cmb2_register_taxonomy_metabox()
    {
        $prefix = 'yourprefix_term_';

        /**
         * Metabox to add fields to categories and tags
         */
        $cmb_term = new_cmb2_box(array(
            'id'               => $prefix . 'edit',
            'title'            => esc_html__('Category Metabox', 'cmb2'), // Doesn't output for term boxes
            'object_types'     => array('term'), // Tells CMB2 to use term_meta vs post_meta
            'taxonomies'       => array('category', 'post_tag'), // Tells CMB2 which taxonomies should have these fields
            // 'new_term_section' => true, // Will display in the "Add New Category" section
        ));

        $cmb_term->add_field(array(
            'name'     => esc_html__('Extra Info', 'cmb2'),
            'desc'     => esc_html__('field description (optional)', 'cmb2'),
            'id'       => $prefix . 'extra_info',
            'type'     => 'title',
            'on_front' => false,
        ));

        $cmb_term->add_field(array(
            'name' => esc_html__('Term Image', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'avatar',
            'type' => 'file',
        ));

        $cmb_term->add_field(array(
            'name' => esc_html__('Arbitrary Term Field', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'term_text_field',
            'type' => 'text',
        ));
    }

    /**
     * Hook in and add a metabox to add fields to the user profile pages
     */
    public function cmb2_register_user_profile_metabox()
    {
        $prefix = 'yourprefix_user_';

        /**
         * Metabox for the user profile screen
         */
        $cmb_user = new_cmb2_box(array(
            'id'               => $prefix . 'edit',
            'title'            => esc_html__('User Profile Metabox', 'cmb2'), // Doesn't output for user boxes
            'object_types'     => array('user'), // Tells CMB2 to use user_meta vs post_meta
            'show_names'       => true,
            'new_user_section' => 'add-new-user', // where form will show on new user page. 'add-existing-user' is only other valid option.
        ));

        $cmb_user->add_field(array(
            'name'     => esc_html__('Extra Info', 'cmb2'),
            'desc'     => esc_html__('field description (optional)', 'cmb2'),
            'id'       => $prefix . 'extra_info',
            'type'     => 'title',
            'on_front' => false,
        ));

        $cmb_user->add_field(array(
            'name'    => esc_html__('Avatar', 'cmb2'),
            'desc'    => esc_html__('field description (optional)', 'cmb2'),
            'id'      => $prefix . 'avatar',
            'type'    => 'file',
        ));

        $cmb_user->add_field(array(
            'name' => esc_html__('Facebook URL', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'facebookurl',
            'type' => 'text_url',
        ));

        $cmb_user->add_field(array(
            'name' => esc_html__('Twitter URL', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'twitterurl',
            'type' => 'text_url',
        ));

        $cmb_user->add_field(array(
            'name' => esc_html__('Google+ URL', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'googleplusurl',
            'type' => 'text_url',
        ));

        $cmb_user->add_field(array(
            'name' => esc_html__('Linkedin URL', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'linkedinurl',
            'type' => 'text_url',
        ));

        $cmb_user->add_field(array(
            'name' => esc_html__('User Field', 'cmb2'),
            'desc' => esc_html__('field description (optional)', 'cmb2'),
            'id'   => $prefix . 'user_text_field',
            'type' => 'text',
        ));
    }

    /**
     * Hook in and register a metabox to handle a theme options page and adds a menu item.
     */
    public function cmb2_register_theme_options_metabox()
    {

        /**
         * Registers options page menu item and form.
         */
        $cmb_options = new_cmb2_box(array(
            'id'           => 'yourprefix_theme_options_page',
            'title'        => esc_html__('Theme Options', 'cmb2'),
            'object_types' => array('options-page'),

            /*
		 * The following parameters are specific to the options-page box
		 * Several of these parameters are passed along to add_menu_page()/add_submenu_page().
		 */

            'option_key'      => 'yourprefix_theme_options', // The option key and admin menu page slug.
            'icon_url'        => 'dashicons-palmtree', // Menu icon. Only applicable if 'parent_slug' is left empty.
            // 'menu_title'      => esc_html__( 'Options', 'cmb2' ), // Falls back to 'title' (above).
            // 'parent_slug'     => 'themes.php', // Make options page a submenu item of the themes menu.
            // 'capability'      => 'manage_options', // Cap required to view options-page.
            // 'position'        => 1, // Menu position. Only applicable if 'parent_slug' is left empty.
            // 'admin_menu_hook' => 'network_admin_menu', // 'network_admin_menu' to add network-level options page.
            // 'display_cb'      => false, // Override the options-page form output (CMB2_Hookup::options_page_output()).
            // 'save_button'     => esc_html__( 'Save Theme Options', 'cmb2' ), // The text for the options-page save button. Defaults to 'Save'.
            // 'disable_settings_errors' => true, // On settings pages (not options-general.php sub-pages), allows disabling.
            // 'message_cb'      => 'yourprefix_options_page_message_callback',
        ));

        /**
         * Options fields ids only need
         * to be unique within this box.
         * Prefix is not needed.
         */
        $cmb_options->add_field(array(
            'name'    => esc_html__('Site Background Color', 'cmb2'),
            'desc'    => esc_html__('field description (optional)', 'cmb2'),
            'id'      => 'bg_color',
            'type'    => 'colorpicker',
            'default' => '#ffffff',
        ));

        $group_field_id = $cmb_options->add_field(array(
            'id'          => 'wiki_test_repeat_group',
            'type'        => 'group',
            'description' => __('Generates reusable form entries', 'cmb2'),
            // 'repeatable'  => false, // use false if you want non-repeatable group
            'options'     => array(
                'group_title'       => __('Entry {#}', 'cmb2'), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __('Add Another Entry', 'cmb2'),
                'remove_button'     => __('Remove Entry', 'cmb2'),
                'sortable'          => true,
                // 'closed'         => true, // true to have the groups closed by default
                // 'remove_confirm' => esc_html__( 'Are you sure you want to remove?', 'cmb2' ), // Performs confirmation before removing group.
            ),
        ));

        // Id's for group's fields only need to be unique for the group. Prefix is not needed.
        $cmb_options->add_group_field($group_field_id, array(
            'name' => 'Entry Title',
            'id'   => 'title',
            'type' => 'text',
            // 'repeatable' => true, // Repeatable fields are supported w/in repeatable groups (for most types)
        ));

        $cmb_options->add_group_field($group_field_id, array(
            'name' => 'Description',
            'description' => 'Write a short description for this entry',
            'id'   => 'description',
            'type' => 'textarea_small',
        ));

        $cmb_options->add_group_field($group_field_id, array(
            'name' => 'Entry Image',
            'id'   => 'image',
            'type' => 'file',
        ));

        $cmb_options->add_group_field($group_field_id, array(
            'name' => 'Image Caption',
            'id'   => 'image_caption',
            'type' => 'text',
        ));

        $cmb_options->add_group_field($group_field_id, array(
            'name'             => 'Test Select',
            'desc'             => 'Select an option',
            'id'               => 'wiki_test_select',
            'type'             => 'select',
            'show_option_none' => true,
            'default'          => 'custom',
            'options'          => array(
                'standard' => __('Option One', 'cmb2'),
                'custom'   => __('Option Two', 'cmb2'),
                'none'     => __('Option Three', 'cmb2'),
            ),
        ));

        $cmb_options->add_group_field($group_field_id, array(
            'name'    => 'Test File',
            'desc'    => 'Upload an image or enter an URL.',
            'id'      => 'wiki_test_image',
            'type'    => 'file',
            // Optional:
            'options' => array(
                'url' => false, // Hide the text input for the url
            ),
            'text'    => array(
                'add_upload_file_text' => 'Add File' // Change upload button text. Default: "Add or Upload File"
            ),
            // query_args are passed to wp.media's library query.
            'query_args' => array(
                'type' => 'application/pdf', // Make library only display PDFs.
                // Or only allow gif, jpg, or png images
                // 'type' => array(
                // 	'image/gif',
                // 	'image/jpeg',
                // 	'image/png',
                // ),
            ),
            'preview_size' => 'large', // Image size to use when previewing in the admin.
        ));
    }

    /**
     * Hook in and register a metabox for the admin comment edit page.
     */
    public function cmb2_register_comment_metabox()
    {

        /**
         * Sample metabox to demonstrate each field type included
         */
        $cmb = new_cmb2_box(array(
            'id'           => 'yourprefix_comment_metabox',
            'title'        => 'Test Metabox',
            'object_types' => array('comment'),
        ));

        $cmb->add_field(array(
            'name' => 'Test Text Small',
            'desc' => 'field description (optional)',
            'id'   => 'yourprefix_comment_textsmall',
            'type' => 'text_small',
            'column' => array(
                'position' => 2,
                'name' => 'CMB2 Custom Column',
            ),
        ));

        $cmb->add_field(array(
            'name'    => 'Test Color Picker',
            'desc'    => 'field description (optional)',
            'id'      => 'yourprefix_comment_colorpicker',
            'type'    => 'colorpicker',
            'default' => '#ffffff',
            'column' => array(
                'position' => 2,
            ),

        ));
    }
}
