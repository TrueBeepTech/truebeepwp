# Modular WordPress Plugin Update System

This is a modular GitHub-based update system for WordPress plugins that can be easily moved between different plugins. It provides automatic updates from GitHub releases without requiring plugins to be hosted on WordPress.org.

## Features

- ✅ **Modular Design**: Easy to copy between plugins
- ✅ **GitHub Integration**: Works with public and private repositories
- ✅ **Smart Caching**: Reduces API calls with intelligent caching
- ✅ **Network Resilient**: Handles timeouts and API rate limits
- ✅ **Manual Update Check**: "Check for update" button for instant checks
- ✅ **Security**: Proper nonce validation and permission checks
- ✅ **Configurable**: Multiple configuration methods
- ✅ **Translation Ready**: Full i18n support

## Quick Start

### 1. Copy the Update Module

Copy the entire `Update` folder to your plugin's `includes` directory:

```
your-plugin/
├── includes/
│   └── Update/
│       ├── GitHubUpdater.php
│       ├── UpdateManager.php
│       ├── update-config.php
│       └── README.md
└── your-plugin.php
```

### 2. Configure Your Repository

Edit `includes/Update/update-config.php`:

```php
return [
    'repository_url' => 'https://github.com/your-username/your-plugin-repo',
    'text_domain' => 'your-plugin-textdomain',
    'cache_prefix' => 'your-plugin-prefix',
];
```

### 3. Initialize in Your Plugin

Add to your main plugin file:

```php
// In your plugin's main class constructor or init method
private function init_updater() {
    $config_file = plugin_dir_path(__FILE__) . 'includes/Update/update-config.php';
    
    if (file_exists($config_file)) {
        $update_manager = \YourNamespace\Update\UpdateManager::from_config_file(
            __FILE__, // Main plugin file
            $config_file,
            [
                'text_domain' => 'your-textdomain',
                'cache_prefix' => 'your-prefix'
            ]
        );
    }
}
```

## Configuration Options

### Method 1: Config File (Recommended)

```php
// includes/Update/update-config.php
return [
    // Required: GitHub repository URL
    'repository_url' => 'https://github.com/username/repo-name',
    
    // Optional: Access token for private repos
    'access_token' => 'your_github_token',
    
    // Optional: Plugin text domain
    'text_domain' => 'your-plugin',
    
    // Optional: Cache prefix (auto-generated if not set)
    'cache_prefix' => 'your-plugin',
];
```

### Method 2: Direct Configuration

```php
$update_manager = \YourNamespace\Update\UpdateManager::from_repository_url(
    __FILE__,
    'https://github.com/username/repo-name',
    [
        'text_domain' => 'your-plugin',
        'access_token' => 'optional-token'
    ]
);
```

### Method 3: Manual Configuration

```php
$update_manager = \YourNamespace\Update\UpdateManager::create(
    __FILE__,
    'github-username',
    'repository-name',
    [
        'text_domain' => 'your-plugin',
        'access_token' => 'optional-token'
    ]
);
```

## GitHub Repository Setup

### 1. Version Management

Update these files before creating a release:

```php
// In your main plugin file header
* Version: 1.2.3

// In your main plugin class
const version = '1.2.3';
```

### 2. Creating Releases

Use semantic versioning:

```bash
# Tag and release
git tag v1.2.3
git push origin v1.2.3

# Or create release via GitHub UI
```

### 3. Release Notes

The updater will show your GitHub release notes as changelog. Use markdown formatting:

```markdown
## What's New
- Added new feature X
- Fixed bug Y
- Improved performance

## Breaking Changes
- Removed deprecated function Z
```

## Advanced Usage

### Custom Icons

```php
add_filter('your-prefix_plugin_icons', function($icons, $plugin_file) {
    return [
        '1x' => 'https://your-site.com/icon-128x128.png',
        '2x' => 'https://your-site.com/icon-256x256.png'
    ];
}, 10, 2);
```

### Error Handling

```php
// Set fallback version for offline scenarios
\YourNamespace\Update\GitHubUpdater::set_fallback_version('1.2.3', 'your-prefix');
```

### Private Repositories

1. Generate a GitHub Personal Access Token
2. Add it to your config:

```php
'access_token' => 'ghp_your_token_here'
```

## File Structure

```
includes/Update/
├── GitHubUpdater.php      # Core updater logic
├── UpdateManager.php      # Configuration manager
├── update-config.php      # Configuration file
└── README.md             # This documentation
```

## Security Considerations

- ✅ Nonce validation for all update checks
- ✅ Permission checks (`update_plugins` capability)
- ✅ Sanitized input/output
- ✅ SSL verification disabled only for local development
- ✅ No sensitive data logging

## Troubleshooting

### Updates Not Showing

1. Check repository URL in config
2. Verify GitHub releases exist
3. Check WordPress error logs
4. Use "Check for update" button
5. Verify version numbers are correct

### Timeout Errors

1. Check network connectivity to GitHub
2. Verify firewall allows GitHub access
3. Consider using GitHub token for higher rate limits

### Permission Errors

1. Verify user has `update_plugins` capability
2. Check file permissions on plugin directory

## Migration from Other Update Systems

### From WP Updates API

1. Copy Update module to your plugin
2. Replace your existing updater initialization
3. Configure GitHub repository
4. Remove old updater code

### From Other GitHub Updaters

1. Copy Update module
2. Update configuration format
3. Remove old updater files
4. Test thoroughly

## Best Practices

1. **Version Consistency**: Keep version numbers synchronized across:
   - Plugin header
   - Plugin class constant
   - GitHub release tags

2. **Release Process**:
   - Update version numbers
   - Commit and push changes
   - Create GitHub release
   - Test update process

3. **Caching**: The system caches for 30 minutes. For immediate testing:
   - Use "Check for update" button
   - Or manually clear transients

4. **Backup**: Always backup before updates in production

## Contributing

When modifying this update system:

1. Maintain backward compatibility
2. Update documentation
3. Test with multiple plugins
4. Consider security implications
5. Follow WordPress coding standards

## License

This update system is released under the same license as the parent plugin (typically GPL v2+).