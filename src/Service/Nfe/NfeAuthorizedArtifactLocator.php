<?php

declare(strict_types=1);

namespace App\Service\Nfe;

final class NfeAuthorizedArtifactLocator
{
    public function __construct(
        private readonly string $basePath = __DIR__ . '/../../../NFe/arqs',
    ) {
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
