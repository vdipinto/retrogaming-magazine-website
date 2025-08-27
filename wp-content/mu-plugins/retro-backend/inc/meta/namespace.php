<?php
namespace Retro\Backend\Meta;

defined('ABSPATH') || exit;

function setup(): void {
    // Load the ACF-specific hooks for auto logic
    require_once __DIR__ . '/acf-hooks.php';
    \Retro\Backend\Meta\Acf\setup();
}