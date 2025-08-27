<?php
/**
 * MU Plugins Loader
 *
 * Loads MU plugin modules from subfolders and lists them in Plugins → Must-Use.
 */
defined('ABSPATH') || exit;

$hm_mu_plugins = [
    'retro-backend/plugin.php', // ✅ only the module bootstrap
];

foreach ($hm_mu_plugins as $relative_file) {
    $path = WP_CONTENT_DIR . '/mu-plugins/' . ltrim($relative_file, '/');
    if (file_exists($path)) {
        require_once $path; // ✅ require_once
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MU Loader: included {$relative_file}");
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MU Loader: missing {$relative_file}");
        }
    }
}

add_filter('show_advanced_plugins', function ($show, $type) use ($hm_mu_plugins) {
    if ($type !== 'mustuse') return $show;

    if (!function_exists('get_file_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    global $plugins;
    $plugins['mustuse'] = $plugins['mustuse'] ?? [];
    foreach ($hm_mu_plugins as $relative_file) {
        $path = WP_CONTENT_DIR . '/mu-plugins/' . ltrim($relative_file, '/');
        if (!file_exists($path)) continue;

        $data = get_file_data($path, [
            'Name'        => 'Plugin Name',
            'Description' => 'Description',
            'Version'     => 'Version',
            'Author'      => 'Author',
            'TextDomain'  => 'Text Domain',
        ], 'plugin');

        $plugins['mustuse'][$relative_file] = [
            'Name'        => $data['Name'] ?: basename(dirname($path)),
            'Description' => $data['Description'],
            'Version'     => $data['Version'],
            'Author'      => $data['Author'],
            'Title'       => $data['Name'] ?: basename(dirname($path)),
            'file'        => $path,
        ];
    }
    return true;
}, 10, 2);
