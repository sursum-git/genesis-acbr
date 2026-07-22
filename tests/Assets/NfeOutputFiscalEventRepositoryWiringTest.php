<?php

declare(strict_types=1);

$services = file_get_contents(dirname(__DIR__, 2) . '/config/services.yaml');
if ($services === false) {
    fwrite(STDERR, "Could not read services.yaml.\n");
    exit(1);
}

$expected = <<<'YAML'
  App\Repository\NfeOutputFiscalEventRepository:
    arguments:
      $auditConnection: '@app.audit_connection'
YAML;

if (!str_contains($services, $expected)) {
    fwrite(STDERR, "NfeOutputFiscalEventRepository must use app.audit_connection, not the default Doctrine connection.\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
