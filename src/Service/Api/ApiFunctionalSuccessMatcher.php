<?php

declare(strict_types=1);

namespace App\Service\Api;

final class ApiFunctionalSuccessMatcher
{
    /**
     * @param array<string, mixed> $payload
     * @return array{c_stat_receita: string}|null
     */
    public function match(int $statusCode, array $payload, string $httpCodesCsv, string $receitaCodesCsv): ?array
    {
        if (!$this->containsCode($httpCodesCsv, (string) $statusCode)) {
            return null;
        }

        $cStat = $this->extractReceitaCode($payload);
        if ($cStat === null || !$this->containsCode($receitaCodesCsv, $cStat)) {
            return null;
        }

        return ['c_stat_receita' => $cStat];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function extractReceitaCode(array $payload): ?string
    {
        foreach ($this->candidateTexts($payload) as $text) {
            if (preg_match('/^CStat=(\d+)$/mi', $text, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/^cStat=(\d+)$/mi', $text, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function containsCode(string $csv, string $expected): bool
    {
        $codes = array_filter(array_map(static fn (string $item): string => trim($item), explode(',', $csv)));

        return in_array($expected, $codes, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function candidateTexts(array $payload): array
    {
        $texts = [];

        foreach (['mensagem', 'raw'] as $key) {
            if (is_string($payload[$key] ?? null)) {
                $texts[] = (string) $payload[$key];
            }
        }

        if (is_array($payload['resultado'] ?? null)) {
            foreach (['mensagem', 'raw'] as $key) {
                if (is_string($payload['resultado'][$key] ?? null)) {
                    $texts[] = (string) $payload['resultado'][$key];
                }
            }
        }

        return $texts;
    }
}
