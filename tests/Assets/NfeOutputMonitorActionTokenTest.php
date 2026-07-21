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

foreach (['firstResponseValue', 'responseTextCandidates', 'cStat', 'nProt', 'Request ID'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe action result should expose confirmation detail: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['data-action-event-url', 'actionEventUrl', 'monitor-nfe-situation', 'nfe-fiscal-events-grid'] as $expectedToken) {
    if (!str_contains($template . $script, $expectedToken)) {
        fwrite(STDERR, "NFe monitor should expose fiscal event UI token: {$expectedToken}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
