<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeOutputFiscalEventRepository;
use Doctrine\DBAL\DriverManager;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t99019 (id_t99019 INTEGER PRIMARY KEY AUTOINCREMENT, ch_nfe TEXT, n_nf TEXT, situacao_fiscal TEXT)');

$connection->executeStatement("INSERT INTO t99019 (id_t99019, ch_nfe, n_nf, situacao_fiscal) VALUES (1, '32260606013812000158550030001972461604403624', '197246', 'Autorizada')");
$repository = new NfeOutputFiscalEventRepository($connection);

$cancelEvent = $repository->recordActionResult(
    1,
    'cancelar',
    'req-cancel-ok',
    ['AeChave' => '32260606013812000158550030001972461604403624'],
    [
        'resultado' => [
            'mensagem' => "CStat=135\nXMotivo=Evento registrado e vinculado a NF-e\nnProt=135260000000001",
        ],
    ]
);

assertSameValue('cancelamento', $cancelEvent['tipo_acao'] ?? null, 'Cancel action should be recorded as cancellation.');
assertSameValue('Cancelada', $cancelEvent['situacao'] ?? null, 'Successful cancellation should record canceled situation.');
assertSameValue('135', $cancelEvent['c_stat'] ?? null, 'Successful cancellation should record cStat.');
assertSameValue('135260000000001', $cancelEvent['protocolo'] ?? null, 'Successful cancellation should record protocol.');
assertSameValue('Cancelada', $connection->fetchOne('SELECT situacao_fiscal FROM t99019 WHERE id_t99019 = 1'), 'Successful cancellation should update note fiscal situation.');

$inutEvent = $repository->recordActionResult(
    1,
    'inutilizar',
    'req-inut-error',
    ['ANumeroInicial' => '197246', 'ANumeroFinal' => '197246'],
    [
        'resultado' => [
            'mensagem' => "CStat=563\nXMotivo=Rejeicao: inutilizacao ja existe para esta faixa",
        ],
    ]
);

assertSameValue('inutilizacao', $inutEvent['tipo_acao'] ?? null, 'Inutilization action should be recorded.');
assertSameValue('Erro na inutilização', $inutEvent['situacao'] ?? null, 'Rejected inutilization should record an error event.');
assertSameValue('Cancelada', $connection->fetchOne('SELECT situacao_fiscal FROM t99019 WHERE id_t99019 = 1'), 'Rejected inutilization should not replace a successful fiscal situation.');

$events = $repository->findByNoteId(1);
assertSameValue(2, count($events), 'Repository should list all fiscal events for the note.');
assertSameValue('req-inut-error', $events[0]['request_id'] ?? null, 'Newest event should come first.');
assertSameValue('req-cancel-ok', $events[1]['request_id'] ?? null, 'Older event should remain available.');

fwrite(STDOUT, "OK\n");
