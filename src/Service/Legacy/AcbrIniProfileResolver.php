<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use InvalidArgumentException;
use RuntimeException;

final class AcbrIniProfileResolver
{
    public function __construct(
        private readonly ?string $baseDir = null,
        private readonly string $libraryName = 'ACBrNFe',
        private readonly string $activeKey = 'nfe_mt',
    ) {
    }

    public function resolve(?string $profile): string
    {
        $profile = $this->normalizeNullableProfile($profile);
        if ($profile !== null) {
            return $this->existingProfilePath($profile);
        }

        $activeProfile = $this->readActiveProfile();
        if ($activeProfile !== null) {
            return $this->existingProfilePath($activeProfile);
        }

        return $this->defaultIniPath();
    }

    /**
     * @return list<array{id: string, active: bool, path: string}>
     */
    public function listProfiles(): array
    {
        $profilesDir = $this->profilesDir();
        if (!is_dir($profilesDir)) {
            return [];
        }

        $activeProfile = $this->readActiveProfile();
        $profiles = [];

        foreach ((scandir($profilesDir) ?: []) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $profile = $this->normalizeNullableProfile($entry);
            if ($profile === null || $profile !== $entry) {
                continue;
            }

            $path = $this->profileIniPath($profile);
            if (!is_file($path)) {
                continue;
            }

            $profiles[] = [
                'id' => $profile,
                'active' => $activeProfile === $profile,
                'path' => $path,
            ];
        }

        usort($profiles, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $profiles;
    }

    public function createProfile(string $profile, ?string $sourceProfile = null): string
    {
        $profile = $this->normalizeRequiredProfile($profile);
        $targetPath = $this->profileIniPath($profile);

        if (file_exists($targetPath)) {
            throw new RuntimeException("Perfil INI ja existe: {$profile}");
        }

        $sourceProfile = $this->normalizeNullableProfile($sourceProfile);
        $sourcePath = $sourceProfile !== null ? $this->existingProfilePath($sourceProfile) : $this->defaultIniPath();

        if (!is_file($sourcePath)) {
            throw new RuntimeException("Arquivo INI padrao nao encontrado: {$sourcePath}");
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Nao foi possivel criar a pasta do perfil INI: {$targetDir}");
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Nao foi possivel criar o perfil INI: {$profile}");
        }

        return $targetPath;
    }

    public function setActiveProfile(string $profile): void
    {
        $profile = $this->normalizeRequiredProfile($profile);
        $this->existingProfilePath($profile);

        $activeFile = $this->activeProfileFile();
        $activeDir = dirname($activeFile);
        if (!is_dir($activeDir) && !mkdir($activeDir, 0777, true) && !is_dir($activeDir)) {
            throw new RuntimeException("Nao foi possivel criar a pasta de perfis INI: {$activeDir}");
        }

        $data = is_file($activeFile) ? json_decode((string) file_get_contents($activeFile), true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $data[$this->activeKey] = $profile;
        $tmpFile = $activeFile . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($tmpFile, $json . PHP_EOL, LOCK_EX) === false || !rename($tmpFile, $activeFile)) {
            @unlink($tmpFile);
            throw new RuntimeException("Nao foi possivel gravar o perfil INI ativo.");
        }
    }

    public function defaultIniPath(): string
    {
        return $this->baseDir() . DIRECTORY_SEPARATOR . $this->libraryName . '.INI';
    }

    public function profilesDir(): string
    {
        return $this->baseDir() . DIRECTORY_SEPARATOR . 'configs';
    }

    private function activeProfileFile(): string
    {
        return $this->profilesDir() . DIRECTORY_SEPARATOR . 'active-profile.json';
    }

    private function profileIniPath(string $profile): string
    {
        return $this->profilesDir() . DIRECTORY_SEPARATOR . $profile . DIRECTORY_SEPARATOR . $this->libraryName . '.INI';
    }

    private function existingProfilePath(string $profile): string
    {
        $path = $this->profileIniPath($profile);
        if (!is_file($path)) {
            throw new RuntimeException("Perfil INI nao encontrado: {$profile}");
        }

        return $path;
    }

    private function readActiveProfile(): ?string
    {
        $activeFile = $this->activeProfileFile();
        if (!is_file($activeFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($activeFile), true);
        if (!is_array($data)) {
            return null;
        }

        $profile = $data[$this->activeKey] ?? $data['active'] ?? null;
        return is_string($profile) ? $this->normalizeNullableProfile($profile) : null;
    }

    private function normalizeRequiredProfile(string $profile): string
    {
        $normalized = $this->normalizeNullableProfile($profile);
        if ($normalized === null) {
            throw new InvalidArgumentException('Perfil INI invalido. Use apenas letras, numeros, ponto, hifen ou underline.');
        }

        return $normalized;
    }

    private function normalizeNullableProfile(?string $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $profile = trim($profile);
        if ($profile === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $profile)) {
            throw new InvalidArgumentException('Perfil INI invalido. Use apenas letras, numeros, ponto, hifen ou underline.');
        }

        if (str_contains($profile, '..') || str_contains($profile, '/') || str_contains($profile, '\\')) {
            throw new InvalidArgumentException('Perfil INI invalido. Caminhos nao sao permitidos.');
        }

        return $profile;
    }

    private function baseDir(): string
    {
        if ($this->baseDir !== null && $this->baseDir !== '') {
            return rtrim($this->baseDir, DIRECTORY_SEPARATOR);
        }

        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'NFe' . DIRECTORY_SEPARATOR . 'MT';
    }
}
