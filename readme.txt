=== Truebeep: Smart Wallet Loyalty ===
Contributors: truebeep
Tags: loyalty, points, rewards, woocommerce, wallet
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reward customers with points they can track and redeem via Wallet. Retain them with smart tools.

== Description ==

Truebeep is an all-in-one loyalty and marketing solution that helps WooCommerce stores reward customers and encourage repeat purchases. Set up flexible loyalty programs where shoppers earn points on purchases and redeem them instantly at checkout. With support for Apple Wallet and Google Wallet, customers can easily track and use their rewards from their phones. Branded wallet passes and push notifications create a smooth and engaging experience.

= Key Features =

* **Earn & Redeem Points**: Collect points with each order, use them for discounts.
* **Digital Wallets**: Sync rewards to Google & Apple Wallets for easy access.
* **Marketing Suite**: Automate campaigns, personalize offers, and track insights.

= Additional Features =

**Points & Rewards System**
* Earn points on every purchase with configurable rates
* Flexible redemption options at checkout
* Automatic point adjustments for refunds and cancellations
* Real-time point balance updates

**Tier-Based Loyalty**
* Create unlimited customer tiers
* Progressive benefits and multipliers
* Automatic tier advancement
* VIP recognition and exclusive perks

**Customer Experience**
* Floating loyalty panel showing point balance
* Dedicated My Account dashboard integration
* Responsive design for all devices
* Instant notifications for point earnings

**Flexible Redemption Options**
* Dynamic coupons - customers choose exact points to redeem
* Predefined coupons - fixed redemption values ($5, $10, $25)
* Real-time discount calculations
* Customizable redemption rates

**Admin Features**
* Comprehensive WooCommerce integration
* Order management with point details
* Bulk customer synchronization
* Detailed transaction logging
* Performance monitoring dashboard

= How It Works =

1. **Customers Shop** - Earn points with every purchase based on your configured rates
2. **Track Progress** - View points and tier status in real-time via account dashboard
3. **Redeem Rewards** - Apply points for instant discounts at checkout
4. **Level Up** - Unlock better benefits as customers reach new tiers

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 4.0 or higher
* PHP 7.2 or higher
* SSL Certificate (recommended)
* Truebeep API account (sign up at truebeep.com)

= API Integration =

Truebeep uses secure API integration to synchronize customer data and manage loyalty points. The plugin communicates with Truebeep's servers to:
* Sync customer information
* Update point balances
* Manage tier assignments
* Generate wallet passes

All data transmission is encrypted and follows industry security standards.

= Privacy & Data =

This plugin processes the following customer data:
* Email addresses
* Names
* Order history
* Point balances
* Tier information

Data is transmitted securely to Truebeep servers for loyalty program management. For complete privacy information, please visit truebeep.com/privacy.

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Truebeep"
3. Click "Install Now" and then "Activate"
4. Navigate to WooCommerce > Settings > Truebeep
5. Enter your API credentials from your Truebeep dashboard
6. Configure your loyalty program settings

= Manual Installation =

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Extract the zip file
4. Activate the plugin through the Plugins menu
5. Go to WooCommerce > Settings > Truebeep for configuration

= Configuration =

After activation:
1. Navigate to WooCommerce > Settings > Truebeep
2. Enter your API URL: `https://api.truebeep.com/v1`
3. Enter your API Key from the Truebeep dashboard
4. Configure point earning rates
5. Set up customer tiers
6. Choose redemption options
7. Save settings

== Frequently Asked Questions ==

= How do I get API credentials? =

Sign up for a Truebeep account at truebeep.com. Your API credentials will be available in your dashboard under Settings > API.

= How do I configure the API settings? =

1. Use `https://api.truebeep.com/v1` as the API URL
2. Enter your API Key generated from the Truebeep dashboard
3. Save settings and test the connection

= How do I add wallet passes? =

To add wallet passes for your customers:
1. Log in to your Truebeep dashboard
2. Navigate to the Wallet Pass section
3. Create or select a wallet pass template
4. Copy the wallet pass ID
5. Paste it into the Wallet Template ID field in WooCommerce > Settings > Truebeep

= Can customers use points with other discounts? =

Yes, the plugin is compatible with WooCommerce coupons and other discount methods. You can configure whether points can be combined with other offers in the settings.

= How are points calculated? =

Points are calculated based on the order total after discounts (excluding shipping and taxes). You can set different earning rates for different customer tiers.

= What happens to points when an order is refunded? =

Points are automatically revoked when an order is refunded or cancelled. The customer's point balance is adjusted accordingly.

= Is there a minimum order value for earning points? =

You can configure minimum order values in the plugin settings. Orders below this threshold won't earn points.

= Can I manually adjust customer points? =

Yes, administrators can manually adjust customer points through the WordPress admin panel under Users or through the Truebeep dashboard.

= Do points expire? =

Point expiration can be configured in your Truebeep dashboard settings. You can set expiration periods or choose to have points never expire.

= How do I sync existing customers? =

Go to Users > Sync to Truebeep in your WordPress admin. Click "Start Sync" to begin synchronizing all existing customers with the Truebeep platform.

== Screenshots ==

1. WooCommerce settings page for Truebeep configuration
2. Customer loyalty dashboard in My Account area
3. Floating loyalty panel showing point balance
4. Checkout page with point redemption options
5. Admin order view with loyalty point information
6. Customer sync interface for bulk operations
7. Wallet pass integration example
8. Tier configuration settings

== Additional Information ==

= Support =

For support, please visit our documentation at truebeep.com/docs or contact our support team.

= Contributing =

We welcome contributions and suggestions. Please visit our GitHub repository or contact us through our website.

= Credits =

Truebeep is developed and maintained by the Truebeep team. Special thanks to the WordPress and WooCommerce communities for their continued support.