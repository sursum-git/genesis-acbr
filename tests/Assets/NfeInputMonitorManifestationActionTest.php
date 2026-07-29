<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/templates/admin/nfe_input_monitor.html.twig');
$script = file_get_contents($root . '/catalog-assets/monitor/nfe-output-monitor.js');

if (!is_string($template) || !is_string($script)) {
    fwrite(STDERR, "Input monitor template and script should be readable.\n");
    exit(1);
}

foreach (['data-manifestation-url', 'data-default-environment="1"', 'manifestationUrl', 'Manifestar', 'nfe-manifestation-type', '210200', '210210', '210220', '210240'] as $expectedToken) {
    if (!str_contains($template . $script, $expectedToken)) {
        fwrite(STDERR, "Input monitor should expose recipient manifestation token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (["$('#nfe-manifestation-type').kendoDropDownList({", 'appendTo: actionWindowElement'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "Recipient manifestation dropdown should render its popup inside the Kendo window: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['id="nfe-action-window"', 'id="nfe-action-token"', 'id="nfe-action-justification"', 'id="nfe-manifestation-type"'] as $expectedToken) {
    if (!str_contains($template, $expectedToken)) {
        fwrite(STDERR, "Input monitor should expose Kendo action window token: {$expectedToken}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
