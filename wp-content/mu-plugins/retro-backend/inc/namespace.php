<?php
namespace Retro\Backend;

defined('ABSPATH') || exit;

function setup(): void {
    if (defined('WP_DEBUG') && WP_DEBUG) error_log('Retro\\Backend: setup()');

    require_once __DIR__ . '/cpt/namespace.php';
    require_once __DIR__ . '/tax/namespace.php';
    require_once __DIR__ . '/acf/namespace.php';
    require_once __DIR__ . '/meta/namespace.php';

    $rest = __DIR__ . '/rest/namespace.php';
    if (file_exists($rest)) {
        require_once $rest;
    }

    \Retro\Backend\Cpt\setup();
    \Retro\Backend\Tax\setup();
    \Retro\Backend\Acf\setup();
    \Retro\Backend\Meta\setup();

    if (\function_exists('\\Retro\\Backend\\Rest\\setup')) {
        \Retro\Backend\Rest\setup();
    }
}
