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

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
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
$connection->executeStatement('CREATE TABLE t99012 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, id_estrangeiro TEXT, x_nome TEXT)');
$connection->executeStatement('CREATE TABLE t99023 (id_t99023 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99020_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99024 (id_t99024 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99021_id INTEGER)');
$connection->executeStatement('CREATE TABLE t99026 (id_t99026 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, num INTEGER, descricao TEXT, qtd TEXT, quantidade_comercial TEXT, unidade_comercial TEXT, valor TEXT, valor_unitario_comercializacao TEXT, codigo_produto TEXT, codigo_ncm TEXT, cfop TEXT, valor_desconto TEXT, valor_total_frete TEXT, valor_seguro TEXT, valor_aproximado_tributos TEXT)');
$connection->executeStatement('CREATE TABLE t99031 (id_t99031 INTEGER PRIMARY KEY AUTOINCREMENT, t99026_id INTEGER, tag TEXT, tag_api TEXT)');
$connection->executeStatement('CREATE TABLE t99027 (id_t99027 INTEGER PRIMARY KEY AUTOINCREMENT, t99031_id INTEGER, nome_imposto TEXT, cst TEXT, base_calculo TEXT, aliquota TEXT, valor TEXT)');
$connection->executeStatement('CREATE TABLE t99032 (id_t99032 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, tag TEXT, tag_api TEXT)');
$connection->executeStatement('CREATE TABLE t99033 (id_t99033 INTEGER PRIMARY KEY AUTOINCREMENT, t99032_id INTEGER, nome_imposto TEXT, cst TEXT, base_calculo TEXT, aliquota TEXT, valor TEXT)');

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-ok', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 10:00:00', NULL, NULL, '{\"c_identificador\":\"cliente_a\",\"c_nome\":\"Cliente A\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (1, 'req-ok', 'procNFe')");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, tp_amb) VALUES (1, 1)");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (1, 1, '35123456789012345678901234567890123456789012', '123', '55', '3', '2026-07-03 09:59:00', '155.40', '<xml>ok</xml>', '/tmp/danfe-ok.pdf')");
$connection->executeStatement("INSERT INTO t99020 (id_t99020, nome_razao_social, cnpj) VALUES (1, 'EMITENTE A', '11111111000111')");
$connection->executeStatement("INSERT INTO t99021 (id_t99021, nome_razao_social, cnpj) VALUES (1, 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL', '12345678000199')");
$connection->executeStatement("INSERT INTO t99012 (t99008_id, cnpj, x_nome) VALUES (1, '12345678000199', 'ACME LTDA')");
$connection->executeStatement("INSERT INTO t99023 (t99019_id, t99020_id) VALUES (1, 1)");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (1, 1)");
$connection->executeStatement("INSERT INTO t99026 (id_t99026, t99019_id, num, descricao, quantidade_comercial, unidade_comercial, valor, valor_unitario_comercializacao, codigo_produto, codigo_ncm, cfop, valor_aproximado_tributos) VALUES (1, 1, 1, 'Produto teste', '2.0000', 'UN', '155.40', '77.70', 'SKU1', '1234', '5102', '12.34')");
$connection->executeStatement("INSERT INTO t99031 (id_t99031, t99026_id, tag, tag_api) VALUES (1, 1, 'ICMS00', 'ICMS')");
$connection->executeStatement("INSERT INTO t99027 (t99031_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'ICMS', '00', '100.00', '18.00', '18.00')");
$connection->executeStatement("INSERT INTO t99027 (t99031_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'CBS', '00', '100.00', '0.90', '0.90')");
$connection->executeStatement("INSERT INTO t99027 (t99031_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'IBSUF', '00', '100.00', '0.10', '0.10')");
$connection->executeStatement("INSERT INTO t99032 (id_t99032, t99019_id, tag, tag_api) VALUES (1, 1, 'ICMSTot', 'total')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'ICMS', '00', '100.00', '18.00', '18.00')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'COFINS', '01', '100.00', '7.60', '7.60')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'PIS', '01', '100.00', '1.65', '1.65')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'IBSUF', '00', '100.00', '0.10', '0.10')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'IBSMUN', '00', '100.00', '0.20', '0.20')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (1, 'CBS', '00', '100.00', '0.90', '0.90')");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-fail', '/nfe/envio/enviar-sincrono-xml', 'nfe', 4, 500, '2026-07-03 11:00:00', 'Falha na transmissao', NULL, '{\"c_identificador\":\"cliente_b\",\"c_nome\":\"Cliente B\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (2, 'req-fail', 'procNFe')");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, tp_amb) VALUES (2, 2)");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (2, 2, '44123456789012345678901234567890123456789012', '999', '55', '1', '2026-07-03 10:59:00', '42.00', '', '')");
$connection->executeStatement("INSERT INTO t99020 (id_t99020, nome_razao_social, cnpj) VALUES (2, 'EMITENTE B', '22222222000122')");
$connection->executeStatement("INSERT INTO t99021 (id_t99021, nome_razao_social, cnpj) VALUES (2, 'FOO SA', '99887766000155')");
$connection->executeStatement("INSERT INTO t99023 (t99019_id, t99020_id) VALUES (2, 2)");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (2, 2)");
$connection->executeStatement("INSERT INTO t99032 (id_t99032, t99019_id, tag, tag_api) VALUES (2, 2, 'ICMSTot', 'total')");
$connection->executeStatement("INSERT INTO t99033 (t99032_id, nome_imposto, cst, base_calculo, aliquota, valor) VALUES (2, 'ICMS', '00', '42.00', '18.00', '7.56')");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-base64', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 12:00:00', NULL, '{\"danfe_base64\":\"UERG\",\"xml_autorizado\":\"<xml>base64</xml>\",\"resultado\":{\"XML\":\"<protNFe>evento</protNFe>\"}}', '{\"c_identificador\":\"cliente_b\",\"c_nome\":\"Cliente B\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (3, 'req-base64', 'procNFe')");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, tp_amb) VALUES (3, 1)");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (3, 3, '55123456789012345678901234567890123456789012', '777', '55', '2', '2026-07-03 11:59:00', '99.90', '', '')");
$connection->executeStatement("INSERT INTO t99020 (id_t99020, nome_razao_social, cnpj) VALUES (3, 'EMITENTE C', '33333333000133')");
$connection->executeStatement("INSERT INTO t99023 (t99019_id, t99020_id) VALUES (3, 3)");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-homolog-placeholder', '/nfe/envio/enviar-sincrono-xml', 'nfe', 3, 200, '2026-07-03 13:00:00', NULL, NULL, '{\"c_identificador\":\"TECNO-FLEX\",\"c_nome\":\"TECNO-FLEX IND. E COM. LTDA.\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (4, 'req-homolog-placeholder', 'procNFe')");
$connection->executeStatement("INSERT INTO t99010 (t99008_id, tp_amb) VALUES (4, 2)");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, mod, serie, dh_emi, v_nf, xml_autorizado, caminho_danfe) VALUES (4, 4, '32260606013812000158550030001972461604403624', '197246', '55', '3', '2026-07-03 12:59:00', '5317.75', '', '')");
$connection->executeStatement("INSERT INTO t99020 (id_t99020, nome_razao_social, cnpj) VALUES (4, 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL', '06013812000158')");
$connection->executeStatement("INSERT INTO t99021 (id_t99021, nome_razao_social, cnpj) VALUES (4, 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL', '40456687000199')");
$connection->executeStatement("INSERT INTO t99012 (t99008_id, cnpj, x_nome) VALUES (4, '40456687000199', 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL')");
$connection->executeStatement("INSERT INTO t99023 (t99019_id, t99020_id) VALUES (4, 4)");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (4, 4)");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_cod_programa, si_status_processamento, si_status_http, dt_hr_recebimento, t_erro, t_corpo_resposta, t_assinante_json) VALUES ('req-cancel', '/nfe/eventos/cancelar', 'nfe', 3, 200, '2026-07-03 13:05:00', NULL, NULL, '{\"c_identificador\":\"TECNO-FLEX\",\"c_nome\":\"TECNO-FLEX IND. E COM. LTDA.\"}')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (5, 'req-cancel', 'procEventoNFe')");
$connection->executeStatement("INSERT INTO t99016 (t99008_id, ch_nfe, tp_evento, dh_evento, c_stat, x_motivo, n_prot) VALUES (5, '32260606013812000158550030001972461604403624', '110111', '2026-07-03 13:04:00', 135, 'Evento registrado e vinculado a NF-e', '135260000000001')");

$repository = new NfeOutputMonitorRepository($connection);

$rows = $repository->search([
    'date_from' => '2026-07-03',
    'date_to' => '2026-07-03',
]);

assertSameValue(4, count($rows), 'search should return all NFe send attempts in the fixture.');
assertSameValue('req-homolog-placeholder', $rows[0]['request_id'], 'Newest send attempt should come first.');
assertSameValue('40456687000199', $rows[0]['cliente'], 'Grid should not expose homologation placeholder as customer name.');
assertSameValue('TECNO-FLEX IND. E COM. LTDA.', $rows[0]['emitente_nome'], 'Grid should not expose homologation placeholder as issuer name.');
assertSameValue('Transmitida', $rows[0]['status_envio'], 'Grid should keep transmission status even when cancellation event exists.');
assertSameValue(true, $rows[0]['cancelamento']['cancelada'] ?? null, 'Repository should expose cancellation flag for detail view.');
assertSameValue('req-cancel', $rows[0]['cancelamento']['request_id'] ?? null, 'Repository should expose cancellation request id for detail view.');
assertSameValue('135260000000001', $rows[0]['cancelamento']['protocolo'] ?? null, 'Repository should expose cancellation protocol for detail view.');
assertSameValue('', $rows[0]['acoes_nfe']['cancelar_url'] ?? null, 'Grid should hide cancel action for canceled note.');
assertSameValue('/nfe/inutilizacao/inutilizar', $rows[0]['acoes_nfe']['inutilizar_url'] ?? null, 'Grid should expose NFe inutilization action endpoint.');
assertSameValue('32260606013812000158550030001972461604403624', $rows[0]['acoes_nfe']['chave'] ?? null, 'Grid should expose access key for cancel action.');
assertSameValue('06013812000158', $rows[0]['acoes_nfe']['cnpj_emitente'] ?? null, 'Grid should expose issuer document for NFe actions.');
assertSameValue('26', $rows[0]['acoes_nfe']['ano'] ?? null, 'Grid should expose two-digit year for inutilization.');
assertSameValue('55', $rows[0]['acoes_nfe']['modelo'] ?? null, 'Grid should expose document model for inutilization.');
assertSameValue('3', $rows[0]['acoes_nfe']['serie'] ?? null, 'Grid should expose note series for inutilization.');
assertSameValue('197246', $rows[0]['acoes_nfe']['numero_inicial'] ?? null, 'Grid should expose note number range for inutilization.');
assertSameValue('197246', $rows[0]['acoes_nfe']['numero_final'] ?? null, 'Grid should expose note number range for inutilization.');
assertSameValue('req-base64', $rows[1]['request_id'], 'Base64 send should come after the homologation placeholder fixture.');
assertSameValue('/monitor-saida-nfe/danfe/req-base64', $rows[1]['danfe_url'], 'Grid should expose DANFE route when only base64 is available.');
assertSameValue('/monitor-saida-nfe/xml/req-base64', $rows[1]['xml_url'], 'Grid should expose XML route extracted from response body.');
assertSameValue('EMITENTE C', $rows[1]['emitente_nome'], 'Grid should expose issuer name.');
assertSameValue('req-fail', $rows[2]['request_id'], 'Failed send should come after the base64 fixture.');
assertSameValue('999', $rows[2]['numero_nota'], 'Grid should expose note number.');
assertSameValue('FOO SA', $rows[2]['cliente'], 'Grid should expose customer.');
assertSameValue('Falha', $rows[2]['status_envio'], 'Grid should expose failed transmission status.');
assertSameValue('', $rows[2]['danfe_url'], 'Grid should expose empty DANFE link when unavailable.');
assertSameValue('18.00', $rows[3]['impostos']['ICMS']['valor'] ?? null, 'Grid should expose ICMS total.');
assertSameValue('ACME LTDA', $rows[3]['cliente'], 'Grid should prefer extracted customer name when link table contains homologation placeholder.');
assertSameValue('7.60', $rows[3]['impostos']['COFINS']['valor'] ?? null, 'Grid should expose COFINS total.');
assertSameValue('1.65', $rows[3]['impostos']['PIS']['valor'] ?? null, 'Grid should expose PIS total.');
assertSameValue('0.30', $rows[3]['impostos']['IBS']['valor'] ?? null, 'Grid should expose IBS total aggregated from IBSUF and IBSMUN.');
assertSameValue('0.90', $rows[3]['impostos']['CBS']['valor'] ?? null, 'Grid should expose CBS total.');

$productionRows = $repository->search([
    'date_from' => '2026-07-03',
    'date_to' => '2026-07-03',
    'ambiente' => '1',
]);
assertSameValue(2, count($productionRows), 'search should filter production output notes.');
assertSameValue('req-base64', $productionRows[0]['request_id'], 'Newest production output note should come first.');
assertSameValue('1', $productionRows[0]['ambiente'], 'Production output row should expose environment.');

$homologationRows = $repository->search([
    'date_from' => '2026-07-03',
    'date_to' => '2026-07-03',
    'ambiente' => '2',
]);
assertSameValue(2, count($homologationRows), 'search should filter homologation output notes.');
assertSameValue('req-homolog-placeholder', $homologationRows[0]['request_id'], 'Newest homologation output note should come first.');
assertSameValue('2', $homologationRows[0]['ambiente'], 'Homologation output row should expose environment.');

$filteredRows = $repository->search([
    'assinante' => 'cliente_a',
    'emissor' => 'EMITENTE A',
    'status' => [3],
]);
assertSameValue(1, count($filteredRows), 'search should apply subscriber, issuer and multi-status filters.');
assertSameValue('req-ok', $filteredRows[0]['request_id'], 'Filtered result should match req-ok.');

$detail = $repository->findByRequestId('req-ok');
assertTrueValue(is_array($detail), 'findByRequestId should return detail for existing request.');
assertSameValue('123', $detail['numero_nota'], 'Detail should expose note number.');
assertSameValue('/monitor-saida-nfe/danfe/req-ok', $detail['danfe_url'], 'Detail should expose DANFE route.');
assertSameValue('/monitor-saida-nfe/xml/req-ok', $detail['xml_url'], 'Detail should expose XML route.');
assertSameValue('<xml>ok</xml>', $detail['xml_autorizado'], 'Detail should expose XML content.');
assertSameValue('18.00', $detail['impostos']['ICMS']['valor'] ?? null, 'Detail should expose ICMS total.');
assertSameValue('EMITENTE A', $detail['emitente_nome'], 'Detail should expose issuer.');
assertSameValue('ACME LTDA', $detail['cliente'], 'Detail should prefer extracted customer name.');
assertSameValue('Cliente A', $detail['assinante_nome'], 'Detail should expose subscriber name.');
assertSameValue(1, count($detail['itens'] ?? []), 'Detail should expose note items.');
assertSameValue('Produto teste', $detail['itens'][0]['descricao'] ?? null, 'Detail should expose item description.');
assertSameValue('CBS', $detail['itens'][0]['impostos'][1]['nome'] ?? null, 'Detail should expose item taxes.');

$base64Detail = $repository->findByRequestId('req-base64');
assertTrueValue(is_array($base64Detail), 'findByRequestId should return detail for base64 DANFE fixture.');
assertSameValue('UERG', $base64Detail['danfe_base64'], 'Detail should expose DANFE base64 extracted from response body.');
assertSameValue('/monitor-saida-nfe/danfe/req-base64', $base64Detail['danfe_url'], 'Detail should expose DANFE route when base64 is available.');
assertSameValue('<xml>base64</xml>', $base64Detail['xml_autorizado'], 'Detail should expose full XML extracted from response body.');
assertSameValue('<protNFe>evento</protNFe>', $base64Detail['xml_evento_autorizacao'], 'Detail should expose authorization event XML.');

$missing = $repository->findByRequestId('nao-existe');
assertSameValue(null, $missing, 'findByRequestId should return null for unknown request.');

fwrite(STDOUT, "OK\n");
