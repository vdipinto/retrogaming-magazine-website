<?php
/**
 * Plugin Name:       Gaming Tickets Selector
 * Description:       Example block scaffolded with Create Block tool.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gaming-tickets-selector
 *
 * @package CreateBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function create_block_gaming_tickets_selector_block_init() {
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
		return;
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		register_block_type( __DIR__ . "/build/{$block_type}" );
	}
}
add_action( 'init', 'create_block_gaming_tickets_selector_block_init' );




// Load the REST endpoints for the server-side cart
// This registers /gaming-tickets/v1/cart and related routes
// (add, update, remove, checkout), so the front-end checkout page
// can interact with a persistent cart stored in WP transients.
require_once __DIR__ . '/includes/cart.php';
// Load the REST endpoint that exposes Pretix "instances"
// This registers the /gaming-tickets/v1/instances route
// so the front-end can query available dates/times from Pretix.
require_once __DIR__ . '/includes/rest.php';


// Note: env.php is NOT required directly here because both rest.php
// and cart.php already require it internally when they need Pretix config.


// /**
//  * Ensure our front-end overrides load after theme/plugin styles.
//  */
// add_action('wp_enqueue_scripts', function () {
//     // Load (or re-load) our compiled CSS late so it wins in cascade.
//     $path = __DIR__ . '/build/style-index.css';
//     if ( file_exists( $path ) ) {
//         wp_enqueue_style(
//             'gts-frontend-overrides',
//             plugins_url('build/style-index.css', __FILE__),
//             [], // no deps; we just want this to print last
//             filemtime( $path )
//         );
//     }
// }, 100); // 👈 late priority


//this script is temporay and it is used to test the add-to-basket event. It provides the payload to the console.
add_action('wp_enqueue_scripts', function () {
    wp_add_inline_script(
        'create-block-gaming-tickets-selector-view-script', // block view script handle
        "window.addEventListener('gts:add-to-basket', e => {
            console.log('[GTS] Basket payload:', e.detail);
        });",
        'after'
    );
}, 110);