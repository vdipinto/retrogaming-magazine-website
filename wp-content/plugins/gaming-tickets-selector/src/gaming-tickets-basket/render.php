<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<?php if ( ! defined( 'ABSPATH' ) ) exit;
$wrapper = function_exists('get_block_wrapper_attributes')
  ? get_block_wrapper_attributes([ 'class' => 'wp-block-gts-basket' ])
  : 'class="wp-block-gts-basket"';
$nonce = wp_create_nonce('wp_rest');
?>
<div <?php echo $wrapper; // phpcs:ignore ?>>
  <div class="gts-basket-root" data-rest-nonce="<?php echo esc_attr($nonce); ?>"></div>
</div>