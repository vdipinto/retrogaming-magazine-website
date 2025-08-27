<?php
namespace Retro\Backend\Meta\Acf;
defined('ABSPATH') || exit;

function setup(): void {
    // Run after ACF writes field values
    add_action('acf/save_post', __NAMESPACE__ . '\\after_acf_save', 20);
}

/** Derive release_year + auto-assign players_bucket after ACF saves fields */
function after_acf_save($post_id) {
    if (get_post_type($post_id) !== 'game') return;

    // Derive release_year from release_date (normalize to Y-m-d)
    $date = get_post_meta($post_id, 'release_date', true);
    if ($date) {
        $ts = strtotime($date);
        if ($ts) {
            update_post_meta($post_id, 'release_year', (int) date('Y', $ts));
            update_post_meta($post_id, 'release_date', date('Y-m-d', $ts));
        }
    }

    // Map max_players → players_bucket taxonomy
    $max = (int) get_post_meta($post_id, 'max_players', true);
    if ($max > 0) {
        $map = [
            1  => ['single-player' => 'Single Player'],
            2  => ['2-plus'       => '2+ Players'],
            3  => ['3-plus'       => '3+ Players'],
            4  => ['4-plus'       => '4+ Players'],
            6  => ['6-plus'       => '6+ Players'],
            8  => ['8-plus'       => '8+ Players'],
            10 => ['10-plus'      => '10+ Players'],
        ];
        $termsWanted = [];
        foreach ($map as $threshold => $term) {
            if ($max >= $threshold) $termsWanted += $term; // merge slugs => labels
        }
        if ($termsWanted) {
            $termIds = [];
            foreach ($termsWanted as $slug => $label) {
                $t = get_term_by('slug', $slug, 'players_bucket');
                if (!$t) {
                    $created = wp_insert_term($label, 'players_bucket', ['slug' => $slug]);
                    if (!is_wp_error($created)) $termIds[] = (int) $created['term_id'];
                } else {
                    $termIds[] = (int) $t->term_id;
                }
            }
            if ($termIds) wp_set_object_terms($post_id, $termIds, 'players_bucket', false);
        }
    }
}
