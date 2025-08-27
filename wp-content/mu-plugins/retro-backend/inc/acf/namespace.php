<?php
namespace Retro\Backend\Acf;
defined('ABSPATH') || exit;

/**
 * Ensure ACF Local JSON points to MU plugin's /acf-json.
 * Register filters early so ACF sees them on first load.
 */
function setup(): void {
    // Register the paths right away (no ACF check).
    add_filter('acf/settings/load_json', __NAMESPACE__ . '\\load_json', 20);
    add_filter('acf/settings/save_json', __NAMESPACE__ . '\\save_json', 20);

    // (Optional) Log once ACF is fully initialized.
    add_action('acf/init', function () {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Retro\\Backend\\Acf: acf/init fired');
        }
    });

    // Ensure folder exists (helps on fresh installs)
    $dir = _json_dir();
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Retro\\Backend\\Acf: created ' . $dir);
        }
    }
}

/** Absolute path to /acf-json at MU plugin root */
function _json_dir(): string {
    // /wp-content/mu-plugins/retro-backend/inc/acf/namespace.php → up two levels to /retro-backend
    $root = dirname(__DIR__, 2);
    return $root . '/acf-json';
}

/** Add our MU JSON path so ACF auto-loads groups */
function load_json(array $paths): array {
    $dir = _json_dir();
    if (is_dir($dir)) {
        $paths[] = $dir; // append; keep theme paths too
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Retro\\Backend\\Acf: load_json path = ' . $dir);
        }
    }
    return $paths;
}

/** Save updated groups back into our MU folder */
function save_json(string $path): string {
    $dir = _json_dir();
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Retro\\Backend\\Acf: save_json path = ' . $dir);
    }
    return $dir;
}
