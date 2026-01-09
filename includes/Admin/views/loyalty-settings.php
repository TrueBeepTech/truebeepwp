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
                <label><?php esc_html_e('Tiers', 'truebeep-smart-wallet-loyalty'); ?></label>
                <p style="font-weight: normal; text-size:12px; color: #646970"><?php esc_html_e('Create membership levels with different earning rates and benefits. Customers automatically upgrade as they earn more points.', 'truebeep-smart-wallet-loyalty'); ?></p>
            </th>
            <td class="forminp">
                <div id="truebeep-tiers-container">
                    <table class="wp-list-table widefat fixed striped" id="truebeep-tiers-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Tier Name', 'truebeep-smart-wallet-loyalty'); ?></th>
                                <th><?php esc_html_e('Order to Points', 'truebeep-smart-wallet-loyalty'); ?></th>
                                <th><?php esc_html_e('Points to Amount', 'truebeep-smart-wallet-loyalty'); ?></th>
                                <th><?php esc_html_e('Threshold Points', 'truebeep-smart-wallet-loyalty'); ?></th>
                                <th><?php esc_html_e('Actions', 'truebeep-smart-wallet-loyalty'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="truebeep-tiers-list">
                            <?php foreach ($tiers as $truebeep_index => $truebeep_tier) : ?>
                                <tr class="tier-row" data-index="<?php echo esc_attr($truebeep_index); ?>">
                                    <td><?php echo esc_html($truebeep_tier['name']); ?></td>
                                    <td><?php echo esc_html($truebeep_tier['order_to_points'] ?? '1'); ?></td>
                                    <td><?php echo esc_html($truebeep_tier['points_to_amount'] ?? '1'); ?></td>
                                    <td><?php echo esc_html($truebeep_tier['threshold'] ?? '0'); ?></td>
                                    <td>
                                        <button type="button" class="button edit-tier" data-tier='<?php echo esc_attr(json_encode($truebeep_tier)); ?>' data-index="<?php echo esc_attr($truebeep_index); ?>"><?php esc_html_e('Edit', 'truebeep-smart-wallet-loyalty'); ?></button>
                                        <button type="button" class="button remove-tier" data-index="<?php echo esc_attr($truebeep_index); ?>"><?php esc_html_e('Remove', 'truebeep-smart-wallet-loyalty'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button button-secondary" id="add-tier-button"><?php esc_html_e('Add More Tier', 'truebeep-smart-wallet-loyalty'); ?></button>
                        <button type="button" class="button button-primary" id="save-all-button"><?php esc_html_e('Save Changes', 'truebeep-smart-wallet-loyalty'); ?></button>
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
            <h2><?php esc_html_e('Edit Tier', 'truebeep-smart-wallet-loyalty'); ?></h2>
            <span class="tier-modal-close">&times;</span>
        </div>
        <div class="tier-modal-body">
            <input type="hidden" id="edit-tier-index" />
            <table class="form-table">
                <tr>
                    <th><label for="edit-tier-name"><?php esc_html_e('Tier Name (e.g. "Bronze", "Silver", "Gold")', 'truebeep-smart-wallet-loyalty'); ?></label></th>
                    <td><input type="text" id="edit-tier-name" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="edit-tier-order-points"><?php esc_html_e('Order Amount to Points Conversion', 'truebeep-smart-wallet-loyalty'); ?></label></th>
                    <td><input type="number" id="edit-tier-order-points" class="regular-text" step="0.01" min="0" /></td>
                </tr>
                <tr>
                    <th><label for="edit-tier-points-amount"><?php esc_html_e('Points to Amount Conversion', 'truebeep-smart-wallet-loyalty'); ?></label></th>
                    <td><input type="number" id="edit-tier-points-amount" class="regular-text" step="0.01" min="0" /></td>
                </tr>
                <tr>
                    <th><label for="edit-tier-threshold"><?php esc_html_e('Points to Reach This Tier', 'truebeep-smart-wallet-loyalty'); ?></label></th>
                    <td><input type="number" id="edit-tier-threshold" class="regular-text" min="0" /></td>
                </tr>
            </table>
        </div>
        <div class="tier-modal-footer">
            <button type="button" class="button button-primary" id="save-tier-button"><?php esc_html_e('Save Tier', 'truebeep-smart-wallet-loyalty'); ?></button>
            <button type="button" class="button" id="cancel-tier-button"><?php esc_html_e('Cancel', 'truebeep-smart-wallet-loyalty'); ?></button>
        </div>
    </div>
</div>

<!-- Coupon Edit Modal -->
<div id="coupon-edit-modal" class="tier-modal" style="display:none;">
    <div class="tier-modal-content">
        <div class="tier-modal-header">
            <h2><?php esc_html_e('Edit Coupon', 'truebeep-smart-wallet-loyalty'); ?></h2>
            <span class="coupon-modal-close">&times;</span>
        </div>
        <div class="tier-modal-body">
            <input type="hidden" id="edit-coupon-index" />
            <table class="form-table">
                <tr>
                    <th><label for="edit-coupon-name"><?php esc_html_e('Coupon Name', 'truebeep-smart-wallet-loyalty'); ?></label></th>
                    <td><input type="text" id="edit-coupon-name" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="edit-coupon-value"><?php esc_html_e('Value ($)', 'truebeep-smart-wallet-loyalty'); ?></label></th>
                    <td><input type="number" id="edit-coupon-value" class="regular-text" step="0.01" min="0" /></td>
                </tr>
            </table>
        </div>
        <div class="tier-modal-footer">
            <button type="button" class="button button-primary" id="save-coupon-button"><?php esc_html_e('Save Coupon', 'truebeep-smart-wallet-loyalty'); ?></button>
            <button type="button" class="button" id="cancel-coupon-button"><?php esc_html_e('Cancel', 'truebeep-smart-wallet-loyalty'); ?></button>
        </div>
    </div>
</div>