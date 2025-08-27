<?php
/**
 * Plugin Name: Retro Backend (Bootstrap)
 * Description: Core backend features (CPTs, taxonomies, meta, REST).
 * Version: 0.1.0
 * Author: You
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/namespace.php';

if (!defined('RETRO_BACKEND_BOOTSTRAPPED')) {
    define('RETRO_BACKEND_BOOTSTRAPPED', true);

    add_action('plugins_loaded', function () {
        static $ran = false;
        if ($ran) return;
        $ran = true;

        if (function_exists('\\Retro\\Backend\\setup')) {
            \Retro\Backend\setup();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Retro\\Backend: setup() hooked on plugins_loaded');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Retro\\Backend: setup() not found');
            }
        }
    }, 20);
}
