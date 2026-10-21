<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use App\Repository\AcbrLibraryReleaseRepository;
use DateTimeInterface;
use RuntimeException;

final class AcbrLibraryReleaseResolver
{
    public function __construct(
        private readonly AcbrLibraryReleaseRepository $repository,
        private readonly AcbrIniProfileResolver $iniProfiles,
        private readonly string $allowedRoot = '/opt/acbr_libs/releases',
    ) {
    }

    public function resolveNfe(?string $profile, DateTimeInterface $at, ?string $apiEnvironment = null): AcbrLibraryReleaseSelection
    {
        $environment = $apiEnvironment === null ? null : match ($apiEnvironment) {
            '1' => 'producao',
            '2' => 'homologacao',
            default => throw new RuntimeException('X-ACBr-Ambiente inválido. Use 1 para produção ou 2 para homologação.'),
        };
        $row = null;
        if ($environment !== null) {
            $row = $this->repository->findActive('nfe', $environment, $at);
            if ($row === null) {
                throw new RuntimeException('Nenhuma release ACBr NFe está vigente para o ambiente fiscal selecionado.');
            }
            if ($profile !== null && $profile !== $row['c_perfil_ini']) {
                throw new RuntimeException('O perfil INI informado conflita com o ambiente selecionado no header.');
            }
            $profile = (string) $row['c_perfil_ini'];
            if ($profile === '') {
                throw new RuntimeException('A release vigente não possui perfil INI cadastrado.');
            }
        }
        $iniPath = $this->iniProfiles->resolve($profile);
        $ini = parse_ini_file($iniPath, true, INI_SCANNER_RAW);
        $nativeEnvironment = match ((string) ($ini['NFe']['Ambiente'] ?? '')) {
            '0' => 'producao',
            '1' => 'homologacao',
            default => throw new RuntimeException('Ambiente fiscal NFe ausente ou inválido no perfil INI.'),
        };
        if ($environment !== null && $environment !== $nativeEnvironment) {
            throw new RuntimeException('O ambiente do perfil INI diverge do ambiente selecionado no header.');
        }
        $environment ??= $nativeEnvironment;
        $profile ??= basename(dirname($iniPath));
        $row ??= $this->repository->findActive('nfe', $environment, $at);
        if ($row === null) {
            throw new RuntimeException('Nenhuma release ACBr NFe está vigente para o ambiente fiscal selecionado.');
        }
        if (($row['c_perfil_ini'] ?? null) !== $profile) {
            throw new RuntimeException('A release ACBr vigente não corresponde ao perfil INI selecionado.');
        }
        $path = realpath((string) $row['c_caminho_so']);
        $root = realpath($this->allowedRoot);
        if ($path === false || $root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new RuntimeException('Caminho da biblioteca ACBr não é permitido ou não existe.');
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false || !hash_equals((string) $row['c_sha256'], $hash)) {
            throw new RuntimeException('Checksum da biblioteca ACBr não confere.');
        }
        return new AcbrLibraryReleaseSelection((int) $row['id_t99037'], 'nfe', $environment, $profile, (string) $row['c_release'], $path, $hash);
    }
}
