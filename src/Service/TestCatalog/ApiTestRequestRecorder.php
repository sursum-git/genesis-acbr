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

    public function recordIfCataloged(Request $request, Response $response): void
    {
        $test = $this->repository->findTestByRequestSignature(
            $request->getMethod(),
            $this->buildRequestTarget($request)
        );

        if ($test === null) {
            return;
        }

        $requestBody = $request->getContent();
        $requestUrl = $request->getUri();
        $responseContent = $response->getContent();
        $baseUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');
        $success = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

        $batchId = $this->repository->createBatch(
            Uuid::v4()->toRfc4122(),
            'api_platform',
            sprintf('Try it out - %s', $test['name']),
            $baseUrl,
            1
        );

        $errorMessage = null;
        if (!$success) {
            $errorMessage = $response->headers->get('x-debug-exception');
            if ($errorMessage === null || $errorMessage === '') {
                $errorMessage = null;
            }
        }

        $this->repository->recordRun($batchId, (int) $test['id'], 1, [
            'request_method' => strtoupper($request->getMethod()),
            'request_url' => $requestUrl,
            'request_body' => $requestBody !== '' ? $this->truncate($requestBody) : null,
            'response_status_code' => $response->getStatusCode(),
            'response_body' => is_string($responseContent) ? $this->truncate($responseContent) : null,
            'response_headers' => $this->truncate($this->headersToString($response)),
            'duration_ms' => $this->resolveDurationMs($request),
            'success' => $success,
            'error_message' => $errorMessage,
            'executed_at' => date('c'),
        ]);

        $this->repository->finalizeBatch($batchId, $success ? 1 : 0, $success ? 0 : 1);
    }

    private function buildRequestTarget(Request $request): string
    {
        $requestTarget = $request->getRequestUri();
        if ($requestTarget === '') {
            return $request->getPathInfo();
        }

        return $requestTarget;
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
