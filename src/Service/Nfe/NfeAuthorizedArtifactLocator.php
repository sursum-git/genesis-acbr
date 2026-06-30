<?php

declare(strict_types=1);

namespace App\Service\Nfe;

final class NfeAuthorizedArtifactLocator
{
    private readonly string $basePath;

    public function __construct(
        ?string $basePath = null,
    ) {
        $this->basePath = $basePath ?? dirname(__DIR__, 3) . '/NFe/arqs';
    }

    /**
     * @param array<string, mixed> $result
     */
    public function extractAccessKeyFromResult(array $result): ?string
    {
        foreach ($this->candidateTexts($result) as $text) {
            if (preg_match('/^chDFe=(\d{44})$/mi', $text, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/^chNFe=(\d{44})$/mi', $text, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    public function locateAuthorizedXmlPath(string $accessKey): ?string
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?? '';
        if ($accessKey === '' || !is_dir($this->basePath)) {
            return null;
        }

        $expectedName = $accessKey . '-nfe.xml';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->basePath, \FilesystemIterator::SKIP_DOTS)
        );

        $candidate = null;
        $candidateMtime = 0;

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if ($file->getFilename() !== $expectedName) {
                continue;
            }

            $mtime = $file->getMTime();
            if ($candidate === null || $mtime >= $candidateMtime) {
                $candidate = $file->getPathname();
                $candidateMtime = $mtime;
            }
        }

        return $candidate;
    }

    public function extractPdfPath(string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        if (is_file($message)) {
            return $message;
        }

        if (preg_match('/(\/[^\s]+\.pdf)/i', $message, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function extractPdfBinary(string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', $message);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        $decoded = base64_decode($normalized, true);
        if (!is_string($decoded) || $decoded === '' || !str_starts_with($decoded, '%PDF-')) {
            return null;
        }

        return $decoded;
    }

    public function buildDanfePath(string $accessKey): string
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?? '';

        return rtrim($this->basePath, '/\\') . '/danfes/' . $accessKey . '-danfe.pdf';
    }

    public function resolveDanfePath(string $accessKey, string $xmlPath): string
    {
        $preferredPath = $this->buildDanfePath($accessKey);
        if ($this->ensureDirectory(dirname($preferredPath))) {
            return $preferredPath;
        }

        $scopedPath = $this->buildScopedDanfePath($xmlPath, $accessKey);
        if ($this->ensureDirectory(dirname($scopedPath))) {
            return $scopedPath;
        }

        return $preferredPath;
    }

    private function buildScopedDanfePath(string $xmlPath, string $accessKey): string
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?? '';
        $xmlPath = str_replace('\\', '/', $xmlPath);
        $anchor = '/NFe/';
        $position = strpos($xmlPath, $anchor);
        $baseDirectory = $position === false
            ? dirname($xmlPath)
            : substr($xmlPath, 0, $position);

        return rtrim($baseDirectory, '/\\') . '/danfes/' . $accessKey . '-danfe.pdf';
    }

    private function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        return @mkdir($directory, 0777, true) || is_dir($directory);
    }

    /**
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private function candidateTexts(array $result): array
    {
        $texts = [];

        foreach (['mensagem', 'raw'] as $key) {
            if (is_string($result[$key] ?? null)) {
                $texts[] = (string) $result[$key];
            }
        }

        return $texts;
    }
}
