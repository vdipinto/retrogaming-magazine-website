<?php
/**
 * Plugin Name: My Newsletter
 * Description: Newsletter signup form with Vite-built assets and a WordPress REST endpoint.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: my-newsletter
 */

if (!defined('ABSPATH')) exit;

final class MyNews_Plugin {
    const DB_VERSION = '2';
    const SHORTCODE  = 'newsletter_signup';

    public function __construct() {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /** Activation: create/upgrade DB table */
    public static function activate() {
        global $wpdb;
        $table   = $wpdb->prefix . 'mynews_signups';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(190) NOT NULL DEFAULT '',
            last_name VARCHAR(190) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('mynews_db_version', self::DB_VERSION, false);
    }

    /** Auto-run dbDelta if version changes later */
    public static function maybe_upgrade() {
        if (get_option('mynews_db_version') !== self::DB_VERSION) {
            self::activate();
        }
    }

    /** REST: POST /wp-json/mynews/v1/signup */
    public function register_rest_routes() {
        register_rest_route('mynews/v1', '/signup', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_signup'],
            'permission_callback' => '__return_true',
            'args' => [
                'email'      => ['required' => true],
                'first_name' => ['required' => false],
                'last_name'  => ['required' => false],
            ],
        ]);
    }

    public function rest_signup(\WP_REST_Request $req) {
        $email = sanitize_email((string) $req->get_param('email'));
        $first = sanitize_text_field((string) ($req->get_param('first_name') ?? ''));
        $last  = sanitize_text_field((string) ($req->get_param('last_name') ?? ''));

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', __('Please provide a valid email.', 'my-newsletter'), ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mynews_signups';

        $ok = $wpdb->replace($table, [
            'email'      => $email,
            'first_name' => $first,
            'last_name'  => $last,
            'created_at' => current_time('mysql'),
        ]);

        if ($ok === false) {
            return new \WP_Error('db_error', __('Database error, try again.', 'my-newsletter'), ['status' => 500]);
        }

        return ['success' => true, 'message' => __('You are subscribed!', 'my-newsletter')];
    }

    /** Shortcode output + asset enqueue */
    public function render_shortcode() {
        $this->enqueue_assets();

        ob_start(); ?>
        <form class="ns-form" method="post">
            <div class="ns-field">
                <label for="ns-first"><?php esc_html_e('First Name', 'my-newsletter'); ?></label>
                <input type="text" id="ns-first" name="first_name">
            </div>

            <div class="ns-field">
                <label for="ns-last"><?php esc_html_e('Last Name', 'my-newsletter'); ?></label>
                <input type="text" id="ns-last" name="last_name">
            </div>

            <div class="ns-field">
                <label for="ns-email"><?php esc_html_e('Email *', 'my-newsletter'); ?></label>
                <input type="email" id="ns-email" name="email" required>
            </div>

            <button type="submit" class="ns-submit"><?php esc_html_e('Subscribe', 'my-newsletter'); ?></button>
            <p class="ns-message" aria-live="polite"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    /** Read Vite manifest and enqueue CSS/JS, then localize REST URL */
    private function enqueue_assets() {
        $entry = $this->get_manifest_entry();
        if (!$entry || empty($entry['file'])) {
            if (current_user_can('manage_options')) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p><strong>My Newsletter:</strong> Vite build not found. Run <code>npm run build</code> so <code>assets/manifest.json</code> exists.</p></div>';
                });
            }
            return;
        }

        // CSS
        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $i => $css_rel) {
                wp_enqueue_style(
                    'mynews-frontend' . ($i ? "-$i" : ''),
                    $this->assets_url($css_rel),
                    [],
                    null
                );
            }
        }

        // JS
        $handle = 'mynews-frontend';
        wp_register_script($handle, $this->assets_url($entry['file']), [], null, true);

        // Localize REST URL + messages for JS
        wp_localize_script($handle, 'NSNewsletter', [
            'restUrl' => get_rest_url(null, 'mynews/v1/signup'),
            'ok'      => __('Thanks! Check your inbox.', 'my-newsletter'),
            'err'     => __('Something went wrong.', 'my-newsletter'),
        ]);

        wp_enqueue_script($handle);
    }

    /** Helpers */
    private function assets_dir() { return plugin_dir_path(__FILE__) . 'assets/'; }
    private function assets_url($rel) { return plugin_dir_url(__FILE__) . 'assets/' . ltrim($rel, '/'); }

    private function get_manifest_entry() {
        // Support both assets/manifest.json and assets/.vite/manifest.json
        $candidates = [
            $this->assets_dir() . 'manifest.json',
            $this->assets_dir() . '.vite/manifest.json',
        ];
        $manifest = null;
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $json = json_decode(file_get_contents($path), true);
                if (is_array($json)) { $manifest = $json; break; }
            }
        }
        if (!$manifest) return null;

        // Prefer exact key
        if (isset($manifest['resources/js/frontend.js'])) {
            return $manifest['resources/js/frontend.js'];
        }
        // Else entry named "frontend"
        foreach ($manifest as $entry) {
            if (!empty($entry['isEntry']) && (($entry['name'] ?? '') === 'frontend')) return $entry;
        }
        // Else any entry
        foreach ($manifest as $entry) {
            if (!empty($entry['isEntry'])) return $entry;
        }
        return null;
    }
}

/** Bootstrap */
register_activation_hook(__FILE__, ['MyNews_Plugin', 'activate']);
add_action('plugins_loaded', ['MyNews_Plugin', 'maybe_upgrade']);
add_action('plugins_loaded', function () { new MyNews_Plugin(); });
