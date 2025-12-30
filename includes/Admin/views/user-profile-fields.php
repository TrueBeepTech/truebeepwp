<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$truebeep_customer_id = get_user_meta($user->ID, '_truebeep_customer_id', true);
$truebeep_sync_status = get_user_meta($user->ID, '_truebeep_sync_status', true);
$truebeep_last_sync = get_user_meta($user->ID, '_truebeep_last_sync', true);
$truebeep_sync_error = get_user_meta($user->ID, '_truebeep_sync_error', true);

?>


<h3><?php esc_html_e('Truebeep Integration', 'truebeep'); ?></h3>
<table class="form-table">
    <tr>
        <th><label for="truebeep_customer_id"><?php esc_html_e('Truebeep Customer ID', 'truebeep'); ?></label></th>
        <td>
            <input type="text" name="truebeep_customer_id" id="truebeep_customer_id" value="<?php echo esc_attr($truebeep_customer_id); ?>" class="regular-text" readonly />
            <p class="description"><?php esc_html_e('The customer ID in Truebeep system.', 'truebeep'); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e('Sync Status', 'truebeep'); ?></label></th>
        <td>
            <?php if ($truebeep_sync_status === 'synced'): ?>
                <span style="color: green;">✓ <?php esc_html_e('Synced', 'truebeep'); ?></span>
            <?php elseif ($truebeep_sync_status === 'error'): ?>
                <span style="color: red;">✗ <?php esc_html_e('Error', 'truebeep'); ?></span>
                <?php if ($truebeep_sync_error): ?>
                    <p class="description" style="color: red;"><?php echo esc_html($truebeep_sync_error); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <span><?php esc_html_e('Not synced', 'truebeep'); ?></span>
            <?php endif; ?>

            <?php if ($truebeep_last_sync): ?>
                <p class="description"><?php 
                /* translators: %s: last sync date/time */
                printf(esc_html__('Last sync: %s', 'truebeep'), esc_html($truebeep_last_sync)); 
                ?></p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th></th>
        <td>
            <button type="button" class="button" id="truebeep-smwl-sync-user" data-user-id="<?php echo esc_attr($user->ID); ?>">
                <?php esc_html_e('Sync with Truebeep', 'truebeep'); ?>
            </button>
            <?php if (!empty($truebeep_customer_id)): ?>
                <button type="button" class="button" id="truebeep-smwl-remove-sync" data-user-id="<?php echo esc_attr($user->ID); ?>">
                    <?php esc_html_e('Remove Truebeep Link', 'truebeep'); ?>
                </button>
            <?php endif; ?>
        </td>
    </tr>
</table>