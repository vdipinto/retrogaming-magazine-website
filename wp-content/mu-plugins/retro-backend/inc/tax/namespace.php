<?php
namespace Retro\Backend\Tax;

defined('ABSPATH') || exit;

const OBJECT_TYPE = ['game'];

/**
 * Bootstrap taxonomy registration.
 */
function setup(): void {
    add_action('init', __NAMESPACE__ . '\\register', 5);
}

/**
 * Register all taxonomies used by the Game CPT.
 */
function register(): void {
    // Flat (non-hierarchical) taxonomies
    _register_flat('system',         __('System', 'retro-backend'),         __('Systems', 'retro-backend'),         'games/system');
    _register_flat('region',         __('Region', 'retro-backend'),         __('Regions', 'retro-backend'),         'games/region');
    _register_flat('release_status', __('Release Status', 'retro-backend'), __('Release Status', 'retro-backend'),  'games/status');
    _register_flat('players_bucket', __('Players', 'retro-backend'),        __('Players', 'retro-backend'),         'games/players');

    // Hierarchical Genre taxonomy
    register_taxonomy('genre', OBJECT_TYPE, [
        'labels' => [
            'name'              => __('Genres', 'retro-backend'),
            'singular_name'     => __('Genre', 'retro-backend'),
            'search_items'      => __('Search Genres', 'retro-backend'),
            'all_items'         => __('All Genres', 'retro-backend'),
            'parent_item'       => __('Parent Genre', 'retro-backend'),
            'parent_item_colon' => __('Parent Genre:', 'retro-backend'),
            'edit_item'         => __('Edit Genre', 'retro-backend'),
            'update_item'       => __('Update Genre', 'retro-backend'),
            'add_new_item'      => __('Add New Genre', 'retro-backend'),
            'new_item_name'     => __('New Genre Name', 'retro-backend'),
            'menu_name'         => __('Genres', 'retro-backend'),
        ],
        'public'        => true,
        'show_ui'       => true,
        'show_in_rest'  => true,
        'hierarchical'  => true,
        'show_admin_column' => true,
        'rewrite'       => [
            'slug'          => 'games/genre',
            'hierarchical'  => true,
            'with_front'    => false,
        ],
    ]);
}

/**
 * Helper to register a flat taxonomy with consistent args.
 */
function _register_flat(string $slug, string $singular, string $plural, string $base): void {
    register_taxonomy($slug, OBJECT_TYPE, [
        'labels' => [
            'name'          => $plural,
            'singular_name' => $singular,
            'search_items'  => sprintf(__('Search %s', 'retro-backend'), $plural),
            'all_items'     => sprintf(__('All %s', 'retro-backend'), $plural),
            'edit_item'     => sprintf(__('Edit %s', 'retro-backend'), $singular),
            'update_item'   => sprintf(__('Update %s', 'retro-backend'), $singular),
            'add_new_item'  => sprintf(__('Add New %s', 'retro-backend'), $singular),
            'new_item_name' => sprintf(__('New %s', 'retro-backend'), $singular),
            'menu_name'     => $plural,
        ],
        'public'            => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'hierarchical'      => false,
        'show_admin_column' => true,
        'rewrite'           => [
            'slug'       => $base,
            'with_front' => false,
        ],
    ]);
}
