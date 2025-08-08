# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "Truebeep" that extends WooCommerce functionality with custom features including loyalty programs, wallet functionality, and API integrations.

## Development Commands

### Dependency Management
- Install PHP dependencies: `composer install`
- Update PHP dependencies: `composer update`
- The plugin uses Composer autoloading with PSR-4 for the `Truebeep\` namespace

### Testing in Local Environment
- This plugin runs in a Local Sites environment at path: `/Users/roman/Local Sites/tbeep/app/public/wp-content/plugins/truebeep`
- WooCommerce must be installed and activated for the plugin to work
- The plugin will automatically deactivate if WooCommerce is not present

## Architecture Overview

### Core Plugin Structure
- **Main Entry**: `truebeep.php` - Plugin bootstrap file that initializes the singleton instance
- **Namespace**: All classes use the `Truebeep\` namespace
- **Autoloading**: PSR-4 autoloading via Composer, with additional classmap and file includes

### Key Components

1. **Admin Components** (`includes/Admin/`)
   - `Menu.php` - Admin menu management
   - `Settings.php` - General plugin settings
   - `WooCommerceSettings.php` - WooCommerce integration settings (Credentials, Loyalty, Wallet)
   - `CMB2.php` - Custom Meta Box 2 integration for advanced fields
   - `TestBgJob.php` - Background job processing using WP Background Processing library

2. **Frontend Components** (`includes/Frontend/`)
   - `Shortcode.php` - Plugin shortcodes
   - Views in `views/` directory for rendering

3. **Integration Points**
   - **Elementor Integration**: Custom Elementor widgets in `includes/Elementor/`
   - **WooCommerce**: Deep integration with settings tabs and API
   - **WordPress Customizer**: Theme customization options in `includes/Customizer/`
   - **AJAX**: Handler in `includes/Ajax.php`

4. **Libraries** (`includes/Library/`)
   - CMB2 Switch Button - Toggle field type
   - CMB2 Tags - Tag input field type
   - Select2 Field - Enhanced select boxes

### Asset Management
- CSS files in `assets/css/` (admin and frontend styles)
- JavaScript files in `assets/js/` (admin and frontend scripts)
- Assets are enqueued via `includes/Assets.php`

### Key Dependencies
- **CMB2**: Custom Meta Box framework for advanced form fields
- **Extended CPTs**: Library for enhanced custom post type functionality
- **WP Background Processing**: For handling long-running tasks
- **Carbon**: Date/time manipulation library
- **WooCommerce**: Required dependency

### Plugin Constants
- `TRUEBEEP_VERSION` - Plugin version
- `TRUEBEEP_FILE` - Main plugin file path
- `TRUEBEEP_PATH` - Plugin directory path
- `TRUEBEEP_URL` - Plugin URL
- `TRUEBEEP_ASSETS` - Assets URL
- `TRUEBEEP_DIR_PATH` - Full directory path
- `TRUEBEEP_ELEMENTOR` - Elementor directory path

### WooCommerce Settings Integration
The plugin adds a custom "Truebeep" tab to WooCommerce settings with sections:
- Credentials (API configuration)
- Loyalty (Customer loyalty program settings)
- Wallet (Digital wallet functionality)

Settings are saved via AJAX handlers and standard WooCommerce settings API.