<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeInputFiscalEventRepository;
use Doctrine\DBAL\DriverManager;

function assertSameInputEventValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t99008 (id_t99008 INTEGER PRIMARY KEY AUTOINCREMENT, ch_nfe TEXT)');
$connection->executeStatement("INSERT INTO t99008 (id_t99008, ch_nfe) VALUES (1, '32260712345678000199550030006420801000000012')");

$repository = new NfeInputFiscalEventRepository($connection);
$event = $repository->recordManifestationResult(
    1,
    'req-manifest',
    [
        'chave' => '32260712345678000199550030006420801000000012',
        'documento_destinatario' => '06013812000158',
        'tpEvento' => '210240',
        'justificativa' => 'Mercadoria recusada no recebimento.',
    ],
    [
        'resultado' => [
            'mensagem' => "[Evento]\nCStat=128\nXMotivo=Lote de Evento Processado\n\n[Evento001]\nCStat=135\nXMotivo=Evento registrado e vinculado a NF-e\nnProt=135260000000009\n",
        ],
    ]
);

assertSameInputEventValue('210240', $event['tipo_evento'] ?? null, 'Manifestation should record the selected tpEvento.');
assertSameInputEventValue('Operação não Realizada', $event['tipo_acao'] ?? null, 'Manifestation should expose a user-friendly action.');
assertSameInputEventValue('Registrado', $event['situacao'] ?? null, 'Successful manifestation should be registered.');
assertSameInputEventValue('135', $event['c_stat'] ?? null, 'Manifestation should record the event cStat instead of the batch cStat.');
assertSameInputEventValue('Evento registrado e vinculado a NF-e', $event['motivo'] ?? null, 'Manifestation should record only the event reason.');
assertSameInputEventValue('135260000000009', $event['protocolo'] ?? null, 'Manifestation should record protocol.');

$events = $repository->findByAccessKey('32260712345678000199550030006420801000000012');
assertSameInputEventValue(1, count($events), 'Repository should list recorded manifestations by access key.');
assertSameInputEventValue('req-manifest', $events[0]['request_id'] ?? null, 'Repository should expose manifestation request id.');

fwrite(STDOUT, "OK\n");
