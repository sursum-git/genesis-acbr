<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Service\Legacy\AcbrIniProfileResolver;

function assertSameIniValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertTrueIniValue(bool $actual, string $message): void
{
    if (!$actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assertThrowsIniValue(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$baseDir = sys_get_temp_dir() . '/acbr-ini-profile-test-' . bin2hex(random_bytes(4));
mkdir($baseDir, 0777, true);
file_put_contents($baseDir . '/ACBrNFe.INI', "[Principal]\nLogNivel=4\n");

$resolver = new AcbrIniProfileResolver($baseDir, 'ACBrNFe', 'nfe_mt');

assertSameIniValue($baseDir . '/ACBrNFe.INI', $resolver->resolve(null), 'Resolver should use the current legacy INI as fallback.');

$createdPath = $resolver->createProfile('homologacao');
assertSameIniValue($baseDir . '/configs/homologacao/ACBrNFe.INI', $createdPath, 'Created profile path should use the safe profile folder.');
assertSameIniValue(file_get_contents($baseDir . '/ACBrNFe.INI'), file_get_contents($createdPath), 'Created profile should copy the default INI.');

$resolver->setActiveProfile('homologacao');
assertSameIniValue($createdPath, $resolver->resolve(null), 'Resolver should use the selected active profile.');
assertSameIniValue($createdPath, $resolver->resolve('homologacao'), 'Resolver should use the requested profile explicitly.');

$profiles = $resolver->listProfiles();
assertSameIniValue('homologacao', $profiles[0]['id'] ?? null, 'List should include created profile.');
assertTrueIniValue((bool) ($profiles[0]['active'] ?? false), 'List should mark the selected active profile.');

assertThrowsIniValue(fn () => $resolver->resolve('../ACBrNFe.INI'), 'Resolver should reject path traversal profile names.');
assertThrowsIniValue(fn () => $resolver->createProfile('perfil invalido'), 'Resolver should reject profile names with spaces.');
assertThrowsIniValue(fn () => $resolver->setActiveProfile('inexistente'), 'Resolver should reject selecting missing profiles.');

fwrite(STDOUT, "OK\n");
