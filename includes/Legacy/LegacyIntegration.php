<?php

namespace Truebeep\Legacy;

/**
 * Legacy Integration
 * Handles the integration of legacy import functionality
 */
class LegacyIntegration
{
    /**
     * Initialize legacy features
     */
    public static function init()
    {
        // Only initialize in admin
        if (!is_admin()) {
            return;
        }
        
        // Initialize import manager for AJAX handlers
        new ImportManager();
        
        // Add admin notices for import status
        add_action('admin_notices', [self::class, 'show_import_notices']);
        
        // Add import status to WooCommerce status page
        add_action('woocommerce_system_status_report', [self::class, 'add_system_status_info']);
    }
    
    /**
     * Show admin notices for import status
     */
    public static function show_import_notices()
    {
        // Only show on relevant admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['woocommerce_page_truebeep-import', 'woocommerce_page_wc-settings'])) {
            return;
        }
        
        $import_status = get_option('truebeep_import_status', 'idle');
        
        if ($import_status === 'running') {
            $progress = get_option('truebeep_import_progress', []);
            $processed = $progress['processed'] ?? 0;
            $total = $progress['total'] ?? 0;
            
            echo '<div class="notice notice-info">';
            echo '<p>';
            printf(
                __('Truebeep customer import is running in the background. %d of %d customers processed.', 'truebeep'),
                $processed,
                $total
            );
            echo ' <a href="' . admin_url('admin.php?page=truebeep-import') . '">' . __('View Progress', 'truebeep') . '</a>';
            echo '</p>';
            echo '</div>';
        }
        
        if ($import_status === 'completed') {
            $progress = get_option('truebeep_import_progress', []);
            $successful = $progress['successful'] ?? 0;
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>';
            printf(
                __('Truebeep customer import completed successfully! %d customers were imported.', 'truebeep'),
                $successful
            );
            echo ' <a href="' . admin_url('admin.php?page=truebeep-import') . '">' . __('View Details', 'truebeep') . '</a>';
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add import information to WooCommerce system status
     */
    public static function add_system_status_info()
    {
        _log('add_system_status_info');

        $import_status = get_option('truebeep_import_status', 'idle');
        $progress = get_option('truebeep_import_progress', []);
        $importer = new CustomerImporter();
        $stats = $importer->get_import_statistics();
        
        ?>
        <table class="wc_status_table widefat" cellspacing="0">
            <thead>
                <tr>
                    <th colspan="3" data-export-label="Truebeep Import">
                        <h2><?php _e('Truebeep Import', 'truebeep'); ?></h2>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-export-label="Import Status"><?php _e('Import Status', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo ucfirst($import_status); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Total Customers"><?php _e('Total Customers', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo number_format($stats['total']); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Customers Remaining"><?php _e('Customers Remaining', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo number_format($stats['remaining']); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Import Progress"><?php _e('Import Progress', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <?php 
                        echo sprintf(
                            '%s / %s (%s%%)', 
                            number_format($progress['processed'] ?? 0),
                            number_format($stats['total']),
                            $stats['percentage']
                        ); 
                        ?>
                    </td>
                </tr>
                <?php if (!empty($progress['successful'])): ?>
                <tr>
                    <td data-export-label="Successful Imports"><?php _e('Successful Imports', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo number_format($progress['successful']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($progress['failed'])): ?>
                <tr>
                    <td data-export-label="Failed Imports"><?php _e('Failed Imports', 'truebeep'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo number_format($progress['failed']); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Get import statistics for external use
     * 
     * @return array Import statistics
     */
    public static function get_statistics()
    {
        $importer = new CustomerImporter();
        return $importer->get_import_statistics();
    }
    
    /**
     * Check if import is available (API configured)
     * 
     * @return bool
     */
    public static function is_import_available()
    {
        $api_url = get_option('truebeep_api_url', '');
        $api_key = get_option('truebeep_api_key', '');
        
        return !empty($api_url) && !empty($api_key);
    }
}