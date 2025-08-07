<?php

namespace Truebeep;

/**
 * Generator class
 * 
 * @description: Extended CPTs is a library which provides extended functionality to WordPress custom post types and taxonomies. 
 * This allows developers to quickly build post types and taxonomies without having to write the same code again and again.
 * 
 * @api https://github.com/johnbillion/extended-cpts
 */
class Generator
{
    /**
     * Class initialize
     */
    function __construct()
    {
        // add_action('init', [$this, 'init_generator']);
    }

    public function init_generator()
    {
        register_extended_post_type( 'story', array(

            # Add the post type to the site's main RSS feed:
            'show_in_feed' => true,
    
            # Use the block editor:
            'show_in_rest' => true,
    
            # Show all posts on the post type archive:
            'archive' => array(
                'nopaging' => true
            ),
    
            # Add some custom columns to the admin screen:
            'admin_cols' => array(
                'featured_image' => array(
                    'title'          => 'Illustration',
                    'featured_image' => 'thumbnail'
                ),
                'published' => array(
                    'title'       => 'Published',
                    'meta_key'    => 'published_date',
                    'date_format' => 'd/m/Y'
                ),
                'genre' => array(
                    'taxonomy' => 'genre'
                )
            ),
    
            # Add a dropdown filter to the admin screen:
            'admin_filters' => array(
                'genre' => array(
                    'taxonomy' => 'genre'
                )
            )
    
        ), array(
    
            # Override the base names used for labels:
            'singular' => 'Story',
            'plural'   => 'Stories',
            'slug'     => 'stories'
    
        ) );


        register_extended_taxonomy( 'genre', 'story', array(

            # Use radio buttons in the meta box for this taxonomy on the post editing screen:
            'meta_box' => 'radio',
        
            # Show this taxonomy in the 'At a Glance' dashboard widget:
            'dashboard_glance' => true,
        
            # Add a custom column to the admin screen:
            'admin_cols' => array(
                'updated' => array(
                    'title'       => 'Updated',
                    'meta_key'    => 'updated_date',
                    'date_format' => 'd/m/Y'
                ),
            ),
        
        ), array(
        
            # Override the base names used for labels:
            'singular' => 'Genre',
            'plural'   => 'Genres',
            'slug'     => 'story-genre'
        
        ) );
    }

    
}
