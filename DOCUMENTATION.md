# Truebeep - WooCommerce Loyalty Points & Rewards Plugin

## Table of Contents
1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Initial Setup](#initial-setup)
5. [Configuration](#configuration)
   - [Credentials](#credentials)
   - [Loyalty Settings](#loyalty-settings)
   - [Wallet Settings](#wallet-settings)
6. [Features](#features)
7. [Customer Experience](#customer-experience)
8. [Admin Management](#admin-management)
9. [Shortcodes](#shortcodes)
10. [Troubleshooting](#troubleshooting)
11. [Developer Guide](#developer-guide)

---

## Overview

Truebeep is a comprehensive loyalty points and rewards plugin for WooCommerce that enables merchants to create and manage customer loyalty programs. The plugin integrates with the Truebeep API to provide seamless point tracking, tier-based rewards, and digital wallet functionality.

### Key Features
- **Points-based loyalty program** with customizable earning and redemption rates
- **Tier-based rewards** with different benefits per tier
- **Digital wallet integration** (Apple Wallet & Google Wallet)
- **Flexible redemption options** (dynamic or predefined coupons)
- **Floating loyalty panel** for customer engagement
- **My Account integration** for point tracking
- **Comprehensive order management** with point tracking

---

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- SSL certificate (recommended for API communications)
- Truebeep API credentials

---

## Installation

### Method 1: WordPress Admin Upload
1. Download the `truebeep.zip` plugin file
2. Navigate to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** button
4. Choose the downloaded zip file
5. Click **Install Now**
6. Activate the plugin after installation

### Method 2: FTP Upload
1. Extract the `truebeep.zip` file
2. Upload the `truebeep` folder to `/wp-content/plugins/` directory
3. Navigate to **WordPress Admin → Plugins**
4. Find "Truebeep" in the plugin list
5. Click **Activate**

### Method 3: Composer Installation
```bash
composer require truebeep/woocommerce-loyalty
```

---

## Initial Setup

After activation, complete the initial setup:

1. Navigate to **WooCommerce → Settings → Truebeep**
2. You'll see three tabs: **Credentials**, **Loyalty**, and **Wallet**
3. Start with the **Credentials** tab to configure API access

---

## Configuration

### Credentials

Navigate to **WooCommerce → Settings → Truebeep → Credentials**

#### API Configuration

| Setting | Description | Required |
|---------|-------------|----------|
| **API URL** | Your Truebeep API endpoint URL | Yes |
| **API Username** | Your Truebeep API username | Yes |
| **API Password** | Your Truebeep API password | Yes |
| **Enable Test Mode** | Toggle to use test environment | No |

**Example Configuration:**
```
API URL: https://api.truebeep.com
API Username: merchant_12345
API Password: ••••••••••••
```

### Connect TrueBeep to Shopify

To connect TrueBeep to your Shopify store, add the API URL and API Key in the TrueBeep settings:

1. **API URL**: Use `https://api.truebeep.com/v1` as the API URL
2. **API Key**: Enter your API Key generated from the TrueBeep dashboard

**Configuration Steps:**
1. Navigate to **WooCommerce → Settings → Truebeep → Credentials**
2. Enter `https://api.truebeep.com/v1` in the API URL field
3. Enter your API Key from the TrueBeep dashboard in the appropriate field
4. Save your settings

### Loyalty Settings

Navigate to **WooCommerce → Settings → Truebeep → Loyalty**

#### Basic Configuration

| Setting | Description | Default | Example |
|---------|-------------|---------|---------|
| **Way to Redeem** | How customers redeem points | Dynamic Coupon | - |
| **Earning Value** | Points earned per currency unit | 1 | 1 point per $1 |
| **Redeeming Value** | Points needed for $1 discount | 100 | 100 points = $1 |
| **Earn Points on Redeemed Orders** | Allow earning on orders with redemptions | No | - |
| **Award Points on Order Status** | When to award points | Completed | - |

#### Way to Redeem Options

**1. Dynamic Coupon**
- Customers enter any amount of points to redeem
- Flexible redemption amounts
- Real-time discount calculation

**2. Coupon (Predefined Values)**
- Fixed redemption options (e.g., $5, $10, $20)
- Dropdown selection at checkout
- Predictable discount values

#### Order Status Options

| Option | Description | Use Case |
|--------|-------------|----------|
| **Processing** | Award immediately after payment | Digital products, immediate gratification |
| **Completed** | Award after order fulfillment | Physical products, prevent fraud |
| **Both** | Award on either status | Mixed product types |

#### Tier Configuration

Create loyalty tiers with escalating benefits:

| Tier Name | Order to Points | Points to Amount | Threshold |
|-----------|----------------|------------------|-----------|
| Free | 1.0 | 100 | 0 |
| Bronze | 1.5 | 90 | 100 |
| Silver | 2.0 | 80 | 500 |
| Gold | 3.0 | 50 | 1000 |

**Explanation:**
- **Order to Points**: Multiplier for points earned (2.0 = 2x points)
- **Points to Amount**: Points needed per $1 discount (lower = better)
- **Threshold**: Points needed to reach this tier

#### Predefined Coupons

If using predefined redemption values:

| Coupon Name | Value |
|-------------|-------|
| $5 Off | 5 |
| $10 Off | 10 |
| $25 Off | 25 |
| $50 Off | 50 |

### Wallet Settings

Navigate to **WooCommerce → Settings → Truebeep → Wallet**

| Setting | Description | Example |
|---------|-------------|---------|
| **Wallet URL** | Base URL for wallet services | https://wallet.truebeep.com |
| **Wallet ID** | Your wallet template identifier | TEMPLATE_123 |
| **Apple Wallet Template ID** | Apple-specific template ID | APPLE_TEMPLATE_456 |
| **Google Wallet Template ID** | Google-specific template ID | GOOGLE_TEMPLATE_789 |
| **Show Floating Panel** | Enable loyalty panel on frontend | Yes |
| **Panel Position** | Where to display the panel | Bottom Right |

### Add Wallet Pass

To add a wallet pass for your customers, navigate to the TrueBeep dashboard and follow these steps:

1. **Navigate to the Wallet Pass section** in your TrueBeep dashboard
2. **Copy the wallet pass ID** from the dashboard
3. **Paste the wallet pass ID** into the Wallet Template ID field in the TrueBeep settings
4. **Save your settings** to enable wallet pass functionality

**Important Notes:**
- The wallet pass ID is unique to your store
- Different IDs may be needed for Apple Wallet and Google Wallet
- Wallet passes automatically sync with customer point balances
- [Learn more about Wallet Passes](https://docs.truebeep.com/wallet-passes)

---

## Features

### 1. Points Earning

Customers earn points through:
- **Purchase-based earning**: Points based on order total
- **Tier multipliers**: Higher tiers earn more points
- **Special promotions**: Bonus point events (via API)

**Calculation Example:**
```
Order Total: $100
Base Rate: 1 point per $1
Tier Multiplier: 2.0 (Silver)
Points Earned: 100 × 2.0 = 200 points
```

### 2. Points Redemption

#### Checkout Redemption
1. Customer reaches checkout page
2. Sees available points balance
3. Either:
   - **Dynamic**: Enters points to redeem
   - **Predefined**: Selects from dropdown
4. Discount applied automatically
5. Order total updates in real-time

#### Redemption Limits
- Cannot exceed order total
- Minimum/maximum thresholds apply
- Tier-based redemption rates

### 3. Floating Loyalty Panel

**Features:**
- Displays current points balance
- Shows tier status
- Quick access to wallet downloads
- Customizable position
- Mobile responsive

**Customer sees:**
```
Welcome back, John!
1,250 Points
SILVER TIER
[Apple Wallet] [Google Wallet]
```

### 4. My Account Integration

**Dashboard Section:**
- Points balance display
- Current tier status
- Wallet download buttons
- Recent transactions

**Access:** My Account → Dashboard

### 5. Digital Wallet Integration

**Apple Wallet:**
- One-click add to Apple Wallet
- Real-time balance updates
- Push notifications for changes

**Google Wallet:**
- Easy Google Pay integration
- Balance synchronization
- Cross-device accessibility

---

## Customer Experience

### Earning Points

1. **Making a Purchase**
   - Shop and add items to cart
   - Complete checkout
   - Points awarded based on settings:
     - Immediately (Processing status)
     - After fulfillment (Completed status)

2. **Viewing Points**
   - Floating panel (if enabled)
   - My Account dashboard
   - Order confirmation emails

3. **Tier Progression**
   - Automatic tier upgrades
   - Better earning rates
   - Improved redemption values

### Redeeming Points

1. **At Checkout**
   - View available balance
   - Choose redemption amount/coupon
   - Apply discount
   - Complete purchase

2. **Redemption Examples**

**Dynamic Redemption:**
```
Available: 5,000 points
Enter: 2,500 points
Discount: $25.00 (at 100 points = $1)
```

**Predefined Coupon:**
```
Available: 5,000 points
Select: "$50 Off Coupon"
Required: 5,000 points
Discount: $50.00
```

---

## Admin Management

### Order Management

**View Points Information:**
1. Navigate to **WooCommerce → Orders**
2. Click on any order
3. View "Loyalty Points Summary" section

**Information Displayed:**
- Points Earned (with status)
- Points Redeemed
- Point adjustments for refunds

### Customer Points Management

**Via User Profile:**
1. Navigate to **Users → All Users**
2. Edit user profile
3. View Truebeep Customer ID
4. See current points balance

### Point Adjustments

**Order Cancellation:**
- Earned points automatically revoked
- Redeemed points automatically returned
- Order notes added for tracking

**Partial Refunds:**
- Proportional point adjustments
- Automatic calculations
- Detailed order notes

### Reports and Analytics

**Track via Order Notes:**
- Point awards
- Point redemptions
- Adjustments and refunds
- Failed transactions

---

## Shortcodes

### [truebeep_loyalty]

Display loyalty information anywhere on your site.

**Basic Usage:**
```
[truebeep_loyalty]
```

**Parameters:**

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| show_points | true/false | true | Display points balance |
| show_tier | true/false | true | Display tier status |
| show_wallet | true/false | true | Show wallet buttons |
| layout | horizontal/vertical/compact | horizontal | Layout style |
| style | default/card/minimal | default | Visual style |

**Examples:**

```
// Points only
[truebeep_loyalty show_tier="false" show_wallet="false"]

// Compact for sidebar
[truebeep_loyalty layout="compact" style="minimal"]

// Full card style
[truebeep_loyalty layout="vertical" style="card"]
```

---

## Troubleshooting

### Common Issues

#### 1. Points Not Awarding

**Check:**
- Order status matches settings
- API credentials are correct
- Customer has Truebeep ID
- "Earn on Redeemed Orders" setting

**Solution:**
1. Verify API connection in Credentials
2. Check order notes for errors
3. Ensure WooCommerce order emails are sent

#### 2. Redemption Not Working

**Check:**
- Customer has sufficient points
- Redemption method is configured
- JavaScript errors in console

**Solution:**
1. Clear cache
2. Check browser console for errors
3. Verify API connectivity

#### 3. Floating Panel Not Showing

**Check:**
- Setting enabled in Wallet tab
- User is logged in
- No JavaScript conflicts

**Solution:**
1. Enable in settings
2. Check theme compatibility
3. Test in different browser

### API Connection Issues

**Test Connection:**
1. Go to Credentials tab
2. Save settings
3. Check for success message

**Common Errors:**

| Error | Cause | Solution |
|-------|-------|----------|
| 401 Unauthorized | Invalid credentials | Verify username/password |
| 404 Not Found | Wrong API URL | Check API endpoint |
| 500 Server Error | API server issue | Contact support |
| Timeout | Network issue | Check firewall/hosting |

---

## Developer Guide

### Hooks and Filters

#### Actions

```php
// After points awarded
do_action('truebeep_points_awarded', $order_id, $points, $customer_id);

// After points redeemed
do_action('truebeep_points_redeemed', $order_id, $points, $customer_id);

// After tier change
do_action('truebeep_tier_changed', $user_id, $old_tier, $new_tier);
```

#### Filters

```php
// Modify points calculation
add_filter('truebeep_calculate_points', function($points, $order_total, $user_id) {
    // Custom logic
    return $points;
}, 10, 3);

// Modify redemption rate
add_filter('truebeep_redemption_rate', function($rate, $user_id) {
    // Custom rate logic
    return $rate;
}, 10, 2);
```

### Database Structure

#### User Meta

| Meta Key | Description |
|----------|-------------|
| `_truebeep_customer_id` | Truebeep API customer ID |
| `_truebeep_points_balance` | Current points (cached) |
| `_truebeep_current_tier` | Current tier name |

#### Order Meta

| Meta Key | Description |
|----------|-------------|
| `_truebeep_points_earned` | Points earned on order |
| `_truebeep_points_awarded` | Award status (yes/no) |
| `_truebeep_points_redeemed_amount` | Points used |
| `_truebeep_points_returned` | Return status |

### API Integration

#### Making API Calls

```php
use Truebeep\Traits\ApiHelper;

class CustomClass {
    use ApiHelper;
    
    public function get_customer_data($customer_id) {
        return $this->make_api_request(
            'customer/' . $customer_id,
            'GET'
        );
    }
}
```

#### Available API Methods

```php
// Get customer points
$points = $this->get_customer_points($customer_id);

// Update points
$this->update_loyalty_points($customer_id, $points, 'increment', 'custom_reason');

// Get customer tier
$tier = $this->get_customer_tier($customer_id);
```

---

## Support and Resources

### Getting Help

1. **Documentation**: This guide
2. **Support Email**: support@truebeep.com
3. **GitHub Issues**: [Report bugs](https://github.com/truebeep/woocommerce-plugin/issues)
4. **Community Forum**: [Join discussions](https://community.truebeep.com)

### Updates

Check for updates regularly:
1. WordPress Admin → Updates
2. Review changelog before updating
3. Backup before major updates

### Contributing

We welcome contributions:
1. Fork the repository
2. Create feature branch
3. Submit pull request
4. Follow coding standards

---

## License

This plugin is licensed under the GPL v2 or later.

---

## Changelog

### Version 1.0.0
- Initial release
- Points earning and redemption
- Tier system
- Digital wallet integration
- My Account integration
- Floating panel
- Admin management tools

---

*Last updated: 2025*
*Documentation version: 1.0.0*