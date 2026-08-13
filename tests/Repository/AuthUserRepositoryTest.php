<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use Doctrine\DBAL\DriverManager;

function assertAuthSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertAuthTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t00002 (id_t00002 INTEGER PRIMARY KEY AUTOINCREMENT, c_nome TEXT, c_identificador TEXT, c_token TEXT, log_ativo INTEGER DEFAULT 1)');

(new AuthSchemaManager($connection))->ensureSchema();

$repository = new AuthUserRepository($connection);
$userId = $repository->createUser('Operador_1', password_hash('senha-segura', PASSWORD_DEFAULT), 'common', true);
$companyA = $repository->createCompany('Empresa A', '06013812000158', true);
$companyB = $repository->createCompany('Empresa B', '12345678000199', true);
$connection->executeStatement("INSERT INTO t00002 (id_t00002, c_nome, c_identificador, c_token, log_ativo) VALUES (10, 'Cliente A', 'cliente_a', 'tok_a', 1)");
$connection->executeStatement("INSERT INTO t00002 (id_t00002, c_nome, c_identificador, c_token, log_ativo) VALUES (11, 'Cliente B', 'cliente_b', 'tok_b', 1)");

$repository->assignUserCompany($userId, $companyA, 'company_admin');
$repository->assignUserCompany($userId, $companyB, 'common');
$repository->assignCompanySubscriber($companyA, 10);
$repository->assignCompanySubscriber($companyB, 11);

$loaded = $repository->findActiveByUsername('operador_1');
assertAuthTrue(is_array($loaded), 'user lookup should be case-insensitive.');
assertAuthSame($userId, (int) $loaded['id_t00005'], 'loaded user id should match created user.');
assertAuthSame(['common', 'company_admin'], $repository->rolesForUser($userId), 'roles should combine global and company roles.');
assertAuthSame([10, 11], $repository->subscriberIdsForUser($userId), 'user should inherit subscribers from all linked companies.');
assertAuthSame('tok_b', $repository->subscriberTokenForUser($userId, 11), 'repository should return token for an allowed subscriber.');
assertAuthSame(null, $repository->subscriberTokenForUser($userId, 99), 'repository should reject a subscriber outside user companies.');

fwrite(STDOUT, "OK\n");
