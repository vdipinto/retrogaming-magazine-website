<?php
/**
 * retro-gaming-magazine functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package retro-gaming-magazine
 */

defined('ABSPATH') || exit;

/**
 * Optionally define a theme version for cache-busting enqueues, etc.
 */
if (!defined('RETRO_GM_VERSION')) {
    define('RETRO_GM_VERSION', '1.0.0');
}

/**
 * Safely include theme bootstrap.
 */
$bootstrap = get_theme_file_path('inc/bootstrap.php');
if ($bootstrap && file_exists($bootstrap)) {
    require_once $bootstrap;
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('retro-gaming-magazine: missing inc/bootstrap.php; skipping include.');
    }
}

/**
 * (Optional) Safely include Customizer code.
 * Comment out if you don’t use it.
 */
$customizer = get_theme_file_path('inc/customizer.php');
if ($customizer && file_exists($customizer)) {
    require_once $customizer;
} else {
    // No fatal — just log in debug
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // comment out this log if it’s noisy for you
        // error_log('retro-gaming-magazine: missing inc/customizer.php; skipping include.');
    }
}

/**
 * Basic theme setup (add supports/menus as needed).
 */
add_action('after_setup_theme', function () {
    // add_theme_support('title-tag');
    // add_theme_support('post-thumbnails');
    // register_nav_menus(['primary' => __('Primary Menu', 'retro-gaming-magazine')]);
});


/**
 * Tweak only the FRONTEND MAIN QUERY.
 * Never touch admin or REST (Gutenberg block preview uses REST).
 */
add_action('pre_get_posts', 'retrogm_tune_main_query', 10);
function retrogm_tune_main_query( $q ) {
    if ( is_admin() ) return;
    $is_rest = ( function_exists('wp_is_rest') && wp_is_rest() ) || ( defined('REST_REQUEST') && REST_REQUEST );
    if ( $is_rest ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('retrogm_tune_main_query: bailed in REST route='.( function_exists('rest_get_current_route') ? rest_get_current_route() : '' ));
        }
        return;
    }
    if ( ! $q->is_main_query() ) return;

    // (your front-end main query tweaks here)
}
