<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;

final class NfeInputFiscalEventRepository
{
    private const EVENT_TABLE = 't99036';

    private bool $schemaEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @param array<string, mixed> $actionPayload
     * @param array<string, mixed> $responsePayload
     * @return array<string, mixed>
     */
    public function recordManifestationResult(int $documentId, string $requestId, array $actionPayload, array $responsePayload): array
    {
        $this->ensureSchema();

        $eventType = $this->manifestationType((string) ($actionPayload['tpEvento'] ?? ''));
        $cStat = $this->extractLastLineValue($responsePayload, ['cStat', 'CStat'])
            ?: $this->extractValue($responsePayload, ['c_stat_receita', 'cStat', 'CStat', 'resultado.cStat', 'resultado.CStat']);
        $motivo = $this->extractLastLineValue($responsePayload, ['xMotivo', 'XMotivo'])
            ?: $this->extractValue($responsePayload, ['resultado.xMotivo', 'resultado.XMotivo', 'resultado.motivo', 'mensagem', 'message']);
        $protocol = $this->extractLastLineValue($responsePayload, ['nProt', 'NProt', 'Protocolo'])
            ?: $this->extractValue($responsePayload, ['nProt', 'NProt', 'protocolo', 'resultado.nProt', 'resultado.NProt']);
        $success = in_array((int) $cStat, [135, 136, 155], true);
        $now = date('c');

        $event = [
            't99008_id' => $documentId,
            'u_c_request_id' => $this->limitString($requestId, 36),
            'tipo_evento' => $eventType,
            'tipo_acao' => $this->displayEventType($eventType),
            'situacao' => $success ? 'Registrado' : 'Não registrado',
            'ch_nfe' => $this->limitString((string) ($actionPayload['chave'] ?? ''), 44),
            'documento_destinatario' => $this->limitString((string) ($actionPayload['documento_destinatario'] ?? ''), 20),
            'c_stat' => $cStat !== '' ? (int) $cStat : null,
            'x_motivo' => $motivo !== '' ? $this->limitString($motivo, 255) : null,
            'n_prot' => $protocol !== '' ? $this->limitString($protocol, 20) : null,
            'dh_evento' => $now,
            't_payload_json' => json_encode([
                'payload' => $actionPayload,
                'response' => $responsePayload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dt_hr_atu' => $now,
        ];

        $this->auditConnection->insert(self::EVENT_TABLE, $event);

        return $this->normalizeEvent($event + ['id_t99036' => (int) $this->auditConnection->lastInsertId()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAccessKey(string $accessKey): array
    {
        if (trim($accessKey) === '') {
            return [];
        }

        $this->ensureSchema();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM ' . self::EVENT_TABLE . ' WHERE ch_nfe = :access_key ORDER BY dh_evento DESC, id_t99036 DESC',
            ['access_key' => $accessKey]
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
            'id' => isset($event['id_t99036']) ? (int) $event['id_t99036'] : null,
            'request_id' => trim((string) ($event['u_c_request_id'] ?? '')),
            'tipo_evento' => trim((string) ($event['tipo_evento'] ?? '')),
            'tipo_acao' => trim((string) ($event['tipo_acao'] ?? '')),
            'situacao' => trim((string) ($event['situacao'] ?? '')),
            'chave_nfe' => trim((string) ($event['ch_nfe'] ?? '')),
            'c_stat' => trim((string) ($event['c_stat'] ?? '')),
            'motivo' => trim((string) ($event['x_motivo'] ?? '')),
            'protocolo' => trim((string) ($event['n_prot'] ?? '')),
            'data' => trim((string) ($event['dh_evento'] ?? '')),
        ];
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        if (!$this->auditConnection->createSchemaManager()->tablesExist([self::EVENT_TABLE])) {
            $platform = $this->auditConnection->getDatabasePlatform();
            $idColumn = $platform instanceof SqlitePlatform ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'bigserial PRIMARY KEY';

            $this->auditConnection->executeStatement(<<<SQL
                CREATE TABLE t99036 (
                    id_t99036 {$idColumn},
                    t99008_id integer NOT NULL,
                    u_c_request_id varchar(36),
                    tipo_evento varchar(30),
                    tipo_acao varchar(80),
                    situacao varchar(60),
                    ch_nfe varchar(44),
                    documento_destinatario varchar(20),
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

    private function manifestationType(string $value): string
    {
        return in_array($value, ['210200', '210210', '210220', '210240'], true) ? $value : '210210';
    }

    private function displayEventType(string $eventType): string
    {
        return match ($eventType) {
            '210200' => 'Confirmação da Operação',
            '210210' => 'Ciência da Operação',
            '210220' => 'Desconhecimento da Operação',
            '210240' => 'Operação não Realizada',
            default => 'Manifestação do Destinatário',
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function extractValue(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $current = $payload;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    continue 2;
                }

                $current = $current[$segment];
            }

            if (is_scalar($current) && trim((string) $current) !== '') {
                return trim((string) $current);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function extractLastLineValue(array $payload, array $keys): string
    {
        $lastValue = '';
        foreach ($this->textCandidates($payload) as $text) {
            foreach ($keys as $key) {
                if (preg_match_all('/(?:^|\n)\s*' . preg_quote($key, '/') . '\s*=\s*([^\r\n<]*)/i', $text, $matches) === false) {
                    continue;
                }

                foreach ($matches[1] ?? [] as $value) {
                    $normalized = trim((string) $value);
                    if ($normalized !== '') {
                        $lastValue = $normalized;
                    }
                }
            }
        }

        return $lastValue;
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

    private function limitString(string $value, int $limit): string
    {
        if (function_exists('mb_strcut')) {
            return mb_strcut($value, 0, $limit, 'UTF-8');
        }

        return substr($value, 0, $limit);
    }
}
