<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\NfeMonitorRepository;
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

$connection->executeStatement('CREATE TABLE t99001 (id_t99001 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, c_caminho TEXT, c_nome_operacao TEXT, c_cod_programa TEXT, si_status_processamento INTEGER, si_status_http INTEGER, si_status_extracao INTEGER, t_erro TEXT, t_erro_extracao TEXT, t_corpo_resposta TEXT, t_corpo_requisicao TEXT, dt_hr_recebimento TEXT, dt_hr_ini_processamento TEXT, dt_hr_fim_processamento TEXT, dt_hr_ini_extracao TEXT, dt_hr_fim_extracao TEXT)');
$connection->executeStatement('CREATE TABLE t99008 (id_t99008 INTEGER PRIMARY KEY AUTOINCREMENT, u_c_request_id TEXT, schema_family TEXT)');
$connection->executeStatement('CREATE TABLE t99019 (id_t99019 INTEGER PRIMARY KEY AUTOINCREMENT, t99008_id INTEGER, ch_nfe TEXT, n_nf TEXT, v_nf TEXT, dh_emi TEXT, xml_autorizado TEXT, caminho_danfe TEXT)');
$connection->executeStatement('CREATE TABLE t99021 (id_t99021 INTEGER PRIMARY KEY AUTOINCREMENT, nome_razao_social TEXT, cnpj TEXT)');
$connection->executeStatement('CREATE TABLE t99024 (id_t99024 INTEGER PRIMARY KEY AUTOINCREMENT, t99019_id INTEGER, t99021_id INTEGER)');

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_nome_operacao, c_cod_programa, si_status_processamento, si_status_http, si_status_extracao, t_erro, t_erro_extracao, t_corpo_resposta, t_corpo_requisicao, dt_hr_recebimento) VALUES ('req-ok', '/nfe/envio/enviar-sincrono-xml', 'enviar-sincrono-xml', 'nfe', 3, 200, 3, NULL, NULL, '{\"ok\":true}', '<enviNFe/>', '2026-06-30 10:00:00')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (1, 'req-ok', 'procNFe')");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, v_nf, dh_emi, xml_autorizado, caminho_danfe) VALUES (1, 1, '35123456789012345678901234567890123456789012', '123', '155.40', '2026-06-30 10:00:00', '<xml/>', '/tmp/danfe-ok.pdf')");
$connection->executeStatement("INSERT INTO t99021 (id_t99021, nome_razao_social, cnpj) VALUES (1, 'ACME LTDA', '12345678000199')");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (1, 1)");

$connection->executeStatement("INSERT INTO t99001 (u_c_request_id, c_caminho, c_nome_operacao, c_cod_programa, si_status_processamento, si_status_http, si_status_extracao, t_erro, t_erro_extracao, t_corpo_resposta, t_corpo_requisicao, dt_hr_recebimento) VALUES ('req-erro', '/nfe/envio/enviar-assincrono-xml', 'enviar-assincrono-xml', 'nfe', 4, 500, 4, 'Falha no envio', 'Falha na extracao', '{\"ok\":false}', '<enviNFe/>', '2026-06-30 11:00:00')");
$connection->executeStatement("INSERT INTO t99008 (id_t99008, u_c_request_id, schema_family) VALUES (2, 'req-erro', 'procNFe')");
$connection->executeStatement("INSERT INTO t99019 (id_t99019, t99008_id, ch_nfe, n_nf, v_nf, dh_emi, xml_autorizado, caminho_danfe) VALUES (2, 2, '44123456789012345678901234567890123456789012', '999', '42.00', '2026-06-30 11:00:00', '<xml-erro/>', '/tmp/danfe-erro.pdf')");
$connection->executeStatement("INSERT INTO t99021 (id_t99021, nome_razao_social, cnpj) VALUES (2, 'FOO SA', '99887766000155')");
$connection->executeStatement("INSERT INTO t99024 (t99019_id, t99021_id) VALUES (2, 2)");

$repository = new NfeMonitorRepository($connection);

$rows = $repository->listLatest(100);
assertSameValue(2, count($rows), 'listLatest should return the two inserted rows.');
assertSameValue('req-erro', $rows[0]['request_id'], 'Newest row should come first.');
assertSameValue('999', $rows[0]['numero_nota'], 'Should expose note number.');
assertSameValue('FOO SA', $rows[0]['cliente'], 'Should expose client name.');
assertSameValue('Enviado com falha', $rows[0]['situacao'], 'Should map failed send status.');
assertSameValue('erro', $rows[0]['ocorrencia'], 'Should map failed occurrence.');

$detail = $repository->findDetailByRequestId('req-ok');
assertTrueValue(is_array($detail), 'findDetailByRequestId should return an array for existing request id.');
assertSameValue('123', $detail['numero_nota'], 'Detail should expose note number.');
assertSameValue('ACME LTDA', $detail['cliente'], 'Detail should expose client name.');
assertSameValue('/tmp/danfe-ok.pdf', $detail['caminho_danfe'], 'Detail should expose DANFE path.');
assertSameValue('Enviado com sucesso', $detail['situacao'], 'Detail should map success status.');

$missing = $repository->findDetailByRequestId('nao-existe');
assertSameValue(null, $missing, 'findDetailByRequestId should return null for unknown request id.');

fwrite(STDOUT, "OK\n");
