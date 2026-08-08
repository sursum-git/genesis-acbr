<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/templates/admin/nfe_output_monitor.html.twig');
$script = file_get_contents($root . '/catalog-assets/monitor/nfe-output-monitor.js');

if (!is_string($template) || str_contains($template, 'id="nfe-action-token"')) {
    fwrite(STDERR, "Action window should not expose an API token field.\n");
    exit(1);
}

if (!is_string($script) || str_contains($script, "'X-Api-Token': token") || str_contains($script, 'nfe-action-token')) {
    fwrite(STDERR, "NFe action requests should not send a browser-provided API token.\n");
    exit(1);
}

foreach (['firstResponseValue', 'responseTextCandidates', 'cStat', 'nProt', 'Request ID'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe action result should expose confirmation detail: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['extractLastLineValue', 'response.event', "event.motivo", "event.c_stat"] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe action result should summarize fiscal event responses without raw ACBr output: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['data-action-event-url', 'actionEventUrl', 'monitor-nfe-situation', 'monitor-nfe-events', 'nfe-fiscal-events-grid'] as $expectedToken) {
    if (!str_contains($template . $script, $expectedToken)) {
        fwrite(STDERR, "NFe monitor should expose fiscal event UI token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['monitor-row-actions', 'initializeRowActionButtons', '.kendoDropDownButton({', "title: 'Ações'", 'locked: true'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe monitor should expose a left Kendo dropdown action button token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (["popup: { appendTo: document.body }", "click: function (event)", 'handleRowAction(String(event.id || \'\'), row);'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe row action dropdown should be anchored by Kendo to its own button: {$expectedToken}.\n");
        exit(1);
    }
}

if (str_contains($script, '.kendoContextMenu(') || str_contains($script, 'rowActionMenu.open(')) {
    fwrite(STDERR, "NFe row actions should not use the coordinate-based Kendo ContextMenu.\n");
    exit(1);
}

foreach (['data-cancel-url', 'data-inutilize-url', '/monitor-saida-nfe/acoes/cancelar', '/monitor-saida-nfe/acoes/inutilizar'] as $expectedToken) {
    if (!str_contains($template, $expectedToken)) {
        fwrite(STDERR, "NFe output monitor should use internal action endpoint token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['data-correction-url', 'correctionUrl', 'Carta de Correção', 'carta_correcao', 'correcao'] as $expectedToken) {
    if (!str_contains($template . $script, $expectedToken)) {
        fwrite(STDERR, "NFe monitor should expose correction letter action token: {$expectedToken}.\n");
        exit(1);
    }
}

foreach (['fiscalEventsFromRow', "row.get('eventos_nfe')", 'events.toJSON'] as $expectedToken) {
    if (!str_contains($script, $expectedToken)) {
        fwrite(STDERR, "NFe monitor event window should support Kendo observable event arrays: {$expectedToken}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
