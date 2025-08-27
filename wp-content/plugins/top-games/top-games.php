<?php
/**
 * Plugin Name:       Top Games
 * Plugin URI:        https://vitodipint.dev/projects/top-games
 * Description:       Provides a “Top Games” dynamic block and a companion 3-column Top Stories pattern.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Vito Dipinto
 * Author URI:        https://vitodipint.dev
 * Text Domain:       top-games
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        false
 *
 * @package Top_Games
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TOP_GAMES_VERSION', '1.0.0' );
define( 'TOP_GAMES_PATH', plugin_dir_path( __FILE__ ) );
define( 'TOP_GAMES_URL',  plugin_dir_url( __FILE__ ) );

require_once TOP_GAMES_PATH . 'includes/render-top-games.php';

/**
 * Load translations (optional, if you add /languages/top-games.pot)
 */
add_action( 'init', function () {
	load_plugin_textdomain( 'top-games', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Register the dynamic block.
 * The build/top-games/block.json should exist; we also wire the render callback here.
 */
add_action( 'init', function () {
	register_block_type(
		TOP_GAMES_PATH . 'build/top-games',
		array( 'render_callback' => 'top_games_render_block' )
	);

	// Debug the registry (remove in production)
	$r = WP_Block_Type_Registry::get_instance()->get_registered( 'top-games/top-games' );
	error_log( 'TG registry ' . ( $r ? 'OK' : 'MISSING' ) . ' render_callback=' . ( $r && $r->render_callback ? 'set' : 'none' ) );
} );

/**
 * Optional: prove WP renders the block pipeline (debug only)
 */
// add_filter( 'render_block', function ( $content, $block ) {
// 	if ( ( $block['blockName'] ?? '' ) === 'top-games/top-games' ) {
// 		error_log( 'TG render_block fired' );
// 		return '<!-- TG render_block hook -->' . $content;
// 	}
// 	return $content;
// }, 10, 2 );

/**
 * Register block patterns from /patterns
 * Each file returns an array for register_block_pattern().
 */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) return;

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'top-games',
			[ 'label' => __( 'Top Games', 'top-games' ) ]
		);
	}

	$dir = TOP_GAMES_PATH . 'patterns';
	if ( ! is_dir( $dir ) ) return;

	foreach ( glob( $dir . '/*.php' ) as $file ) {
		$pattern = require $file;
		if ( ! is_array( $pattern ) || empty( $pattern['title'] ) || empty( $pattern['content'] ) ) continue;

		$slug = ! empty( $pattern['slug'] ) ? $pattern['slug'] : basename( $file, '.php' );
		register_block_pattern( 'top-games/' . $slug, $pattern );
	}
} );
