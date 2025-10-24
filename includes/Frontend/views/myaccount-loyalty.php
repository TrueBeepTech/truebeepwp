<?php
/**
 * My Account - Loyalty Points template
 * 
 * @var int $points Current points balance
 * @var int $total_earned Total earned points
 * @var int $total_spent Total spent points
 * @var string $tier_name Current tier name
 * @var string $apple_wallet_url Apple Wallet URL
 * @var string $google_wallet_url Google Wallet URL
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="truebeep-loyalty-account">
    
    <!-- Points Overview Section -->
    <div class="loyalty-overview">
        <div class="loyalty-card points-balance">
            <div class="card-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="currentColor"/>
                </svg>
            </div>
            <div class="card-content">
                <h3><?php esc_html_e('Available Points', 'truebeep'); ?></h3>
                <div class="points-value"><?php echo number_format($points); ?></div>
            </div>
        </div>

        <div class="loyalty-card tier-status">
            <div class="card-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 7C5 5.89543 5.89543 5 7 5H17C18.1046 5 19 5.89543 19 7V19L12 15.5L5 19V7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="card-content">
                <h3><?php esc_html_e('Current Tier', 'truebeep'); ?></h3>
                <div class="tier-value"><?php echo esc_html(ucfirst($tier_name)); ?></div>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <div class="loyalty-stats">
        <div class="stat-item">
            <span class="stat-label"><?php esc_html_e('Total Earned Points', 'truebeep'); ?></span>
            <span class="stat-value"><?php echo number_format($total_earned); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php esc_html_e('Total Redeemed Points', 'truebeep'); ?></span>
            <span class="stat-value"><?php echo number_format($total_spent); ?></span>
        </div>
    </div>

    <!-- Digital Wallets Section -->
    <?php if ($apple_wallet_url || $google_wallet_url): ?>
    <div class="loyalty-wallets">
        <h3><?php esc_html_e('Add to Digital Wallet', 'truebeep'); ?></h3>
        <p><?php esc_html_e('Keep your loyalty card handy by adding it to your digital wallet.', 'truebeep'); ?></p>
        
        <div class="wallet-buttons">
            <?php if ($apple_wallet_url): ?>
            <a href="<?php echo esc_url($apple_wallet_url); ?>" class="wallet-btn apple-wallet" target="_blank" rel="noopener">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.71 19.5C17.88 20.74 17 21.95 15.66 21.97C14.32 22 13.89 21.18 12.37 21.18C10.84 21.18 10.37 21.95 9.09997 22C7.78997 22.05 6.79997 20.68 5.95997 19.47C4.24997 17 2.93997 12.45 4.69997 9.39C5.56997 7.87 7.12997 6.91 8.81997 6.88C10.1 6.86 11.32 7.75 12.11 7.75C12.89 7.75 14.37 6.68 15.92 6.84C16.57 6.87 18.39 7.1 19.56 8.82C19.47 8.88 17.39 10.1 17.41 12.63C17.44 15.65 20.06 16.66 20.09 16.67C20.06 16.74 19.67 18.11 18.71 19.5ZM13 3.5C13.73 2.67 14.94 2.04 15.94 2C16.07 3.17 15.6 4.35 14.9 5.19C14.21 6.04 13.07 6.7 11.95 6.61C11.8 5.46 12.36 4.26 13 3.5Z"/>
                </svg>
                <span><?php esc_html_e('Add to Apple Wallet', 'truebeep'); ?></span>
            </a>
            <?php endif; ?>
            
            <?php if ($google_wallet_url): ?>
            <a href="<?php echo esc_url($google_wallet_url); ?>" class="wallet-btn google-wallet" target="_blank" rel="noopener">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M21.35 11.1H12.18V13.83H18.69C18.36 17.64 15.19 19.27 12.19 19.27C8.36 19.27 5 16.25 5 12C5 7.9 8.2 4.73 12.2 4.73C15.29 4.73 17.1 6.7 17.1 6.7L19 4.72C19 4.72 16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12C2.03 17.05 6.16 22 12.25 22C17.6 22 21.5 18.33 21.5 12.91C21.5 11.76 21.35 11.1 21.35 11.1Z" fill="currentColor"/>
                </svg>
                <span><?php esc_html_e('Add to Google Wallet', 'truebeep'); ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- How It Works Section -->
    <div class="loyalty-info">
        <h3><?php esc_html_e('How Loyalty Points Work', 'truebeep'); ?></h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon">💰</div>
                <h4><?php esc_html_e('Earn Points', 'truebeep'); ?></h4>
                <p><?php esc_html_e('Earn points with every purchase. The more you shop, the more you earn!', 'truebeep'); ?></p>
            </div>
            <div class="info-item">
                <div class="info-icon">🎁</div>
                <h4><?php esc_html_e('Redeem Rewards', 'truebeep'); ?></h4>
                <p><?php esc_html_e('Use your points for discounts on future purchases at checkout.', 'truebeep'); ?></p>
            </div>
            <div class="info-item">
                <div class="info-icon">⭐</div>
                <h4><?php esc_html_e('Level Up', 'truebeep'); ?></h4>
                <p><?php esc_html_e('Reach higher tiers for better rewards and exclusive benefits.', 'truebeep'); ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Transactions (Optional - placeholder for future) -->
    <?php if (false): // Set to true when transaction history is implemented ?>
    <div class="loyalty-transactions">
        <h3><?php esc_html_e('Recent Transactions', 'truebeep'); ?></h3>
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'truebeep'); ?></th>
                    <th><?php esc_html_e('Description', 'truebeep'); ?></th>
                    <th><?php esc_html_e('Points', 'truebeep'); ?></th>
                    <th><?php esc_html_e('Balance', 'truebeep'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Transaction rows would go here -->
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
</div>