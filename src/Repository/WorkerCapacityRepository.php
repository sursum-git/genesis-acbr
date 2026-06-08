<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class WorkerCapacityRepository
{
    private ?bool $tableExists = null;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConfigs(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM t99005 ORDER BY log_ativo DESC, dt_inicio_vigencia DESC, id_t99005 DESC'
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findConfig(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            'SELECT * FROM t99005 WHERE id_t99005 = :id LIMIT 1',
            ['id' => $id]
        );

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCurrent(): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT *
            FROM t99005
            WHERE log_ativo = TRUE
              AND dt_inicio_vigencia <= now()
              AND (dt_fim_vigencia IS NULL OR dt_fim_vigencia >= now())
            ORDER BY dt_inicio_vigencia DESC, id_t99005 DESC
            LIMIT 1
            SQL
        );

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload, ?int $id = null): void
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('A tabela t99005 ainda não existe no banco de auditoria.');
        }

        $data = [
            'qtd_workers' => max(1, (int) ($payload['qtd_workers'] ?? 1)),
            'dt_inicio_vigencia' => $this->normalizeDateTime((string) ($payload['dt_inicio_vigencia'] ?? '')),
            'dt_fim_vigencia' => $this->normalizeNullableDateTime($payload['dt_fim_vigencia'] ?? null),
            'log_ativo' => $this->normalizeBoolean($payload['log_ativo'] ?? false),
            't_observacao' => $this->nullableTrim($payload['t_observacao'] ?? null),
            'dt_hr_atu' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $this->auditConnection->insert('t99005', $data);

            return;
        }

        $this->auditConnection->update('t99005', $data, ['id_t99005' => $id]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function hasOverlap(array $payload, ?int $exceptId = null): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        if (!(bool) ($payload['log_ativo'] ?? false)) {
            return false;
        }

        $params = [
            'inicio' => $this->normalizeDateTime((string) ($payload['dt_inicio_vigencia'] ?? '')),
            'fim' => $this->normalizeNullableDateTime($payload['dt_fim_vigencia'] ?? null),
        ];

        $sql = <<<'SQL'
        SELECT COUNT(*)
        FROM t99005
        WHERE log_ativo = TRUE
          AND CAST(:inicio AS timestamp) <= COALESCE(dt_fim_vigencia, TIMESTAMP '9999-12-31 23:59:59')
          AND COALESCE(CAST(:fim AS timestamp), TIMESTAMP '9999-12-31 23:59:59') >= dt_inicio_vigencia
        SQL;

        if ($exceptId !== null) {
            $sql .= ' AND id_t99005 <> :except_id';
            $params['except_id'] = $exceptId;
        }

        return (int) $this->auditConnection->fetchOne($sql, $params) > 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function closePreviousActiveRanges(array $payload, ?int $exceptId = null): int
    {
        if (!$this->tableExists() || !(bool) ($payload['log_ativo'] ?? false)) {
            return 0;
        }

        $params = [
            'inicio' => $this->normalizeDateTime((string) ($payload['dt_inicio_vigencia'] ?? '')),
        ];

        $sql = <<<'SQL'
        UPDATE t99005
        SET dt_fim_vigencia = CAST(:inicio AS timestamp) - INTERVAL '1 second',
            dt_hr_atu = now()
        WHERE log_ativo = TRUE
          AND dt_inicio_vigencia < CAST(:inicio AS timestamp)
          AND (dt_fim_vigencia IS NULL OR dt_fim_vigencia >= CAST(:inicio AS timestamp))
        SQL;

        if ($exceptId !== null) {
            $sql .= ' AND id_t99005 <> :except_id';
            $params['except_id'] = $exceptId;
        }

        return $this->auditConnection->executeStatement($sql, $params);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 't', 'on', 'sim', 'yes'], true) ? '1' : '0';
    }

    private function normalizeNullableDateTime(mixed $value): ?string
    {
        $trimmed = $this->nullableTrim($value);

        return $trimmed === null ? null : $this->normalizeDateTime($trimmed);
    }

    private function normalizeDateTime(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return $trimmed;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function tableExists(): bool
    {
        if ($this->tableExists === null) {
            $this->tableExists = $this->auditConnection->createSchemaManager()->tablesExist(['t99005']);
        }

        return $this->tableExists;
    }
}
