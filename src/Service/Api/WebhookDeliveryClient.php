<?php

namespace App\Service\Api;

final class WebhookDeliveryClient
{
    /**
     * @param array<string, mixed> $delivery
     * @return array{status_code:?int,response_headers:?string,response_body:?string,error:?string}
     */
    public function send(array $delivery): array
    {
        $body = (string) ($delivery['t_payload_json'] ?? '{}');
        $payload = $this->decodePayload($body);
        $url = $this->buildUrl($delivery, $payload);
        if ($url === '') {
            return [
                'status_code' => null,
                'response_headers' => null,
                'response_body' => null,
                'error' => 'Webhook sem URL configurada.',
            ];
        }
        if ($this->isBlockedUrl($url)) {
            return [
                'status_code' => null,
                'response_headers' => null,
                'response_body' => null,
                'error' => 'Webhook bloqueado: URL local, privada, reservada ou sem resolucao publica.',
            ];
        }

        $method = strtoupper(trim((string) ($delivery['c_metodo_http'] ?? 'POST')));
        $timeout = max(1, min(120, (int) ($delivery['si_timeout_segundos'] ?? 10)));
        $headers = $this->buildHeaders($delivery, $body, $payload);
        $responseHeaders = [];

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'status_code' => null,
                'response_headers' => null,
                'response_body' => null,
                'error' => 'Falha ao inicializar cURL para webhook.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $responseHeaders[] = rtrim($headerLine, "\r\n");

                return strlen($headerLine);
            },
        ]);

        $responseBody = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [
            'status_code' => $statusCode > 0 ? (int) $statusCode : null,
            'response_headers' => $responseHeaders === [] ? null : implode("\n", $responseHeaders),
            'response_body' => is_string($responseBody) ? $responseBody : null,
            'error' => $error !== '' ? $error : null,
        ];
    }

    /**
     * @param array<string, mixed> $delivery
     * @return list<string>
     */
    private function buildHeaders(array $delivery, string $body, array $payload): array
    {
        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . (string) ($delivery['c_evento'] ?? ''),
            'X-Webhook-Attempt: ' . (string) ($delivery['si_num_tentativa'] ?? 1),
            'X-Webhook-Timestamp: ' . (string) time(),
            'Idempotency-Key: ' . $this->buildIdempotencyKey($delivery),
        ];

        $secret = trim((string) ($delivery['t_secret'] ?? ''));
        if ($secret !== '') {
            $timestamp = $this->findHeaderValue($headers, 'X-Webhook-Timestamp') ?? (string) time();
            $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }

        $json = trim((string) ($delivery['t_headers_json'] ?? ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $name => $value) {
                    if (!is_scalar($value)) {
                        continue;
                    }

                    $headers[] = sprintf('%s: %s', (string) $name, (string) $value);
                }
            }
        }

        $variables = $this->decodeVariables($delivery);
        foreach (($variables['headers'] ?? []) as $name => $source) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $value = $this->resolveValue((string) $source, $payload);
            if ($value === null) {
                continue;
            }

            $headers[] = sprintf('%s: %s', $name, $value);
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function buildIdempotencyKey(array $delivery): string
    {
        return hash('sha256', implode(':', [
            (string) ($delivery['id_t99006'] ?? ''),
            (string) ($delivery['t99001_id'] ?? ''),
        ]));
    }

    /**
     * @param list<string> $headers
     */
    private function findHeaderValue(array $headers, string $name): ?string
    {
        $prefix = strtolower($name) . ':';
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return trim(substr($header, strlen($prefix)));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $delivery
     * @param array<string, mixed> $payload
     */
    private function buildUrl(array $delivery, array $payload): string
    {
        $url = trim((string) ($delivery['c_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        $variables = $this->decodeVariables($delivery);
        foreach (($variables['path'] ?? []) as $name => $source) {
            $value = $this->resolveValue((string) $source, $payload);
            if ($value === null) {
                continue;
            }

            $encoded = rawurlencode($value);
            $url = str_replace(['{' . $name . '}', ':' . $name], $encoded, $url);
        }

        $query = [];
        foreach (($variables['query'] ?? []) as $name => $source) {
            $value = $this->resolveValue((string) $source, $payload);
            if ($value === null) {
                continue;
            }

            $query[(string) $name] = $value;
        }

        if ($query !== []) {
            $url .= str_contains($url, '?') ? '&' : '?';
            $url .= http_build_query($query);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $delivery
     * @return array{query:array<string, mixed>,headers:array<string, mixed>,path:array<string, mixed>}
     */
    private function decodeVariables(array $delivery): array
    {
        $json = trim((string) ($delivery['t_variaveis_json'] ?? ''));
        $decoded = $json !== '' ? json_decode($json, true) : null;
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return [
            'query' => isset($decoded['query']) && is_array($decoded['query']) ? $decoded['query'] : [],
            'headers' => isset($decoded['headers']) && is_array($decoded['headers']) ? $decoded['headers'] : [],
            'path' => isset($decoded['path']) && is_array($decoded['path']) ? $decoded['path'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveValue(string $source, array $payload): ?string
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (str_starts_with($source, 'literal:')) {
            return substr($source, 8);
        }

        $current = $payload;
        foreach (explode('.', $source) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return null;
        }

        if (is_scalar($current) || $current === null) {
            return $current === null ? null : (string) $current;
        }

        return json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function isBlockedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return true;
        }

        $host = trim($host, "[] \t\n\r\0\x0B");
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !$this->isPublicIp($host);
        }

        $resolvedIps = @gethostbynamel($host);
        if ($resolvedIps === false || $resolvedIps === []) {
            return true;
        }

        foreach ($resolvedIps as $ip) {
            if (!$this->isPublicIp($ip)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
