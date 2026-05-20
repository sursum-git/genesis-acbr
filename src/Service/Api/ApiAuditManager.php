<?php

namespace App\Service\Api;

use App\Repository\ApiAuditRepository;
use App\Support\ApiRequestAttributes;
use App\Support\ApiRequestStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiAuditManager
{
    public function __construct(
        private readonly ApiAuditRepository $repository,
        private readonly ApiTokenHasher $tokenHasher,
        private readonly ApiProgramVersionResolver $programVersionResolver,
    ) {
    }

    public function begin(Request $request): string
    {
        $token = $this->extractBearerToken($request);
        $tokenHash = $token === null ? null : $this->tokenHasher->hash($token);
        $programVersion = $this->programVersionResolver->resolveByPath($request->getPathInfo());
        $requestId = $this->repository->createRequest([
            'c_metodo' => $request->getMethod(),
            'c_caminho' => $request->getPathInfo() !== '' ? $request->getPathInfo() : '/',
            'c_cod_programa' => $programVersion['code'] ?? null,
            'c_nome_programa' => $programVersion['name'] ?? null,
            'c_versao_programa' => $programVersion['version'] ?? null,
            'dt_hr_ult_atu_programa' => $programVersion['last_updated_at'] ?? null,
            'c_revisao_programa' => $programVersion['reference_commit'] ?? null,
            'c_fonte_versao' => $programVersion['version_source'] ?? null,
            'c_caminho_fisico_programa' => $programVersion['physical_path'] ?? null,
            't_query_string' => $request->getQueryString(),
            't_corpo_requisicao' => $this->truncate($request->getContent()),
            't_headers_requisicao' => json_encode($this->sanitizeRequestHeaders($request), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'c_rota' => $request->attributes->get('_route'),
            'c_nome_operacao' => null,
            'c_modo_execucao' => null,
            'c_token_hash' => $tokenHash,
            'c_ip_origem' => $request->getClientIp(),
        ]);

        $request->attributes->set(ApiRequestAttributes::REQUEST_ID, $requestId);
        if ($tokenHash !== null) {
            $request->attributes->set(ApiRequestAttributes::TOKEN_HASH, $tokenHash);
        }

        return $requestId;
    }

    public function attachAuthentication(Request $request, ?array $assinante): void
    {
        $requestId = (string) $request->attributes->get(ApiRequestAttributes::REQUEST_ID, '');
        if ($requestId === '') {
            return;
        }

        $tokenHash = $request->attributes->get(ApiRequestAttributes::TOKEN_HASH);
        $assinanteSanitizado = $assinante === null ? null : $this->sanitizeAssinante($assinante);

        $this->repository->updateAuthenticationContext(
            $requestId,
            is_string($tokenHash) ? $tokenHash : null,
            $assinanteSanitizado
        );
    }

    public function markUnauthorized(Request $request): void
    {
        $requestId = (string) $request->attributes->get(ApiRequestAttributes::REQUEST_ID, '');
        if ($requestId === '') {
            return;
        }

        $this->repository->markUnauthorized($requestId);
    }

    public function markAsyncAccepted(Request $request, string $operationName): void
    {
        $requestId = (string) $request->attributes->get(ApiRequestAttributes::REQUEST_ID, '');
        if ($requestId === '') {
            return;
        }

        $this->repository->markQueued(
            $requestId,
            is_string($request->attributes->get('_route')) ? $request->attributes->get('_route') : null,
            $operationName !== '' ? $operationName : null
        );
    }

    public function finalize(Request $request, Response $response): void
    {
        $requestId = (string) $request->attributes->get(ApiRequestAttributes::REQUEST_ID, '');
        if ($requestId === '') {
            return;
        }

        $statusProcessamento = $this->resolveRequestStatus($request, $response);
        $this->repository->finalizeRequest(
            $requestId,
            $response->getStatusCode(),
            $this->truncate($response->getContent()),
            $this->truncate($this->headersToString($response)),
            $statusProcessamento === ApiRequestStatus::FALHA ? $this->extractErrorMessage($response) : null,
            $statusProcessamento,
            $this->resolveDurationMs($request)
        );
    }

    public function updateOperationContext(Request $request, ?string $operationName, ?string $mode): void
    {
        $requestId = (string) $request->attributes->get(ApiRequestAttributes::REQUEST_ID, '');
        if ($requestId === '') {
            return;
        }

        $this->repository->updateOperationContext(
            $requestId,
            is_string($request->attributes->get('_route')) ? $request->attributes->get('_route') : null,
            $operationName,
            $mode
        );
    }

    public function buildAcceptedPayload(Request $request, array $echo): array
    {
        return [
            'request_id' => (string) $request->attributes->get(ApiRequestAttributes::REQUEST_ID, ''),
            'status' => 'queued',
            'endpoint' => $request->getPathInfo(),
            'received_at' => date('c'),
            'echo' => $echo,
        ];
    }

    private function resolveRequestStatus(Request $request, Response $response): int
    {
        if ($response->getStatusCode() === Response::HTTP_ACCEPTED) {
            return ApiRequestStatus::ENFILEIRADA;
        }

        if ($response->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
            return ApiRequestStatus::NAO_AUTORIZADA;
        }

        return $response->getStatusCode() >= 400 ? ApiRequestStatus::FALHA : ApiRequestStatus::CONCLUIDA;
    }

    /**
     * @return array<string, string>
     */
    private function sanitizeRequestHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = in_array($name, ['authorization', 'x-api-token'], true)
                ? ($name === 'authorization' ? 'Bearer [mascarado]' : '[mascarado]')
                : implode(', ', $values);
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $assinante
     * @return array<string, mixed>
     */
    private function sanitizeAssinante(array $assinante): array
    {
        unset($assinante['c_token']);

        return $assinante;
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

    private function extractErrorMessage(Response $response): ?string
    {
        $content = trim((string) $response->getContent());

        return $content === '' ? null : $this->truncate($content, 2000);
    }

    private function resolveDurationMs(Request $request): ?int
    {
        $requestTimeFloat = $request->server->get('REQUEST_TIME_FLOAT');
        if (!is_numeric($requestTimeFloat)) {
            return null;
        }

        return (int) round((microtime(true) - (float) $requestTimeFloat) * 1000);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if ($authorization === '') {
            $authorization = trim((string) $request->server->get('HTTP_AUTHORIZATION', ''));
        }
        if ($authorization === '') {
            $authorization = trim((string) $request->server->get('REDIRECT_HTTP_AUTHORIZATION', ''));
        }
        if ($authorization === '') {
            $apiToken = trim((string) $request->headers->get('X-Api-Token', ''));
            if ($apiToken === '') {
                $apiToken = trim((string) $request->server->get('HTTP_X_API_TOKEN', ''));
            }

            return $apiToken === '' ? null : $apiToken;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));

        return $token === '' ? null : $token;
    }

    private function truncate(?string $value, int $maxLength = 12000): ?string
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
