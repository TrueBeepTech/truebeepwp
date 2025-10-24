<?php
/**
 * Loyalty Shortcode Template
 * 
 * @var array $attributes Shortcode attributes
 * @var string $user_name User's display name
 * @var int $points Current points balance
 * @var string $tier_name Current tier name
 * @var string $apple_wallet_url Apple Wallet URL
 * @var string $google_wallet_url Google Wallet URL
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sanitize attributes
$show_points = $attributes['show_points'] === 'true';
$show_tier = $attributes['show_tier'] === 'true';
$show_wallet = $attributes['show_wallet'] === 'true';
$layout = sanitize_text_field($attributes['layout']);
$style = sanitize_text_field($attributes['style']);

// Set classes
$container_classes = [
    'truebeep-loyalty-shortcode',
    'layout-' . $layout,
    'style-' . $style
];
?>

<div class="<?php echo esc_attr(implode(' ', $container_classes)); ?>">
    
    <?php if ($show_points || $show_tier): ?>
    <div class="loyalty-welcome">
        <div class="welcome-content">
            <?php if ($show_points): ?>
            <h3>
                <?php 
                printf(
                    /* translators: %1$s: user name, %2$s: number of loyalty points */
                    esc_html__('Hello %1$s, you have %2$s loyalty points', 'truebeep'),
                    '<span class="user-name">' . esc_html($user_name) . '</span>',
                    '<span class="points-count">' . number_format($points) . '</span>'
                );
                ?>
            </h3>
            <?php endif; ?>
            
            <?php if ($show_tier && $tier_name && $tier_name !== 'bronze'): ?>
            <p class="tier-status">
                <?php 
                /* translators: %s: tier name */
                echo wp_kses_post(sprintf(__('Your current tier: %s', 'truebeep'), '<strong>' . esc_html(ucfirst($tier_name)) . '</strong>')); 
                ?>
            </p>
            <?php endif; ?>
        </div>
        
        <div class="loyalty-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="currentColor"/>
            </svg>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($show_wallet && ($apple_wallet_url || $google_wallet_url)): ?>
    <div class="loyalty-wallet-section">
        <h4><?php esc_html_e('Add your loyalty card to your digital wallet', 'truebeep'); ?></h4>
        
        <div class="wallet-buttons-dashboard">
            <?php if ($apple_wallet_url): ?>
            <a href="<?php echo esc_url($apple_wallet_url); ?>" class="wallet-btn-dashboard apple-wallet" target="_blank" rel="noopener">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.71 19.5C17.88 20.74 17 21.95 15.66 21.97C14.32 22 13.89 21.18 12.37 21.18C10.84 21.18 10.37 21.95 9.09997 22C7.78997 22.05 6.79997 20.68 5.95997 19.47C4.24997 17 2.93997 12.45 4.69997 9.39C5.56997 7.87 7.12997 6.91 8.81997 6.88C10.1 6.86 11.32 7.75 12.11 7.75C12.89 7.75 14.37 6.68 15.92 6.84C16.57 6.87 18.39 7.1 19.56 8.82C19.47 8.88 17.39 10.1 17.41 12.63C17.44 15.65 20.06 16.66 20.09 16.67C20.06 16.74 19.67 18.11 18.71 19.5ZM13 3.5C13.73 2.67 14.94 2.04 15.94 2C16.07 3.17 15.6 4.35 14.9 5.19C14.21 6.04 13.07 6.7 11.95 6.61C11.8 5.46 12.36 4.26 13 3.5Z"/>
                </svg>
                <span><?php esc_html_e('Apple Wallet', 'truebeep'); ?></span>
            </a>
            <?php endif; ?>
            
            <?php if ($google_wallet_url): ?>
            <a href="<?php echo esc_url($google_wallet_url); ?>" class="wallet-btn-dashboard google-wallet" target="_blank" rel="noopener">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M21.35 11.1H12.18V13.83H18.69C18.36 17.64 15.19 19.27 12.19 19.27C8.36 19.27 5 16.25 5 12C5 7.9 8.2 4.73 12.2 4.73C15.29 4.73 17.1 6.7 17.1 6.7L19 4.72C19 4.72 16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12C2.03 17.05 6.16 22 12.25 22C17.6 22 21.5 18.33 21.5 12.91C21.5 11.76 21.35 11.1 21.35 11.1Z" fill="currentColor"/>
                </svg>
                <span><?php esc_html_e('Google Wallet', 'truebeep'); ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
</div>