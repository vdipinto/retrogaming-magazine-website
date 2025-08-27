<?php
/**
 * Plugin Name: Redis Cache Config (MU)
 * Description: Minimal Redis object cache configuration for all environments.
 * Version: 1.0.0
 * Author: You
 */

defined('ABSPATH') || exit;

/**
 * Enable the WordPress object cache. (Harmless if defined elsewhere.)
 */
if (!defined('WP_CACHE')) {
    define('WP_CACHE', true);
}

/**
 * Core Redis connection settings.
 * Prefer overriding these in wp-config.php or environment variables in prod.
 */
if (!defined('WP_REDIS_HOST'))     define('WP_REDIS_HOST', 'redis'); // Docker service/host
if (!defined('WP_REDIS_PORT'))     define('WP_REDIS_PORT', 6379);
if (!defined('WP_REDIS_DATABASE')) define('WP_REDIS_DATABASE', 0);   // optional, default 0

// Optional auth (set in wp-config.php for production)
// if (!defined('WP_REDIS_PASSWORD')) define('WP_REDIS_PASSWORD', 'changeme');

/**
 * Cache key salt — keeps keys unique per install/environment.
 * Make this environment-specific in wp-config.php for staging/prod.
 */
if (!defined('WP_CACHE_KEY_SALT')) define('WP_CACHE_KEY_SALT', 'wp-local:');

/**
 * Optional TTL cap (seconds) for cached values.
 * if (!defined('WP_REDIS_MAXTTL')) define('WP_REDIS_MAXTTL', 3600);
 */
