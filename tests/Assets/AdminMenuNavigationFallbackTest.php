<?php

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/templates/admin/base.html.twig');
$scriptPath = $root . '/catalog-assets/adminlte-portal.js';

if (!is_string($template) || !str_contains($template, '/catalog-assets/adminlte-portal.js')) {
    fwrite(STDERR, "Admin layout should include the local AdminLTE navigation fallback script.\n");
    exit(1);
}

if (!is_file($scriptPath)) {
    fwrite(STDERR, "AdminLTE navigation fallback script should exist.\n");
    exit(1);
}

$script = file_get_contents($scriptPath);
if (!is_string($script)) {
    fwrite(STDERR, "AdminLTE navigation fallback script should be readable.\n");
    exit(1);
}

foreach ([
    '.app-sidebar .nav-link[href]',
    'data-lte-toggle="sidebar"',
    '.sidebar-overlay',
    'window.location.assign',
] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "AdminLTE navigation fallback should contain token: {$expectedToken}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
