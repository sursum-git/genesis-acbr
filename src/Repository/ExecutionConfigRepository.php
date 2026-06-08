<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ExecutionConfigRepository
{
    private ?array $tableExistsCache = null;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConfigs(): array
    {
        if (!$this->tableExists('t99003')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM t99003 ORDER BY log_ativo DESC, c_nome_operacao ASC NULLS LAST, c_caminho ASC NULLS LAST, id_t99003 DESC'
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findConfig(int $id): ?array
    {
        if (!$this->tableExists('t99003')) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            'SELECT * FROM t99003 WHERE id_t99003 = :id LIMIT 1',
            ['id' => $id]
        );

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload, ?int $id = null): void
    {
        if (!$this->tableExists('t99003')) {
            throw new \RuntimeException('A tabela t99003 ainda não existe no banco de auditoria.');
        }

        $data = [
            'c_chave_configuracao' => trim((string) ($payload['c_chave_configuracao'] ?? '')),
            'c_caminho' => $this->nullableTrim($payload['c_caminho'] ?? null),
            'c_nome_operacao' => $this->nullableTrim($payload['c_nome_operacao'] ?? null),
            'c_modo_execucao' => strtolower(trim((string) ($payload['c_modo_execucao'] ?? 'sync'))),
            'log_ativo' => $this->normalizeBoolean($payload['log_ativo'] ?? false),
            'dt_hr_atu' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $this->auditConnection->insert('t99003', $data);

            return;
        }

        $this->auditConnection->update('t99003', $data, ['id_t99003' => $id]);
    }

    public function hasActiveConflict(array $payload, ?int $exceptId = null): bool
    {
        if (!$this->tableExists('t99003')) {
            return false;
        }

        $params = [
            'c_chave_configuracao' => trim((string) ($payload['c_chave_configuracao'] ?? '')),
            'c_caminho' => $this->nullableTrim($payload['c_caminho'] ?? null),
            'c_nome_operacao' => $this->nullableTrim($payload['c_nome_operacao'] ?? null),
        ];

        $sql = <<<'SQL'
        SELECT COUNT(*)
        FROM t99003
        WHERE log_ativo = TRUE
          AND c_chave_configuracao = CAST(:c_chave_configuracao AS varchar)
          AND c_caminho IS NOT DISTINCT FROM CAST(:c_caminho AS varchar)
          AND c_nome_operacao IS NOT DISTINCT FROM CAST(:c_nome_operacao AS varchar)
        SQL;

        if ($exceptId !== null) {
            $sql .= ' AND id_t99003 <> :except_id';
            $params['except_id'] = $exceptId;
        }

        return (int) $this->auditConnection->fetchOne($sql, $params) > 0;
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

    private function tableExists(string $table): bool
    {
        if ($this->tableExistsCache === null) {
            $this->tableExistsCache = [];
        }

        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
        }

        return $this->tableExistsCache[$table];
    }
}
