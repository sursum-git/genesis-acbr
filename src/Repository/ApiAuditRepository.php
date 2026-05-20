<?php

namespace App\Repository;

use App\Support\ApiRequestStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class ApiAuditRepository
{
    public function __construct(private readonly Connection $auditConnection)
    {
    }

    public function createRequest(array $payload): string
    {
        $requestId = Uuid::v4()->toRfc4122();
        $now = date('c');

        $this->auditConnection->insert('t99001', [
            'u_c_request_id' => $requestId,
            'c_metodo' => strtoupper((string) ($payload['c_metodo'] ?? 'GET')),
            'c_caminho' => (string) ($payload['c_caminho'] ?? '/'),
            'c_cod_programa' => $payload['c_cod_programa'] ?? null,
            'c_nome_programa' => $payload['c_nome_programa'] ?? null,
            'c_versao_programa' => $payload['c_versao_programa'] ?? null,
            'dt_hr_ult_atu_programa' => $payload['dt_hr_ult_atu_programa'] ?? null,
            'c_revisao_programa' => $payload['c_revisao_programa'] ?? null,
            'c_fonte_versao' => $payload['c_fonte_versao'] ?? null,
            'c_caminho_fisico_programa' => $payload['c_caminho_fisico_programa'] ?? null,
            't_query_string' => $payload['t_query_string'] ?? null,
            't_corpo_requisicao' => $payload['t_corpo_requisicao'] ?? null,
            't_headers_requisicao' => $payload['t_headers_requisicao'] ?? null,
            'c_rota' => $payload['c_rota'] ?? null,
            'c_nome_operacao' => $payload['c_nome_operacao'] ?? null,
            'c_modo_execucao' => $payload['c_modo_execucao'] ?? null,
            'c_token_hash' => $payload['c_token_hash'] ?? null,
            't_assinante_json' => $payload['t_assinante_json'] ?? null,
            'c_ip_origem' => $payload['c_ip_origem'] ?? null,
            'si_status_processamento' => ApiRequestStatus::RECEBIDA,
            'dt_hr_recebimento' => $now,
            'dt_hr_atu' => $now,
        ]);

        return $requestId;
    }

    public function updateAuthenticationContext(string $requestId, ?string $tokenHash, ?array $assinante): void
    {
        $this->auditConnection->update('t99001', [
            'c_token_hash' => $tokenHash,
            't_assinante_json' => $assinante === null ? null : json_encode($assinante, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dt_hr_atu' => date('c'),
        ], [
            'u_c_request_id' => $requestId,
        ]);
    }

    public function updateOperationContext(string $requestId, ?string $routeName, ?string $operationName, ?string $mode): void
    {
        $this->auditConnection->update('t99001', [
            'c_rota' => $routeName,
            'c_nome_operacao' => $operationName,
            'c_modo_execucao' => $mode,
            'dt_hr_atu' => date('c'),
        ], [
            'u_c_request_id' => $requestId,
        ]);
    }

    public function markQueued(string $requestId, ?string $routeName, ?string $operationName): void
    {
        $this->auditConnection->update('t99001', [
            'si_status_processamento' => ApiRequestStatus::ENFILEIRADA,
            'c_modo_execucao' => 'async',
            'c_rota' => $routeName,
            'c_nome_operacao' => $operationName,
            'dt_hr_atu' => date('c'),
        ], [
            'u_c_request_id' => $requestId,
        ]);
    }

    public function markUnauthorized(string $requestId): void
    {
        $this->auditConnection->update('t99001', [
            'si_status_processamento' => ApiRequestStatus::NAO_AUTORIZADA,
            'dt_hr_atu' => date('c'),
        ], [
            'u_c_request_id' => $requestId,
        ]);
    }

    public function finalizeRequest(
        string $requestId,
        int $statusCode,
        ?string $responseBody,
        ?string $responseHeaders,
        ?string $errorMessage,
        int $statusProcessamento,
        ?int $durationMs
    ): void
    {
        $payload = [
            'si_status_http' => $statusCode,
            't_corpo_resposta' => $responseBody,
            't_headers_resposta' => $responseHeaders,
            't_erro' => $errorMessage,
            'si_status_processamento' => $statusProcessamento,
            'i_tempo_processamento_ms' => $durationMs,
            'dt_hr_atu' => date('c'),
        ];

        if ($statusProcessamento !== ApiRequestStatus::ENFILEIRADA) {
            $payload['dt_hr_fim_processamento'] = date('c');
        }

        $this->auditConnection->update('t99001', $payload, [
            'u_c_request_id' => $requestId,
        ]);
    }

    public function claimNextQueuedRequest(): ?array
    {
        $this->auditConnection->beginTransaction();

        try {
            $row = $this->auditConnection->fetchAssociative(
                <<<'SQL'
                SELECT *
                FROM t99001
                WHERE si_status_processamento = :si_status_processamento
                ORDER BY id_t99001 ASC
                FOR UPDATE SKIP LOCKED
                LIMIT 1
                SQL,
                [
                    'si_status_processamento' => ApiRequestStatus::ENFILEIRADA,
                ]
            );

            if ($row === false) {
                $this->auditConnection->commit();

                return null;
            }

            $this->auditConnection->update('t99001', [
                'si_status_processamento' => ApiRequestStatus::PROCESSANDO,
                'dt_hr_ini_processamento' => date('c'),
                'dt_hr_atu' => date('c'),
            ], [
                'id_t99001' => $row['id_t99001'],
            ]);

            $this->auditConnection->commit();

            return $row;
        } catch (\Throwable $throwable) {
            $this->auditConnection->rollBack();
            throw $throwable;
        }
    }

    public function createAttempt(int $requestInternalId): int
    {
        $attemptNumber = (int) $this->auditConnection->fetchOne(
            'SELECT COUNT(*) FROM t99002 WHERE t99001_id = :t99001_id',
            ['t99001_id' => $requestInternalId]
        ) + 1;

        $programSnapshot = $this->auditConnection->fetchAssociative(
            'SELECT c_cod_programa, c_nome_programa, c_versao_programa, dt_hr_ult_atu_programa, c_revisao_programa, c_fonte_versao, c_caminho_fisico_programa FROM t99001 WHERE id_t99001 = :id_t99001 LIMIT 1',
            ['id_t99001' => $requestInternalId]
        ) ?: [];

        $this->auditConnection->insert('t99002', [
            't99001_id' => $requestInternalId,
            'si_num_tentativa' => $attemptNumber,
            'si_status_processamento' => ApiRequestStatus::PROCESSANDO,
            'c_cod_programa' => $programSnapshot['c_cod_programa'] ?? null,
            'c_nome_programa' => $programSnapshot['c_nome_programa'] ?? null,
            'c_versao_programa' => $programSnapshot['c_versao_programa'] ?? null,
            'dt_hr_ult_atu_programa' => $programSnapshot['dt_hr_ult_atu_programa'] ?? null,
            'c_revisao_programa' => $programSnapshot['c_revisao_programa'] ?? null,
            'c_fonte_versao' => $programSnapshot['c_fonte_versao'] ?? null,
            'c_caminho_fisico_programa' => $programSnapshot['c_caminho_fisico_programa'] ?? null,
            'dt_hr_ini_processamento' => date('c'),
            'dt_hr_atu' => date('c'),
        ]);

        return (int) $this->auditConnection->lastInsertId();
    }

    public function finalizeAttempt(int $attemptId, int $statusCode, ?string $responseBody, ?string $errorMessage, int $statusProcessamento): void
    {
        $this->auditConnection->update('t99002', [
            'si_status_http' => $statusCode,
            't_corpo_resposta' => $responseBody,
            't_erro' => $errorMessage,
            'si_status_processamento' => $statusProcessamento,
            'dt_hr_fim_processamento' => date('c'),
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99002' => $attemptId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRequestStatus(string $requestId, string $tokenHash): ?array
    {
        $row = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT
                u_c_request_id,
                c_metodo,
                c_caminho,
                c_nome_operacao,
                c_modo_execucao,
                c_cod_programa,
                c_nome_programa,
                c_versao_programa,
                dt_hr_ult_atu_programa,
                c_revisao_programa,
                c_fonte_versao,
                c_caminho_fisico_programa,
                si_status_processamento,
                si_status_http,
                t_corpo_resposta,
                t_erro,
                dt_hr_recebimento,
                dt_hr_ini_processamento,
                dt_hr_fim_processamento,
                i_tempo_processamento_ms
            FROM t99001
            WHERE u_c_request_id = :u_c_request_id
              AND c_token_hash = :c_token_hash
            LIMIT 1
            SQL,
            [
                'u_c_request_id' => $requestId,
                'c_token_hash' => $tokenHash,
            ]
        );

        return $row === false ? null : $row;
    }
}
