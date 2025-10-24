<?php

$truebeep_customer_id = get_user_meta($user->ID, '_truebeep_customer_id', true);
$sync_status = get_user_meta($user->ID, '_truebeep_sync_status', true);
$last_sync = get_user_meta($user->ID, '_truebeep_last_sync', true);
$sync_error = get_user_meta($user->ID, '_truebeep_sync_error', true);

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
            <?php if ($sync_status === 'synced'): ?>
                <span style="color: green;">✓ <?php esc_html_e('Synced', 'truebeep'); ?></span>
            <?php elseif ($sync_status === 'error'): ?>
                <span style="color: red;">✗ <?php esc_html_e('Error', 'truebeep'); ?></span>
                <?php if ($sync_error): ?>
                    <p class="description" style="color: red;"><?php echo esc_html($sync_error); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <span><?php esc_html_e('Not synced', 'truebeep'); ?></span>
            <?php endif; ?>

            <?php if ($last_sync): ?>
                <p class="description"><?php 
                /* translators: %s: last sync date/time */
                printf(__('Last sync: %s', 'truebeep'), $last_sync); 
                ?></p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th></th>
        <td>
            <button type="button" class="button" id="truebeep-sync-user" data-user-id="<?php echo $user->ID; ?>">
                <?php esc_html_e('Sync with Truebeep', 'truebeep'); ?>
            </button>
            <?php if (!empty($truebeep_customer_id)): ?>
                <button type="button" class="button" id="truebeep-remove-sync" data-user-id="<?php echo $user->ID; ?>">
                    <?php esc_html_e('Remove Truebeep Link', 'truebeep'); ?>
                </button>
            <?php endif; ?>
        </td>
    </tr>
</table>

<script>
    jQuery(document).ready(function($) {
        $('#truebeep-sync-user').on('click', function() {
            var button = $(this);
            var userId = button.data('user-id');

            button.prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'truebeep')); ?>');

            $.post(ajaxurl, {
                action: 'truebeep_sync_user',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('truebeep_sync_user'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Sync failed', 'truebeep')); ?>');
                    button.prop('disabled', false).text('<?php echo esc_js(__('Sync with Truebeep', 'truebeep')); ?>');
                }
            });
        });

        $('#truebeep-remove-sync').on('click', function() {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to remove the Truebeep link?', 'truebeep')); ?>')) {
                return;
            }

            var button = $(this);
            var userId = button.data('user-id');

            button.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'truebeep_remove_sync',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('truebeep_remove_sync'); ?>'
            }, function(response) {
                location.reload();
            });
        });
    });
</script>