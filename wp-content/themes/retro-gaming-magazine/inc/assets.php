<?php
// inc/assets.php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    // Keep style.css minimal; theme.json should do the heavy lifting.
    $css_path = get_stylesheet_directory() . '/style.css';
    if ( file_exists($css_path) ) {
        wp_enqueue_style(
            'retro-gaming-magazine',
            get_stylesheet_uri(),
            [],
            (string) filemtime($css_path)
        );
    }

    // Optional: tiny vanilla JS (no jQuery).
    $js_path = get_stylesheet_directory() . '/assets/js/frontend.js';
    if ( file_exists($js_path) ) {
        wp_enqueue_script(
            'retro-gaming-magazine-frontend',
            get_stylesheet_directory_uri() . '/assets/js/frontend.js',
            [],
            (string) filemtime($js_path),
            true
        );
    }
}, 20);

// Optional: editor-only CSS (try theme.json first; add this only if truly needed).
// add_action('admin_init', function () {
//     add_editor_style('assets/css/editor.css');
// });
