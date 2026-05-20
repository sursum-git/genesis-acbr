<?php

namespace App\Service\Api;

use App\Support\ApiRequestAttributes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InternalApiRequestRunner
{
    public function __construct(
        private readonly HttpKernelInterface $httpKernel,
        private readonly string $internalBaseUrl = 'http://127.0.0.1',
    ) {
    }

    public function run(array $requestData): Response
    {
        $base = parse_url($this->internalBaseUrl);
        $scheme = strtolower((string) ($base['scheme'] ?? 'http'));
        $host = (string) ($base['host'] ?? '127.0.0.1');
        $port = (int) ($base['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string) ($requestData['c_caminho'] ?? '/');
        $queryString = (string) ($requestData['t_query_string'] ?? '');
        $uri = $queryString === '' ? $path : $path . '?' . $queryString;

        $headers = $this->decodeJson((string) ($requestData['t_headers_requisicao'] ?? ''));
        unset($headers['authorization']);

        $server = [
            'HTTP_HOST' => $host,
            'SERVER_PORT' => $port,
            'REQUEST_SCHEME' => $scheme,
            'HTTPS' => $scheme === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        foreach ($headers as $name => $value) {
            $normalized = match (strtolower($name)) {
                'content-type' => 'CONTENT_TYPE',
                'content-length' => 'CONTENT_LENGTH',
                default => 'HTTP_' . strtoupper(str_replace('-', '_', $name)),
            };
            $server[$normalized] = (string) $value;
        }

        $request = Request::create(
            $uri,
            strtoupper((string) ($requestData['c_metodo'] ?? 'GET')),
            [],
            [],
            [],
            $server,
            (string) ($requestData['t_corpo_requisicao'] ?? '')
        );

        $request->attributes->set(ApiRequestAttributes::INTERNAL_WORKER, true);
        $request->attributes->set(ApiRequestAttributes::BYPASS_QUEUE, true);

        return $this->httpKernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
