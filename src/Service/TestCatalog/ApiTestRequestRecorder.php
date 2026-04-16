<?php

namespace App\Service\TestCatalog;

use App\Repository\ApiTestCatalogRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ApiTestRequestRecorder
{
    public function __construct(private readonly ApiTestCatalogRepository $repository)
    {
    }

    public function recordFromApiPlatform(Request $request, Response $response): void
    {
        $normalizedPath = $this->normalizePath($request->getPathInfo() !== '' ? $request->getPathInfo() : $request->getRequestUri());

        if (!$this->isApiPlatformRequest($request, $normalizedPath)) {
            return;
        }

        $group = $this->resolveGroup($normalizedPath);
        $testCase = $this->buildTestCase($request, $response, $group, $normalizedPath);
        $savedTest = $this->repository->saveObservedTestCase($group, $testCase);

        $batchId = $this->repository->createBatch(
            Uuid::v4()->toRfc4122(),
            'api_platform',
            sprintf('API Platform - %s', $savedTest['name']),
            rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/'),
            1
        );

        $success = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        $this->repository->recordRun($batchId, $savedTest['id'], 1, [
            'request_method' => strtoupper($request->getMethod()),
            'request_url' => $request->getUri(),
            'request_body' => $testCase['request_body'],
            'response_status_code' => $testCase['last_status_code'],
            'response_body' => $testCase['last_response_body'],
            'response_headers' => $testCase['last_response_headers'],
            'duration_ms' => $testCase['last_duration_ms'],
            'success' => $success,
            'error_message' => $success ? null : $this->resolveErrorMessage($response),
            'executed_at' => date('c'),
        ]);
        $this->repository->finalizeBatch($batchId, $success ? 1 : 0, $success ? 0 : 1);
    }

    private function isApiPlatformRequest(Request $request, string $normalizedPath): bool
    {
        if (
            $request->attributes->has('_api_resource_class')
            || $request->attributes->has('_api_operation_name')
            || $request->attributes->has('_api_operation')
        ) {
            return true;
        }

        foreach (['/nfe', '/nfse', '/acbr-cep'] as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{code:string,name:string,module:string,description:string,sort_order:int}
     */
    private function resolveGroup(string $pathInfo): array
    {
        $normalized = trim($pathInfo, '/');
        $segments = $normalized === '' ? [] : explode('/', $normalized);
        $first = $segments[0] ?? 'geral';
        $second = $segments[1] ?? '';

        if ($first === 'acbr-cep') {
            return [
                'code' => 'cep',
                'name' => 'CEP',
                'module' => 'cep',
                'description' => 'Cenarios gravados do modulo CEP a partir do API Platform.',
                'sort_order' => 10,
            ];
        }

        if (in_array($first, ['nfe', 'nfse'], true)) {
            $module = $first;
            $groupKey = $second !== '' ? str_replace('-', '_', $second) : 'geral';

            return [
                'code' => $module . '_' . $groupKey,
                'name' => strtoupper($module) . ' ' . $this->humanize($groupKey),
                'module' => $module,
                'description' => sprintf('Cenarios gravados do grupo %s do modulo %s.', $this->humanize($groupKey), strtoupper($module)),
                'sort_order' => $module === 'nfe' ? 20 : 60,
            ];
        }

        return [
            'code' => str_replace('-', '_', $first),
            'name' => $this->humanize($first),
            'module' => $first,
            'description' => sprintf('Cenarios gravados do grupo %s.', $this->humanize($first)),
            'sort_order' => 90,
        ];
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>
     */
    private function buildTestCase(Request $request, Response $response, array $group, string $normalizedPath): array
    {
        $queryString = trim((string) ($request->getQueryString() ?? $request->server->get('QUERY_STRING', '')));
        $requestBody = trim($request->getContent());
        $signature = sha1(json_encode([
            'method' => strtoupper($request->getMethod()),
            'path' => $normalizedPath,
            'query' => $queryString,
            'body' => $requestBody,
        ], JSON_UNESCAPED_SLASHES));

        $scenarioSuffix = substr($signature, 0, 8);
        $label = sprintf('%s %s · %s', strtoupper($request->getMethod()), $normalizedPath, $scenarioSuffix);
        $responseContent = $response->getContent();

        return [
            'code' => 'cenario_' . substr($signature, 0, 12),
            'name' => $label,
            'description' => $this->buildDescription($normalizedPath, $queryString, $requestBody, (string) $group['name']),
            'method' => strtoupper($request->getMethod()),
            'path' => $normalizedPath,
            'query_string' => $queryString !== '' ? $queryString : null,
            'request_body' => $requestBody !== '' ? $this->truncate($requestBody) : null,
            'headers_json' => json_encode($this->filterRequestHeaders($request), JSON_UNESCAPED_SLASHES),
            'request_signature' => $signature,
            'last_status_code' => $response->getStatusCode(),
            'last_response_body' => is_string($responseContent) ? $this->truncate($responseContent) : null,
            'last_response_headers' => $this->truncate($this->headersToString($response)),
            'last_duration_ms' => $this->resolveDurationMs($request),
        ];
    }

    private function normalizePath(string $pathInfo): string
    {
        $path = trim($pathInfo);
        if ($path === '') {
            return '/';
        }

        $path = preg_replace('#^https?://[^/]+#i', '', $path) ?? $path;
        $path = preg_replace('#^/index\.php#', '', $path) ?? $path;
        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /**
     * @return list<string>
     */
    private function filterRequestHeaders(Request $request): array
    {
        $headers = [];

        foreach (['accept', 'content-type'] as $headerName) {
            $value = $request->headers->get($headerName);
            if (is_string($value) && $value !== '') {
                $headers[] = sprintf('%s: %s', $headerName, $value);
            }
        }

        return $headers;
    }

    private function buildDescription(string $path, string $queryString, string $requestBody, string $groupName): string
    {
        $parts = [sprintf('Cenario gravado automaticamente do grupo %s para o endpoint %s.', $groupName, $path)];

        if ($queryString !== '') {
            $parts[] = 'Query: ' . $this->truncate($queryString, 160);
        }

        if ($requestBody !== '') {
            $parts[] = 'Payload: ' . $this->truncate($requestBody, 160);
        }

        return implode(' ', $parts);
    }

    private function headersToString(Response $response): string
    {
        $lines = [];

        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            foreach ($values as $value) {
                $lines[] = sprintf('%s: %s', $name, $value);
            }
        }

        return implode("\n", $lines);
    }

    private function resolveDurationMs(Request $request): ?int
    {
        $requestTimeFloat = $request->server->get('REQUEST_TIME_FLOAT');
        if (!is_numeric($requestTimeFloat)) {
            return null;
        }

        return (int) round((microtime(true) - (float) $requestTimeFloat) * 1000);
    }

    private function resolveErrorMessage(Response $response): ?string
    {
        $exceptionHeader = $response->headers->get('x-debug-exception');
        if (is_string($exceptionHeader) && $exceptionHeader !== '') {
            return $exceptionHeader;
        }

        return null;
    }

    private function humanize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);

        return ucwords($value);
    }

    private function truncate(?string $value, int $maxLength = 4000): ?string
    {
        if ($value === null) {
            return null;
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . "\n...[truncado]";
    }
}
