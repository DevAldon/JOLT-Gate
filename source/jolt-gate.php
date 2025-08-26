<?php
/*
Plugin Name: JOLT Gate
Plugin URI: https://github.com/johnoltmans/JOLT-Gate
Description: Replaces wp-login.php with a configurable custom login URL (default: /myadmin). Also blocks XML-RPC and restricts the REST API to logged-in users only.
Version: 3.4.1
Requires at least: 6.8
Requires PHP: 7.4
Author: John Oltmans
Author URI: https://www.johnoltmans.nl/
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: replaces-wp-login.php-with-a-configurable-custom-login-url-by-john-oltmans
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=jolt-gate') . '">Settings</a>';
    $links[] = $settings_link;
    return $links;
});

if (!defined('ABSPATH')) exit;

class JoltGate {
    private $option_name = 'jolt_gate_login_slug';
    private $default_slug = 'myadmin';
    private $slug = '';

    public function __construct() {
        $this->slug = get_option($this->option_name, $this->default_slug);

        add_action('init', [$this, 'block_wp_login_direct'], 0);
        add_action('init', [$this, 'handle_custom_login'], 1);
        add_filter('login_url', [$this, 'custom_login_url'], 10, 2);
        add_filter('site_url', [$this, 'rewrite_wp_login_url'], 10, 4);
        add_filter('network_site_url', [$this, 'rewrite_wp_login_url'], 10, 4);
        add_filter('login_redirect', [$this, 'fix_login_redirect'], 10, 3);

        add_filter('xmlrpc_enabled', '__return_false', 99);
        add_filter('rest_authentication_errors', [$this, 'restrict_rest_api']);

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        add_filter('admin_body_class', function($classes) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'settings_page_jolt-gate') {
                $classes .= ' jolt-gate-full-bg';
            }
            return $classes;
        });
    }

    public function block_wp_login_direct() {
        $is_wp_login = isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'wp-login.php';
        if (!$is_wp_login) return;
        $allowed = ['logout', 'lostpassword', 'rp', 'resetpass', 'postpass'];
        $action = $_REQUEST['action'] ?? '';
        if (in_array($action, $allowed)) return;
        wp_redirect(home_url('/'));
        exit;
    }

    public function handle_custom_login() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $url_path = trim(strtok($request_uri, '?'), '/');
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
        if (!empty($home_path) && strpos($url_path, $home_path) === 0) {
            $url_path = trim(substr($url_path, strlen($home_path)), '/');
        }
        if ($url_path !== $this->slug) return;
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

    public function custom_login_url($login_url, $redirect = '') {
        $custom_url = home_url($this->slug);
        if (!empty($redirect)) {
            $custom_url = add_query_arg('redirect_to', urlencode($redirect), $custom_url);
        }
        return $custom_url;
    }

    public function rewrite_wp_login_url($url, $path, $scheme, $blog_id) {
        if (strpos($url, 'wp-login.php') === false) return $url;
        return str_replace('wp-login.php', $this->slug, $url);
    }

    public function fix_login_redirect($redirect_to, $request, $user) {
        $slug_url = home_url($this->slug);
        if ($redirect_to === $slug_url || strpos($redirect_to, $slug_url) === 0) {
            return admin_url();
        }
        return $redirect_to;
    }

    public function restrict_rest_api($result) {
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
    }

    public function sanitize_slug($slug) {
        $slug = trim(sanitize_title_with_dashes($slug));
        if (empty($slug)) $slug = $this->default_slug;
        return $slug;
    }

    public function settings_page_html() {
        $current_slug = get_option($this->option_name, $this->default_slug);
        $current_url = esc_html(home_url($current_slug));
        $logo_url = plugins_url('assets/joltgatetransparant.png', __FILE__);
        echo '<link rel="stylesheet" href="' . esc_url(plugins_url('assets/style.css', __FILE__)) . '" type="text/css" media="all" />';
        ?>
        <div class="jolt-gate-admin-page">
            <div class="jolt-gate-header">
                <img src="<?php echo esc_url($logo_url); ?>" alt="JOLT Gate Logo" class="jolt-gate-logo" />
                <div>
                    <h1>JOLT Gate Settings</h1>
                    <div class="jolt-gate-welcome">
                        Welcome to <strong>JOLT Gate!</strong> Easily change your WordPress login URL for better security.
                    </div>
                </div>
            </div>
            <div class="jolt-gate-warning">
                <strong>Important:</strong> Make sure to save your new login URL. Be careful when sharing this link, as anyone with access to it can log in to your site.
                <div class="jolt-gate-url-copy">
                    <span id="jolt-login-url"><?php echo $current_url; ?></span>
                    <button type="button" id="jolt-copy-btn" onclick="joltCopyLoginUrl()">Copy</button>
                </div>
            </div>
            <div class="jolt-gate-card">
                <h2>Login URL Settings</h2>
                <form method="post" action="options.php" class="jolt-gate-form">
                    <?php settings_fields('jolt-gate-settings'); ?>
                    <label for="jolt-gate-login-slug">Custom Login Slug:</label>
                    <input type="text" id="jolt-gate-login-slug" name="<?php echo esc_attr($this->option_name); ?>" value="<?php echo esc_attr($current_slug); ?>" />
                    <div class="jolt-gate-description">
                        Enter the new login slug (e.g. <code>myadmin</code>, <code>admin</code>, <code>login</code>). Only letters, numbers and hyphens.<br>
                        <strong>Current login URL:</strong> <code><?php echo $current_url; ?></code>
                    </div>
                    <?php submit_button('Save Changes', 'primary', '', false); ?>
                </form>
            </div>
            <hr>
            <div class="jolt-gate-help">
                Need help? If you're not sure what something does, ask your website administrator before proceeding.
            </div>
        </div>
        <script>
        function joltCopyLoginUrl() {
            const urlText = document.getElementById('jolt-login-url').innerText;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(urlText);
            } else {
                // fallback
                const tempInput = document.createElement('input');
                tempInput.value = urlText;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
            }
            document.getElementById('jolt-copy-btn').innerText = "Copied!";
            setTimeout(function(){
                document.getElementById('jolt-copy-btn').innerText = "Copy";
            }, 1500);
        }
        </script>
        <?php
    }
}

new JoltGate();