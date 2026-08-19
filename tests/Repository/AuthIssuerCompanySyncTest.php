<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use Doctrine\DBAL\DriverManager;

function assertIssuerSyncSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t00002 (id_t00002 INTEGER PRIMARY KEY AUTOINCREMENT, c_nome TEXT, c_identificador TEXT, c_token TEXT, log_ativo INTEGER DEFAULT 1)');
$connection->executeStatement('CREATE TABLE t99020 (id_t99020 INTEGER PRIMARY KEY AUTOINCREMENT, nome_razao_social TEXT, cnpj TEXT)');
$connection->executeStatement('CREATE TABLE t99011 (t99008_id INTEGER PRIMARY KEY, cnpj TEXT, cpf TEXT, x_nome TEXT)');
$connection->executeStatement("INSERT INTO t99020 (nome_razao_social, cnpj) VALUES ('EMITENTE SAIDA A', '11111111000111')");
$connection->executeStatement("INSERT INTO t99020 (nome_razao_social, cnpj) VALUES ('EMITENTE SAIDA DUPLICADO', '11111111000111')");
$connection->executeStatement("INSERT INTO t99011 (t99008_id, cnpj, cpf, x_nome) VALUES (1, '22222222000122', NULL, 'EMITENTE ENTRADA B')");
$connection->executeStatement("INSERT INTO t99011 (t99008_id, cnpj, cpf, x_nome) VALUES (2, NULL, '12345678901', 'CPF NAO DEVE VIRAR EMPRESA')");

(new AuthSchemaManager($connection))->ensureSchema();

$repository = new AuthUserRepository($connection);
$repository->createCompany('EMPRESA MANUAL', '33333333000133', true);

assertIssuerSyncSame(2, $repository->syncCompaniesFromMonitorIssuers(), 'sync should insert only new CNPJ issuers from monitors.');
assertIssuerSyncSame(0, $repository->syncCompaniesFromMonitorIssuers(), 'sync should be idempotent.');

$companies = $connection->fetchAllAssociative('SELECT c_nome, c_cnpj, log_ativo FROM t00006 ORDER BY c_cnpj ASC');
assertIssuerSyncSame([
    ['c_nome' => 'EMITENTE SAIDA A', 'c_cnpj' => '11111111000111', 'log_ativo' => 1],
    ['c_nome' => 'EMITENTE ENTRADA B', 'c_cnpj' => '22222222000122', 'log_ativo' => 1],
    ['c_nome' => 'EMPRESA MANUAL', 'c_cnpj' => '33333333000133', 'log_ativo' => 1],
], array_map(static fn (array $row): array => [
    'c_nome' => (string) $row['c_nome'],
    'c_cnpj' => (string) $row['c_cnpj'],
    'log_ativo' => (int) $row['log_ativo'],
], $companies), 'companies table should contain monitor issuers plus existing manual companies.');

fwrite(STDOUT, "OK\n");
