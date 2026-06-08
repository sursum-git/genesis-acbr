<?php

namespace App\Repository;

use App\Support\ApiRequestStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class WorkerMonitorRepository
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueueMetrics(): array
    {
        if (!$this->tableExists('t99001')) {
            return [
                'queued' => 0,
                'processing' => 0,
                'failed_last_hour' => 0,
                'completed_last_hour' => 0,
                'oldest_queued_at' => null,
                'oldest_processing_at' => null,
                'avg_async_ms_last_day' => 0,
            ];
        }

        $row = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT
                SUM(CASE WHEN si_status_processamento = :queued THEN 1 ELSE 0 END)::int AS queued,
                SUM(CASE WHEN si_status_processamento = :processing THEN 1 ELSE 0 END)::int AS processing,
                SUM(CASE WHEN si_status_processamento = :failed AND dt_hr_atu >= now() - interval '1 hour' THEN 1 ELSE 0 END)::int AS failed_last_hour,
                SUM(CASE WHEN si_status_processamento = :completed AND dt_hr_atu >= now() - interval '1 hour' THEN 1 ELSE 0 END)::int AS completed_last_hour,
                MIN(CASE WHEN si_status_processamento = :queued THEN dt_hr_recebimento END) AS oldest_queued_at,
                MIN(CASE WHEN si_status_processamento = :processing THEN dt_hr_ini_processamento END) AS oldest_processing_at,
                COALESCE(ROUND(AVG(CASE WHEN c_modo_execucao = 'async' AND dt_hr_recebimento >= now() - interval '1 day' THEN NULLIF(i_tempo_processamento_ms, 0) END)), 0)::int AS avg_async_ms_last_day
            FROM t99001
            SQL,
            [
                'queued' => ApiRequestStatus::ENFILEIRADA,
                'processing' => ApiRequestStatus::PROCESSANDO,
                'failed' => ApiRequestStatus::FALHA,
                'completed' => ApiRequestStatus::CONCLUIDA,
            ]
        ) ?: [];

        return [
            'queued' => (int) ($row['queued'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'failed_last_hour' => (int) ($row['failed_last_hour'] ?? 0),
            'completed_last_hour' => (int) ($row['completed_last_hour'] ?? 0),
            'oldest_queued_at' => $row['oldest_queued_at'] ?? null,
            'oldest_processing_at' => $row['oldest_processing_at'] ?? null,
            'avg_async_ms_last_day' => (int) ($row['avg_async_ms_last_day'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findQueueHighlights(int $limit = 12): array
    {
        if (!$this->tableExists('t99001')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                id_t99001,
                u_c_request_id,
                c_caminho,
                c_nome_operacao,
                c_modo_execucao,
                si_status_processamento,
                dt_hr_recebimento,
                dt_hr_ini_processamento,
                i_tempo_processamento_ms
            FROM t99001
            WHERE si_status_processamento IN (:queued, :processing, :failed)
            ORDER BY
                CASE WHEN si_status_processamento = :processing THEN 1
                     WHEN si_status_processamento = :queued THEN 2
                     ELSE 3
                END,
                COALESCE(dt_hr_ini_processamento, dt_hr_recebimento) ASC
            LIMIT :limit
            SQL,
            [
                'queued' => ApiRequestStatus::ENFILEIRADA,
                'processing' => ApiRequestStatus::PROCESSANDO,
                'failed' => ApiRequestStatus::FALHA,
                'limit' => max(1, $limit),
            ],
            [
                'queued' => ParameterType::INTEGER,
                'processing' => ParameterType::INTEGER,
                'failed' => ParameterType::INTEGER,
                'limit' => ParameterType::INTEGER,
            ]
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookMetrics(): array
    {
        if (!$this->tableExists('t99006')) {
            return [
                'pending' => 0,
                'processing' => 0,
                'failed' => 0,
                'failed_final' => 0,
                'success_last_day' => 0,
            ];
        }

        $row = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT
                SUM(CASE WHEN c_status_entrega = 'pending' THEN 1 ELSE 0 END)::int AS pending,
                SUM(CASE WHEN c_status_entrega = 'processing' THEN 1 ELSE 0 END)::int AS processing,
                SUM(CASE WHEN c_status_entrega = 'failed' THEN 1 ELSE 0 END)::int AS failed,
                SUM(CASE WHEN c_status_entrega = 'failed_final' THEN 1 ELSE 0 END)::int AS failed_final,
                SUM(CASE WHEN c_status_entrega = 'success' AND dt_hr_atu >= now() - interval '1 day' THEN 1 ELSE 0 END)::int AS success_last_day
            FROM t99006
            SQL
        ) ?: [];

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'failed_final' => (int) ($row['failed_final'] ?? 0),
            'success_last_day' => (int) ($row['success_last_day'] ?? 0),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWorkerExecutionSummary(): array
    {
        return [
            'api' => $this->getApiWorkerExecutionSummary(),
            'webhook' => $this->getWebhookWorkerExecutionSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getApiWorkerExecutionSummary(): array
    {
        if (!$this->tableExists('t99002')) {
            return [
                'last_started_at' => null,
                'last_finished_at' => null,
                'last_activity_at' => null,
                'last_worker_id' => null,
                'last_worker_pid' => null,
                'last_status' => null,
                'last_http_status' => null,
                'active_attempts' => 0,
            ];
        }

        $last = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT
                c_worker_id,
                i_worker_pid,
                si_status_processamento,
                si_status_http,
                dt_hr_ini_processamento,
                dt_hr_fim_processamento,
                COALESCE(dt_hr_fim_processamento, dt_hr_ini_processamento, dt_hr_atu) AS last_activity_at
            FROM t99002
            ORDER BY COALESCE(dt_hr_fim_processamento, dt_hr_ini_processamento, dt_hr_atu) DESC, id_t99002 DESC
            LIMIT 1
            SQL
        ) ?: [];

        $activeAttempts = (int) $this->auditConnection->fetchOne(
            'SELECT COUNT(*) FROM t99002 WHERE dt_hr_fim_processamento IS NULL AND si_status_processamento = :processing',
            ['processing' => ApiRequestStatus::PROCESSANDO],
            ['processing' => ParameterType::INTEGER]
        );

        return [
            'last_started_at' => $last['dt_hr_ini_processamento'] ?? null,
            'last_finished_at' => $last['dt_hr_fim_processamento'] ?? null,
            'last_activity_at' => $last['last_activity_at'] ?? null,
            'last_worker_id' => $last['c_worker_id'] ?? null,
            'last_worker_pid' => $last['i_worker_pid'] ?? null,
            'last_status' => isset($last['si_status_processamento']) ? (int) $last['si_status_processamento'] : null,
            'last_http_status' => isset($last['si_status_http']) ? (int) $last['si_status_http'] : null,
            'active_attempts' => $activeAttempts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getWebhookWorkerExecutionSummary(): array
    {
        if (!$this->tableExists('t99006')) {
            return [
                'last_started_at' => null,
                'last_finished_at' => null,
                'last_activity_at' => null,
                'last_status' => null,
                'last_http_status' => null,
                'active_attempts' => 0,
            ];
        }

        $last = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT
                c_status_entrega,
                si_status_http,
                dt_hr_ini_processamento,
                dt_hr_fim_processamento,
                COALESCE(dt_hr_fim_processamento, dt_hr_ini_processamento, dt_hr_atu) AS last_activity_at
            FROM t99006
            ORDER BY COALESCE(dt_hr_fim_processamento, dt_hr_ini_processamento, dt_hr_atu) DESC, id_t99006 DESC
            LIMIT 1
            SQL
        ) ?: [];

        $activeAttempts = (int) $this->auditConnection->fetchOne(
            "SELECT COUNT(*) FROM t99006 WHERE c_status_entrega = 'processing'"
        );

        return [
            'last_started_at' => $last['dt_hr_ini_processamento'] ?? null,
            'last_finished_at' => $last['dt_hr_fim_processamento'] ?? null,
            'last_activity_at' => $last['last_activity_at'] ?? null,
            'last_status' => $last['c_status_entrega'] ?? null,
            'last_http_status' => isset($last['si_status_http']) ? (int) $last['si_status_http'] : null,
            'active_attempts' => $activeAttempts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findFailedDeliveries(int $limit = 12): array
    {
        if (!$this->tableExists('t99006') || !$this->tableExists('t00004') || !$this->tableExists('t00003')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                d.id_t99006,
                d.t99001_id,
                d.c_status_entrega,
                d.si_num_tentativa,
                d.si_status_http,
                d.t_erro,
                d.dt_hr_proxima_tentativa,
                d.dt_hr_atu,
                b.c_assinante_identificador,
                b.c_programa,
                b.c_evento,
                w.c_nome AS c_webhook_nome
            FROM t99006 d
            INNER JOIN t00004 b ON b.id_t00004 = d.t00004_id
            INNER JOIN t00003 w ON w.id_t00003 = b.t00003_id
            WHERE d.c_status_entrega IN ('failed', 'failed_final')
            ORDER BY d.dt_hr_atu DESC, d.id_t99006 DESC
            LIMIT :limit
            SQL,
            ['limit' => max(1, $limit)],
            ['limit' => ParameterType::INTEGER]
        );

        return $rows;
    }

    public function requeueFailedDelivery(int $deliveryId): void
    {
        if (!$this->tableExists('t99006')) {
            return;
        }

        $this->auditConnection->update('t99006', [
            'c_status_entrega' => 'pending',
            'dt_hr_proxima_tentativa' => date('c'),
            'dt_hr_ini_processamento' => null,
            'dt_hr_fim_processamento' => null,
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99006' => $deliveryId,
        ]);
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
        }

        return $this->tableExistsCache[$table];
    }
}
