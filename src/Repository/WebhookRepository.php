<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class WebhookRepository
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];
    private bool $webhookColumnsEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(): array
    {
        if (!$this->tableExists('t00003')) {
            return [];
        }
        $this->ensureWebhookConfigColumns();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM t00003 ORDER BY log_ativo DESC, c_nome ASC, id_t00003 DESC'
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBindings(): array
    {
        if (!$this->tableExists('t00004') || !$this->tableExists('t00003')) {
            return [];
        }
        $this->ensureWebhookConfigColumns();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                b.*,
                w.c_nome AS c_webhook_nome,
                w.c_url AS c_webhook_url
            FROM t00004 b
            INNER JOIN t00003 w ON w.id_t00003 = b.t00003_id
            ORDER BY b.log_ativo DESC, b.c_assinante_identificador ASC, b.c_programa ASC, b.id_t00004 DESC
            SQL
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentDeliveries(int $limit = 40): array
    {
        if (!$this->tableExists('t99006') || !$this->tableExists('t00004') || !$this->tableExists('t00003')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                d.*,
                b.c_assinante_identificador,
                b.c_programa,
                b.c_evento,
                w.c_nome AS c_webhook_nome
            FROM t99006 d
            INNER JOIN t00004 b ON b.id_t00004 = d.t00004_id
            INNER JOIN t00003 w ON w.id_t00003 = b.t00003_id
            ORDER BY d.id_t99006 DESC
            LIMIT :limit
            SQL,
            ['limit' => max(1, $limit)],
            ['limit' => ParameterType::INTEGER]
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWebhook(int $id): ?array
    {
        if (!$this->tableExists('t00003')) {
            return null;
        }
        $this->ensureWebhookConfigColumns();

        $row = $this->auditConnection->fetchAssociative(
            'SELECT * FROM t00003 WHERE id_t00003 = :id LIMIT 1',
            ['id' => $id]
        );

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBinding(int $id): ?array
    {
        if (!$this->tableExists('t00004')) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            'SELECT * FROM t00004 WHERE id_t00004 = :id LIMIT 1',
            ['id' => $id]
        );

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveWebhook(array $payload, ?int $id = null): void
    {
        if (!$this->tableExists('t00003')) {
            throw new \RuntimeException('A tabela t00003 ainda não existe no banco de auditoria.');
        }
        $this->ensureWebhookConfigColumns();

        $data = [
            'c_nome' => trim((string) ($payload['c_nome'] ?? '')),
            'c_url' => $this->validateWebhookUrl(trim((string) ($payload['c_url'] ?? ''))),
            'c_metodo_http' => strtoupper(trim((string) ($payload['c_metodo_http'] ?? 'POST'))),
            't_headers_json' => $this->normalizeJsonHeaders($payload['t_headers_json'] ?? null),
            'si_timeout_segundos' => max(1, min(120, (int) ($payload['si_timeout_segundos'] ?? 10))),
            't_variaveis_json' => $this->normalizeVariablesJson($payload['t_variaveis_json'] ?? null),
            'c_success_mode' => $this->normalizeSuccessMode($payload['c_success_mode'] ?? 'status_only'),
            'c_success_status_codes' => $this->normalizeStatusCodes((string) ($payload['c_success_status_codes'] ?? '200,201,202,204')),
            't_success_payload_rules_json' => $this->normalizePayloadRulesJson($payload['t_success_payload_rules_json'] ?? null),
            'si_max_tentativas' => max(1, min(20, (int) ($payload['si_max_tentativas'] ?? 3))),
            'si_intervalo_tentativas_segundos' => max(1, min(86400, (int) ($payload['si_intervalo_tentativas_segundos'] ?? 300))),
            'log_ativo' => $this->normalizeBoolean($payload['log_ativo'] ?? false),
            'dt_hr_atu' => date('c'),
        ];

        if ($id === null) {
            $data['t_secret'] = $this->nullableTrim($payload['t_secret'] ?? null) ?? bin2hex(random_bytes(24));
            $this->auditConnection->insert('t00003', $data);

            return;
        }

        $secret = $this->nullableTrim($payload['t_secret'] ?? null);
        if ($secret !== null) {
            $data['t_secret'] = $secret;
        }

        $this->auditConnection->update('t00003', $data, ['id_t00003' => $id]);
    }

    public function regenerateWebhookSecret(int $id): string
    {
        if (!$this->tableExists('t00003')) {
            throw new \RuntimeException('A tabela t00003 ainda não existe no banco de auditoria.');
        }

        $secret = bin2hex(random_bytes(24));
        $this->auditConnection->update('t00003', [
            't_secret' => $secret,
            'dt_hr_atu' => date('c'),
        ], [
            'id_t00003' => $id,
        ]);

        return $secret;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveBinding(array $payload, ?int $id = null): void
    {
        if (!$this->tableExists('t00004')) {
            throw new \RuntimeException('A tabela t00004 ainda não existe no banco de auditoria.');
        }

        $data = [
            'c_assinante_identificador' => trim((string) ($payload['c_assinante_identificador'] ?? '')),
            't00003_id' => (int) ($payload['t00003_id'] ?? 0),
            'c_programa' => $this->normalizeProgram($payload['c_programa'] ?? '*'),
            'c_evento' => trim((string) ($payload['c_evento'] ?? 'request.completed')),
            'c_caminho' => $this->nullableTrim($payload['c_caminho'] ?? null),
            'c_modo_execucao' => strtolower(trim((string) ($payload['c_modo_execucao'] ?? 'sync'))),
            'log_ativo' => $this->normalizeBoolean($payload['log_ativo'] ?? false),
            'dt_hr_atu' => date('c'),
        ];

        if ($id === null) {
            $this->auditConnection->insert('t00004', $data);

            return;
        }

        $this->auditConnection->update('t00004', $data, ['id_t00004' => $id]);
    }

    public function findExecutionModeForSubscriber(string $subscriberIdentifier, string $path, ?string $program): ?string
    {
        if (!$this->tableExists('t00004') || !$this->tableExists('t00003')) {
            return null;
        }
        $this->ensureWebhookConfigColumns();

        $row = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT b.c_modo_execucao
            FROM t00004 b
            INNER JOIN t00003 w ON w.id_t00003 = b.t00003_id
            WHERE b.log_ativo = TRUE
              AND w.log_ativo = TRUE
              AND b.c_assinante_identificador = :subscriber
              AND (b.c_programa = '*' OR b.c_programa = :program)
              AND (b.c_caminho IS NULL OR b.c_caminho = :path)
            ORDER BY
                CASE WHEN b.c_caminho = :path THEN 1 ELSE 2 END,
                b.id_t00004 DESC
            LIMIT 1
            SQL,
            [
                'subscriber' => $subscriberIdentifier,
                'program' => $program ?? '*',
                'path' => $path,
            ]
        );

        if ($row === false) {
            return null;
        }

        $mode = strtolower(trim((string) ($row['c_modo_execucao'] ?? '')));

        return in_array($mode, ['sync', 'async'], true) ? $mode : null;
    }

    /**
     * @param array<string, mixed> $requestRow
     * @return list<array<string, mixed>>
     */
    public function findEligibleBindingsForRequest(array $requestRow, string $event): array
    {
        if (!$this->tableExists('t00004') || !$this->tableExists('t00003')) {
            return [];
        }
        $this->ensureWebhookConfigColumns();

        $subscriberIdentifier = trim((string) ($requestRow['c_assinante_identificador'] ?? ''));
        if ($subscriberIdentifier === '') {
            return [];
        }

        $program = trim((string) ($requestRow['c_cod_programa'] ?? ''));
        $path = trim((string) ($requestRow['c_caminho'] ?? ''));

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                b.*,
                w.c_nome AS c_webhook_nome,
                w.c_url,
                w.c_metodo_http,
                w.t_headers_json,
                w.t_secret,
                w.si_timeout_segundos,
                w.t_variaveis_json,
                w.c_success_mode,
                w.c_success_status_codes,
                w.t_success_payload_rules_json,
                w.si_max_tentativas,
                w.si_intervalo_tentativas_segundos
            FROM t00004 b
            INNER JOIN t00003 w ON w.id_t00003 = b.t00003_id
            WHERE b.log_ativo = TRUE
              AND w.log_ativo = TRUE
              AND b.c_assinante_identificador = :subscriber
              AND b.c_evento = :event
              AND (b.c_programa = '*' OR b.c_programa = :program)
              AND (b.c_caminho IS NULL OR b.c_caminho = :path)
            ORDER BY
                CASE WHEN b.c_caminho = :path THEN 1 ELSE 2 END,
                b.id_t00004 DESC
            SQL,
            [
                'subscriber' => $subscriberIdentifier,
                'event' => $event,
                'program' => $program !== '' ? $program : '*',
                'path' => $path,
            ]
        );

        return $rows;
    }

    public function createDelivery(int $bindingId, int $requestInternalId, string $payloadJson): void
    {
        if (!$this->tableExists('t99006')) {
            return;
        }

        $this->auditConnection->executeStatement(
            <<<'SQL'
            INSERT INTO t99006 (t00004_id, t99001_id, c_status_entrega, si_num_tentativa, t_payload_json, dt_hr_proxima_tentativa, dt_hr_atu)
            VALUES (:binding_id, :request_id, 'pending', 0, :payload_json, now(), now())
            ON CONFLICT (t00004_id, t99001_id) DO NOTHING
            SQL,
            [
                'binding_id' => $bindingId,
                'request_id' => $requestInternalId,
                'payload_json' => $payloadJson,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNextPendingDelivery(): ?array
    {
        if (!$this->tableExists('t99006') || !$this->tableExists('t00004') || !$this->tableExists('t00003')) {
            return null;
        }
        $this->ensureWebhookConfigColumns();

        $this->auditConnection->beginTransaction();

        try {
            $row = $this->auditConnection->fetchAssociative(
                <<<'SQL'
                SELECT
                    d.*,
                    b.c_assinante_identificador,
                    b.c_programa,
                    b.c_evento,
                    w.c_nome AS c_webhook_nome,
                    w.c_url,
                    w.c_metodo_http,
                    w.t_headers_json,
                    w.t_secret,
                    w.si_timeout_segundos,
                    w.t_variaveis_json,
                    w.c_success_mode,
                    w.c_success_status_codes,
                    w.t_success_payload_rules_json,
                    w.si_max_tentativas,
                    w.si_intervalo_tentativas_segundos
                FROM t99006 d
                INNER JOIN t00004 b ON b.id_t00004 = d.t00004_id
                INNER JOIN t00003 w ON w.id_t00003 = b.t00003_id
                WHERE d.c_status_entrega IN ('pending', 'failed')
                  AND COALESCE(d.dt_hr_proxima_tentativa, now()) <= now()
                  AND b.log_ativo = TRUE
                  AND w.log_ativo = TRUE
                ORDER BY d.id_t99006 ASC
                FOR UPDATE SKIP LOCKED
                LIMIT 1
                SQL
            );

            if ($row === false) {
                $this->auditConnection->commit();

                return null;
            }

            $attemptNumber = (int) ($row['si_num_tentativa'] ?? 0) + 1;
            $this->auditConnection->update('t99006', [
                'c_status_entrega' => 'processing',
                'si_num_tentativa' => $attemptNumber,
                'dt_hr_ini_processamento' => date('c'),
                'dt_hr_atu' => date('c'),
            ], [
                'id_t99006' => $row['id_t99006'],
            ]);

            $row['si_num_tentativa'] = $attemptNumber;
            $this->auditConnection->commit();

            return $row;
        } catch (\Throwable $throwable) {
            $this->auditConnection->rollBack();
            throw $throwable;
        }
    }

    public function markDeliverySuccess(int $deliveryId, int $statusCode, ?string $responseHeaders, ?string $responseBody): void
    {
        if (!$this->tableExists('t99006')) {
            return;
        }

        $this->auditConnection->update('t99006', [
            'c_status_entrega' => 'success',
            'si_status_http' => $statusCode,
            't_headers_resposta' => $responseHeaders,
            't_corpo_resposta' => $responseBody,
            't_erro' => null,
            'dt_hr_fim_processamento' => date('c'),
            'dt_hr_proxima_tentativa' => null,
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99006' => $deliveryId,
        ]);
    }

    public function markDeliveryFailure(int $deliveryId, int $attemptNumber, ?int $statusCode, ?string $responseHeaders, ?string $responseBody, ?string $error): void
    {
        $this->markDeliveryFailureWithPolicy($deliveryId, $attemptNumber, 3, 300, $statusCode, $responseHeaders, $responseBody, $error);
    }

    public function markDeliveryFailureWithPolicy(int $deliveryId, int $attemptNumber, int $maxAttempts, int $retryDelaySeconds, ?int $statusCode, ?string $responseHeaders, ?string $responseBody, ?string $error): void
    {
        if (!$this->tableExists('t99006')) {
            return;
        }

        $maxAttempts = max(1, min(20, $maxAttempts));
        $retryDelaySeconds = max(1, min(86400, $retryDelaySeconds));
        $shouldRetry = $attemptNumber < $maxAttempts;
        $delayWithJitter = $this->calculateRetryDelaySeconds($attemptNumber, $retryDelaySeconds);

        $this->auditConnection->update('t99006', [
            'c_status_entrega' => $shouldRetry ? 'failed' : 'failed_final',
            'si_status_http' => $statusCode,
            't_headers_resposta' => $responseHeaders,
            't_corpo_resposta' => $responseBody,
            't_erro' => $error,
            'dt_hr_fim_processamento' => date('c'),
            'dt_hr_proxima_tentativa' => $shouldRetry ? date('c', time() + $delayWithJitter) : null,
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99006' => $deliveryId,
        ]);
    }

    public function requeueDelivery(int $deliveryId): void
    {
        if (!$this->tableExists('t99006')) {
            return;
        }

        $this->auditConnection->update('t99006', [
            'c_status_entrega' => 'pending',
            'si_num_tentativa' => 0,
            'dt_hr_proxima_tentativa' => date('c'),
            'dt_hr_ini_processamento' => null,
            'dt_hr_fim_processamento' => null,
            't_erro' => null,
            'dt_hr_atu' => date('c'),
        ], [
            'id_t99006' => $deliveryId,
        ]);
    }

    private function normalizeJsonHeaders(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return null;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeVariablesJson(mixed $value): ?string
    {
        $decoded = $this->decodeOptionalJsonObject($value);
        if ($decoded === null) {
            return null;
        }

        $normalized = [
            'query' => [],
            'headers' => [],
            'path' => [],
        ];
        foreach ($normalized as $section => $_) {
            if (!isset($decoded[$section]) || !is_array($decoded[$section])) {
                continue;
            }

            foreach ($decoded[$section] as $name => $source) {
                $name = trim((string) $name);
                if ($name === '' || (!is_scalar($source) && $source !== null)) {
                    continue;
                }

                $normalized[$section][$name] = trim((string) $source);
            }
        }

        if ($normalized['query'] === [] && $normalized['headers'] === [] && $normalized['path'] === []) {
            return null;
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizePayloadRulesJson(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return null;
        }

        $rules = array_is_list($decoded) ? $decoded : [$decoded];
        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $path = trim((string) ($rule['path'] ?? $rule['tag'] ?? ''));
            $operator = strtolower(trim((string) ($rule['operator'] ?? 'equals')));
            $value = $rule['value'] ?? '';
            if ($path === '' || !in_array($operator, ['equals', 'contains', 'in'], true)) {
                continue;
            }

            if (is_array($value)) {
                $normalizedValue = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== ''));
            } else {
                $normalizedValue = trim((string) $value);
            }

            $normalized[] = [
                'path' => $path,
                'operator' => $operator,
                'value' => $normalizedValue,
            ];
        }

        return $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeOptionalJsonObject(mixed $value): ?array
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    private function normalizeSuccessMode(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));

        return in_array($mode, ['status_only', 'status_and_payload'], true) ? $mode : 'status_only';
    }

    private function normalizeStatusCodes(string $value): string
    {
        $parts = preg_split('/[\s,;]+/', $value) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if ($part === '') {
                continue;
            }

            if (preg_match('/^[1-5]xx$/', $part) || preg_match('/^[1-5][0-9]{2}$/', $part)) {
                $normalized[] = $part;
            }
        }

        return $normalized === [] ? '200,201,202,204' : implode(',', array_values(array_unique($normalized)));
    }

    private function normalizeBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 't', 'on', 'sim', 'yes'], true) ? '1' : '0';
    }

    private function validateWebhookUrl(string $url): string
    {
        if ($url === '') {
            throw new \InvalidArgumentException('URL do webhook e obrigatoria.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \InvalidArgumentException('URL do webhook deve usar http ou https e possuir host valido.');
        }

        if ($this->isBlockedWebhookHost($host)) {
            throw new \InvalidArgumentException('URL do webhook nao pode apontar para host local, privado ou reservado.');
        }

        return $url;
    }

    private function isBlockedWebhookHost(string $host): bool
    {
        $normalizedHost = trim($host, "[] \t\n\r\0\x0B");
        if ($normalizedHost === '' || $normalizedHost === 'localhost' || str_ends_with($normalizedHost, '.localhost')) {
            return true;
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP)) {
            return !$this->isPublicIp($normalizedHost);
        }

        $resolvedIps = @gethostbynamel($normalizedHost);
        if ($resolvedIps === false || $resolvedIps === []) {
            return true;
        }

        foreach ($resolvedIps as $ip) {
            if (!$this->isPublicIp($ip)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function calculateRetryDelaySeconds(int $attemptNumber, int $baseDelaySeconds): int
    {
        $multiplier = 2 ** max(0, min(6, $attemptNumber - 1));
        $delay = min(86400, $baseDelaySeconds * $multiplier);
        $jitterMax = max(1, (int) floor($delay * 0.2));

        return min(86400, $delay + random_int(0, $jitterMax));
    }

    private function normalizeProgram(mixed $value): string
    {
        $program = strtolower(trim((string) $value));

        return $program === '' ? '*' : $program;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
        }

        return $this->tableExistsCache[$table];
    }

    private function ensureWebhookConfigColumns(): void
    {
        if ($this->webhookColumnsEnsured || !$this->tableExists('t00003')) {
            return;
        }

        foreach ([
            "ALTER TABLE t00003 ADD COLUMN IF NOT EXISTS t_variaveis_json text",
            "ALTER TABLE t00003 ADD COLUMN IF NOT EXISTS c_success_mode varchar(30) NOT NULL DEFAULT 'status_only'",
            "ALTER TABLE t00003 ADD COLUMN IF NOT EXISTS c_success_status_codes varchar(255) NOT NULL DEFAULT '200,201,202,204'",
            "ALTER TABLE t00003 ADD COLUMN IF NOT EXISTS t_success_payload_rules_json text",
            "ALTER TABLE t00003 ADD COLUMN IF NOT EXISTS si_max_tentativas smallint NOT NULL DEFAULT 3",
            "ALTER TABLE t00003 ADD COLUMN IF NOT EXISTS si_intervalo_tentativas_segundos integer NOT NULL DEFAULT 300",
        ] as $sql) {
            $this->auditConnection->executeStatement($sql);
        }

        $this->webhookColumnsEnsured = true;
    }
}
