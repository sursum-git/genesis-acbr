<?php

namespace App\Repository;

use App\Support\ApiExtractionStatus;
use App\Support\ApiRequestStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ApiExtractionRepository
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];
    private bool $schemaEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNextPendingRequest(): ?array
    {
        $this->ensureSchema();

        if (!$this->tableExists('t99001')) {
            return null;
        }

        $this->auditConnection->beginTransaction();

        try {
            $row = $this->claimByStatus(ApiExtractionStatus::PENDENTE);

            if ($row === false) {
                $row = $this->claimStaleProcessingRow();
            }

            if ($row === false) {
                $this->auditConnection->commit();

                return null;
            }

            $this->auditConnection->update('t99001', [
                'si_status_extracao' => ApiExtractionStatus::PROCESSANDO,
                'dt_hr_ini_extracao' => date('c'),
                'dt_hr_fim_extracao' => null,
                't_erro_extracao' => null,
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

    /**
     * @return array<string, mixed>|false
     */
    private function claimByStatus(int $status): array|false
    {
        return $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT *
            FROM t99001
            WHERE si_status_processamento = :completed
              AND si_status_extracao = :status
            ORDER BY id_t99001 ASC
            FOR UPDATE SKIP LOCKED
            LIMIT 1
            SQL,
            [
                'completed' => ApiRequestStatus::CONCLUIDA,
                'status' => $status,
            ],
            [
                'completed' => ParameterType::INTEGER,
                'status' => ParameterType::INTEGER,
            ]
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function claimStaleProcessingRow(): array|false
    {
        return $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT *
            FROM t99001
            WHERE si_status_processamento = :completed
              AND si_status_extracao = :processing
              AND dt_hr_ini_extracao < now() - interval '15 minutes'
              AND dt_hr_fim_extracao IS NULL
            ORDER BY dt_hr_ini_extracao ASC, id_t99001 ASC
            FOR UPDATE SKIP LOCKED
            LIMIT 1
            SQL,
            [
                'completed' => ApiRequestStatus::CONCLUIDA,
                'processing' => ApiExtractionStatus::PROCESSANDO,
            ],
            [
                'completed' => ParameterType::INTEGER,
                'processing' => ParameterType::INTEGER,
            ]
        );
    }

    public function markCompleted(int $requestInternalId): void
    {
        $this->ensureSchema();

        $this->auditConnection->update('t99001', [
            'si_status_extracao' => ApiExtractionStatus::CONCLUIDO,
            'dt_hr_fim_extracao' => date('c'),
            't_erro_extracao' => null,
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99001' => $requestInternalId,
        ]);
    }

    public function markFailed(int $requestInternalId, string $error): void
    {
        $this->ensureSchema();

        $this->auditConnection->update('t99001', [
            'si_status_extracao' => ApiExtractionStatus::FALHA,
            'dt_hr_fim_extracao' => date('c'),
            't_erro_extracao' => $this->truncate($error, 4000),
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99001' => $requestInternalId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function storeNfeExtraction(array $payload): void
    {
        $this->ensureSchema();

        if (!$this->tableExists('t99007')) {
            return;
        }

        $this->auditConnection->insert('t99007', [
            't99001_id' => (int) ($payload['t99001_id'] ?? 0),
            'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
            'c_caminho_origem' => $this->nullableTrim($payload['c_caminho_origem'] ?? null),
            'c_tipo_documento' => $this->nullableTrim($payload['c_tipo_documento'] ?? null),
            'c_chave_acesso' => $this->nullableTrim($payload['c_chave_acesso'] ?? null),
            'c_nsu_relacionado' => $this->nullableTrim($payload['c_nsu_relacionado'] ?? null),
            'c_numero' => $this->nullableTrim($payload['c_numero'] ?? null),
            'c_serie' => $this->nullableTrim($payload['c_serie'] ?? null),
            'c_modelo' => $this->nullableTrim($payload['c_modelo'] ?? null),
            'c_emitente_documento' => $this->nullableTrim($payload['c_emitente_documento'] ?? null),
            'c_destinatario_documento' => $this->nullableTrim($payload['c_destinatario_documento'] ?? null),
            'c_interessado_documento' => $this->nullableTrim($payload['c_interessado_documento'] ?? null),
            'c_stat' => $this->nullableTrim($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'c_situacao' => $this->nullableTrim($payload['c_situacao'] ?? null),
            'dt_emissao' => $this->normalizeDateTime($payload['dt_emissao'] ?? null),
            'dt_autorizacao' => $this->normalizeDateTime($payload['dt_autorizacao'] ?? null),
            't_payload_bruto' => $payload['t_payload_bruto'] ?? null,
            'dt_hr_extracao' => date('c'),
            'dt_hr_atu' => date('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function storeNsuExtraction(array $payload): void
    {
        $this->ensureSchema();

        if (!$this->tableExists('t99008')) {
            return;
        }

        $this->auditConnection->insert('t99008', [
            't99001_id' => (int) ($payload['t99001_id'] ?? 0),
            'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
            'c_caminho_origem' => $this->nullableTrim($payload['c_caminho_origem'] ?? null),
            'c_tipo_item' => $this->nullableTrim($payload['c_tipo_item'] ?? null),
            'c_nsu_consultado' => $this->nullableTrim($payload['c_nsu_consultado'] ?? null),
            'c_nsu' => $this->nullableTrim($payload['c_nsu'] ?? null),
            'c_ult_nsu' => $this->nullableTrim($payload['c_ult_nsu'] ?? null),
            'c_max_nsu' => $this->nullableTrim($payload['c_max_nsu'] ?? null),
            'c_schema' => $this->nullableTrim($payload['c_schema'] ?? null),
            'c_chave_acesso' => $this->nullableTrim($payload['c_chave_acesso'] ?? null),
            'c_stat' => $this->nullableTrim($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'c_situacao' => $this->nullableTrim($payload['c_situacao'] ?? null),
            't_payload_bruto' => $payload['t_payload_bruto'] ?? null,
            'dt_hr_extracao' => date('c'),
            'dt_hr_atu' => date('c'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNfeByRequest(int $requestInternalId): array
    {
        $this->ensureSchema();

        if (!$this->tableExists('t99007')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM t99007 WHERE t99001_id = :id ORDER BY id_t99007 DESC',
            ['id' => $requestInternalId]
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNsuByRequest(int $requestInternalId): array
    {
        $this->ensureSchema();

        if (!$this->tableExists('t99008')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM t99008 WHERE t99001_id = :id ORDER BY id_t99008 DESC',
            ['id' => $requestInternalId]
        );

        return $rows;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured || !$this->tableExists('t99001')) {
            return;
        }

        $statements = [
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS si_status_extracao smallint NOT NULL DEFAULT 0",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS dt_hr_ini_extracao timestamp",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS dt_hr_fim_extracao timestamp",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS t_erro_extracao text",
            "CREATE INDEX IF NOT EXISTS t99001_si_status_extracao_idx ON t99001 (si_status_extracao, dt_hr_recebimento)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99007 (
                id_t99007 bigserial PRIMARY KEY,
                t99001_id bigint NOT NULL REFERENCES t99001 (id_t99001) ON DELETE CASCADE,
                u_c_request_id varchar(36) NOT NULL,
                c_caminho_origem varchar(255),
                c_tipo_documento varchar(60),
                c_chave_acesso varchar(44),
                c_nsu_relacionado varchar(20),
                c_numero varchar(20),
                c_serie varchar(10),
                c_modelo varchar(10),
                c_emitente_documento varchar(20),
                c_destinatario_documento varchar(20),
                c_interessado_documento varchar(20),
                c_stat varchar(10),
                x_motivo varchar(500),
                c_situacao varchar(120),
                dt_emissao timestamp,
                dt_autorizacao timestamp,
                t_payload_bruto text,
                dt_hr_extracao timestamp NOT NULL DEFAULT now(),
                dt_hr_atu timestamp NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99007_t99001_id_idx ON t99007 (t99001_id, dt_hr_extracao DESC)",
            "CREATE INDEX IF NOT EXISTS t99007_chave_idx ON t99007 (c_chave_acesso)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99008 (
                id_t99008 bigserial PRIMARY KEY,
                t99001_id bigint NOT NULL REFERENCES t99001 (id_t99001) ON DELETE CASCADE,
                u_c_request_id varchar(36) NOT NULL,
                c_caminho_origem varchar(255),
                c_tipo_item varchar(60),
                c_nsu_consultado varchar(20),
                c_nsu varchar(20),
                c_ult_nsu varchar(20),
                c_max_nsu varchar(20),
                c_schema varchar(120),
                c_chave_acesso varchar(44),
                c_stat varchar(10),
                x_motivo varchar(500),
                c_situacao varchar(120),
                t_payload_bruto text,
                dt_hr_extracao timestamp NOT NULL DEFAULT now(),
                dt_hr_atu timestamp NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99008_t99001_id_idx ON t99008 (t99001_id, dt_hr_extracao DESC)",
            "CREATE INDEX IF NOT EXISTS t99008_nsu_idx ON t99008 (c_nsu, c_ult_nsu, c_max_nsu)",
        ];

        foreach ($statements as $statement) {
            $this->auditConnection->executeStatement($statement);
        }

        $this->schemaEnsured = true;
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
        }

        return $this->tableExistsCache[$table];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $trimmed = $this->nullableTrim($value);
        if ($trimmed === null) {
            return null;
        }

        $timestamp = strtotime($trimmed);

        return $timestamp === false ? $trimmed : date('Y-m-d H:i:s', $timestamp);
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . "\n...[truncado]";
    }
}
