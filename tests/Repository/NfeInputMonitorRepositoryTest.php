<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeInputMonitorRepository;
use Doctrine\DBAL\DriverManager;

function assertSameInputValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

$connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, c_cod_programa TEXT, si_status_http INTEGER, si_status_extracao INTEGER, t_erro_extracao TEXT, t_assinante_json TEXT, dt_hr_recebimento TEXT)');
$connection->executeStatement('CREATE TABLE t99007 (id_t99007 INTEGER PRIMARY KEY AUTOINCREMENT, t99001_id INTEGER, documento_consulta TEXT, tipo_consulta TEXT, nsu_entrada TEXT, ult_nsu TEXT, max_nsu TEXT, q_doc_zip INTEGER, xml_envelope TEXT, dh_resp TEXT, caminho_origem TEXT, tp_amb INTEGER)');
$connection->executeStatement('CREATE TABLE t99008 (id_t99008 INTEGER PRIMARY KEY AUTOINCREMENT, t99007_id INTEGER, schema_name TEXT, schema_family TEXT, ch_nfe TEXT, nsu TEXT, n_prot TEXT, xml_descompactado TEXT, emit_cnpj_cpf TEXT, dt_hr_processado_em TEXT, tp_amb INTEGER)');
$connection->executeStatement('CREATE TABLE t99009 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, x_nome TEXT, ie TEXT, dh_emi TEXT, tp_nf INTEGER, v_nf TEXT, dig_val TEXT, dh_recbto TEXT, c_sit_nfe TEXT, versao TEXT)');
$connection->executeStatement('CREATE TABLE t99010 (t99008_id INTEGER PRIMARY KEY, n_nf TEXT, dh_emi TEXT, ch_nfe TEXT, n_prot TEXT)');
$connection->executeStatement('CREATE TABLE t99011 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, x_nome TEXT)');
$connection->executeStatement('CREATE TABLE t99012 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, id_estrangeiro TEXT, x_nome TEXT)');
$connection->executeStatement('CREATE TABLE t99014 (t99008_id INTEGER PRIMARY KEY, v_nf TEXT, v_icms TEXT, v_cofins TEXT, v_pis TEXT, v_ipi TEXT)');
$connection->executeStatement('CREATE TABLE t99015 (t99008_id INTEGER PRIMARY KEY, x_evento TEXT)');

$connection->executeStatement("INSERT INTO t99001 (id_t99001, u_c_request_id, c_caminho, c_cod_programa, si_status_http, si_status_extracao, t_assinante_json, dt_hr_recebimento) VALUES (1, 'req-resnfe', '/nfe/distribuicao-dfe/por-ult-nsu', 'nfe', 200, 3, '{\"c_identificador\":\"TECNO-FLEX\",\"c_nome\":\"TECNO-FLEX IND. E COM. LTDA.\"}', '2026-07-15 10:00:00')");
$connection->executeStatement("INSERT INTO t99007 (id_t99007, t99001_id, documento_consulta, tipo_consulta, nsu_entrada, ult_nsu, max_nsu, q_doc_zip, xml_envelope, dh_resp, caminho_origem, tp_amb) VALUES (1, 1, '06013812000158', 'CNPJ', '000000000642079', '000000000642080', '000000000642080', 1, '<retDistDFeInt/>', '2026-07-15 10:00:01', '/nfe/distribuicao-dfe/por-ult-nsu', 1)");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, t99007_id, schema_name, schema_family, ch_nfe, nsu, n_prot, xml_descompactado, emit_cnpj_cpf, dt_hr_processado_em, tp_amb) VALUES (1, 1, 'resNFe_v1.01.xsd', 'resNFe', '32260712345678000199550030006420801000000012', '000000000642080', NULL, '<resNFe/>', '12345678000199', '2026-07-15 10:00:02', 1)");
$connection->executeStatement("INSERT INTO t99009 (t99008_id, cnpj, x_nome, ie, dh_emi, tp_nf, v_nf, dig_val, dh_recbto, c_sit_nfe, versao) VALUES (1, '12345678000199', 'FORNECEDOR RESUMO LTDA', '082345670', '2026-07-15 09:55:00', 1, '345.67', 'abc', '2026-07-15 10:00:00', '100', '1.01')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, t99007_id, schema_name, schema_family, ch_nfe, nsu, n_prot, xml_descompactado, emit_cnpj_cpf, dt_hr_processado_em, tp_amb) VALUES (2, 1, 'procNFe_v4.00.xsd', 'procNFe', '32260712345678000199550030006420801000000013', '000000000642081', '135260000000001', '<procNFe/>', '12345678000199', '2026-07-15 10:00:03', 1)");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, n_nf, dh_emi, ch_nfe, n_prot) VALUES (2, '9876', '2026-07-15 09:56:00', '32260712345678000199550030006420801000000013', '135260000000001')");

$repository = new NfeInputMonitorRepository($connection);
$rows = $repository->search([]);

assertSameInputValue(1, count($rows), 'resNFe row should be listed.');
assertSameInputValue('resNFe', $rows[0]['schema_family'], 'input monitor should list only NFe summary documents.');
assertSameInputValue('642080', $rows[0]['numero_nota'], 'resNFe note number should be derived from the access key.');
assertSameInputValue('TECNO-FLEX IND. E COM. LTDA.', $rows[0]['cliente'], 'resNFe client should be the consulted company/subscriber.');
assertSameInputValue('06013812000158', $rows[0]['cliente_documento'], 'resNFe client document should be the consulted document.');
assertSameInputValue('FORNECEDOR RESUMO LTDA', $rows[0]['emitente_nome'], 'resNFe issuer should come from the summary sender.');
assertSameInputValue('12345678000199', $rows[0]['emitente_documento'], 'resNFe issuer document should come from the summary sender.');
assertSameInputValue('1', $rows[0]['ambiente'], 'resNFe row should expose production environment.');

$productionRows = $repository->search(['ambiente' => '1']);
assertSameInputValue(1, count($productionRows), 'input monitor should filter production rows.');

$homologationRows = $repository->search(['ambiente' => '2']);
assertSameInputValue(0, count($homologationRows), 'input monitor should filter homologation rows.');

$procDetail = $repository->findByRequestId('2');
assertSameInputValue(null, $procDetail, 'input monitor detail should not expose full procNFe documents.');

fwrite(STDOUT, "OK\n");
