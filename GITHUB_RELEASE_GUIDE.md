# GitHub Release Guide for Truebeep WordPress Plugin

This guide explains how to create releases on GitHub that will trigger automatic updates for users who have installed your plugin.

## Prerequisites

1. Your plugin must be hosted in a public GitHub repository
2. The GitHub configuration file (`github-config.php`) must be properly configured
3. Users must have the plugin installed on their WordPress sites

## Current Configuration

The plugin is configured to check for updates from:
- **GitHub Username/Organization:** wildrain
- **Repository Name:** tbpublic
- **Branch:** master

## Creating a New Release

### Step 1: Update Version Numbers

Before creating a release, update the version number in these files:

1. **truebeep.php** - Update the `Version:` header comment and the `const version`
   ```php
   * Version:           1.0.1
   ```
   ```php
   const version = '1.0.1';
   ```

2. **readme.txt** - Update the `Stable tag:` field
   ```
   Stable tag: 1.0.1
   ```

### Step 2: Commit and Push Changes

```bash
git add .
git commit -m "Bump version to 1.0.1"
git push origin master
```

### Step 3: Create a GitHub Release

#### Option A: Using GitHub Web Interface

1. Go to your repository on GitHub: https://github.com/wildrain/tbpublic
2. Click on "Releases" (usually on the right side)
3. Click "Create a new release" or "Draft a new release"
4. Fill in the release details:
   - **Tag version:** Enter the version number (e.g., `1.0.1` or `v1.0.1`)
   - **Target:** Select the branch (master)
   - **Release title:** Enter a descriptive title (e.g., "Version 1.0.1 - Bug fixes and improvements")
   - **Description:** Add release notes describing what's new, fixed, or changed
5. Click "Publish release"

#### Option B: Using GitHub CLI

```bash
# Create a tag
git tag -a v1.0.1 -m "Version 1.0.1"

# Push the tag
git push origin v1.0.1

# Create release using GitHub CLI
gh release create v1.0.1 \
  --title "Version 1.0.1 - Bug fixes and improvements" \
  --notes "### What's Changed
- Fixed issue with loyalty points calculation
- Improved API error handling
- Updated documentation"
```

## Version Naming Convention

The plugin updater supports both formats:
- `1.0.1` (without 'v' prefix)
- `v1.0.1` (with 'v' prefix)

The updater will automatically strip the 'v' prefix when comparing versions.

## How Updates Work for Users

1. When users visit their WordPress admin dashboard, WordPress checks for plugin updates
2. The plugin's GitHub Updater class queries your GitHub repository for the latest release
3. If a newer version is found, WordPress displays an update notification
4. Users can update directly from the WordPress admin, just like any other plugin

## Update Check Frequency

- WordPress checks for updates every 12 hours by default
- The check is also triggered when users visit the Plugins page
- Users can force a check by clicking "Check Again" on the Updates page

## Best Practices

### 1. Semantic Versioning
Follow semantic versioning (MAJOR.MINOR.PATCH):
- **MAJOR:** Incompatible API changes
- **MINOR:** New functionality in a backwards compatible manner
- **PATCH:** Backwards compatible bug fixes

### 2. Release Notes
Always include clear release notes that describe:
- New features
- Bug fixes
- Breaking changes
- Migration instructions (if needed)

### 3. Testing Before Release
- Test the update process on a staging site
- Verify that the version number is correctly updated in all files
- Ensure backward compatibility

### 4. Asset Management
If you want to include a pre-built ZIP file:
1. Build your plugin ZIP file (excluding development files)
2. Attach it to the release as an asset
3. The updater will prioritize this over the auto-generated archive

## Troubleshooting

### Users Not Seeing Updates

If users report not seeing updates:

1. **Clear WordPress transients:**
   ```php
   delete_site_transient('update_plugins');
   ```

2. **Check GitHub API rate limits:**
   - The GitHub API has rate limits (60 requests per hour for unauthenticated requests)
   - For private repositories or higher rate limits, add a personal access token to `github-config.php`

3. **Verify release visibility:**
   - Ensure the release is published (not draft)
   - Check that the repository is public

### Version Comparison Issues

- Always use proper version numbers (e.g., 1.0.0, not 1.0)
- Ensure version numbers match across all files
- Remember that 1.0.10 is greater than 1.0.9 (not 1.0.1)

## Private Repository Support

If you need to use a private repository:

1. Generate a GitHub Personal Access Token:
   - Go to GitHub Settings > Developer settings > Personal access tokens
   - Generate a new token with `repo` scope

2. Add the token to `github-config.php`:
   ```php
   'access_token' => 'your_token_here',
   ```

3. Never commit the token to your repository

## Support

For issues with the update mechanism, check:
- The browser console for JavaScript errors
- WordPress debug log for PHP errors
- GitHub API responses using tools like Postman

## Example Release Workflow

```bash
# 1. Update version in files
# Edit truebeep.php and readme.txt

# 2. Commit changes
git add .
git commit -m "Prepare release 1.0.1"

# 3. Push to master
git push origin master

# 4. Create and push tag
git tag -a v1.0.1 -m "Release version 1.0.1"
git push origin v1.0.1

# 5. Create GitHub release
gh release create v1.0.1 \
  --title "Version 1.0.1" \
  --notes-file CHANGELOG.md
```

## Important Notes

- The plugin folder name on user sites must match the repository name for updates to work correctly
- The main plugin file (`truebeep.php`) must be in the root of the repository
- Always test the update process before announcing a new release to users