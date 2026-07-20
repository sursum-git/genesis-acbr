<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/templates/admin/nfe_output_monitor.html.twig');
$script = file_get_contents($root . '/catalog-assets/monitor/nfe-output-monitor.js');

if (!is_string($template) || !str_contains($template, 'id="nfe-action-token"')) {
    fwrite(STDERR, "Action window should expose an API token field.\n");
    exit(1);
}

if (!is_string($script) || !str_contains($script, "'X-Api-Token': token")) {
    fwrite(STDERR, "NFe action requests should send X-Api-Token header.\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
