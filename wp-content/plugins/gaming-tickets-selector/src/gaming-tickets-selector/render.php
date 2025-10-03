<?php
/**
 * Server-side render file for the Gaming Tickets Selector block
 *
 * What this file does:
 *  - Outputs the block wrapper (classes/styles added by WP)
 *  - Prints a mount point: <div class="gtx-root" ...> that your React app attaches to
 *  - Passes configuration to JS via data-* attributes:
 *      • data-event-id         (Pretix event slug)
 *      • data-rest-endpoint    (WP REST proxy URL your JS should call)
 *      • data-initial-month    (YYYY-MM the calendar should open on)
 *      • data-max-per-order    (hard cap for per-order quantity in the UI)
 *      • data-rest-nonce       (for future POSTs; not needed for GET)
 *
 * Notes on "Max per order":
 *  - Pretix can enforce a product-level limit (recommended! set it in Pretix UI).
 *  - We ALSO pass a UI cap (data-max-per-order), so the quantity stepper never shows > cap.
 *  - This keeps UX tidy AND you still have Pretix as the backend authority.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Wrapper attributes (WP will add block supports here: align, spacing, etc.)
 * We also force our custom class for easier targeting.
 */
$wrapper_attrs = function_exists( 'get_block_wrapper_attributes' )
	? get_block_wrapper_attributes( [ 'class' => 'wp-block-gaming-tickets-selector' ] )
	: 'class="wp-block-gaming-tickets-selector"';

/**
 * Security nonce:
 * Not required for simple GET availability, but useful if later you POST
 * to your proxy (e.g., reserve/add-to-basket).
 */
$nonce = wp_create_nonce( 'wp_rest' );

/**
 * Read block attributes coming from block.json/editor.
 * - eventId: Pretix event slug (string)
 * - maxPerOrder: UI cap for per-order quantity (number)
 *
 * If you want editors to control the cap in the block sidebar, add to block.json:
 *
 * "attributes": {
 *   "eventId": { "type": "string", "default": "sessionplay" },
 *   "maxPerOrder": { "type": "number", "default": 2 }
 * }
 */
$event_id = isset( $attributes['eventId'] ) && $attributes['eventId'] !== ''
	? sanitize_text_field( $attributes['eventId'] )
	: 'sessionplay';

// Default to 2 if not provided. You can set to null/0 to mean "no UI cap".
$max_per_order = isset( $attributes['maxPerOrder'] ) && $attributes['maxPerOrder'] !== ''
	? max( 0, intval( $attributes['maxPerOrder'] ) )
	: 2;

/**
 * Build the REST proxy endpoint your JS should call.
 * Using rest_url() ensures correct domain/path across environments.
 */
$endpoint = esc_url( rest_url( 'gaming-tickets/v1/instances' ) );

/**
 * Provide an initial month (YYYY-MM) for the calendar.
 * We use GMT to keep it deterministic server-side.
 */
$initial_month = gmdate( 'Y-m' );
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<!-- Optional banner so you can verify SSR ran -->
	<div class="gtx-banner">
		<strong>Widget root OK</strong> — rendered by <code>render.php</code>.
	</div>

	<!--
	  Mount point for the React app.
	  The view script finds all .gtx-root elements and calls ReactDOM.createRoot(node).
	  All configuration is passed via data-* attributes.
	-->
	<div
		class="gtx-root"
		data-event-id="<?php echo esc_attr( $event_id ); ?>"
		data-rest-endpoint="<?php echo $endpoint; ?>"
		data-initial-month="<?php echo esc_attr( $initial_month ); ?>"
		<?php if ( $max_per_order > 0 ) : ?>
			data-max-per-order="<?php echo esc_attr( $max_per_order ); ?>"
		<?php endif; ?>
		data-rest-nonce="<?php echo esc_attr( $nonce ); ?>"
	></div>
</div>
