<?php

declare(strict_types=1);

use App\Repository\AcbrLibraryReleaseRepository;
use Doctrine\DBAL\DriverManager;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$repository = new AcbrLibraryReleaseRepository($connection);
$repository->ensureSchema();

$repository->save([
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

fwrite(STDOUT, "OK\n");
