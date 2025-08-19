<?php
/**
 * GitHub Updater Configuration
 * 
 * Configure your GitHub repository details here for automatic updates.
 * Update the repository_url with your GitHub repository URL.
 */

 return [
    // GitHub repository URL - CHANGE THIS TO YOUR REPOSITORY
    'repository_url' => 'https://github.com/TrueBeepTech/TruebeepWp',
    
    // Optional: GitHub personal access token for private repositories
    // Leave empty for public repositories
    'access_token' => '',
    
    // Optional: Branch to check for updates (default: main/master)
    'branch' => 'main',
    
    // Legacy fields (auto-populated from repository_url)
    'username' => null,  // Will be extracted from repository_url
    'repository' => null, // Will be extracted from repository_url
];