<?php
/*
Plugin Name: JOLT Gate
Plugin URI: https://github.com/johnoltmans/JOLT-Gate
Description: Replaces wp-login.php with a configurable custom login URL (default: /myadmin). Also blocks XML-RPC and restricts the REST API to logged-in users only.
Version: 3.3.4
Author: John Oltmans
Author URI: https://www.johnoltmans.nl
License: GPL 3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) exit;

class JoltGate {
    private $option_name = 'jolt_gate_login_slug';
    private $default_slug = 'myadmin';
    private $slug = '';

    public function __construct() {
        // Get the configured slug, or use the default
        $this->slug = get_option($this->option_name, $this->default_slug);

        add_action('init', [$this, 'block_wp_login_direct'], 0);
        add_action('init', [$this, 'handle_custom_login'], 1);
        add_filter('login_url', [$this, 'custom_login_url'], 10, 2);
        add_filter('site_url', [$this, 'rewrite_wp_login_url'], 10, 4);
        add_filter('network_site_url', [$this, 'rewrite_wp_login_url'], 10, 4);
        add_filter('login_redirect', [$this, 'fix_login_redirect'], 10, 3);

        // Disable XML-RPC
        add_filter('xmlrpc_enabled', '__return_false', 99);

        // Restrict REST API to logged-in users only
        add_filter('rest_authentication_errors', [$this, 'restrict_rest_api']);

        // Admin settings
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    // Block direct access to wp-login.php, redirect to homepage unless it's allowed action
    public function block_wp_login_direct() {
        $is_wp_login = isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'wp-login.php';
        if (!$is_wp_login) return;

        // Allow essentials (password reset etc.)
        $allowed = ['logout', 'lostpassword', 'rp', 'resetpass', 'postpass'];
        $action = $_REQUEST['action'] ?? '';
        if (in_array($action, $allowed)) return;

        // Redirect to homepage
        wp_redirect(home_url('/'));
        exit;
    }

    // Show wp-login.php at the custom slug
    public function handle_custom_login() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $url_path = trim(strtok($request_uri, '?'), '/');
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
        if (!empty($home_path) && strpos($url_path, $home_path) === 0) {
            $url_path = trim(substr($url_path, strlen($home_path)), '/');
        }
        if ($url_path !== $this->slug) return;

        // Initialize variables for wp-login.php
        global $user_login, $error, $interim_login, $action, $redirect_to;
        if (!isset($user_login)) $user_login = '';
        if (!isset($error)) $error = '';
        if (!isset($interim_login)) $interim_login = false;
        if (!isset($action)) $action = '';
        if (!isset($redirect_to)) $redirect_to = '';
        $GLOBALS['pagenow'] = 'wp-login.php';

        require ABSPATH . 'wp-login.php';
        exit;
    }

    // Rewrite login links to the custom slug
    public function custom_login_url($login_url, $redirect = '') {
        $custom_url = home_url($this->slug);
        if (!empty($redirect)) {
            $custom_url = add_query_arg('redirect_to', urlencode($redirect), $custom_url);
        }
        return $custom_url;
    }

    // Rewrite all wp-login.php links in WordPress to the custom login slug
    public function rewrite_wp_login_url($url, $path, $scheme, $blog_id) {
        if (strpos($url, 'wp-login.php') === false) return $url;
        return str_replace('wp-login.php', $this->slug, $url);
    }

    // Ensure redirect after login never points to the slug (e.g. /myadmin)
    public function fix_login_redirect($redirect_to, $request, $user) {
        $slug_url = home_url($this->slug);
        if ($redirect_to === $slug_url || strpos($redirect_to, $slug_url) === 0) {
            return admin_url();
        }
        return $redirect_to;
    }

    // REST API only for logged-in users
    public function restrict_rest_api($result) {
        // Allow specific endpoints for anonymous users here if needed
        // if ( isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/contact-form-7') !== false ) return $result;
        if (!empty($result)) {
            return $result;
        }
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                'REST API is only available for logged-in users.',
                array('status' => 401)
            );
        }
        return $result;
    }

    // Admin menu for setting the slug
    public function add_settings_page() {
        add_options_page(
            'JOLT Gate Settings',
            'JOLT Gate',
            'manage_options',
            'jolt-gate',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting('jolt-gate-settings', $this->option_name, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_slug'],
            'default' => $this->default_slug
        ]);
        // NO add_settings_section or add_settings_field here, we build the form manually
    }

    public function sanitize_slug($slug) {
        $slug = trim(sanitize_title_with_dashes($slug));
        if (empty($slug)) $slug = $this->default_slug;
        return $slug;
    }

    public function settings_page_html() {
        $current_slug = get_option($this->option_name, $this->default_slug);
        $current_url = esc_html(home_url($current_slug));
        ?>
        <div class="wrap">
            <h1>JOLT Gate Settings</h1>
            <div style="border-left:4px solid #2196F3; background:#f6fbff; padding:10px 24px; margin:20px 0 10px 0;">
                <strong>Important:</strong> Remember your new login URL! If you forget it, you can deactivate the plugin via FTP by renaming the plugin folder.
            </div>
            <div style="border-left:4px solid #ffb300; background:#fffdf3; padding:10px 24px; margin:0 0 28px 0;">
                <strong>Note:</strong> After changing the login slug you may need to refresh your permalink structure by going to Settings &rarr; Permalinks and clicking "Save Changes".
            </div>
            <h2 style="margin-top:32px;">Login URL Settings</h2>
            <p>Change your WordPress login URL for better security.</p>
            <form method="post" action="options.php">
                <?php settings_fields('jolt-gate-settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="jolt-gate-login-slug">Custom Login Slug</label></th>
                        <td>
                            <input type="text" id="jolt-gate-login-slug" name="<?php echo esc_attr($this->option_name); ?>" value="<?php echo esc_attr($current_slug); ?>" class="regular-text" />
                            <p class="description">
                                Enter the new login slug (for example: <code>myadmin</code>, <code>admin</code>, <code>login</code>). Use only letters, numbers and hyphens.
                            </p>
                            <p style="margin:8px 0 0 0;"><strong>Current login URL:</strong>
                                <code><?php echo $current_url; ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Changes'); ?>
            </form>
        </div>
        <?php
    }
}

new JoltGate();