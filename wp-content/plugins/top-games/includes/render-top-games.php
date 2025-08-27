<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Server-side render callback for "Top Games" block.
 * Markup: each item is a <div class="top-games__card"> with a full-card <a>, media, and content.
 */
function top_games_render_block( array $attributes = [], string $content = '', $block = null ): string {
	$max_to_show = 2;

	// ---- Attributes (keep in sync with block.json / editor) ----
	$selected = isset( $attributes['selected'] ) && is_array( $attributes['selected'] )
		? array_values( array_filter( array_map( 'intval', $attributes['selected'] ) ) )
		: [];

	$items = isset( $attributes['items'] ) ? (int) $attributes['items'] : 1;
	$items = max( 1, min( $max_to_show, $items ) );

	$offset = isset( $attributes['offset'] ) ? (int) $attributes['offset'] : 0;
	$offset = max( 0, $offset );

	$show_thumbs = array_key_exists( 'showThumbs', $attributes )
		? (bool) $attributes['showThumbs']
		: true;

	$variant = isset( $attributes['variant'] ) && is_string( $attributes['variant'] )
		? $attributes['variant']
		: 'standard';

	$heading_text = isset( $attributes['headingText'] ) ? (string) $attributes['headingText'] : '';

	// ---- Post type safety ----
	$post_type = post_type_exists( 'game' ) ? 'game' : ( post_type_exists( 'games' ) ? 'games' : '' );
	if ( '' === $post_type ) {
		return is_user_logged_in()
			? '<div class="notice notice-error"><p>Top Games: post type <code>game</code> (or <code>games</code>) not found.</p></div>'
			: '';
	}

	// ---- Query (mirror editor behavior) ----
	$args = array(
		'post_type'               => $post_type,
		'post_status'             => 'publish',
		'ignore_sticky_posts'     => true,
		'no_found_rows'           => true,
		'update_post_term_cache'  => true,
	);

	if ( ! empty( $selected ) ) {
		$selected = array_slice( $selected, 0, $max_to_show );
		$args += array(
			'post__in'       => $selected,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $selected ),
		);
	} else {
		$args += array(
			'posts_per_page' => $items,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
	}

	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		return is_user_logged_in()
			? '<div class="notice notice-warning"><p>Top Games: no matching posts found.</p></div>'
			: '';
	}

	// ---- Wrapper classes/attrs ----
	$extra_classes = implode( ' ', array(
		'top-games',
		'top-games--items-' . ( empty( $selected ) ? $items : count( $selected ) ),
		'top-games--variant-' . sanitize_html_class( $variant ),
	) );

	$wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'wp-block-top-games ' . $extra_classes ) )
		: 'class="wp-block-top-games ' . esc_attr( $extra_classes ) . '"';

	// Image size per variant (editor uses similar logic)
	$image_size = ( 'featured' === $variant ) ? 'large' : 'medium_large';

	ob_start(); ?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( $heading_text !== '' ) : ?>
			<div class="top-games__heading"><?php echo esc_html( $heading_text ); ?></div>
		<?php endif; ?>

		<div class="top-games__list">
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				$post_id = get_the_ID();
				$title   = get_the_title( $post_id );
				$link    = get_permalink( $post_id );
				$excerpt = get_the_excerpt( $post_id ); // used only for featured variant
				?>
				<div class="top-games__card<?php echo ( 'featured' === $variant ) ? ' is-featured' : ' is-standard'; ?>">
					<a
						class="top-games__link"
						href="<?php echo esc_url( $link ); ?>"
						aria-label="<?php echo esc_attr( $title ); ?>"
					></a>

					<div class="top-games__media">
						<?php
						if ( $show_thumbs && has_post_thumbnail( $post_id ) ) {
							// Responsive, includes width/height/srcset.
							echo wp_get_attachment_image(
								get_post_thumbnail_id( $post_id ),
								$image_size,
								false,
								array(
									'class'    => 'top-games__img',
									'loading'  => 'lazy',
									'decoding' => 'async',
									'alt'      => $title,
								)
							);
						}
						?>
					</div>

					<div class="top-games__content">
						<?php
						// First Genre term (linked), if any.
						$terms = get_the_terms( $post_id, 'genre' );
						if ( $terms && ! is_wp_error( $terms ) ) {
							$first = reset( $terms );
							if ( $first && isset( $first->term_id ) ) {
								$term_link = get_term_link( $first, 'genre' );
								if ( ! is_wp_error( $term_link ) ) {
									printf(
										'<div class="top-games__meta"><a class="top-games__genre" href="%1$s">%2$s</a></div>',
										esc_url( $term_link ),
										esc_html( $first->name )
									);
								}
							}
						}
						?>

						<h3 class="top-games__title">
							<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
						</h3>

						<?php if ( 'featured' === $variant && $excerpt ) : ?>
							<p class="top-games__excerpt"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endwhile; ?>
		</div>
	</div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
}
