<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;

final class NfeOutputFiscalEventRepository
{
    private const EVENT_TABLE = 't99035';

    private bool $schemaEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @param array<string, mixed> $actionPayload
     * @param array<string, mixed> $responsePayload
     * @return array<string, mixed>
     */
    public function recordActionResult(int $noteId, string $action, string $requestId, array $actionPayload, array $responsePayload): array
    {
        $this->ensureSchema();

        $normalizedAction = match ($action) {
            'inutilizar' => 'inutilizacao',
            'carta_correcao' => 'carta_correcao',
            default => 'cancelamento',
        };
        $resolvedRequestId = $requestId !== '' ? $requestId : $this->findLatestAuditRequestId($action, $actionPayload);
        $cStat = $this->extractValue($responsePayload, ['c_stat_receita', 'cStat', 'CStat', 'resultado.cStat', 'resultado.CStat'])
            ?: $this->extractLineValue($responsePayload, ['cStat', 'CStat']);
        $motivo = $this->extractValue($responsePayload, ['mensagem', 'message', 'resultado.xMotivo', 'resultado.XMotivo', 'resultado.motivo'])
            ?: $this->extractLineValue($responsePayload, ['xMotivo', 'XMotivo'])
            ?: $this->extractLastReturnReason($responsePayload);
        $protocol = $this->extractValue($responsePayload, ['nProt', 'NProt', 'protocolo', 'resultado.nProt', 'resultado.NProt', 'resultado.protocolo'])
            ?: $this->extractLineValue($responsePayload, ['nProt', 'NProt', 'Protocolo']);
        $accessKey = $this->firstNonEmpty(
            $this->stringOrEmpty($actionPayload['AeChave'] ?? null),
            $this->stringOrEmpty($actionPayload['chave'] ?? null)
        );
        $eventType = match ($normalizedAction) {
            'cancelamento' => '110111',
            'carta_correcao' => '110110',
            default => 'INUTILIZACAO',
        };
        $success = match ($normalizedAction) {
            'cancelamento', 'carta_correcao' => in_array((int) $cStat, [101, 135, 136, 155], true),
            default => (int) $cStat === 102,
        };
        $situation = match (true) {
            $normalizedAction === 'cancelamento' && $success => 'Cancelada',
            $normalizedAction === 'inutilizacao' && $success => 'Inutilizada',
            $normalizedAction === 'carta_correcao' && $success => 'Carta de Correção registrada',
            $normalizedAction === 'cancelamento' => 'Erro no cancelamento',
            $normalizedAction === 'carta_correcao' => 'Erro na Carta de Correção',
            default => 'Erro na inutilização',
        };
        $now = date('c');
        $event = [
            't99019_id' => $noteId,
            'u_c_request_id' => $resolvedRequestId,
            'tipo_evento' => $eventType,
            'tipo_acao' => $normalizedAction,
            'situacao' => $situation,
            'ch_nfe' => $accessKey,
            'c_stat' => $cStat !== '' ? (int) $cStat : null,
            'x_motivo' => $motivo !== '' ? $motivo : null,
            'n_prot' => $protocol !== '' ? $protocol : null,
            'dh_evento' => $now,
            't_payload_json' => json_encode([
                'payload' => $actionPayload,
                'response' => $responsePayload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dt_hr_atu' => $now,
        ];

        $this->auditConnection->insert(self::EVENT_TABLE, $event);

        if ($success && in_array($normalizedAction, ['cancelamento', 'inutilizacao'], true)) {
            $this->auditConnection->update('t99019', [
                'situacao_fiscal' => $situation,
            ], [
                'id_t99019' => $noteId,
            ]);
        }

        return $this->normalizeEvent($event + ['id_t99035' => (int) $this->auditConnection->lastInsertId()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByNoteId(int $noteId): array
    {
        $this->ensureSchema();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT *
            FROM t99035
            WHERE t99019_id = :note_id
            ORDER BY dh_evento DESC, id_t99035 DESC
            SQL,
            ['note_id' => $noteId]
        );

        return array_map(fn (array $row): array => $this->normalizeEvent($row), $rows);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $event): array
    {
        return [
            'id' => isset($event['id_t99035']) ? (int) $event['id_t99035'] : (isset($event['id_t99034']) ? (int) $event['id_t99034'] : null),
            'request_id' => $this->stringOrEmpty($event['u_c_request_id'] ?? null),
            'tipo_evento' => $this->stringOrEmpty($event['tipo_evento'] ?? null),
            'tipo_acao' => $this->stringOrEmpty($event['tipo_acao'] ?? null),
            'situacao' => $this->stringOrEmpty($event['situacao'] ?? null),
            'chave_nfe' => $this->stringOrEmpty($event['ch_nfe'] ?? null),
            'c_stat' => $this->stringOrEmpty($event['c_stat'] ?? null),
            'motivo' => $this->stringOrEmpty($event['x_motivo'] ?? null),
            'protocolo' => $this->stringOrEmpty($event['n_prot'] ?? null),
            'data' => $this->stringOrEmpty($event['dh_evento'] ?? null),
        ];
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        if (!$this->tableHasColumn('t99019', 'situacao_fiscal')) {
            $this->auditConnection->executeStatement('ALTER TABLE t99019 ADD COLUMN situacao_fiscal varchar(40)');
        }

        if (!$this->auditConnection->createSchemaManager()->tablesExist([self::EVENT_TABLE])) {
            $platform = $this->auditConnection->getDatabasePlatform();
            $idColumn = $platform instanceof SqlitePlatform ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'bigserial PRIMARY KEY';

            $this->auditConnection->executeStatement(<<<SQL
                CREATE TABLE t99035 (
                    id_t99035 {$idColumn},
                    t99019_id integer NOT NULL,
                    u_c_request_id varchar(36),
                    tipo_evento varchar(30),
                    tipo_acao varchar(30),
                    situacao varchar(60),
                    ch_nfe varchar(44),
                    c_stat integer,
                    x_motivo varchar(255),
                    n_prot varchar(20),
                    dh_evento varchar(40),
                    t_payload_json text,
                    dt_hr_atu varchar(40)
                )
            SQL);
        }

        $this->schemaEnsured = true;
    }

    /**
     * @param array<string, mixed> $actionPayload
     */
    private function findLatestAuditRequestId(string $action, array $actionPayload): string
    {
        if (!$this->auditConnection->createSchemaManager()->tablesExist(['t99001'])) {
            return '';
        }

        $path = match ($action) {
            'inutilizar' => '/nfe/inutilizacao/inutilizar',
            'carta_correcao' => '/nfe/eventos/enviar-evento',
            default => '/nfe/eventos/cancelar',
        };
        $needle = $this->firstNonEmpty(
            $this->stringOrEmpty($actionPayload['AeChave'] ?? null),
            $this->stringOrEmpty($actionPayload['ANumeroInicial'] ?? null),
            $this->stringOrEmpty($actionPayload['ACNPJ'] ?? null),
            $this->stringOrEmpty($actionPayload['AeCNPJCPF'] ?? null)
        );

        if ($needle === '') {
            return '';
        }

        $requestId = $this->auditConnection->fetchOne(
            <<<'SQL'
            SELECT u_c_request_id
            FROM t99001
            WHERE c_caminho = :path
              AND COALESCE(t_corpo_requisicao, '') LIKE :needle
            ORDER BY dt_hr_recebimento DESC, id_t99001 DESC
            LIMIT 1
            SQL,
            [
                'path' => $path,
                'needle' => '%' . $needle . '%',
            ]
        );

        return $this->stringOrEmpty($requestId);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->auditConnection->createSchemaManager()->tablesExist([$table])) {
            return false;
        }

        return $this->auditConnection->createSchemaManager()->introspectTable($table)->hasColumn($column);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function extractValue(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $parts = explode('.', $path);
            $value = $payload;

            foreach ($parts as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$part];
            }

            $normalized = $this->stringOrEmpty($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function extractLineValue(array $payload, array $keys): string
    {
        foreach ($this->textCandidates($payload) as $text) {
            foreach ($keys as $key) {
                if (preg_match('/(?:^|\n)\s*' . preg_quote($key, '/') . '\s*=\s*([^\r\n<]+)/i', $text, $matches) === 1) {
                    return trim((string) $matches[1]);
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractLastReturnReason(array $payload): string
    {
        foreach ($this->textCandidates($payload) as $text) {
            if (preg_match('/Último retorno:\s*(.+)$/iu', $text, $matches) === 1) {
                return trim((string) $matches[1]);
            }

            if (preg_match('/Ultimo retorno:\s*(.+)$/iu', $text, $matches) === 1) {
                return trim((string) $matches[1]);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function textCandidates(array $payload): array
    {
        $texts = [];
        $collector = function (mixed $value) use (&$collector, &$texts): void {
            if (is_string($value) && trim($value) !== '') {
                $texts[] = $value;
                return;
            }

            if (!is_array($value)) {
                return;
            }

            foreach ($value as $item) {
                $collector($item);
            }
        };

        $collector($payload);

        return $texts;
    }

    private function stringOrEmpty(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
