<?php

declare(strict_types=1);

namespace App\Service\Nfe;

/** Formats shared by numeric and alphanumeric CNPJ/NF-e identifiers. */
final class NfeFiscalIdentifier
{
    public const CNPJ_PATTERN = '[A-Z0-9]{12}[0-9]{2}';
    public const ACCESS_KEY_PATTERN = '[0-9]{6}[A-Z0-9]{12}[0-9]{26}';

    public static function normalizeDocument(string $value): string
    {
        $value = strtoupper(preg_replace('/[.\/\-\s]+/', '', $value) ?? '');
        return preg_match('/^(?:[0-9]{11}|' . self::CNPJ_PATTERN . ')$/D', $value) === 1 ? $value : '';
    }

    public static function normalizeAccessKey(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^' . self::ACCESS_KEY_PATTERN . '$/D', $value) === 1 ? $value : '';
    }

    public static function extractAccessKey(string $text): ?string
    {
        if (preg_match('/(?<![A-Z0-9])(?:NFe)?(' . self::ACCESS_KEY_PATTERN . ')(?![A-Z0-9])/i', $text, $matches) === 1) {
            return strtoupper($matches[1]);
        }
        return null;
    }
}
