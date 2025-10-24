# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Truebeep is a WordPress plugin that provides a comprehensive loyalty and marketing platform for WooCommerce stores. It enables customers to earn and redeem points, integrates with digital wallets (Apple/Google), and offers tier-based rewards.

## Commands

### Development
```bash
# Install PHP dependencies
composer install

# Update composer dependencies
composer update

# Check PHP syntax errors
php -l truebeep.php
php -l includes/**/*.php

# WordPress CLI commands (if wp-cli available)
wp plugin activate truebeep
wp plugin deactivate truebeep
```

### Code Quality
Since there's no formal linting setup, use PHP's built-in syntax checker:
```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

## Architecture

### Plugin Structure
- **Main Entry**: `truebeep.php` - Plugin initialization using singleton pattern
- **Namespace**: `Truebeep\` with sub-namespaces for different modules
- **Autoloading**: Composer PSR-4 autoloading via `vendor/autoload.php`

### Core Components

#### API Integration (`includes/Api/`)
- `LoyaltyHandler`: Manages WooCommerce order hooks for point awards/revocations
- `CustomerHandler`: Syncs customer data with external Truebeep API
- Uses `Traits\ApiHelper` for shared API communication logic
- External API endpoint: `https://api.truebeep.com/v1`

#### Frontend (`includes/Frontend/`)
- `LoyaltyPanel`: Floating widget for points display
- `MyAccount`: Dashboard integration for WooCommerce My Account
- `Checkout\PointsRedemption`: Handles point redemption at checkout
- Shortcode: `[truebeep_loyalty]` for displaying loyalty info

#### Admin (`includes/Admin/`)
- Settings integrated under WooCommerce → Settings → Truebeep
- Order management extensions to display point information
- User profile extensions for loyalty fields

#### Customer Sync System (`includes/Legacy/`)
- `SyncSettings`: Admin interface under Users → Sync to Truebeep
- `SyncManager`: Coordinates bulk customer synchronization with rate limiting
- `CustomerSyncProcessor`: Action Scheduler-based background processing
- `CustomerSyncer`: Handles bulk API operations and customer data mapping
- **Rate Limiting**: 20 customers per batch, 60-second intervals
- **Progress Tracking**: Real-time status updates with error logging
- **Auto-retry**: Failed batches are automatically rescheduled

#### Loyalty System (`includes/Loyalty/`)
- `PointsManager`: Singleton managing all point operations
- Tier-based rewards with configurable benefits
- Dynamic or fixed-value coupon redemption

### Key Design Patterns
- **Singleton Pattern**: Used for main plugin class and PointsManager
- **Trait-Based Code Reuse**: ApiHelper trait for API operations
- **Hook-Based Architecture**: WordPress actions/filters for extensibility
- **Clean Separation**: Admin/Frontend/API concerns separated

### Dependencies
- **WordPress**: 5.0+
- **WooCommerce**: 4.0+ (hard dependency)
- **PHP**: 7.2+
- **Action Scheduler**: Built into WooCommerce (used for background processing)
- **Composer Packages**:
  - `nesbot/carbon`: Date/time manipulation
  - `cmb2/cmb2`: Custom meta boxes framework
  - `johnbillion/extended-cpts`: Enhanced custom post types

### Database Usage
- Uses WordPress options table for settings
- User meta for loyalty data
- Order meta for point transactions
- No custom database tables

### Asset Management
- Assets in `assets/` directory (CSS/JS)
- No build process - direct file inclusion
- Version control via file modification timestamps
- Conditional loading based on context

### Update System
- Custom GitHub-based update mechanism in `includes/Update/`
- Checks GitHub releases for new versions
- Configuration in `update-config.php`

## Development Guidelines

### WordPress Standards
- Follow WordPress coding standards for PHP, JS, and CSS
- Use WordPress functions for database operations
- Implement proper sanitization and escaping
- Use WordPress hooks system for extensibility

### API Communication
- Always use the `ApiHelper` trait methods for external API calls
- Handle API failures gracefully with local fallbacks
- Implement rate limiting for API requests
- Never expose API credentials in code

### Point Operations
- Use `PointsManager::getInstance()` for all point operations
- Points are awarded based on order status (configurable)
- Automatic handling of refunds and cancellations
- Support for tier multipliers and custom calculations

### Customer Sync Operations
- Use `SyncManager` class for initiating bulk customer synchronization
- Background processing via Action Scheduler with rate limiting (20 customers/batch, 60s intervals)
- Progress tracking and error logging with detailed failure reasons
- Auto-retry mechanism for failed batches and stuck sync processes
- Real-time statistics and activity logs via AJAX-powered admin interface

### Testing Considerations
- No formal test suite exists - manual testing required
- Test with different WooCommerce order statuses
- Verify point calculations with various tier configurations
- Test API failures and recovery scenarios

### Common Filters/Actions
```php
// Modify point calculations
add_filter('truebeep_calculate_points', 'callback', 10, 3);

// React to point awards
add_action('truebeep_points_awarded', 'callback', 10, 3);

// Customize redemption rates
add_filter('truebeep_redemption_rate', 'callback', 10, 2);

// Customer sync hooks (Action Scheduler)
add_action('truebeep_customer_sync', 'callback', 10, 1); // Process batch
add_action('truebeep_customer_sync_complete', 'callback', 10); // Completion handler
```

## Important Files and Locations

- Main plugin file: `truebeep.php`
- Configuration: WooCommerce → Settings → Truebeep
- API integration: `includes/Api/LoyaltyHandler.php`
- Points logic: `includes/Loyalty/PointsManager.php`
- Frontend panel: `includes/Frontend/LoyaltyPanel.php`
- Checkout integration: `includes/Frontend/Checkout/PointsRedemption.php`
- Customer sync interface: Users → Sync to Truebeep (`includes/Legacy/SyncSettings.php`)
- Sync management: `includes/Legacy/SyncManager.php`
- Background processing: `includes/Legacy/CustomerSyncProcessor.php`
- Bulk API operations: `includes/Legacy/CustomerSyncer.php`
- Sync styles: `assets/css/sync-admin.css`
- Sync JavaScript: `assets/js/sync-admin.js`
- Update configuration: `update-config.php`

## Customer Sync Technical Details

### Action Scheduler Integration
- **Hook Registration**: Action hooks registered early in WordPress init (priority 1) to ensure availability
- **Background Processing**: Uses WooCommerce's Action Scheduler for reliable background processing
- **Rate Limiting**: 20 customers per batch with 60-second intervals to prevent API overload
- **Error Handling**: Comprehensive error logging with user-friendly error messages
- **Progress Tracking**: Real-time progress updates via AJAX polling

### Sync Process Flow
1. **Initialization**: `SyncManager::start_sync()` prepares customer batches
2. **Scheduling**: `CustomerSyncProcessor::schedule_sync()` creates Action Scheduler tasks
3. **Processing**: Background execution via `CustomerSyncProcessor::process_batch()`
4. **API Communication**: `CustomerSyncer::process_batch()` handles bulk API calls
5. **Completion**: Auto-detection and cleanup via `CustomerSyncProcessor::complete()`

### Database Queries
- Identifies customers without Truebeep IDs using efficient LEFT JOIN queries
- Excludes customers with empty or '0' Truebeep customer IDs
- Includes both role-based customers and order-based customer identification

### UI Features
- Full-width responsive design with modern card-based layout
- Real-time progress bars with animated stripes and shimmer effects
- Comprehensive activity log with error details and success summaries
- Cancel/reset functionality with confirmation prompts
- Estimated completion times and batch scheduling information