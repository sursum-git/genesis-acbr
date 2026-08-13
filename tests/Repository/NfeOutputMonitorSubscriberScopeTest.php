<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeOutputMonitorRepository;
use Doctrine\DBAL\DriverManager;

function assertScopeSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, c_cod_programa TEXT, si_status_processamento INTEGER, si_status_http INTEGER, dt_hr_recebimento TEXT, t_erro TEXT, t_corpo_resposta TEXT, t_assinante_json TEXT, t00002_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99008 (id_t99008 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, schema_family TEXT)');
$connection->executeStatement('CREATE TABLE t99019 (id_t99019 INTEGER PRIMARY KEY AUTOINCREMENT, t99008_id INTEGER, ch_nfe TEXT, n_nf TEXT, mod TEXT, serie TEXT, dh_emi TEXT, v_nf TEXT, xml_autorizado TEXT, caminho_danfe TEXT)');
$connection->executeStatement('CREATE TABLE t99020 (id_t99020 INTEGER PRIMARY KEY AUTOINCREMENT, nome_razao_social TEXT, cnpj TEXT)');
$connection->executeStatement('CREATE TABLE t99021 (id_t99021 INTEGER PRIMARY KEY AUTOINCREMENT, nome_razao_social TEXT, cnpj TEXT)');
$connection->executeStatement('CREATE TABLE t99023 (id_t99023 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99020_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99024 (id_t99024 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99021_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99032 (id_t99032 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, tag TEXT, tag_api TEXT)');
$connection->executeStatement('CREATE TABLE t99033 (id_t99033 INTEGER PRIMARY KEY AUTOINCREMENT, t99032_id INTEGER, nome_imposto TEXT, cst TEXT, base_calculo TEXT, aliquota TEXT, valor TEXT)');

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_assinante_json, t00002_id) VALUES ('req-a', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 10:00:00', '{\"id_t00002\":10,\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}', 10)");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (1, 'req-a', 'procNFe')");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (1, 1, '35123456789012345678901234567890123456789012', '1', '55', '1', '2026-07-03 09:59:00', '10.00', '<xml>a</xml>', '')");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_assinante_json, t00002_id) VALUES ('req-b', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 11:00:00', '{\"id_t00002\":11,\"c_identificador\":\"cliente_b\",\"c_nome\":\"Cliente B\"}', 11)");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (2, 'req-b', 'procNFe')");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (2, 2, '44123456789012345678901234567890123456789012', '2', '55', '1', '2026-07-03 10:59:00', '20.00', '<xml>b</xml>', '')");

$repository = new NfeOutputMonitorRepository($connection);
$rows = $repository->search(['subscriber_ids' => [10]]);

assertScopeSame(1, count($rows), 'monitor search should only return rows from allowed subscribers.');
assertScopeSame('req-a', $rows[0]['request_id'], 'monitor scope should preserve the allowed subscriber row.');
assertScopeSame(null, $repository->findByRequestId('req-b', [10]), 'detail lookup should reject rows outside allowed subscriber scope.');
assertScopeSame('req-a', $repository->findByRequestId('req-a', [10])['request_id'] ?? null, 'detail lookup should allow rows inside subscriber scope.');

fwrite(STDOUT, "OK\n");
