# JOLT Gate WordPress Plugin

A WordPress plugin that allows you to easily change the default login URL (wp-login.php) to a custom, unique URL. Increase your site's security by hiding the login page behind a personalized path.

## Features

- ✅ **Custom Login URL** - Replace wp-login.php with your own URL
- ✅ **WordPress Integration** - Uses proper WordPress rewrite rules
- ✅ **Preserves Functionality** - All WordPress login features work normally
- ✅ **Dashboard Redirects** - Proper redirects after successful login
- ✅ **Admin Bar Support** - Frontend admin bar works as expected
- ✅ **Development Friendly** - Localhost access allowed for development
- ✅ **Security Focused** - Blocks direct wp-login.php access
- ✅ **Easy Configuration** - Simple admin settings page

## How It Works

1. **URL Rewriting** - Creates a custom URL that redirects to wp-login.php
2. **Access Control** - Blocks direct wp-login.php access (with exceptions)
3. **Preservation** - Maintains all WordPress login functionality
4. **Simplicity** - Doesn't override WordPress core behavior

## Installation

1. Upload the plugin files to `/wp-content/plugins/jolt-gate/`
2. Activate the plugin through the WordPress admin
3. Go to Settings > JOLT Gate to configure your custom login URL
4. Visit your new custom login URL

## Configuration

- Navigate to **Settings > JOLT Gate** in your WordPress admin
- Set your custom login URL (default: "myadmin")
- Save settings to activate the new URL

## Security

- Keep your custom URL secret
- Choose a URL that's not easily guessable
- Avoid common words like 'admin', 'login', etc.
- The plugin allows localhost access for development

## Requirements

- WordPress 4.0 or higher
- PHP 7.0 or higher

## License

GPL v2 or later