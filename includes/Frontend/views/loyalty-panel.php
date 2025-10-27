<div id="truebeep-loyalty-panel" class="truebeep-loyalty-panel <?php echo esc_attr($panel_position); ?>" data-customer-id="<?php echo esc_attr($truebeep_customer_id); ?>">
    <div class="panel-toggle">
        Rewards
        <span class="points-badge">--</span>
    </div>

    <div class="panel-content">
        <div class="panel-header">
            <button class="panel-close" aria-label="<?php esc_attr_e('Close panel', 'truebeep'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="panel-body">
            <!-- Welcome Message -->
            <div class="welcome-message">
                <?php esc_html_e('Welcome back, ', 'truebeep'); ?><span class="user-name"></span><?php esc_html_e('!', 'truebeep'); ?>
            </div>

            <!-- Points Display -->
            <div class="points-display">
                <span class="points-number">--</span>
                <span class="points-label"><?php esc_html_e('Point', 'truebeep'); ?></span>
            </div>

            <!-- Tier Badge -->
            <div class="tier-badge">
                <span class="tier-label">--</span>
            </div>

            <!-- Wallet Description -->
            <p class="wallet-description">
                <?php esc_html_e('Download wallet card for easy access to your rewards and points', 'truebeep'); ?>
            </p>

            <!-- Wallet Buttons -->
            <div class="wallet-buttons">
                <a href="#" class="wallet-btn apple-wallet" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url('https://pub-de9716fb9a7948d9a9929d6c524f14f0.r2.dev/add-to-apple-wallet-button.svg'); ?>" alt="Apple Wallet" />
                </a>
                <a href="#" class="wallet-btn google-wallet" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url('https://pub-de9716fb9a7948d9a9929d6c524f14f0.r2.dev/add-to-google-wallet-button.svg'); ?>" alt="Google Wallet" />
                </a>
            </div>
        </div>

        <div class="panel-loading">
            <div class="spinner"></div>
        </div>
    </div>
</div>