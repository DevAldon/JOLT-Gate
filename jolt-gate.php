<?php
/**
 * Plugin Name: JOLT Gate
 * Plugin URI: https://github.com/johnoltmans/JOLT-Gate
 * Description: A WordPress plugin that lets you easily change the default login URL (wp-login.php) to a custom, unique URL. Increase your site's security by hiding the login page behind a personalized path.
 * Version: 1.0.0
 * Author: John Oltmans
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jolt-gate
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JOLT_GATE_VERSION', '1.0.0');
define('JOLT_GATE_PLUGIN_FILE', __FILE__);

// Use WordPress functions if available, otherwise fallback
if (function_exists('plugin_dir_path')) {
    define('JOLT_GATE_PLUGIN_DIR', plugin_dir_path(__FILE__));
} else {
    define('JOLT_GATE_PLUGIN_DIR', dirname(__FILE__) . '/');
}

if (function_exists('plugin_dir_url')) {
    define('JOLT_GATE_PLUGIN_URL', plugin_dir_url(__FILE__));
} else {
    define('JOLT_GATE_PLUGIN_URL', '');
}

// Include required files only if they exist
if (file_exists(JOLT_GATE_PLUGIN_DIR . 'includes/class-jolt-gate.php')) {
    require_once JOLT_GATE_PLUGIN_DIR . 'includes/class-jolt-gate.php';
}

if (file_exists(JOLT_GATE_PLUGIN_DIR . 'includes/class-jolt-gate-admin.php')) {
    require_once JOLT_GATE_PLUGIN_DIR . 'includes/class-jolt-gate-admin.php';
}

/**
 * Initialize the plugin
 */
function jolt_gate_init() {
    // Only initialize if classes exist
    if (class_exists('JoltGate')) {
        new JoltGate();
    }
    
    if (function_exists('is_admin') && is_admin() && class_exists('JoltGateAdmin')) {
        new JoltGateAdmin();
    }
}

// Only add WordPress hooks if we're in WordPress environment
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'jolt_gate_init');
}

/**
 * Activation hook
 */
function jolt_gate_activate() {
    // Set default options
    if (function_exists('get_option') && !get_option('jolt_gate_custom_url')) {
        if (function_exists('add_option')) {
            add_option('jolt_gate_custom_url', 'myadmin');
        }
    }
    
    // Flush rewrite rules
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
}

/**
 * Deactivation hook
 */
function jolt_gate_deactivate() {
    // Flush rewrite rules to remove custom rules
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
}

// Register hooks only if WordPress functions are available
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'jolt_gate_activate');
}

if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, 'jolt_gate_deactivate');
}