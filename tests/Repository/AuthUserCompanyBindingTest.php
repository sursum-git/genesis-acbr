<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use Doctrine\DBAL\DriverManager;

function assertBindingSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t00002 (id_t00002 INTEGER PRIMARY KEY AUTOINCREMENT, c_nome TEXT, c_identificador TEXT, c_token TEXT, log_ativo INTEGER DEFAULT 1)');

(new AuthSchemaManager($connection))->ensureSchema();

$repository = new AuthUserRepository($connection);
$userId = $repository->createUser('operador_empresas', password_hash('senha-segura', PASSWORD_DEFAULT), 'common', true);
$companyA = $repository->createCompany('Empresa A', '06013812000158', true);
$companyB = $repository->createCompany('Empresa B', '12345678000199', true);

$repository->replaceUserCompanies($userId, [$companyA, $companyB], 'common');

assertBindingSame([$companyA, $companyB], array_map(
    static fn (array $company): int => (int) $company['id_t00006'],
    $repository->listCompanies()
), 'company listing should expose active companies for the user screen.');

$activeAfterFirstSave = $connection->fetchAllAssociative(
    'SELECT t00006_id, c_role, log_ativo FROM t00007 WHERE t00005_id = :user_id ORDER BY t00006_id ASC',
    ['user_id' => $userId]
);

assertBindingSame([
    ['t00006_id' => $companyA, 'c_role' => 'common', 'log_ativo' => 1],
    ['t00006_id' => $companyB, 'c_role' => 'common', 'log_ativo' => 1],
], array_map(static fn (array $row): array => [
    't00006_id' => (int) $row['t00006_id'],
    'c_role' => (string) $row['c_role'],
    'log_ativo' => (int) $row['log_ativo'],
], $activeAfterFirstSave), 'first save should create active bindings for selected companies.');

$repository->replaceUserCompanies($userId, [$companyB], 'company_admin');

$companiesByUser = $repository->companiesForUsers([$userId]);
assertBindingSame([
    ['id_t00006' => $companyB, 'c_role' => 'company_admin'],
], array_map(static fn (array $company): array => [
    'id_t00006' => (int) $company['id_t00006'],
    'c_role' => (string) $company['c_role'],
], $companiesByUser[$userId] ?? []), 'company map should expose active company bindings for each user.');

$activeAfterSecondSave = $connection->fetchAllAssociative(
    'SELECT t00006_id, c_role, log_ativo FROM t00007 WHERE t00005_id = :user_id ORDER BY t00006_id ASC',
    ['user_id' => $userId]
);

assertBindingSame([
    ['t00006_id' => $companyA, 'c_role' => 'common', 'log_ativo' => 0],
    ['t00006_id' => $companyB, 'c_role' => 'company_admin', 'log_ativo' => 1],
], array_map(static fn (array $row): array => [
    't00006_id' => (int) $row['t00006_id'],
    'c_role' => (string) $row['c_role'],
    'log_ativo' => (int) $row['log_ativo'],
], $activeAfterSecondSave), 'second save should deactivate removed bindings and update active role.');

fwrite(STDOUT, "OK\n");
