<?php
/**
 * Main JoltGate class
 */

if (!defined('ABSPATH')) {
    exit;
}

class JoltGate {
    
    private $custom_url;
    
    public function __construct() {
        $this->custom_url = get_option('jolt_gate_custom_url', 'myadmin');
        
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_custom_login'));
        add_action('wp_loaded', array($this, 'block_wp_login_access'));
        add_filter('login_redirect', array($this, 'handle_login_redirect'), 10, 3);
        add_action('wp_logout', array($this, 'handle_logout_redirect'));
    }
    
    /**
     * Add rewrite rules for custom login URL
     */
    public function add_rewrite_rules() {
        // Add rewrite rule for custom login URL
        add_rewrite_rule(
            '^' . $this->custom_url . '/?$',
            'index.php?jolt_gate_login=1',
            'top'
        );
        
        // Register query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'jolt_gate_login';
            return $vars;
        });
    }
    
    /**
     * Handle custom login URL access
     */
    public function handle_custom_login() {
        if (get_query_var('jolt_gate_login')) {
            // Preserve any query parameters from the original request
            $query_params = $_GET;
            unset($query_params['jolt_gate_login']);
            
            // Build wp-login.php URL with preserved parameters
            $login_url = wp_login_url();
            if (!empty($query_params)) {
                $login_url = add_query_arg($query_params, $login_url);
            }
            
            // If user is already logged in and no specific action, redirect to dashboard
            if (is_user_logged_in() && empty($_GET['action'])) {
                wp_redirect(admin_url());
                exit;
            }
            
            // Redirect to wp-login.php with preserved functionality
            wp_redirect($login_url);
            exit;
        }
    }
    
    /**
     * Block direct access to wp-login.php
     */
    public function block_wp_login_access() {
        global $pagenow;
        
        // Don't block if accessing wp-login.php for allowed reasons
        if ($pagenow === 'wp-login.php') {
            $allowed_actions = array(
                'logout',
                'lostpassword',
                'resetpass',
                'rp',
                'register',
                'postpass'
            );
            
            $action = isset($_GET['action']) ? $_GET['action'] : '';
            
            // Allow AJAX requests
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return;
            }
            
            // Allow if it's an allowed action
            if (in_array($action, $allowed_actions)) {
                return;
            }
            
            // Allow if user is already logged in (for logout, etc.)
            if (is_user_logged_in() && in_array($action, array('logout', ''))) {
                return;
            }
            
            // Allow access from localhost for development
            if ($this->is_localhost()) {
                return;
            }
            
            // Block direct access and redirect to custom URL
            wp_redirect(home_url('/' . $this->custom_url));
            exit;
        }
    }
    
    /**
     * Handle login redirect to ensure users go to dashboard
     */
    public function handle_login_redirect($redirect_to, $request, $user) {
        // If no specific redirect was requested, send to dashboard
        if (empty($redirect_to) || $redirect_to === admin_url()) {
            return admin_url();
        }
        
        // If redirect_to is the wp-login.php, send to dashboard instead
        if (strpos($redirect_to, 'wp-login.php') !== false) {
            return admin_url();
        }
        
        // Otherwise preserve the intended redirect
        return $redirect_to;
    }
    
    /**
     * Handle logout redirect
     */
    public function handle_logout_redirect() {
        // Redirect to custom login URL after logout
        wp_redirect(home_url('/' . $this->custom_url . '?loggedout=true'));
        exit;
    }
    
    /**
     * Check if request is from localhost
     */
    private function is_localhost() {
        $localhost_ips = array('127.0.0.1', '::1');
        $localhost_hosts = array('localhost', '127.0.0.1');
        
        $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        
        return in_array($remote_ip, $localhost_ips) || in_array($http_host, $localhost_hosts);
    }
    
    /**
     * Get custom login URL
     */
    public function get_custom_login_url() {
        return home_url('/' . $this->custom_url);
    }
}