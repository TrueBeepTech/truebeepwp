<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="truebeep-loyalty-panel" class="truebeep-loyalty-panel <?php echo esc_attr($panel_position); ?>" data-customer-id="<?php echo esc_attr($truebeep_customer_id); ?>">
    <div class="panel-toggle">
        <?php esc_html_e('Rewards', 'truebeep-smart-wallet-loyalty'); ?>
        <span class="points-badge">--</span>
    </div>

    <div class="panel-content">
        <div class="panel-header">
            <button class="panel-close" aria-label="<?php echo esc_attr__('Close panel', 'truebeep-smart-wallet-loyalty'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="panel-body">
            <!-- Welcome Message -->
            <div class="welcome-message">
                <?php esc_html_e('Welcome back, ', 'truebeep-smart-wallet-loyalty'); ?><span class="user-name"></span><?php esc_html_e('!', 'truebeep-smart-wallet-loyalty'); ?>
            </div>

            <!-- Points Display -->
            <div class="points-display">
                <span class="points-number">--</span>
                <span class="points-label"><?php esc_html_e('Point', 'truebeep-smart-wallet-loyalty'); ?></span>
            </div>

            <!-- Tier Badge -->
            <div class="tier-badge">
                <span class="tier-label">--</span>
            </div>

            <!-- Wallet Description -->
            <p class="wallet-description">
                <?php esc_html_e('Download wallet card for easy access to your rewards and points', 'truebeep-smart-wallet-loyalty'); ?>
            </p>

            <!-- Wallet Buttons -->
            <div class="wallet-buttons">
                <a href="#" class="wallet-btn apple-wallet" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url(TRUEBEEP_URL . '/assets/images/add-to-apple-wallet-button.svg'); ?>" alt="<?php echo esc_attr__('Apple Wallet', 'truebeep-smart-wallet-loyalty'); ?>" />
                </a>
                <a href="#" class="wallet-btn google-wallet" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url(TRUEBEEP_URL . '/assets/images/add-to-google-wallet-button.svg'); ?>" alt="<?php echo esc_attr__('Google Wallet', 'truebeep-smart-wallet-loyalty'); ?>" />
                </a>
            </div>
        </div>

        <div class="panel-loading">
            <div class="spinner"></div>
        </div>
    </div>
</div>