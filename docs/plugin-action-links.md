# Plugin Action Links

The Truebeep plugin adds convenient action links to the WordPress Plugins page for easy navigation.

## Default Links

- **Settings**: Links to WooCommerce Truebeep settings tab (or network diagnostics if WooCommerce is not active)
- **Documentation**: Links to https://docs.truebeep.com
- **Support**: Links to https://truebeep.com/support

## Customizing Links

You can customize the documentation and support URLs using WordPress filters:

### Change Documentation URL
```php
add_filter('truebeep_docs_url', function($url) {
    return 'https://your-custom-docs.com';
});
```

### Change Support URL
```php
add_filter('truebeep_support_url', function($url) {
    return 'https://your-support-site.com';
});
```

### Add Custom Action Links
```php
add_filter('truebeep_plugin_action_links', function($links) {
    $links['custom'] = '<a href="https://example.com" target="_blank">Custom Link</a>';
    return $links;
});
```

### Remove Default Links
```php
add_filter('truebeep_plugin_action_links', function($links) {
    unset($links['support']); // Remove support link
    return $links;
});
```

## Styling

The action links are automatically styled with:
- Color coding (Settings: Blue, Docs: Green, Support: Red)
- Icons (⚙️ Settings, 📖 Documentation, 🆘 Support)
- Hover effects

You can override the styles by targeting the CSS classes in your theme or plugin.