<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeOutputMonitorRepository;
use Doctrine\DBAL\DriverManager;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

$connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, c_cod_programa TEXT, si_status_processamento INTEGER, si_status_http INTEGER, dt_hr_recebimento TEXT, t_erro TEXT, t_corpo_resposta TEXT, t_assinante_json TEXT)');
$connection->executeStatement('CREATE TABLE t99008 (id_t99008 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, schema_family TEXT)');
$connection->executeStatement('CREATE TABLE t99010 (t99008_id INTEGER PRIMARY KEY, tp_amb INTEGER)');
$connection->executeStatement('CREATE TABLE t99016 (t99008_id INTEGER PRIMARY KEY, ch_nfe TEXT, tp_evento TEXT, dh_evento TEXT, c_stat INTEGER, x_motivo TEXT, n_prot TEXT)');
$connection->executeStatement('CREATE TABLE t99019 (id_t99019 INTEGER PRIMARY KEY AUTOINCREMENT, t99008_id INTEGER, ch_nfe TEXT, n_nf TEXT, mod TEXT, serie TEXT, dh_emi TEXT, v_nf TEXT, xml_autorizado TEXT, caminho_danfe TEXT)');
$connection->executeStatement('CREATE TABLE t99020 (id_t99020 INTEGER PRIMARY KEY AUTOINCREMENT, nome_razao_social TEXT, cnpj TEXT)');
$connection->executeStatement('CREATE TABLE t99021 (id_t99021 INTEGER PRIMARY KEY AUTOINCREMENT, nome_razao_social TEXT, cnpj TEXT)');
$connection->executeStatement('CREATE TABLE t99023 (id_t99023 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99020_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99024 (id_t99024 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99021_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99032 (id_t99032 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, tag TEXT, tag_api TEXT)');
$connection->executeStatement('CREATE TABLE t99033 (id_t99033 INTEGER PRIMARY KEY AUTOINCREMENT, t99032_id INTEGER, nome_imposto TEXT, cst TEXT, base_calculo TEXT, aliquota TEXT, valor TEXT)');

// Existing application table for success parameters. It is not the NFe fiscal-event table.
$connection->executeStatement('CREATE TABLE t99034 (id_t99034 INTEGER PRIMARY KEY AUTOINCREMENT, c_nome TEXT, dt_inicio_vigencia TEXT)');

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-ok', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 10:00:00', NULL, NULL, '{\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (1, 'req-ok', 'procNFe')");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, tp_amb) VALUES (1, 2)");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (1, 1, '35123456789012345678901234567890123456789012', '123', '55', '3', '2026-07-03 09:59:00', '155.40', '<xml>ok</xml>', '')");
$connection->executeStatement("INSERT INTO t99020 (id_t99020, nome_razao_social, cnpj) VALUES (1, 'EMITENTE A', '11111111000111')");
$connection->executeStatement("INSERT INTO t99021 (id_t99021, nome_razao_social, cnpj) VALUES (1, 'CLIENTE A', '12345678000199')");
$connection->executeStatement("INSERT INTO t99023 (t99019_id, t99020_id) VALUES (1, 1)");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (1, 1)");
$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-cancel', '/nfe/eventos/cancelar', 'nfe', 3, 200, '2026-07-03 10:05:00', NULL, NULL, '{\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (2, 'req-cancel', 'procEventoNFe')");
$connection->executeStatement("INSERT INTO t99016 (t99008_id, ch_nfe, tp_evento, dh_evento, c_stat, x_motivo, n_prot) VALUES (2, '35123456789012345678901234567890123456789012', '110111', '2026-07-03 10:04:00', 135, 'Evento registrado e vinculado a NF-e', '135260000000001')");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-rejected', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 09:00:00', NULL, NULL, '{\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (3, 'req-rejected', 'procNFe')");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, tp_amb) VALUES (3, 2)");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (2, 3, '32260606013812000158550030001972451604403619', '197245', '55', '3', '2026-07-03 08:59:00', '155.40', '<xml>ok</xml>', '')");
$connection->executeStatement("INSERT INTO t99023 (t99019_id, t99020_id) VALUES (2, 1)");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (2, 1)");
$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-cancel-rejected', '/nfe/eventos/cancelar', 'nfe', 3, 200, '2026-07-03 09:05:00', NULL, NULL, '{\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (4, 'req-cancel-rejected', 'procEventoNFe')");
$connection->executeStatement("INSERT INTO t99016 (t99008_id, ch_nfe, tp_evento, dh_evento, c_stat, x_motivo, n_prot) VALUES (4, '32260606013812000158550030001972451604403619', '110111', '2026-07-03 09:04:00', 501, 'Rejeicao: Prazo de Cancelamento Superior ao Previsto na Legislacao', '')");

$repository = new NfeOutputMonitorRepository($connection);
$rows = $repository->search(['ambiente' => '2']);

assertSameValue(2, count($rows), 'search should ignore unrelated t99034 table and keep output monitor populated.');
assertSameValue('Cancelada', $rows[0]['situacao_nfe'], 'Cancellation extracted from SEFAZ event should update fiscal situation even without automatic event table.');
assertSameValue(1, count($rows[0]['eventos_nfe'] ?? []), 'Cancellation extracted from SEFAZ event should appear in fiscal events.');
assertSameValue('req-cancel', $rows[0]['eventos_nfe'][0]['request_id'] ?? null, 'Fallback fiscal event should expose cancellation request id.');
assertSameValue('Autorizada', $rows[1]['situacao_nfe'], 'Rejected cancellation should not mark note as canceled.');
assertSameValue(1, count($rows[1]['eventos_nfe'] ?? []), 'Rejected cancellation extracted from SEFAZ event should appear in fiscal events.');
assertSameValue('Erro no cancelamento', $rows[1]['eventos_nfe'][0]['situacao'] ?? null, 'Rejected cancellation should expose error event situation.');
assertSameValue('501', $rows[1]['eventos_nfe'][0]['c_stat'] ?? null, 'Rejected cancellation event should expose cStat.');

fwrite(STDOUT, "OK\n");
