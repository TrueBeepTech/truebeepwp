=== Truebeep for WooCommerce ===
Contributors: truebeep
Donate link: https://truebeep.com
Tags: woocommerce, loyalty, points, rewards, wallet
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WooCommerce store with powerful loyalty programs, points-based rewards, and digital wallet functionality.

== Description ==

**Truebeep for WooCommerce** is a comprehensive loyalty and rewards plugin that helps you increase customer retention and boost sales through points-based rewards, tier systems, and digital wallet integration.

= Key Features =

* **Points-Based Loyalty Program** - Reward customers with points for purchases
* **Tiered Rewards System** - Create multiple loyalty tiers with different earning rates
* **Flexible Redemption Options** - Dynamic coupons or predefined discount values
* **Digital Wallet Integration** - Apple Wallet and Google Wallet pass generation
* **Floating Loyalty Panel** - Display customer points balance on frontend
* **Checkout Integration** - Seamless points redemption during checkout
* **Admin Dashboard** - Comprehensive settings and management interface

= How It Works =

1. **Earn Points** - Customers automatically earn points based on their order value
2. **Tier Progression** - Customers advance through tiers based on total earned points
3. **Redeem Rewards** - Apply points as discounts during checkout
4. **Track Progress** - View points balance and tier status in account dashboard

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* SSL certificate recommended for API communications

= Third-Party Services =

This plugin integrates with the Truebeep API service for loyalty program management. By using this plugin, you agree to:
* [Truebeep Terms of Service](https://truebeep.com/terms)
* [Truebeep Privacy Policy](https://truebeep.com/privacy)

The plugin communicates with Truebeep servers to:
* Manage customer loyalty points
* Track tier progression
* Generate digital wallet passes

== Installation ==

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins → Add New
3. Search for "Truebeep"
4. Click "Install Now" and then "Activate"
5. Configure the plugin at WooCommerce → Settings → Truebeep

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress dashboard
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the downloaded ZIP file
5. Click "Install Now" and then "Activate"
6. Configure the plugin at WooCommerce → Settings → Truebeep

= Configuration =

1. **API Credentials**
   - Navigate to WooCommerce → Settings → Truebeep
   - Enter your Truebeep API URL and API Key
   - Save changes

2. **Loyalty Settings**
   - Configure earning and redemption rates
   - Set up loyalty tiers with thresholds
   - Choose redemption method (dynamic or predefined coupons)

3. **Wallet Settings**
   - Enable/disable loyalty panel
   - Configure wallet base URL and template ID
   - Customize panel position

== Frequently Asked Questions ==

= Do I need a Truebeep account? =

Yes, you need a Truebeep account to obtain API credentials. Visit [truebeep.com](https://truebeep.com) to sign up.

= Is WooCommerce required? =

Yes, this plugin extends WooCommerce functionality and requires WooCommerce to be installed and activated.

= Can customers earn points on discounted orders? =

Yes, there's an option to allow customers to earn points even on orders where points were redeemed.

= How do loyalty tiers work? =

Tiers are based on total earned points (lifetime). Each tier can have different earning and redemption rates. Customers automatically progress to higher tiers as they accumulate points.

= Can I customize the points redemption interface? =

Yes, the checkout interface can be styled with custom CSS. The floating loyalty panel position can also be configured.

= Is the plugin compatible with other WooCommerce extensions? =

The plugin follows WooCommerce best practices and should be compatible with most extensions. However, conflicts may occur with other loyalty/points plugins.

= How are points calculated? =

Points are calculated based on the order subtotal (before tax and shipping) multiplied by the earning rate for the customer's tier.

= Can points expire? =

Point expiration is managed through the Truebeep API. Configure expiration rules in your Truebeep account.

== Screenshots ==

1. WooCommerce settings integration - Credentials tab
2. Loyalty configuration with tiers and earning rates
3. Wallet settings and panel configuration
4. Checkout page with points redemption interface
5. Floating loyalty panel on frontend
6. Customer points balance in account dashboard

== Changelog ==

= 1.0.0 =
* Initial release
* Points-based loyalty program
* Tier system with configurable thresholds
* Dynamic and predefined coupon redemption
* Apple Wallet and Google Wallet integration
* Floating loyalty panel
* WooCommerce checkout integration
* Admin settings interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of Truebeep for WooCommerce. Requires WooCommerce 5.0+ and PHP 7.4+.

== Developer Information ==

= Hooks and Filters =

The plugin provides several hooks for developers:

**Actions:**
* `truebeep_after_points_earned` - Fired after points are awarded
* `truebeep_after_points_redeemed` - Fired after points are redeemed
* `truebeep_customer_tier_changed` - Fired when customer tier changes

**Filters:**
* `truebeep_points_calculation` - Modify points calculation
* `truebeep_redemption_value` - Modify redemption value
* `truebeep_tier_thresholds` - Modify tier thresholds

= Code Quality =

* Follows WordPress Coding Standards
* Implements proper security measures (nonces, capability checks, data sanitization)
* Uses WordPress APIs and best practices
* Includes inline documentation

= Support =

For support, please visit [truebeep.com/support](https://truebeep.com/support) or contact our support team.

== Privacy Policy ==

This plugin stores customer loyalty data including:
* Truebeep customer ID (user meta)
* Points balance (retrieved from API)
* Tier information (calculated from API data)
* Redemption history (order meta)

Data is transmitted securely to Truebeep servers via HTTPS. For more information, see our [Privacy Policy](https://truebeep.com/privacy).

== Credits ==

* Developed by Truebeep Team
* Uses CMB2 for advanced custom fields
* Integrates with WooCommerce by Automattic
* Background processing powered by WP Background Processing library