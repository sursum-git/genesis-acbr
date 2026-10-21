<?php

declare(strict_types=1);

use App\Repository\AcbrLibraryReleaseRepository;
use App\Service\Legacy\AcbrIniProfileResolver;
use App\Service\Legacy\AcbrLibraryReleaseResolver;
use Doctrine\DBAL\DriverManager;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/acbr-release-test-' . bin2hex(random_bytes(4));
mkdir($root . '/configs/homologacao', 0777, true);
file_put_contents($root . '/configs/homologacao/ACBrNFe.INI', "[NFe]\nAmbiente=1\n");
mkdir($root . '/releases/nfe-1.5.0.444/x64', 0777, true);
$libraryPath = $root . '/releases/nfe-1.5.0.444/x64/libacbrnfe64.so';
file_put_contents($libraryPath, 'fixture');

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$repository = new AcbrLibraryReleaseRepository($connection);
$repository->save([
    'c_modulo' => 'nfe', 'c_ambiente' => 'homologacao', 'c_release' => '1.5.0.489',
    'c_caminho_so' => $libraryPath, 'c_sha256' => hash_file('sha256', $libraryPath),
    'c_perfil_ini' => 'homologacao', 'dt_hr_inicio_vigencia' => '2026-01-01 00:00:00', 'log_ativo' => true,
]);

$resolver = new AcbrLibraryReleaseResolver($repository, new AcbrIniProfileResolver($root), $root . '/releases');
$selection = $resolver->resolveNfe('homologacao', new DateTimeImmutable('2026-10-19 12:00:00'));
if ($selection->environment !== 'homologacao' || $selection->path !== realpath($libraryPath)) {
    fwrite(STDERR, "O resolvedor deve selecionar a release de homologação e seu caminho canônico.\n");
    exit(1);
}

mkdir($root . '/configs/producao', 0777, true);
file_put_contents($root . '/configs/producao/ACBrNFe.INI', "[NFe]\nAmbiente=0\n");
$repository->save([
    'c_modulo' => 'nfe', 'c_ambiente' => 'producao', 'c_release' => '1.5.0.444',
    'c_caminho_so' => $libraryPath, 'c_sha256' => hash_file('sha256', $libraryPath),
    'c_perfil_ini' => 'producao', 'dt_hr_inicio_vigencia' => '2026-01-01 00:00:00', 'log_ativo' => true,
]);
if ($resolver->resolveNfe('producao', new DateTimeImmutable('2026-10-19 12:00:00'))->environment !== 'producao') {
    fwrite(STDERR, "INI Ambiente=0 deve selecionar producao.\n"); exit(1);
}
$at = new DateTimeImmutable('2026-10-19 12:00:00');
foreach (['1' => 'producao', '2' => 'homologacao'] as $header => $environment) {
    $selected = $resolver->resolveNfe(null, $at, (string) $header);
    $expectedRelease = $header == '1' ? '1.5.0.444' : '1.5.0.489';
    if ($selected->release !== $expectedRelease || $selected->environment !== $environment || $selected->profile !== $environment) {
        throw new RuntimeException('Header must resolve the registered environment and its profile.');
    }
}
foreach (['', '0', '3', 'homologacao'] as $invalid) {
    try {
        $resolver->resolveNfe(null, $at, $invalid);
        throw new LogicException('Invalid environment accepted.');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'X-ACBr-Ambiente')) { throw $e; }
    }
}
try {
    $resolver->resolveNfe('producao', $at, '2');
    throw new LogicException('Conflicting profile accepted.');
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'perfil INI')) { throw $e; }
}
file_put_contents($root . '/configs/producao/ACBrNFe.INI', "[NFe]\nAmbiente=1\n");
try {
    $resolver->resolveNfe(null, $at, '1');
    throw new LogicException('Mismatched native INI accepted.');
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'ambiente')) { throw $e; }
}

file_put_contents($root . '/configs/homologacao/ACBrNFe.INI', "[NFe]\nAmbiente=2\n");
try {
    $resolver->resolveNfe('homologacao', new DateTimeImmutable('2026-10-19 12:00:00'));
    fwrite(STDERR, "INI Ambiente=2 invalido nao deve selecionar uma release.\n"); exit(1);
} catch (RuntimeException $e) {
    if (!str_contains($e->getMessage(), 'inválido')) { throw $e; }
}

fwrite(STDOUT, "OK\n");
