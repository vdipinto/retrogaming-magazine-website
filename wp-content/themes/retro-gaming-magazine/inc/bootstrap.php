<?php
// inc/bootstrap.php
defined('ABSPATH') || exit;

/**
 * Optional: Composer autoload (if you add vendor/)
 */
$composer = get_theme_file_path('/vendor/autoload.php');
if ( file_exists($composer) ) {
	require_once $composer;
}

/**
 * Helper to safely include files from /inc
 */
if ( ! function_exists('rmg_require_inc') ) {
	function rmg_require_inc( string $relative ): void {
		$path = get_theme_file_path('/inc/' . ltrim($relative, '/'));
		if ( file_exists($path) ) {
			require_once $path;
		} else {
			// Log a warning in PHP error log, but don't break the site.
			trigger_error( sprintf('retro-gaming-magazine include not found: %s', esc_html($relative)), E_USER_WARNING );
		}
	}
}

/**
 * Curated include list (keep each file small & focused)
 * - setup.php: (optional) if you decide to move theme supports out of functions.php later
 * - assets.php: enqueue minimal CSS/JS (prefer theme.json for styling)
 * - editor/patterns.php: block patterns & categories
 * - blocks/register-dynamic.php: dynamic blocks (register_block_type)
 * - template-tags.php / template-functions.php: your existing helpers
 */
$includes = [
	// 'setup.php', // uncomment if you move after_setup_theme here in the future
	'assets.php',
	'editor/patterns.php',
];

/**
 * Load optional integrations conditionally
 */
foreach ( $includes as $file ) {
	rmg_require_inc( $file );
}

// Jetpack only if present.
if ( defined('JETPACK__VERSION') ) {
	rmg_require_inc('jetpack.php');
}
