<?php
/*
Plugin Name: JOLT Gate
Description: Vervangt wp-login.php door een instelbare custom login URL (standaard: /myadmin).
Version: 3.2.5
Author: John Oltmans
Author URI: https://www.johnoltmans.nl/
*/

if (!defined('ABSPATH')) exit;

class JoltGate {
    private $option_name = 'jolt_gate_login_slug';
    private $default_slug = 'myadmin';
    private $slug = '';

    public function __construct() {
        // Haal ingestelde slug op, of gebruik standaard
        $this->slug = get_option($this->option_name, $this->default_slug);

        add_action('init', [$this, 'block_wp_login_direct'], 0);
        add_action('init', [$this, 'handle_custom_login'], 1);
        add_filter('login_url', [$this, 'custom_login_url'], 10, 2);
        add_filter('site_url', [$this, 'rewrite_wp_login_url'], 10, 4);
        add_filter('network_site_url', [$this, 'rewrite_wp_login_url'], 10, 4);
        add_filter('login_redirect', [$this, 'fix_login_redirect'], 10, 3);

        // Admin instellingen
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    // Blokkeer directe toegang tot wp-login.php, stuur door naar homepage
    public function block_wp_login_direct() {
        $is_wp_login = isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'wp-login.php';
        if (!$is_wp_login) return;

        // Essentials (wachtwoord reset etc.) blijven werken
        $allowed = ['logout', 'lostpassword', 'rp', 'resetpass', 'postpass'];
        $action = $_REQUEST['action'] ?? '';
        if (in_array($action, $allowed)) return;

        // Stuur door naar de homepage
        wp_redirect(home_url('/'));
        exit;
    }

    // Toon wp-login.php op de custom slug
    public function handle_custom_login() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $url_path = trim(strtok($request_uri, '?'), '/');
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
        if (!empty($home_path) && strpos($url_path, $home_path) === 0) {
            $url_path = trim(substr($url_path, strlen($home_path)), '/');
        }
        if ($url_path !== $this->slug) return;

        // Initialiseer variabelen voor wp-login.php
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

    // Loginlinks herschrijven naar custom slug
    public function custom_login_url($login_url, $redirect = '') {
        $custom_url = home_url($this->slug);
        if (!empty($redirect)) {
            $custom_url = add_query_arg('redirect_to', urlencode($redirect), $custom_url);
        }
        return $custom_url;
    }

    // Herschrijf alle wp-login.php links in WordPress naar de custom login slug
    public function rewrite_wp_login_url($url, $path, $scheme, $blog_id) {
        if (strpos($url, 'wp-login.php') === false) return $url;
        return str_replace('wp-login.php', $this->slug, $url);
    }

    // Zorg dat redirect na inloggen nooit naar de slug (bijv. /myadmin) wijst
    public function fix_login_redirect($redirect_to, $request, $user) {
        $slug_url = home_url($this->slug);
        if ($redirect_to === $slug_url || strpos($redirect_to, $slug_url) === 0) {
            return admin_url();
        }
        return $redirect_to;
    }

    // Admin menu voor instellen slug
    public function add_settings_page() {
        add_options_page(
            'JOLT Gate instellingen',
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
        add_settings_section(
            'jolt-gate-main',
            'Login URL Instelling',
            null,
            'jolt-gate'
        );
        add_settings_field(
            'jolt-gate-login-slug',
            'Custom login pad (bijvoorbeeld: <code>myadmin</code>, <code>beheer</code>)',
            [$this, 'slug_input_html'],
            'jolt-gate',
            'jolt-gate-main'
        );
    }

    public function sanitize_slug($slug) {
        $slug = trim(sanitize_title_with_dashes($slug));
        if (empty($slug)) $slug = $this->default_slug;
        return $slug;
    }

    public function slug_input_html() {
        $value = esc_attr(get_option($this->option_name, $this->default_slug));
        echo "<input type='text' name='{$this->option_name}' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Laat <b>myadmin</b> staan of kies je eigen login-pad. Gebruik alleen letters, cijfers en streepjes.</p>";
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>JOLT Gate instellingen</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('jolt-gate-settings');
                do_settings_sections('jolt-gate');
                submit_button();
                ?>
            </form>
            <p>Je loginpagina is nu bereikbaar op: <code><?php echo esc_html(home_url(get_option($this->option_name, $this->default_slug))); ?></code></p>
        </div>
        <?php
    }
}

new JoltGate();