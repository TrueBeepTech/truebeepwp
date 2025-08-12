# Truebeep - WooCommerce Loyalty Points & Rewards

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-4.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Transform your WooCommerce store into a customer retention powerhouse with Truebeep - the comprehensive loyalty points and rewards solution that drives repeat purchases and increases customer lifetime value.

## 🎯 Overview

Truebeep is a feature-rich loyalty points plugin for WooCommerce that seamlessly integrates with the Truebeep API platform to provide enterprise-grade loyalty program management. Whether you're running a small boutique or a large-scale e-commerce operation, Truebeep scales with your business to deliver personalized rewards that keep customers coming back.

### Why Choose Truebeep?

- **🚀 Easy Setup**: Get your loyalty program running in minutes with intuitive configuration
- **💳 Digital Wallet Ready**: Apple Wallet and Google Wallet integration out of the box
- **📊 Tier-Based Rewards**: Create VIP experiences with customizable tier benefits
- **🎨 Flexible Redemption**: Dynamic or fixed-value coupon redemption options
- **📱 Mobile Optimized**: Responsive design ensures perfect experience on all devices
- **🔄 Real-Time Sync**: Instant point updates via secure API integration
- **🛡️ Enterprise Security**: Bank-level encryption for all transactions

## ✨ Key Features

### Points Management
- **Smart Earning Rules**: Configure points per dollar spent with tier multipliers
- **Flexible Redemption**: Let customers choose how to spend their points
- **Automatic Adjustments**: Handle refunds and cancellations automatically
- **Order Status Control**: Award points at processing or completion

### Customer Experience
- **Floating Loyalty Panel**: Always-visible point balance and quick actions
- **My Account Integration**: Dedicated dashboard for loyalty program details
- **Instant Notifications**: Real-time updates on point earnings and tier changes
- **One-Click Wallet Add**: Digital wallet cards for iOS and Android

### Advanced Tiers System
- **Unlimited Tiers**: Create as many loyalty tiers as needed
- **Progressive Benefits**: Increasing earn rates and better redemption values
- **Automatic Upgrades**: Customers advance tiers based on point thresholds
- **VIP Recognition**: Special badges and exclusive perks for top tiers

### Redemption Options

#### Dynamic Coupons
- Customers enter exact points to redeem
- Real-time discount calculation
- Maximum flexibility

#### Predefined Coupons
- Set fixed redemption values ($5, $10, $25, etc.)
- Simple dropdown selection
- Predictable discount structure

### Admin Tools
- **Comprehensive Dashboard**: Monitor program performance at a glance
- **Order Integration**: View points data directly in WooCommerce orders
- **Bulk Actions**: Process multiple point adjustments efficiently
- **Detailed Logging**: Complete audit trail of all point transactions

## 🚀 Quick Start

### Installation

1. Upload the plugin to `/wp-content/plugins/truebeep/`
2. Activate through the WordPress Plugins menu
3. Navigate to WooCommerce → Settings → Truebeep
4. Enter your API credentials
5. Configure loyalty settings
6. Start rewarding customers!

### Basic Configuration

```
WooCommerce → Settings → Truebeep → Credentials
├── API URL: Your Truebeep API endpoint
├── Username: Your API username
└── Password: Your API password

WooCommerce → Settings → Truebeep → Loyalty
├── Earning Rate: 1 point per $1
├── Redemption Rate: 100 points = $1
└── Order Status: When to award points
```

## 📖 Documentation

For detailed setup instructions, configuration guides, and troubleshooting:

📘 [Read Full Documentation](DOCUMENTATION.md)

### Quick Links
- [Installation Guide](DOCUMENTATION.md#installation)
- [Configuration Settings](DOCUMENTATION.md#configuration)
- [Shortcode Reference](DOCUMENTATION.md#shortcodes)
- [Developer API](DOCUMENTATION.md#developer-guide)
- [Troubleshooting](DOCUMENTATION.md#troubleshooting)

## 🎮 Demo

See Truebeep in action:

### Customer Journey
1. **Shop & Earn**: Customers earn points with every purchase
2. **Track Progress**: View points and tier status in real-time
3. **Redeem Rewards**: Apply points for instant discounts at checkout
4. **Level Up**: Unlock better benefits as they reach new tiers

### Admin Experience
1. **Monitor**: Track program metrics from the dashboard
2. **Manage**: Handle points adjustments and customer tiers
3. **Analyze**: Review performance reports and insights
4. **Optimize**: Adjust settings based on customer behavior

## 💻 Shortcode

Display loyalty information anywhere on your site:

```
[truebeep_loyalty]
```

**With Parameters:**
```
[truebeep_loyalty show_points="true" show_tier="true" show_wallet="true" layout="horizontal" style="card"]
```

## 🔧 Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher
- SSL Certificate (recommended)
- Truebeep API Account

## 🛠️ Developer Features

### Hooks & Filters

```php
// Customize point calculations
add_filter('truebeep_calculate_points', 'custom_points_logic', 10, 3);

// React to point awards
add_action('truebeep_points_awarded', 'notify_customer', 10, 3);

// Modify redemption rates
add_filter('truebeep_redemption_rate', 'dynamic_rates', 10, 2);
```

### API Integration

```php
use Truebeep\Traits\ApiHelper;

// Get customer points
$points = $this->get_customer_points($customer_id);

// Update points
$this->update_loyalty_points($customer_id, $points, 'increment', 'reason');
```

## 📊 Use Cases

### E-Commerce Store
- Reward purchases with points
- Offer tier-based shipping discounts
- Create VIP customer experiences

### Subscription Business
- Points for subscription renewals
- Bonus points for annual plans
- Exclusive tier benefits

### Multi-Channel Retail
- Unified loyalty across channels
- Digital wallet for in-store use
- Omnichannel point redemption

## 🤝 Support

### Getting Help
- 📧 Email: support@truebeep.com
- 💬 Forum: [Community Support](https://community.truebeep.com)
- 🐛 Issues: [GitHub Issues](https://github.com/truebeep/woocommerce-plugin/issues)
- 📖 Docs: [Full Documentation](DOCUMENTATION.md)

### Premium Support
Priority support available for enterprise customers. [Contact Sales](mailto:sales@truebeep.com)

## 🔄 Changelog

### Version 1.0.0 (2025)
- ✅ Initial release
- ✅ Points earning and redemption system
- ✅ Tier-based rewards structure
- ✅ Digital wallet integration (Apple & Google)
- ✅ Floating loyalty panel
- ✅ My Account dashboard integration
- ✅ Order management with points tracking
- ✅ Automatic refund handling
- ✅ Customizable earning triggers
- ✅ Admin configuration interface

## 🏆 Credits

### Development Team
- **Lead Developer**: Truebeep Development Team
- **API Integration**: Truebeep Platform Team
- **UI/UX Design**: Truebeep Design Team

### Third-Party Libraries
- [CMB2](https://github.com/CMB2/CMB2) - Custom Meta Boxes Framework
- [WP Background Processing](https://github.com/deliciousbrains/wp-background-processing) - Async Processing

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🌟 Contributing

We welcome contributions from the community!

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards
- Follow WordPress Coding Standards
- Write clean, documented code
- Include unit tests for new features
- Update documentation as needed

## Features (Coming Soon)

- **Advanced Analytics**: Deep insights into program performance
- **Email Automation**: Triggered campaigns based on point events
- **Social Sharing**: Bonus points for social media engagement
- **Referral Program**: Points for customer referrals
- **Custom Rewards**: Beyond discounts - free products, exclusive access
- **Multi-Store Support**: Manage multiple stores from one dashboard
- **White Label**: Full branding customization
- **Priority Support**: 24/7 dedicated support team

---

<p align="center">
  <strong>Build Customer Loyalty. Drive Repeat Sales. Grow Your Business.</strong>
</p>

<p align="center">
  Made with ❤️ by the <a href="https://truebeep.com">Truebeep Team</a>
</p>

---

**[⬆ back to top](#truebeep---woocommerce-loyalty-points--rewards)**
