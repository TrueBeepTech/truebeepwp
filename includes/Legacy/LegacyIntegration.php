<?php

namespace Truebeep\Legacy;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Legacy Integration
 * 
 * Bridge class for integrating legacy sync functionality with the main plugin.
 * Handles initialization of sync features and provides system status information.
 * 
 * @package Truebeep\Legacy
 * @since 1.0.0
 */
class LegacyIntegration
{
    /**
     * Initialize legacy sync features
     * 
     * Sets up sync manager and registers admin notices for sync status.
     * Only initializes in WordPress admin area.
     * 
     * @return void
     */
    public static function init()
    {
        if (!is_admin()) {
            return;
        }
        
        new SyncManager();
        
        add_action('admin_notices', [self::class, 'show_sync_notices']);
        add_action('woocommerce_system_status_report', [self::class, 'add_system_status_info']);
    }
    
    /**
     * Display admin notices for sync status
     * 
     * Shows informational notices about ongoing or completed sync operations
     * on relevant admin pages.
     * 
     * @return void
     */
    public static function show_sync_notices()
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['users_page_truebeep-smwl-sync', 'woocommerce_page_wc-settings'])) {
            return;
        }
        
        $sync_status = get_option('truebeep_sync_status', 'idle');
        
        if ($sync_status === 'running' || $sync_status === 'processing') {
            $progress = get_option('truebeep_sync_progress', []);
            $processed = $progress['processed'] ?? 0;
            $total = $progress['total'] ?? 0;
            
            echo '<div class="notice notice-info">';
            echo '<p>';
            printf(
                /* translators: %1$d: processed customers, %2$d: total customers */
                esc_html__('Truebeep customer sync is running in the background. %1$d of %2$d customers processed.', 'truebeep'),
                intval($processed),
                intval($total)
            );
            echo ' <a href="' . esc_url(admin_url('users.php?page=truebeep-smwl-sync')) . '">' . esc_html__('View Progress', 'truebeep') . '</a>';
            echo '</p>';
            echo '</div>';
        }
        
        if ($sync_status === 'completed') {
            $progress = get_option('truebeep_sync_progress', []);
            $successful = $progress['successful'] ?? 0;
            
            if ($successful > 0) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>';
                printf(
                    /* translators: %d: number of successfully synchronized customers */
                    esc_html__('Truebeep customer sync completed successfully! %d customers were synchronized.', 'truebeep'),
                    intval($successful)
                );
                echo ' <a href="' . esc_url(admin_url('users.php?page=truebeep-smwl-sync')) . '">' . esc_html__('View Details', 'truebeep') . '</a>';
                echo '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Add sync information to WooCommerce system status
     * 
     * Displays sync statistics and current status in the WooCommerce
     * system status report for debugging purposes.
     * 
     * @return void
     */
    public static function add_system_status_info()
    {
        $sync_status = get_option('truebeep_sync_status', 'idle');
        $progress = get_option('truebeep_sync_progress', []);
        $syncer = new CustomerSyncer();
        $stats = $syncer->get_sync_statistics();
        
        ?>
        <table class="wc_status_table widefat" cellspacing="0">
            <thead>
                <tr>
                    <th colspan="3" data-export-label="Truebeep Sync">
                        <h2><?php esc_html_e('Truebeep Customer Sync', 'truebeep'); ?></h2>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-export-label="Sync Status"><?php esc_html_e('Sync Status', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $sync_status))); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Total Customers"><?php esc_html_e('Total Customers', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html(number_format($stats['total'])); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Customers Remaining"><?php esc_html_e('Customers Remaining', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html(number_format($stats['remaining'])); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Sync Progress"><?php esc_html_e('Sync Progress', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <?php 
                        echo esc_html(sprintf(
                            '%s / %s (%s%%)', 
                            number_format($progress['processed'] ?? 0),
                            number_format($stats['total']),
                            esc_html($stats['percentage'])
                        )); 
                        ?>
                    </td>
                </tr>
                <?php if (!empty($progress['successful'])): ?>
                <tr>
                    <td data-export-label="Successful Syncs"><?php esc_html_e('Successful Syncs', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html(number_format($progress['successful'])); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($progress['failed'])): ?>
                <tr>
                    <td data-export-label="Failed Syncs"><?php esc_html_e('Failed Syncs', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html(number_format($progress['failed'])); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Get sync statistics for external use
     * 
     * Provides access to current sync statistics for other components.
     * 
     * @return array Sync statistics including total, processed, and remaining counts
     */
    public static function get_statistics()
    {
        $syncer = new CustomerSyncer();
        return $syncer->get_sync_statistics();
    }
    
    /**
     * Check if sync is available
     * 
     * Verifies that API credentials are configured and sync can be performed.
     * 
     * @return bool True if API is configured, false otherwise
     */
    public static function is_sync_available()
    {
        $api_url = get_option('truebeep_api_url', '');
        $api_key = get_option('truebeep_api_key', '');
        
        return !empty($api_url) && !empty($api_key);
    }
}