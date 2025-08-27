<?php
// inc/editor/patterns.php
defined('ABSPATH') || exit;

add_action('init', function () {
    // Give your patterns a tidy bucket in the inserter
    register_block_pattern_category(
        'retro',
        ['label' => __('Retro Gaming', 'retro-gaming-magazine')]
    );

    $dir = get_theme_file_path('/patterns');
    if ( ! is_dir($dir) ) {
        return;
    }

    // Load every .html file in /patterns as a pattern
    foreach ( glob($dir . '/*.html') as $file ) {
        $slug = 'retro/' . sanitize_title(basename($file, '.html'));
        $title = ucwords(str_replace(['-', '_'], ' ', basename($file, '.html')));

        register_block_pattern($slug, [
            'title'      => $title,
            'categories' => ['retro'],
            'content'    => file_get_contents($file),
        ]);
    }
});
