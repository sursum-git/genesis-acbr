<?php

declare(strict_types=1);

use App\Repository\AcbrLibraryReleaseRepository;
use Doctrine\DBAL\DriverManager;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('ACBR_TEST_POSTGRES') === '1') {
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_pgsql', 'host' => $_ENV['AUDIT_DB_HOST'], 'port' => $_ENV['AUDIT_DB_PORT'],
        'dbname' => $_ENV['AUDIT_DB_NAME'], 'user' => $_ENV['AUDIT_DB_USER'], 'password' => $_ENV['AUDIT_DB_PASSWORD'],
    ]);
    $connection->beginTransaction();
    $connection->executeStatement("SET LOCAL TIME ZONE 'UTC'");
    register_shutdown_function(static function () use ($connection): void {
        if ($connection->isTransactionActive()) { $connection->rollBack(); }
    });
    foreach (['t99037_acbr_library_release' => 'id_t99037', 't99038_acbr_library_release_change' => 'id_t99038'] as $table => $id) {
        $connection->executeStatement("CREATE TEMP TABLE $table ON COMMIT DROP AS SELECT * FROM public.$table WITH NO DATA");
        $connection->executeStatement("CREATE TEMP SEQUENCE test_$id");
        $connection->executeStatement("ALTER TABLE pg_temp.$table ALTER COLUMN $id SET DEFAULT nextval('pg_temp.test_$id')");
        if (!$connection->fetchOne("SELECT to_regclass('$table') = to_regclass('pg_temp.$table')")) {
            throw new RuntimeException('Tests must use temporary tables only.');
        }
    }
} else {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
}
$repository = new AcbrLibraryReleaseRepository($connection);
$repository->ensureSchema();

if ($connection->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\SQLitePlatform) {
$columns = $connection->fetchAllAssociative('PRAGMA table_info(t99037_acbr_library_release)');
$columnTypes = array_column($columns, 'type', 'name');
if (strcasecmp((string) ($columnTypes['dt_hr_inicio_vigencia'] ?? ''), 'TIMESTAMPTZ') !== 0
    || strcasecmp((string) ($columnTypes['dt_hr_fim_vigencia'] ?? ''), 'TIMESTAMPTZ') !== 0) {
    fwrite(STDERR, "A vigência da release deve armazenar instantes com fuso horário.\n");
    exit(1);
}

}

$releaseId = $repository->save([
    'c_modulo' => 'nfe',
    'c_ambiente' => 'homologacao',
    'c_release' => '1.5.0.444',
    'c_caminho_so' => '/opt/acbr_libs/releases/nfe-1.5.0.444/x64/libacbrnfe64.so',
    'c_sha256' => str_repeat('a', 64),
    'c_perfil_ini' => 'homologacao',
    'dt_hr_inicio_vigencia' => '2026-10-19 10:00:00',
    'dt_hr_fim_vigencia' => '2026-10-19 11:00:00',
    'log_ativo' => true,
]);

$repository->saveChange($releaseId, [
    'c_categoria' => 'changelog',
    't_descricao' => 'Corrige URL de consulta para produção de NFe.',
    'c_origem' => 'ACBrNFe-change-log.txt',
]);

$changes = $repository->listChanges($releaseId);
if (($changes[0]['c_categoria'] ?? null) !== 'changelog' || ($changes[0]['t_descricao'] ?? null) !== 'Corrige URL de consulta para produção de NFe.') {
    fwrite(STDERR, "As diferenças da release devem ser persistidas e listadas.\n");
    exit(1);
}

$repository->save([
    'c_modulo' => 'nfe',
    'c_ambiente' => 'homologacao',
    'c_release' => 'inativa',
    'c_caminho_so' => '/opt/acbr_libs/releases/nfe-inativa/x64/libacbrnfe64.so',
    'c_sha256' => str_repeat('b', 64),
    'c_perfil_ini' => 'homologacao',
    'dt_hr_inicio_vigencia' => '2026-10-19 10:00:00',
    'log_ativo' => false,
]);

$atStart = $repository->findActive('nfe', 'homologacao', new DateTimeImmutable('2026-10-19 10:00:00'));
if (($atStart['c_release'] ?? null) !== '1.5.0.444') {
    fwrite(STDERR, "A release deve estar ativa no início da vigência.\n");
    exit(1);
}

$atEnd = $repository->findActive('nfe', 'homologacao', new DateTimeImmutable('2026-10-19 11:00:00'));
if ($atEnd !== null) {
    fwrite(STDERR, "A release não deve estar ativa no fim exclusivo da vigência.\n");
    exit(1);
}

$payload = [
    'c_modulo' => 'nfe', 'c_ambiente' => 'homologacao', 'c_release' => 'next-open',
    'c_caminho_so' => '/opt/acbr_libs/releases/test/libacbrnfe64.so',
    'c_sha256' => str_repeat('c', 64), 'c_perfil_ini' => 'homologacao',
    'dt_hr_inicio_vigencia' => '2026-10-19 11:00:00', 'log_ativo' => true,
];
$nextId = $repository->save($payload);
if (($repository->findActive('nfe', 'homologacao', new DateTimeImmutable('2026-10-19 11:00:00'))['c_release'] ?? null) !== 'next-open') {
    throw new LogicException('An open-ended release must start exactly when the previous release ends.');
}
try {
    $repository->save(array_merge($payload, ['dt_hr_inicio_vigencia' => '2026-10-19 12:00:00']));
    throw new LogicException('Overlapping open-ended release accepted.');
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'sobrepõe')) { throw $e; }
}

fwrite(STDOUT, "OK\n");
