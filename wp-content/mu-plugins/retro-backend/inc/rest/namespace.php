<?php
namespace Retro\Backend\Rest;
defined('ABSPATH') || exit;

function setup(): void {
    add_action('rest_api_init', __NAMESPACE__ . '\\routes');
}

function routes(): void {
    register_rest_route('retro/v1', '/games', [
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\handle_games',
        'permission_callback' => '__return_true',
        'args'                => [
            'page' => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 12],
            // tax filters
            'system' => ['type' => 'array', 'items' => ['type' => 'string']],
            'region' => ['type' => 'array', 'items' => ['type' => 'string']],
            'release_status' => ['type' => 'array', 'items' => ['type' => 'string']],
            'players_bucket' => ['type' => 'array', 'items' => ['type' => 'string']],
            'genre' => ['type' => 'array', 'items' => ['type' => 'string']], // can pass parent or child slugs
            // meta/range filters
            'year_min' => ['type' => 'integer'],
            'year_max' => ['type' => 'integer'],
            'players_min' => ['type' => 'integer'],
        ],
    ]);
}

function handle_games(\WP_REST_Request $req) {
    $page     = max(1, (int)$req->get_param('page'));
    $per_page = max(1, min(100, (int)$req->get_param('per_page')));

    $tax_query = ['relation' => 'AND'];
    foreach (['system','region','release_status','players_bucket'] as $tax) {
        $slugs = (array) $req->get_param($tax);
        if ($slugs) $tax_query[] = ['taxonomy' => $tax, 'field' => 'slug', 'terms' => array_map('sanitize_title', $slugs)];
    }
    // Genre: include descendants if a parent is passed
    $genre_slugs = (array) $req->get_param('genre');
    if ($genre_slugs) {
        $term_ids = [];
        foreach ($genre_slugs as $slug) {
            $t = get_term_by('slug', sanitize_title($slug), 'genre');
            if ($t) {
                $term_ids[] = (int)$t->term_id;
                $children = get_terms(['taxonomy' => 'genre','hide_empty' => false,'parent' => (int)$t->term_id, 'fields' => 'ids']);
                if (!is_wp_error($children)) $term_ids = array_merge($term_ids, $children);
            }
        }
        $term_ids = array_values(array_unique($term_ids));
        if ($term_ids) $tax_query[] = ['taxonomy' => 'genre', 'field' => 'term_id', 'terms' => $term_ids];
    }

    $meta_query = ['relation' => 'AND'];
    $year_min = (int) $req->get_param('year_min');
    $year_max = (int) $req->get_param('year_max');
    if ($year_min) $meta_query[] = ['key' => 'release_year', 'value' => $year_min, 'type' => 'NUMERIC', 'compare' => '>='];
    if ($year_max) $meta_query[] = ['key' => 'release_year', 'value' => $year_max, 'type' => 'NUMERIC', 'compare' => '<='];
    $players_min = (int) $req->get_param('players_min');
    if ($players_min) $meta_query[] = ['key' => 'max_players', 'value' => $players_min, 'type' => 'NUMERIC', 'compare' => '>='];

    $args = [
        'post_type'      => 'game',
        'post_status'    => 'publish',
        'paged'          => $page,
        'posts_per_page' => $per_page,
        'tax_query'      => (count($tax_query) > 1) ? $tax_query : [],
        'meta_query'     => (count($meta_query) > 1) ? $meta_query : [],
    ];

    $q = new \WP_Query($args);

    $items = array_map(function($p) {
        return [
            'id'      => $p->ID,
            'slug'    => $p->post_name,
            'title'   => get_the_title($p),
            'excerpt' => [
                'raw'      => current_user_can('edit_post', $p->ID) ? get_post_field('post_excerpt', $p->ID) : null,
                'rendered' => apply_filters('the_excerpt', get_post_field('post_excerpt', $p->ID)),
            ],
            'thumb'   => get_the_post_thumbnail_url($p, 'medium'),
            'meta'    => [
                'release_date' => get_post_meta($p->ID, 'release_date', true),
                'release_year' => (int) get_post_meta($p->ID, 'release_year', true),
                'min_players'  => (int) get_post_meta($p->ID, 'min_players', true),
                'max_players'  => (int) get_post_meta($p->ID, 'max_players', true),
            ],
            'tax' => [
                'system'         => wp_get_post_terms($p->ID, 'system', ['fields' => 'slugs']),
                'region'         => wp_get_post_terms($p->ID, 'region', ['fields' => 'slugs']),
                'release_status' => wp_get_post_terms($p->ID, 'release_status', ['fields' => 'slugs']),
                'players_bucket' => wp_get_post_terms($p->ID, 'players_bucket', ['fields' => 'slugs']),
                'genre'          => wp_get_post_terms($p->ID, 'genre', ['fields' => 'slugs']),
            ],
        ];
    }, $q->posts);

    return new \WP_REST_Response([
        'page'       => $page,
        'per_page'   => $per_page,
        'total'      => (int)$q->found_posts,
        'totalPages' => (int)$q->max_num_pages,
        'items'      => $items,
    ], 200);
}
