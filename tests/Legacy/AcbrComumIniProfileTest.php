<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/ACBrComum/ACBrComum.php';

function assertSameLegacyIniValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertTrueLegacyIniValue(bool $actual, string $message): void
{
    if (!$actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assertThrowsLegacyIniValue(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$baseDir = sys_get_temp_dir() . '/acbr-comum-ini-profile-test-' . bin2hex(random_bytes(4));
mkdir($baseDir, 0777, true);
file_put_contents($baseDir . '/ACBrNFe.INI', "[Principal]\nLogNivel=4\n");

assertSameLegacyIniValue($baseDir . '/ACBrNFe.INI', ResolveAcbrIniPath($baseDir, 'ACBrNFe', null, 'nfe_mt'), 'Legacy resolver should keep default INI fallback.');

$createdPath = CriaAcbrIniProfile($baseDir, 'ACBrNFe', 'producao', null, 'nfe_mt');
assertSameLegacyIniValue($baseDir . '/configs/producao/ACBrNFe.INI', $createdPath, 'Legacy profile creation should use the safe profile folder.');
assertSameLegacyIniValue(file_get_contents($baseDir . '/ACBrNFe.INI'), file_get_contents($createdPath), 'Legacy profile creation should copy default INI.');

SelecionaAcbrIniProfile($baseDir, 'ACBrNFe', 'producao', 'nfe_mt');
assertSameLegacyIniValue($createdPath, ResolveAcbrIniPath($baseDir, 'ACBrNFe', null, 'nfe_mt'), 'Legacy resolver should use selected active profile.');

$profiles = ListaAcbrIniProfiles($baseDir, 'ACBrNFe', 'nfe_mt');
assertSameLegacyIniValue('producao', $profiles[0]['id'] ?? null, 'Legacy profile list should include created profile.');
assertTrueLegacyIniValue((bool) ($profiles[0]['active'] ?? false), 'Legacy profile list should mark active profile.');

assertThrowsLegacyIniValue(fn () => ResolveAcbrIniPath($baseDir, 'ACBrNFe', '../../ACBrNFe.INI', 'nfe_mt'), 'Legacy resolver should reject unsafe requested profile.');
assertThrowsLegacyIniValue(fn () => CriaAcbrIniProfile($baseDir, 'ACBrNFe', 'perfil invalido', null, 'nfe_mt'), 'Legacy resolver should reject unsafe profile creation.');

fwrite(STDOUT, "OK\n");
