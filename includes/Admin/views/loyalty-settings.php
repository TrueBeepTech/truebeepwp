<?php

/**
 * Loyalty settings view
 * 
 * @package Truebeep
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<table class="form-table">
    <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php _e('Tiers', 'truebeep'); ?></label>
                <p style="font-weight: normal; text-size:12px; color: #646970"><?php _e('Create membership levels with different earning rates and benefits. Customers automatically upgrade as they earn more points.', 'truebeep'); ?></p>
            </th>
            <td class="forminp">
                <div id="truebeep-tiers-container">
                    <table class="wp-list-table widefat fixed striped" id="truebeep-tiers-table">
                        <thead>
                            <tr>
                                <th><?php _e('Tier Name', 'truebeep'); ?></th>
                                <th><?php _e('Order to Points', 'truebeep'); ?></th>
                                <th><?php _e('Points to Amount', 'truebeep'); ?></th>
                                <th><?php _e('Threshold Points', 'truebeep'); ?></th>
                                <th><?php _e('Actions', 'truebeep'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="truebeep-tiers-list">
                            <?php foreach ($tiers as $index => $tier) : ?>
                                <tr class="tier-row" data-index="<?php echo $index; ?>">
                                    <td><?php echo esc_html($tier['name']); ?></td>
                                    <td><?php echo esc_html($tier['order_to_points'] ?? '1'); ?></td>
                                    <td><?php echo esc_html($tier['points_to_amount'] ?? '1'); ?></td>
                                    <td><?php echo esc_html($tier['threshold'] ?? '0'); ?></td>
                                    <td>
                                        <button type="button" class="button edit-tier" data-tier='<?php echo json_encode($tier); ?>' data-index="<?php echo $index; ?>"><?php _e('Edit', 'truebeep'); ?></button>
                                        <button type="button" class="button remove-tier" data-index="<?php echo $index; ?>"><?php _e('Remove', 'truebeep'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button button-secondary" id="add-tier-button"><?php _e('Add More Tier', 'truebeep'); ?></button>
                        <button type="button" class="button button-primary" id="save-all-button"><?php _e('Save Changes', 'truebeep'); ?></button>
                    </p>
                </div>
            </td>
        </tr>
    </tbody>
</table>

<!-- Tier Edit Modal -->
<div id="tier-edit-modal" class="tier-modal" style="display:none;">
    <div class="tier-modal-content">
        <div class="tier-modal-header">
            <h2><?php _e('Edit Tier', 'truebeep'); ?></h2>
            <span class="tier-modal-close">&times;</span>
        </div>
        <div class="tier-modal-body">
            <input type="hidden" id="edit-tier-index" />
            <table class="form-table">
                <tr>
                    <th><label for="edit-tier-name"><?php _e('Tier Name (e.g. "Bronze", "Silver", "Gold")', 'truebeep'); ?></label></th>
                    <td><input type="text" id="edit-tier-name" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="edit-tier-order-points"><?php _e('Order Amount to Points Conversion', 'truebeep'); ?></label></th>
                    <td><input type="number" id="edit-tier-order-points" class="regular-text" step="0.01" min="0" /></td>
                </tr>
                <tr>
                    <th><label for="edit-tier-points-amount"><?php _e('Points to Amount Conversion', 'truebeep'); ?></label></th>
                    <td><input type="number" id="edit-tier-points-amount" class="regular-text" step="0.01" min="0" /></td>
                </tr>
                <tr>
                    <th><label for="edit-tier-threshold"><?php _e('Points to Reach This Tier', 'truebeep'); ?></label></th>
                    <td><input type="number" id="edit-tier-threshold" class="regular-text" min="0" /></td>
                </tr>
            </table>
        </div>
        <div class="tier-modal-footer">
            <button type="button" class="button button-primary" id="save-tier-button"><?php _e('Save Tier', 'truebeep'); ?></button>
            <button type="button" class="button" id="cancel-tier-button"><?php _e('Cancel', 'truebeep'); ?></button>
        </div>
    </div>
</div>

<!-- Coupon Edit Modal -->
<div id="coupon-edit-modal" class="tier-modal" style="display:none;">
    <div class="tier-modal-content">
        <div class="tier-modal-header">
            <h2><?php _e('Edit Coupon', 'truebeep'); ?></h2>
            <span class="coupon-modal-close">&times;</span>
        </div>
        <div class="tier-modal-body">
            <input type="hidden" id="edit-coupon-index" />
            <table class="form-table">
                <tr>
                    <th><label for="edit-coupon-name"><?php _e('Coupon Name', 'truebeep'); ?></label></th>
                    <td><input type="text" id="edit-coupon-name" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="edit-coupon-value"><?php _e('Value ($)', 'truebeep'); ?></label></th>
                    <td><input type="number" id="edit-coupon-value" class="regular-text" step="0.01" min="0" /></td>
                </tr>
            </table>
        </div>
        <div class="tier-modal-footer">
            <button type="button" class="button button-primary" id="save-coupon-button"><?php _e('Save Coupon', 'truebeep'); ?></button>
            <button type="button" class="button" id="cancel-coupon-button"><?php _e('Cancel', 'truebeep'); ?></button>
        </div>
    </div>
</div>