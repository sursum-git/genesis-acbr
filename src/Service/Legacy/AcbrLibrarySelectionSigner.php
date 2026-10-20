<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use DateTimeInterface;
use RuntimeException;

final class AcbrLibrarySelectionSigner
{
    public function __construct(private readonly string $secret)
    {
        if ($secret === '') {
            throw new RuntimeException('Segredo de seleção de biblioteca ACBr não configurado.');
        }
    }

    public function sign(AcbrLibraryReleaseSelection $selection, int $ttlSeconds, DateTimeInterface $now): string
    {
        $payload = json_encode([
            'id' => $selection->id,
            'module' => $selection->module,
            'environment' => $selection->environment,
            'profile' => $selection->profile,
            'release' => $selection->release,
            'path' => $selection->path,
            'sha256' => $selection->sha256,
            'expires_at' => $now->getTimestamp() + $ttlSeconds,
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Não foi possível assinar a seleção da biblioteca ACBr.');
        }
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $this->secret, true);
        return $encoded . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /** @return array<string, mixed> */
    public function verify(string $token, string $module, DateTimeInterface $now): array
    {
        [$encoded, $providedSignature] = array_pad(explode('.', $token, 2), 2, null);
        if (!is_string($encoded) || !is_string($providedSignature)) {
            throw new RuntimeException('Seleção interna de biblioteca ACBr inválida.');
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, $this->secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expected, $providedSignature)) {
            throw new RuntimeException('Assinatura da seleção de biblioteca ACBr inválida.');
        }
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload) || ($payload['module'] ?? null) !== $module || !isset($payload['expires_at']) || (int) $payload['expires_at'] <= $now->getTimestamp()) {
            throw new RuntimeException('Seleção interna de biblioteca ACBr expirada ou incompatível.');
        }
        return $payload;
    }
}
