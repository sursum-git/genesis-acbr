<?php

namespace App\Service\TestCatalog;

use App\Repository\ApiTestCatalogRepository;
use Symfony\Component\Uid\Uuid;

final class ApiTestRunner
{
    public function __construct(private readonly ApiTestCatalogRepository $repository)
    {
    }

    /**
     * @param list<array<string, mixed>> $tests
     */
    public function runTests(array $tests, string $runMode, string $label, string $baseUrl): int
    {
        $normalizedBaseUrl = rtrim($baseUrl, '/');
        $batchId = $this->repository->createBatch(
            Uuid::v4()->toRfc4122(),
            $runMode,
            $label,
            $normalizedBaseUrl,
            count($tests)
        );

        $passedTests = 0;
        $failedTests = 0;

        foreach (array_values($tests) as $index => $test) {
            $result = $this->executeTest($test, $normalizedBaseUrl);
            $this->repository->recordRun($batchId, (int) $test['id'], $index + 1, $result);

            if ($result['success'] === true) {
                ++$passedTests;
            } else {
                ++$failedTests;
            }
        }

        $this->repository->finalizeBatch($batchId, $passedTests, $failedTests);

        return $batchId;
    }

    /**
     * @param array<string, mixed> $test
     * @return array<string, mixed>
     */
    private function executeTest(array $test, string $baseUrl): array
    {
        $headers = $this->decodeHeaders($test['headers_json'] ?? null);
        $requestUrl = $baseUrl . $test['path'];
        $requestBody = isset($test['request_body']) && is_string($test['request_body']) ? $test['request_body'] : null;
        $method = strtoupper((string) $test['method']);

        $curlHandle = curl_init($requestUrl);
        $startedAt = microtime(true);

        curl_setopt_array($curlHandle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        if ($requestBody !== null && $requestBody !== '') {
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $requestBody);
        }

        $rawResponse = curl_exec($curlHandle);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $curlError = curl_error($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);
        curl_close($curlHandle);

        $responseHeaders = null;
        $responseBody = null;

        if (is_string($rawResponse)) {
            $responseHeaders = substr($rawResponse, 0, $headerSize);
            $responseBody = substr($rawResponse, $headerSize);
        }

        $success = $curlError === '' && $statusCode >= 200 && $statusCode < 300;

        return [
            'request_method' => $method,
            'request_url' => $requestUrl,
            'request_body' => $requestBody,
            'response_status_code' => $statusCode > 0 ? $statusCode : null,
            'response_body' => $this->truncate($responseBody),
            'response_headers' => $this->truncate($responseHeaders),
            'duration_ms' => $durationMs,
            'success' => $success,
            'error_message' => $curlError !== '' ? $curlError : null,
            'executed_at' => date('c'),
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeHeaders(mixed $headersJson): array
    {
        if (!is_string($headersJson) || $headersJson === '') {
            return [];
        }

        $headers = json_decode($headersJson, true);
        if (!is_array($headers)) {
            return [];
        }

        return array_values(array_filter($headers, static fn (mixed $value): bool => is_string($value) && $value !== ''));
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
