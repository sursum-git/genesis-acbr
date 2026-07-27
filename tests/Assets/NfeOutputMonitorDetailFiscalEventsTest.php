<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/templates/admin/nfe_output_monitor_detail.html.twig');
$inputTemplate = file_get_contents($root . '/templates/admin/nfe_input_monitor_detail.html.twig');
$script = file_get_contents($root . '/catalog-assets/monitor/nfe-output-monitor-detail.js');

if (!is_string($template) || !is_string($inputTemplate) || !is_string($script)) {
    fwrite(STDERR, "Detail template and script should be readable.\n");
    exit(1);
}

foreach (['Situação da NFe', 'detail.situacao_nfe', 'detail.eventos_nfe', 'nfe-output-detail-tabs', 'Eventos', 'nfe-detail-fiscal-events-grid'] as $expectedToken) {
    if (!str_contains($template, $expectedToken)) {
        fwrite(STDERR, "NFe detail should expose fiscal event UI token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['nfe-output-detail-tabs', 'Eventos', 'detail.eventos_nfe', 'nfe-detail-fiscal-events-grid'] as $expectedToken) {
    if (!str_contains($inputTemplate, $expectedToken)) {
        fwrite(STDERR, "NFe input detail should expose fiscal event UI token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['nfe-detail-fiscal-events-grid', 'kendoGrid', 'nfe-output-detail-tabs', 'kendoTabStrip', 'Nenhum evento fiscal registrado'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe detail script should initialize fiscal events grid token: {$expectedToken}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
