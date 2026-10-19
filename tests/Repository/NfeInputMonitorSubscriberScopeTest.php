<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeInputMonitorRepository;
use Doctrine\DBAL\DriverManager;

function assertInputScopeSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, c_cod_programa TEXT, si_status_http INTEGER, si_status_extracao INTEGER, t_erro_extracao TEXT, t_assinante_json TEXT, dt_hr_recebimento TEXT, t00002_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99007 (id_t99007 INTEGER PRIMARY KEY AUTOINCREMENT, t99001_id INTEGER, documento_consulta TEXT, tipo_consulta TEXT, nsu_entrada TEXT, ult_nsu TEXT, max_nsu TEXT, q_doc_zip INTEGER, xml_envelope TEXT, dh_resp TEXT, caminho_origem TEXT, tp_amb INTEGER)');
$connection->executeStatement('CREATE TABLE t99008 (id_t99008 INTEGER PRIMARY KEY AUTOINCREMENT, t99007_id INTEGER, schema_name TEXT, schema_family TEXT, ch_nfe TEXT, nsu TEXT, n_prot TEXT, xml_descompactado TEXT, emit_cnpj_cpf TEXT, dt_hr_processado_em TEXT, tp_amb INTEGER)');
$connection->executeStatement('CREATE TABLE t99009 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, x_nome TEXT, ie TEXT, dh_emi TEXT, tp_nf INTEGER, v_nf TEXT, dig_val TEXT, dh_recbto TEXT, c_sit_nfe TEXT, versao TEXT)');
$connection->executeStatement('CREATE TABLE t99010 (t99008_id INTEGER PRIMARY KEY, n_nf TEXT, dh_emi TEXT, ch_nfe TEXT, n_prot TEXT)');
$connection->executeStatement('CREATE TABLE t99011 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, x_nome TEXT)');
$connection->executeStatement('CREATE TABLE t99012 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, id_estrangeiro TEXT, x_nome TEXT)');
$connection->executeStatement('CREATE TABLE t99013 (id_t99013 INTEGER PRIMARY KEY AUTOINCREMENT, t99008_id INTEGER, n_item INTEGER, c_prod TEXT, x_prod TEXT, c_ncm TEXT, c_cfop TEXT, q_com TEXT, u_com TEXT, v_un_com TEXT, v_prod TEXT, v_desc TEXT, v_frete TEXT, v_seg TEXT)');
$connection->executeStatement('CREATE TABLE t99014 (t99008_id INTEGER PRIMARY KEY, v_nf TEXT, v_icms TEXT, v_cofins TEXT, v_pis TEXT, v_ipi TEXT)');
$connection->executeStatement('CREATE TABLE t99015 (t99008_id INTEGER PRIMARY KEY, x_evento TEXT)');
$connection->executeStatement('CREATE TABLE t99016 (t99008_id INTEGER PRIMARY KEY, ch_nfe TEXT, tp_evento TEXT, dh_evento TEXT, c_stat INTEGER, x_motivo TEXT, n_prot TEXT, desc_evento TEXT)');

$connection->executeStatement("INSERT INTO t99001 (id_t99001, u_c_request_id, c_caminho, c_cod_programa, si_status_http, si_status_extracao, t_assinante_json, dt_hr_recebimento, t00002_id) VALUES (1, 'req-a', '/nfe/distribuicao-dfe/por-ult-nsu', 'nfe', 200, 3, '{\"id_t00002\":10,\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}', '2026-07-15 10:00:00', 10)");
$connection->executeStatement("INSERT INTO t99007 (id_t99007, t99001_id, documento_consulta, tipo_consulta, nsu_entrada, ult_nsu, max_nsu, q_doc_zip, xml_envelope, dh_resp, caminho_origem, tp_amb) VALUES (1, 1, '11111111000111', 'CNPJ', '1', '1', '1', 1, '<retDistDFeInt/>', '2026-07-15 10:00:01', '/nfe/distribuicao-dfe/por-ult-nsu', 1)");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, t99007_id, schema_name, schema_family, ch_nfe, nsu, n_prot, xml_descompactado, emit_cnpj_cpf, dt_hr_processado_em, tp_amb) VALUES (1, 1, 'resNFe_v1.01.xsd', 'resNFe', '35123456789012345678901234567890123456789012', '1', NULL, '<resNFe/>', '11111111000111', '2026-07-15 10:00:02', 1)");
$connection->executeStatement("INSERT INTO t99009 (t99008_id, cnpj, x_nome, dh_emi, tp_nf, v_nf, c_sit_nfe, versao) VALUES (1, '11111111000111', 'EMITENTE A', '2026-07-15 09:55:00', 1, '10.00', '100', '1.01')");

$connection->executeStatement("INSERT INTO t99001 (id_t99001, u_c_request_id, c_caminho, c_cod_programa, si_status_http, si_status_extracao, t_assinante_json, dt_hr_recebimento, t00002_id) VALUES (2, 'req-b', '/nfe/distribuicao-dfe/por-ult-nsu', 'nfe', 200, 3, '{\"id_t00002\":11,\"c_identificador\":\"cliente_b\",\"c_nome\":\"Cliente B\"}', '2026-07-15 11:00:00', 11)");
$connection->executeStatement("INSERT INTO t99007 (id_t99007, t99001_id, documento_consulta, tipo_consulta, nsu_entrada, ult_nsu, max_nsu, q_doc_zip, xml_envelope, dh_resp, caminho_origem, tp_amb) VALUES (2, 2, '22222222000122', 'CNPJ', '2', '2', '2', 1, '<retDistDFeInt/>', '2026-07-15 11:00:01', '/nfe/distribuicao-dfe/por-ult-nsu', 1)");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, t99007_id, schema_name, schema_family, ch_nfe, nsu, n_prot, xml_descompactado, emit_cnpj_cpf, dt_hr_processado_em, tp_amb) VALUES (2, 2, 'resNFe_v1.01.xsd', 'resNFe', '44123456789012345678901234567890123456789012', '2', NULL, '<resNFe/>', '22222222000122', '2026-07-15 11:00:02', 1)");
$connection->executeStatement("INSERT INTO t99009 (t99008_id, cnpj, x_nome, dh_emi, tp_nf, v_nf, c_sit_nfe, versao) VALUES (2, '22222222000122', 'EMITENTE B', '2026-07-15 10:55:00', 1, '20.00', '100', '1.01')");

$repository = new NfeInputMonitorRepository($connection);
$rows = $repository->search(['subscriber_ids' => [10]]);

assertInputScopeSame(1, count($rows), 'input monitor search should only return rows from allowed subscribers.');
assertInputScopeSame('1', $rows[0]['request_id'], 'input monitor scope should preserve the allowed subscriber row.');
assertInputScopeSame(null, $repository->findByRequestId('2', [10]), 'input detail lookup should reject rows outside allowed subscriber scope.');
assertInputScopeSame('1', $repository->findByRequestId('1', [10])['request_id'] ?? null, 'input detail lookup should allow rows inside subscriber scope.');
assertInputScopeSame('1', $repository->search(['subscriber_ids' => [], 'company_documents' => ['11111111000111']])[0]['request_id'] ?? null, 'input monitor should allow rows from linked company documents even without subscriber relation.');
assertInputScopeSame('1', $repository->findByRequestId('1', [], ['11111111000111'])['request_id'] ?? null, 'input detail lookup should allow rows from linked company documents even without subscriber relation.');
assertInputScopeSame([], $repository->search(['subscriber_ids' => [], 'company_documents' => []]), 'empty user scope should deny all input monitor rows.');
assertInputScopeSame(null, $repository->findByRequestId('1', [], []), 'empty user scope should deny input monitor detail lookup.');

$options = $repository->filterOptions(['subscriber_ids' => [10]]);
assertInputScopeSame(['EMITENTE A'], $options['emissores'], 'input filter options should only include allowed issuers.');
assertInputScopeSame(['Cliente A', 'cliente_a'], $options['assinantes'], 'input filter options should only include allowed subscribers.');
assertInputScopeSame(['EMITENTE A'], $repository->searchFilterOptions('emissor', 'EMITENTE', ['subscriber_ids' => [10]]), 'input filter lookup should only include allowed issuers.');

fwrite(STDOUT, "OK\n");
