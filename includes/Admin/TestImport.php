<?php

namespace Truebeep\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UserImport
 * Handles importing users from the database and processing them in chunks
 * 
 * @package Truebeep\Admin
 */
class TestImport
{
    /**
     * WC_Logger instance
     *
     * @var WC_Logger
     */
    private $logger;

    /**
     * TestBackgroundJob instance
     *
     * @var TestBgJob
     */
    private $background_job;

    /**
     * Initialize the TestImport class
     */
    public function __construct()
    {
        $this->logger = wc_get_logger();
        $this->background_job = new TestBgJob();
    }

    /**
     * Main handler for the user import process
     */
    public function handle_import()
    {
        update_option('user_import_log', 0);
        $this->import_users_from_db();
    }

    /**
     * Import users from the database and process them in chunks
     * 
     * Retrieves users from the database in chunks and processes them using a background task handler.
     */
    public function import_users_from_db()
    {
        global $wpdb;

        $batch_size = 10;
        $offset = 0;
        $total_processed = 0;

        do {
            $users = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->users} LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ), ARRAY_A);

            if (empty($users)) {
                break;
            }

            foreach ($users as $user) {
                $user['user_email'] = 'test@test.com';
                $this->background_job->push_to_queue($user);
                $total_processed++;
            }

            $offset += $batch_size;
            gc_collect_cycles();
        } while (count($users) === $batch_size);

        $this->logger->info('Completed queueing users for import. Total queued: ' . $total_processed, ['source' => 'user-import']);
        $this->background_job->save()->dispatch();
    }
}
