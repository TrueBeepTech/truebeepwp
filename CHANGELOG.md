# Changelog

All notable changes to the Truebeep WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2025-01-XX

### Fixed
- Fixed unescaped and untranslated wp_die() message in plugin activation hook

### Changed
- Excluded AGENTS.md from plugin bundle distribution

## [1.0.1] - 2025-01-XX

### Fixed
- Fixed naming collision issues with unprefixed AJAX action hooks
- Added direct file access protection to all PHP files
- Fixed ABSPATH check placement (after namespace declaration per PHP standards)

### Changed
- Removed unused dependencies (nesbot/carbon, johnbillion/extended-cpts)
- Updated minimum-stability to "stable" in composer.json
- Cleaned up composer dependencies to only include actively used packages

### Security
- All PHP files now protected against direct access
- All AJAX hooks properly prefixed with "truebeep_" to prevent collisions

## [2.0.8] - 2025-09-05

### Changed
- Version bump to 2.0.8

## [2.0.7] - Previous Release

### Added
- Customer sync system improvements with better error handling
- Enhanced rate limiting for API calls
- Improved Action Scheduler integration
- GitHub-based auto-update system
- Loyalty tier configuration improvements

### Fixed
- Various bug fixes and stability improvements