<?php

namespace Truebeep\Admin;

// use WP_Background_Process;

if (!class_exists('WP_Background_Process')) {
    require_once plugin_dir_path(__FILE__) . 'wp-background-processing/wp-background-processing.php';
}

class TestBgJob extends \WP_Background_Process
{
    protected $action = 'wp_test_background_job_action_cron';

    protected function task($dummy_data)
    {
        

        // $logger = wc_get_logger();
        // $logger->info('Processing dummy data: ' . print_r($dummy_data, true), ['source' => 'test_background_job']);

        return false;
    }
}

global $test_background_job;
$test_background_job = new TestBgJob();
