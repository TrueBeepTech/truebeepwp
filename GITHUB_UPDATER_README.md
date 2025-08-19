# GitHub Updater Setup Guide

This plugin includes built-in GitHub update functionality that allows users to update directly from your GitHub repository through the WordPress admin.

## Setup Instructions

### 1. Configure Your GitHub Repository

Edit the `github-config.php` file in the plugin root directory:

```php
return [
    // Your GitHub username or organization name
    'username' => 'your-github-username',
    
    // Your GitHub repository name
    'repository' => 'TruebeepWp',
    
    // Optional: GitHub personal access token for private repositories
    // Leave empty for public repositories
    'access_token' => '',
    
    // Optional: Branch to check for updates (default: main/master)
    'branch' => 'main',
];
```

### 2. Creating a Release

When you're ready to release a new version:

1. **Update the version number** in the main plugin file (`truebeep.php`):
   ```php
   * Version: 1.0.1
   ```
   And in the class constant:
   ```php
   const version = '1.0.1';
   ```

2. **Create a GitHub release**:
   - Go to your repository on GitHub
   - Click on "Releases" → "Create a new release"
   - Create a new tag (e.g., `1.0.1` or `v1.0.1`)
   - Add release title and notes
   - Optionally attach a ZIP file of your plugin

3. **Alternative: Use Git tags** (if you don't want to create releases):
   ```bash
   git tag 1.0.1
   git push origin 1.0.1
   ```

### 3. Private Repository Setup

If your repository is private, you'll need a Personal Access Token:

1. Go to GitHub → Settings → Developer settings → Personal access tokens
2. Generate a new token with `repo` scope
3. Add the token to `github-config.php`:
   ```php
   'access_token' => 'your_personal_access_token_here',
   ```

### 4. How Updates Work

1. The plugin checks for updates when WordPress checks for plugin updates (every 12 hours by default)
2. It compares the local version with the latest GitHub release/tag
3. If a newer version is available, it shows in the WordPress plugins page
4. Users can click "Update" to download and install the new version

### 5. Testing Updates

To test the update mechanism:

1. Install the plugin with version 1.0.0
2. Create a release on GitHub with version 1.0.1
3. In WordPress admin, go to Dashboard → Updates
4. Click "Check Again" to force an update check
5. The plugin should appear in the updates list

### 6. Troubleshooting

**Updates not showing:**
- Verify the GitHub username and repository name are correct
- Check that the version number in the GitHub release is higher than the local version
- For private repos, ensure the access token has proper permissions
- Clear WordPress transients: `wp transient delete --all`

**404 errors:**
- Make sure the repository exists and is accessible
- Check if you're hitting GitHub API rate limits (60 requests/hour for unauthenticated)

**Private repository issues:**
- Verify the personal access token is valid
- Ensure the token has `repo` scope permissions

### 7. File Structure After Update

When the plugin updates from GitHub, it maintains the proper WordPress plugin structure:
```
wp-content/plugins/
└── TruebeepWp/
    ├── truebeep.php
    ├── includes/
    ├── assets/
    └── ... (other plugin files)
```

### 8. Important Notes

- The updater preserves plugin activation status during updates
- Updates are atomic - if something fails, the old version remains
- The updater uses WordPress's built-in update mechanism for safety
- GitHub API has rate limits: 60 requests/hour (unauthenticated), 5000 requests/hour (authenticated)

### 9. Security Considerations

- Never commit the `github-config.php` file with real access tokens to public repositories
- Consider using environment variables for sensitive data in production
- Regularly rotate access tokens for security
- Use the minimum required permissions for access tokens

### 10. Customization

The `GitHubUpdater` class can be customized for your needs:
- Modify update check frequency
- Add custom update messages
- Implement changelog parsing
- Add backup functionality before updates