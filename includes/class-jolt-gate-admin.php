<?php
/**
 * JoltGate Admin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class JoltGateAdmin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'JOLT Gate Settings',
            'JOLT Gate',
            'manage_options',
            'jolt-gate',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'jolt_gate_settings',
            'jolt_gate_custom_url',
            array(
                'sanitize_callback' => array($this, 'sanitize_custom_url')
            )
        );
        
        add_settings_section(
            'jolt_gate_main_section',
            'Login URL Settings',
            array($this, 'settings_section_callback'),
            'jolt-gate'
        );
        
        add_settings_field(
            'jolt_gate_custom_url',
            'Custom Login URL',
            array($this, 'custom_url_field_callback'),
            'jolt-gate',
            'jolt_gate_main_section'
        );
    }
    
    /**
     * Sanitize custom URL
     */
    public function sanitize_custom_url($input) {
        $input = sanitize_text_field($input);
        $input = trim($input, '/');
        
        // Basic validation
        if (empty($input)) {
            add_settings_error(
                'jolt_gate_custom_url',
                'empty_url',
                'Custom URL cannot be empty.',
                'error'
            );
            return get_option('jolt_gate_custom_url', 'myadmin');
        }
        
        // Check for invalid characters
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $input)) {
            add_settings_error(
                'jolt_gate_custom_url',
                'invalid_url',
                'Custom URL can only contain letters, numbers, hyphens, and underscores.',
                'error'
            );
            return get_option('jolt_gate_custom_url', 'myadmin');
        }
        
        // Check for reserved WordPress paths
        $reserved_paths = array(
            'wp-admin',
            'wp-content',
            'wp-includes',
            'wp-login',
            'admin',
            'login',
            'dashboard',
            'wp-json'
        );
        
        if (in_array(strtolower($input), $reserved_paths)) {
            add_settings_error(
                'jolt_gate_custom_url',
                'reserved_url',
                'This URL is reserved by WordPress. Please choose a different one.',
                'error'
            );
            return get_option('jolt_gate_custom_url', 'myadmin');
        }
        
        // If we get here, the URL is valid
        // Flush rewrite rules when URL changes
        flush_rewrite_rules();
        
        add_settings_error(
            'jolt_gate_custom_url',
            'url_updated',
            'Custom login URL updated successfully!',
            'updated'
        );
        
        return $input;
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Configure your custom login URL below. This will replace the default wp-login.php URL.</p>';
    }
    
    /**
     * Custom URL field callback
     */
    public function custom_url_field_callback() {
        $custom_url = get_option('jolt_gate_custom_url', 'myadmin');
        $full_url = home_url('/' . $custom_url);
        
        echo '<input type="text" id="jolt_gate_custom_url" name="jolt_gate_custom_url" value="' . esc_attr($custom_url) . '" class="regular-text" />';
        echo '<p class="description">';
        echo 'Your custom login URL will be: <strong>' . esc_html($full_url) . '</strong><br>';
        echo 'Use only letters, numbers, hyphens, and underscores. Avoid WordPress reserved paths.';
        echo '</p>';
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $custom_url = get_option('jolt_gate_custom_url', 'myadmin');
        $full_url = home_url('/' . $custom_url);
        ?>
        <div class="wrap">
            <h1>JOLT Gate Settings</h1>
            
            <div class="notice notice-info">
                <p><strong>Current Custom Login URL:</strong> <a href="<?php echo esc_url($full_url); ?>" target="_blank"><?php echo esc_html($full_url); ?></a></p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('jolt_gate_settings');
                do_settings_sections('jolt-gate');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>How it works</h2>
                <ul>
                    <li>Your custom login URL replaces the default wp-login.php</li>
                    <li>Direct access to wp-login.php is blocked (except for necessary functions)</li>
                    <li>All WordPress login functionality is preserved</li>
                    <li>Users are properly redirected to the dashboard after login</li>
                    <li>Admin bar functionality is maintained</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Security Notes</h2>
                <ul>
                    <li>Keep your custom URL secret and don't share it publicly</li>
                    <li>Choose a URL that's not easily guessable</li>
                    <li>Avoid common words like 'admin', 'login', etc.</li>
                    <li>The plugin allows localhost access for development</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        settings_errors('jolt_gate_custom_url');
    }
}