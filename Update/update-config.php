<?php
/**
 * Plugin Update Configuration
 * 
 * Configure your GitHub repository details here for automatic updates.
 * This is a modular update system that can be easily moved between plugins.
 */

return [
    // GitHub repository URL - CHANGE THIS TO YOUR REPOSITORY
    'repository_url' => 'https://github.com/TrueBeepTech/TruebeepWp',
    
    // Optional: GitHub personal access token for private repositories
    // Leave empty for public repositories
    'access_token' => '',
    
    // Optional: Plugin text domain for translations (will be auto-detected if not set)
    'text_domain' => 'truebeep',
    
    // Optional: Cache prefix for this plugin (will be auto-generated if not set)
    'cache_prefix' => 'truebeep',
    
    // Optional: Branch to check for updates (currently not used but kept for compatibility)
    'branch' => 'main',
    
    // Auto-populated fields (will be extracted from repository_url)
    'username' => null,   // GitHub username/organization
    'repository' => null, // GitHub repository name
];