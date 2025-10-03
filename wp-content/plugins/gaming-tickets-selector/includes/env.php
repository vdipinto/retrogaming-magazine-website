<?php
/**
 * Shared Pretix environment helper for Gaming Tickets plugin.
 *
 * One single place to read Pretix configuration from environment variables:
 *   - PRETIX_ORG
 *   - PRETIX_EVENT
 *   - PRETIX_BASE
 *   - PRETIX_TOKEN
 *
 * Other files include this helper:
 *   require_once __DIR__ . '/env.php';
 *
 * Wrapped in function_exists() to avoid re-declare fatals.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'gts_pretix_env' ) ) {
    function gts_pretix_env() {
        return [
            'org'   => getenv('PRETIX_ORG')   ?: 'myorg',
            'event' => getenv('PRETIX_EVENT') ?: 'sessionplay',
            'base'  => rtrim(getenv('PRETIX_BASE') ?: 'http://host.docker.internal:8345', '/'),
            'token' => getenv('PRETIX_TOKEN') ?: '',
        ];
    }
}
