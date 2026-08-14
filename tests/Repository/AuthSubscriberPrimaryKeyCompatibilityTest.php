<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use App\Repository\ApiAssinanteRepository;
use Doctrine\DBAL\DriverManager;

function assertPkSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$connection->executeStatement('CREATE TABLE t00002 (id INTEGER PRIMARY KEY AUTOINCREMENT, c_nome TEXT, c_identificador TEXT, c_token TEXT, log_ativo INTEGER DEFAULT 1)');
$connection->executeStatement("INSERT INTO t00002 (id, c_nome, c_identificador, c_token, log_ativo) VALUES (77, 'Cliente Alternativo', 'cliente_alt', 'tok_alt', 1)");

(new AuthSchemaManager($connection))->ensureSchema();

$users = new AuthUserRepository($connection);
$userId = $users->createUser('admin_alt', password_hash('admin', PASSWORD_DEFAULT), 'super_admin', true);

assertPkSame([77], $users->subscriberIdsForUser($userId), 'super admin should list subscribers using the actual t00002 primary key.');
assertPkSame('tok_alt', $users->subscriberTokenForUser($userId, 77), 'subscriber token lookup should use the actual t00002 primary key.');
assertPkSame('tok_alt', (new ApiAssinanteRepository($connection))->findFirstToken(), 'legacy first-token lookup should not assume id_t00002.');

$schemaSql = file_get_contents(dirname(__DIR__, 2) . '/sql/auth_schema.sql');
assertPkSame(false, str_contains((string) $schemaSql, 'REFERENCES public.t00002 (id_t00002)'), 'auth schema SQL should not reference a fixed t00002 primary key.');

fwrite(STDOUT, "OK\n");
