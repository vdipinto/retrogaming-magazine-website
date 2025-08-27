<?php
// wp-content/mu-plugins/rtg-protect-block-render.php
add_filter('render_block', function($content, $block){
  if (!is_array($block) || ($block['blockName'] ?? '') !== 'retro/top-games') return $content;
  static $original;
  if ($original === null) $original = $content; // stash original early
  return $content;
}, 1, 2);

add_filter('render_block', function($content, $block){
  if (!is_array($block) || ($block['blockName'] ?? '') !== 'retro/top-games') return $content;
  $is_rest = (function_exists('wp_is_rest') && wp_is_rest()) || (defined('REST_REQUEST') && REST_REQUEST);
  if ($is_rest && $content === '' && isset($GLOBALS['original'])) return $GLOBALS['original'];
  return $content;
}, PHP_INT_MAX, 2);
