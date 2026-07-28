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
$connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, t_corpo_requisicao TEXT, dt_hr_recebimento TEXT)');

$connection->executeStatement("INSERT INTO t99019 (id_t99019, ch_nfe, n_nf, situacao_fiscal) VALUES (1, '32260606013812000158550030001972461604403624', '197246', 'Autorizada')");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, ch_nfe, n_nf, situacao_fiscal) VALUES (2, '32260606013812000158550030001972451604403619', '197245', 'Autorizada')");
$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, t_corpo_requisicao, dt_hr_recebimento) VALUES ('req-audit-cancel-error', '/nfe/eventos/cancelar', '{\"payload\":{\"AeChave\":\"32260606013812000158550030001972451604403619\"}}', '2026-07-22T18:34:49+00:00')");
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

$correctionEvent = $repository->recordActionResult(
    1,
    'carta_correcao',
    'req-cce-ok',
    [
        'AeChave' => '32260606013812000158550030001972461604403624',
        'xCorrecao' => 'Corrige as informações adicionais da NF-e sem alterar valores fiscais.',
    ],
    [
        'resultado' => [
            'mensagem' => "CStat=135\nXMotivo=Evento registrado e vinculado a NF-e\nnProt=135260000000003\ntpEvento=110110",
        ],
    ]
);

assertSameValue('carta_correcao', $correctionEvent['tipo_acao'] ?? null, 'CC-e action should be recorded as correction letter.');
assertSameValue('Carta de Correção registrada', $correctionEvent['situacao'] ?? null, 'Successful CC-e should record user-friendly situation.');
assertSameValue('110110', $correctionEvent['tipo_evento'] ?? null, 'CC-e should record NF-e event type 110110.');
assertSameValue('135', $correctionEvent['c_stat'] ?? null, 'Successful CC-e should record cStat.');
assertSameValue('135260000000003', $correctionEvent['protocolo'] ?? null, 'Successful CC-e should record protocol.');
assertSameValue('Cancelada', $connection->fetchOne('SELECT situacao_fiscal FROM t99019 WHERE id_t99019 = 1'), 'Successful CC-e should not replace canceled fiscal situation.');

$rejectedCancelEvent = $repository->recordActionResult(
    2,
    'cancelar',
    'req-cancel-rejected',
    ['AeChave' => '32260606013812000158550030001972451604403619'],
    [
        'resultado' => [
            'member' => [
                "[Cancelamento]\nCStat=501\nXMotivo=Rejeicao: Prazo de Cancelamento Superior ao Previsto na Legislacao\nnProt=\ntpEvento=110111\n",
            ],
        ],
    ]
);

assertSameValue('cancelamento', $rejectedCancelEvent['tipo_acao'] ?? null, 'Rejected cancel action should be recorded as cancellation.');
assertSameValue('Erro no cancelamento', $rejectedCancelEvent['situacao'] ?? null, 'Rejected cancellation should record error situation.');
assertSameValue('501', $rejectedCancelEvent['c_stat'] ?? null, 'Rejected cancellation should extract cStat from resultado.member.');
assertSameValue('Rejeicao: Prazo de Cancelamento Superior ao Previsto na Legislacao', $rejectedCancelEvent['motivo'] ?? null, 'Rejected cancellation should extract xMotivo from resultado.member.');
assertSameValue('Autorizada', $connection->fetchOne('SELECT situacao_fiscal FROM t99019 WHERE id_t99019 = 2'), 'Rejected cancellation should not mark the note as canceled.');

$legacyErrorEvent = $repository->recordActionResult(
    2,
    'cancelar',
    '',
    ['AeChave' => '32260606013812000158550030001972451604403619'],
    [
        'resultado' => [
            'member' => [
                'Erro ao cancelar NFe Código de erro: -14. Último retorno: Rejeicao: NF-e nao consta na base de dados da SEFAZ',
            ],
        ],
    ]
);

assertSameValue('Erro no cancelamento', $legacyErrorEvent['situacao'] ?? null, 'Legacy cancel error should be recorded as cancellation error.');
assertSameValue('req-audit-cancel-error', $legacyErrorEvent['request_id'] ?? null, 'Legacy cancel error should be linked to the audit request when the response does not expose request_id.');
assertSameValue('Rejeicao: NF-e nao consta na base de dados da SEFAZ', $legacyErrorEvent['motivo'] ?? null, 'Legacy cancel error should extract the last SEFAZ return as reason.');
assertSameValue('Autorizada', $connection->fetchOne('SELECT situacao_fiscal FROM t99019 WHERE id_t99019 = 2'), 'Legacy cancel error should not mark the note as canceled.');

$events = $repository->findByNoteId(1);
assertSameValue(3, count($events), 'Repository should list all fiscal events for the note.');
assertSameValue('req-cce-ok', $events[0]['request_id'] ?? null, 'Newest event should come first.');
assertSameValue('req-inut-error', $events[1]['request_id'] ?? null, 'Inutilization event should remain available.');
assertSameValue('req-cancel-ok', $events[2]['request_id'] ?? null, 'Older event should remain available.');

fwrite(STDOUT, "OK\n");
