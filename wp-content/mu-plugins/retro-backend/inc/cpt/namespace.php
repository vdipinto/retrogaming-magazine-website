<?php
namespace Retro\Backend\Cpt;

defined('ABSPATH') || exit;

/**
 * Bootstrap CPT registration.
 */
function setup(): void {
    add_action('init', __NAMESPACE__ . '\\register', 0);
}

/**
 * Register the Game custom post type.
 */
function register(): void {
    register_post_type('game', [
        'labels' => [
            'name'          => __('Games', 'retro-backend'),
            'singular_name' => __('Game', 'retro-backend'),
            'add_new_item'  => __('Add New Game', 'retro-backend'),
            'edit_item'     => __('Edit Game', 'retro-backend'),
            'new_item'      => __('New Game', 'retro-backend'),
            'view_item'     => __('View Game', 'retro-backend'),
            'search_items'  => __('Search Games', 'retro-backend'),
        ],
        'public'       => true,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rest_base'    => 'games',
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-games',
        'supports'     => ['title','editor','excerpt','thumbnail','revisions'],
        'rewrite'      => ['slug' => 'games'],
        // Make editor panels appear (Genre + other facets)
        'taxonomies'   => ['genre','system','region','release_status','players_bucket'],
    ]);
}
