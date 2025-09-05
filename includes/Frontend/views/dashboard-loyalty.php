<?php
/**
 * Dashboard - Loyalty Section template
 * 
 * @var string $user_name User's display name
 * @var int $points Current points balance
 * @var string $tier_name Current tier name
 * @var string $apple_wallet_url Apple Wallet URL
 * @var string $google_wallet_url Google Wallet URL
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="truebeep-dashboard-loyalty">
    
    <div class="loyalty-welcome">
        <div class="welcome-content">
            <p class="welcome-content-text">
                <?php 
                printf(
                    __('Hello %s, you have %s loyalty points', 'truebeep'),
                    '<strong>' . esc_html($user_name) . '</strong>',
                    '<span class="points-count">' . number_format($points) . '</span>'
                );
                ?>
            </p>
            
            <?php if ($tier_name && $tier_name !== 'bronze'): ?>
            <p class="tier-status">
                <?php printf(__('Your current tier: %s', 'truebeep'), '<strong>' . esc_html(ucfirst($tier_name)) . '</strong>'); ?>
            </p>
            <?php endif; ?>
        </div>
        
       
    </div>

    <?php if ($apple_wallet_url || $google_wallet_url): ?>
    <div class="loyalty-wallet-section">
        <p><?php _e('Download wallet card for easy access to your rewards and points', 'truebeep'); ?></p>
        
        <div class="wallet-buttons-dashboard">
            <?php if ($apple_wallet_url): ?>
            <a href="<?php echo esc_url($apple_wallet_url); ?>" class="wallet-btn-dashboard apple-wallet" target="_blank" rel="noopener">
                <img src="<?php echo esc_url('https://pub-de9716fb9a7948d9a9929d6c524f14f0.r2.dev/add-to-apple-wallet-button.svg'); ?>" alt="Apple Wallet" />
            </a>
            <?php endif; ?>
            
            <?php if ($google_wallet_url): ?>
            <a href="<?php echo esc_url($google_wallet_url); ?>" class="wallet-btn-dashboard google-wallet" target="_blank" rel="noopener">
                <img src="<?php echo esc_url('https://pub-de9716fb9a7948d9a9929d6c524f14f0.r2.dev/add-to-google-wallet-button.svg'); ?>" alt="Google Wallet" />
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
</div>