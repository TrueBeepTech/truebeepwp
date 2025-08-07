<?php

namespace Truebeep\Frontend;

/**
 * Shortcode class
 */
class Shortcode
{
    /**
     * Initialize class
     */
    public function __construct()
    {
        add_shortcode('truebeep_shortcode', [$this, 'truebeep_shortcode']);
        add_shortcode('truebeep_enquiry', [$this, 'truebeep_enquiry']);
    }

    /**
     * Shortcode
     *
     * @param array $atts
     * @param string $content
     * @return string
     */
    public function truebeep_shortcode($atts, $content = null)
    {
        wp_enqueue_script('truebeep-script');
        wp_enqueue_style('truebeep-style');

        ob_start();

        include __DIR__ . '/views/shortcode.php';

        return ob_get_clean();
    }

    /**
     * Shortcode
     *
     * @param array $atts
     * @param string $content
     * @return string
     */
    public function truebeep_enquiry($atts, $content = null)
    {
        wp_enqueue_script('truebeep-enquiry-script');
        wp_enqueue_style('truebeep-style');

        // wp_localize_script('shazabo-manager-enquiry-script', 'shazabo_manager_data', [
        //     'ajax_url' => admin_url('admin-ajax.php'),
        //     'message' => __('Message from enquiry form', 'shazabo-manager'),
        // ]);

        ob_start();

        include __DIR__ . '/views/enquiry.php';

        return ob_get_clean();
    }
}
