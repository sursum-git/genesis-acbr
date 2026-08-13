<?php

namespace App\Service\Auth;

final class AuthTokenService
{
    public function __construct(
        private readonly string $jwtSecret = '',
        private readonly int $accessTtlSeconds = 900,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function issueAccessToken(int $userId, array $roles, ?int $subscriberId = null): string
    {
        $now = time();
        $payload = [
            'sub' => $userId,
            'roles' => array_values($roles),
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + $this->accessTtlSeconds,
            'jti' => bin2hex(random_bytes(16)),
        ];
        if ($subscriberId !== null) {
            $payload['sid'] = $subscriberId;
        }

        return $this->encode($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyAccessToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $this->secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $claims = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($claims)) {
            return null;
        }

        $now = time();
        if ((int) ($claims['nbf'] ?? 0) > $now || (int) ($claims['exp'] ?? 0) < $now) {
            return null;
        }

        return $claims;
    }

    /**
     * @return array{plain:string,hash:string,family:string,expires_at:string}
     */
    public function issueRefreshToken(int $ttlSeconds = 2592000): array
    {
        $plain = 'rft_' . bin2hex(random_bytes(32));

        return [
            'plain' => $plain,
            'hash' => hash('sha256', $plain),
            'family' => bin2hex(random_bytes(16)),
            'expires_at' => date('c', time() + $ttlSeconds),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $body, $this->secret(), true));

        return $header . '.' . $body . '.' . $signature;
    }

    private function secret(): string
    {
        return $this->jwtSecret !== '' ? $this->jwtSecret : (string) ($_ENV['APP_AUTH_JWT_SECRET'] ?? $_SERVER['APP_AUTH_JWT_SECRET'] ?? 'change-me-in-env');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
