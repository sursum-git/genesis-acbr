<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ApiAuditDashboardRepository
{
    private const ASSINANTE_JSON_EXPR = "(CASE WHEN t.t_assinante_json IS NULL OR btrim(t.t_assinante_json) = '' THEN NULL ELSE t.t_assinante_json::jsonb END)";
    private bool $extractionColumnsEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function getSummary(array $filters): array
    {
        $this->ensureExtractionColumns();
        [$whereSql, $params] = $this->buildWhereSql($filters);

        $sql = <<<SQL
            WITH filtradas AS (
                SELECT
                    t.*,
                    {$this->assinanteIdentificadorExpr()} AS c_assinante_identificador
                FROM t99001 t
                {$whereSql}
            )
            SELECT
                COUNT(*)::int AS total,
                SUM(CASE WHEN si_status_processamento = 3 THEN 1 ELSE 0 END)::int AS concluidas,
                SUM(CASE WHEN si_status_processamento = 4 THEN 1 ELSE 0 END)::int AS falhas,
                SUM(CASE WHEN si_status_processamento = 1 THEN 1 ELSE 0 END)::int AS enfileiradas,
                SUM(CASE WHEN si_status_processamento = 2 THEN 1 ELSE 0 END)::int AS processando,
                SUM(CASE WHEN c_modo_execucao = 'async' THEN 1 ELSE 0 END)::int AS async,
                COUNT(DISTINCT NULLIF(c_assinante_identificador, ''))::int AS assinantes
            FROM filtradas
            SQL;

        $summary = $this->auditConnection->fetchAssociative($sql, $params);

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'concluidas' => (int) ($summary['concluidas'] ?? 0),
            'falhas' => (int) ($summary['falhas'] ?? 0),
            'enfileiradas' => (int) ($summary['enfileiradas'] ?? 0),
            'processando' => (int) ($summary['processando'] ?? 0),
            'async' => (int) ($summary['async'] ?? 0),
            'assinantes' => (int) ($summary['assinantes'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getAdvancedMetrics(array $filters): array
    {
        $this->ensureExtractionColumns();
        [$whereSql, $params] = $this->buildWhereSql($filters);

        $aggregate = $this->auditConnection->fetchAssociative(
            <<<SQL
            SELECT
                COALESCE(ROUND(AVG(NULLIF(i_tempo_processamento_ms, 0))), 0)::int AS tempo_medio_ms,
                COALESCE(MAX(i_tempo_processamento_ms), 0)::int AS tempo_max_ms,
                COALESCE(ROUND(AVG(CASE WHEN si_status_http >= 400 THEN 1 ELSE 0 END) * 100), 0)::int AS taxa_erro_perc
            FROM t99001 t
            {$whereSql}
            SQL,
            $params
        ) ?: [];

        return [
            'tempo_medio_ms' => (int) ($aggregate['tempo_medio_ms'] ?? 0),
            'tempo_max_ms' => (int) ($aggregate['tempo_max_ms'] ?? 0),
            'taxa_erro_perc' => (int) ($aggregate['taxa_erro_perc'] ?? 0),
            'top_endpoints' => $this->findTopEndpoints($filters),
            'top_assinantes' => $this->findTopAssinantes($filters),
            'status_http' => $this->findHttpStatusBreakdown($filters),
            'requisicoes_por_dia' => $this->findRequestsPerDay($filters),
            'erros_por_dia' => $this->findErrorsPerDay($filters),
            'tempo_medio_por_endpoint' => $this->findAverageTimeByEndpoint($filters),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function findRequests(array $filters, int $limit = 80, int $offset = 0, string $sort = 'dt_hr_recebimento', string $direction = 'desc'): array
    {
        $this->ensureExtractionColumns();
        [$whereSql, $params] = $this->buildWhereSql($filters);
        $params['limit'] = min(max($limit, 1), 300);
        $params['offset'] = max($offset, 0);
        $sortColumn = $this->resolveSortColumn($sort);
        $sortDirection = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $sql = <<<SQL
            SELECT
                t.id_t99001,
                t.u_c_request_id,
                t.c_metodo,
                t.c_caminho,
                t.c_nome_operacao,
                t.c_modo_execucao,
                t.c_cod_programa,
                t.c_nome_programa,
                t.c_versao_programa,
                t.si_status_processamento,
                t.si_status_http,
                t.si_status_extracao,
                t.t_erro_extracao,
                t.i_tempo_processamento_ms,
                t.dt_hr_recebimento,
                t.dt_hr_fim_processamento,
                t.dt_hr_fim_extracao,
                {$this->assinanteIdentificadorExpr()} AS c_assinante_identificador,
                {$this->assinanteNomeExpr()} AS c_assinante_nome,
                LEFT(COALESCE(NULLIF(t.t_erro, ''), NULLIF(t.t_corpo_resposta, ''), NULLIF(t.t_corpo_requisicao, ''), ''), 260) AS t_resumo
            FROM t99001 t
            {$whereSql}
            ORDER BY {$sortColumn} {$sortDirection}, t.id_t99001 DESC
            LIMIT :limit
            OFFSET :offset
            SQL;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative($sql, $params);

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>|null
     */
    public function findSelectedRequest(array $filters, ?string $requestId): ?array
    {
        $this->ensureExtractionColumns();
        if ($requestId === null || $requestId === '') {
            return null;
        }

        [$whereSql, $params] = $this->buildWhereSql($filters);
        $params['request_id'] = $requestId;
        $whereSql .= $whereSql === '' ? ' WHERE ' : ' AND ';
        $whereSql .= 't.u_c_request_id = :request_id';

        $sql = <<<SQL
            SELECT
                t.*,
                {$this->assinanteIdentificadorExpr()} AS c_assinante_identificador,
                {$this->assinanteNomeExpr()} AS c_assinante_nome
            FROM t99001 t
            {$whereSql}
            ORDER BY t.id_t99001 DESC
            LIMIT 1
            SQL;

        $request = $this->auditConnection->fetchAssociative($sql, $params);

        return $request === false ? null : $request;
    }

    /**
     * @return list<array<string, string>>
     */
    public function findAssinanteOptions(): array
    {
        $this->ensureExtractionColumns();
        $sql = <<<SQL
            SELECT DISTINCT
                {$this->assinanteIdentificadorExpr()} AS identificador,
                {$this->assinanteNomeExpr()} AS nome
            FROM t99001 t
            WHERE {$this->assinanteIdentificadorExpr()} IS NOT NULL
              AND {$this->assinanteIdentificadorExpr()} <> ''
            ORDER BY nome ASC, identificador ASC
            SQL;

        /** @var list<array<string, string>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative($sql);

        return $rows;
    }

    /** @return list<string> */
    public function findMethodOptions(): array
    {
        $this->ensureExtractionColumns();
        return $this->findDistinctTextValues('c_metodo');
    }

    /** @return list<string> */
    public function findModeOptions(): array
    {
        $this->ensureExtractionColumns();
        return $this->findDistinctTextValues('c_modo_execucao');
    }

    /** @return list<string> */
    public function findProgramOptions(): array
    {
        $this->ensureExtractionColumns();
        return $this->findDistinctTextValues('c_cod_programa');
    }

    /**
     * @param int $requestInternalId
     * @return list<array<string, mixed>>
     */
    public function findAttempts(int $requestInternalId): array
    {
        $this->ensureExtractionColumns();
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                id_t99002,
                si_num_tentativa,
                si_status_processamento,
                si_status_http,
                c_worker_id,
                i_worker_pid,
                c_versao_programa,
                c_revisao_programa,
                dt_hr_ini_processamento,
                dt_hr_fim_processamento,
                t_erro,
                t_corpo_resposta
            FROM t99002
            WHERE t99001_id = :t99001_id
            ORDER BY si_num_tentativa DESC, id_t99002 DESC
            SQL,
            ['t99001_id' => $requestInternalId]
        );

        return $rows;
    }

    /**
     * @param int $requestInternalId
     * @return list<array<string, mixed>>
     */
    public function findEvents(int $requestInternalId): array
    {
        $this->ensureExtractionColumns();
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                id_t99004,
                c_evento,
                t_detalhe,
                dt_hr_evento
            FROM t99004
            WHERE t99001_id = :t99001_id
            ORDER BY dt_hr_evento DESC, id_t99004 DESC
            SQL,
            ['t99001_id' => $requestInternalId]
        );

        return $rows;
    }

    /**
     * @param int $requestInternalId
     * @return list<array<string, mixed>>
     */
    public function findExtractedNfe(int $requestInternalId): array
    {
        $this->ensureExtractionColumns();
        $bundle = $this->findExtractionBundle($requestInternalId);
        $rows = [];

        foreach ($bundle['documents'] as $document) {
            $schemaFamily = (string) ($document['c_schema_family'] ?? '');
            if (!in_array($schemaFamily, ['resNFe', 'procNFe'], true)) {
                continue;
            }

            $documentId = (string) $document['id_t99008'];
            $rows[] = [
                'documento' => $document,
                'resumo' => $bundle['nfe_resumo'][$documentId] ?? null,
                'proc' => $bundle['nfe_proc'][$documentId] ?? null,
                'emitente' => $bundle['nfe_emitente'][$documentId] ?? null,
                'destinatario' => $bundle['nfe_destinatario'][$documentId] ?? null,
                'total' => $bundle['nfe_totais'][$documentId] ?? null,
                'itens' => array_values(array_filter(
                    $bundle['nfe_itens'],
                    static fn (array $item): bool => (int) ($item['t99008_id'] ?? 0) === (int) $documentId
                )),
            ];
        }

        return $rows;
    }

    /**
     * @param int $requestInternalId
     * @return list<array<string, mixed>>
     */
    public function findExtractedNsu(int $requestInternalId): array
    {
        $this->ensureExtractionColumns();
        return $this->findExtractionBundle($requestInternalId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecentFailures(int $limit = 8): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                t.u_c_request_id,
                t.c_metodo,
                t.c_caminho,
                t.c_cod_programa,
                t.si_status_processamento,
                t.si_status_http,
                t.i_tempo_processamento_ms,
                t.dt_hr_recebimento,
                {$this->assinanteIdentificadorExpr()} AS c_assinante_identificador,
                {$this->assinanteNomeExpr()} AS c_assinante_nome,
                LEFT(COALESCE(NULLIF(t.t_erro, ''), NULLIF(t.t_corpo_resposta, ''), ''), 220) AS t_resumo
            FROM t99001 t
            WHERE t.si_status_processamento IN (4, 5)
               OR t.si_status_http >= 400
            ORDER BY t.id_t99001 DESC
            LIMIT :limit
            SQL,
            ['limit' => max(1, min($limit, 20))],
            ['limit' => ParameterType::INTEGER]
        );

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string, mixed>}
     */
    private function buildWhereSql(array $filters): array
    {
        $where = [];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $params['q'] = '%' . $query . '%';
            $where[] = sprintf(
                '(
                    t.u_c_request_id ILIKE :q
                    OR t.c_caminho ILIKE :q
                    OR COALESCE(t.c_nome_operacao, \'\') ILIKE :q
                    OR COALESCE(t.c_metodo, \'\') ILIKE :q
                    OR COALESCE(t.c_cod_programa, \'\') ILIKE :q
                    OR COALESCE(%s, \'\') ILIKE :q
                    OR COALESCE(%s, \'\') ILIKE :q
                )',
                $this->assinanteIdentificadorExpr(),
                $this->assinanteNomeExpr()
            );
        }

        $assinante = trim((string) ($filters['assinante'] ?? ''));
        if ($assinante !== '') {
            $params['assinante'] = $assinante;
            $where[] = $this->assinanteIdentificadorExpr() . ' = :assinante';
        }

        $metodo = strtoupper(trim((string) ($filters['metodo'] ?? '')));
        if ($metodo !== '') {
            $params['metodo'] = $metodo;
            $where[] = 't.c_metodo = :metodo';
        }

        $modo = strtolower(trim((string) ($filters['modo'] ?? '')));
        if ($modo !== '') {
            $params['modo'] = $modo;
            $where[] = 't.c_modo_execucao = :modo';
        }

        $programa = trim((string) ($filters['programa'] ?? ''));
        if ($programa !== '') {
            $params['programa'] = $programa;
            $where[] = 't.c_cod_programa = :programa';
        }

        $statusProcessamento = trim((string) ($filters['status_processamento'] ?? ''));
        if ($statusProcessamento !== '' && is_numeric($statusProcessamento)) {
            $params['status_processamento'] = (int) $statusProcessamento;
            $where[] = 't.si_status_processamento = :status_processamento';
        }

        $statusHttp = trim((string) ($filters['status_http'] ?? ''));
        if ($statusHttp !== '' && is_numeric($statusHttp)) {
            $params['status_http'] = (int) $statusHttp;
            $where[] = 't.si_status_http = :status_http';
        }

        $path = trim((string) ($filters['caminho'] ?? ''));
        if ($path !== '') {
            $params['caminho'] = '%' . $path . '%';
            $where[] = 't.c_caminho ILIKE :caminho';
        }

        $dataIni = trim((string) ($filters['data_ini'] ?? ''));
        if ($dataIni !== '') {
            $params['data_ini'] = $dataIni . ' 00:00:00';
            $where[] = 't.dt_hr_recebimento >= :data_ini';
        }

        $dataFim = trim((string) ($filters['data_fim'] ?? ''));
        if ($dataFim !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dataFim);
            if ($date !== false) {
                $params['data_fim'] = $date->modify('+1 day')->format('Y-m-d 00:00:00');
                $where[] = 't.dt_hr_recebimento < :data_fim';
            }
        }

        if ($where === []) {
            return ['', $params];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    /** @return list<string> */
    private function findDistinctTextValues(string $column): array
    {
        $sql = sprintf(
            'SELECT DISTINCT %1$s FROM t99001 WHERE %1$s IS NOT NULL AND btrim(%1$s) <> \'\' ORDER BY %1$s ASC',
            $column
        );

        /** @var list<string> $rows */
        $rows = $this->auditConnection->fetchFirstColumn($sql);

        return array_values(array_filter($rows, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    /** @return list<array<string, mixed>> */
    private function findTopEndpoints(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT t.c_caminho, COUNT(*)::int AS total
            FROM t99001 t
            {$whereSql}
            GROUP BY t.c_caminho
            ORDER BY total DESC, t.c_caminho ASC
            LIMIT 5
            SQL,
            $params
        );

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function findTopAssinantes(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                {$this->assinanteIdentificadorExpr()} AS identificador,
                {$this->assinanteNomeExpr()} AS nome,
                COUNT(*)::int AS total
            FROM t99001 t
            {$whereSql}
            GROUP BY identificador, nome
            ORDER BY total DESC, identificador ASC
            LIMIT 5
            SQL,
            $params
        );

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function findHttpStatusBreakdown(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT COALESCE(si_status_http, 0)::int AS status_http, COUNT(*)::int AS total
            FROM t99001 t
            {$whereSql}
            GROUP BY status_http
            ORDER BY total DESC, status_http ASC
            LIMIT 6
            SQL,
            $params
        );

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function findRequestsPerDay(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT to_char(date_trunc('day', t.dt_hr_recebimento), 'DD/MM') AS dia, COUNT(*)::int AS total
            FROM t99001 t
            {$whereSql}
            GROUP BY date_trunc('day', t.dt_hr_recebimento)
            ORDER BY date_trunc('day', t.dt_hr_recebimento) DESC
            LIMIT 10
            SQL,
            $params
        );

        return array_reverse($rows);
    }

    /** @return list<array<string, mixed>> */
    private function findErrorsPerDay(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereSql($filters);
        $whereSql .= $whereSql === '' ? ' WHERE ' : ' AND ';
        $whereSql .= '(t.si_status_processamento IN (4, 5) OR t.si_status_http >= 400)';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT to_char(date_trunc('day', t.dt_hr_recebimento), 'DD/MM') AS dia, COUNT(*)::int AS total
            FROM t99001 t
            {$whereSql}
            GROUP BY date_trunc('day', t.dt_hr_recebimento)
            ORDER BY date_trunc('day', t.dt_hr_recebimento) DESC
            LIMIT 10
            SQL,
            $params
        );

        return array_reverse($rows);
    }

    /** @return list<array<string, mixed>> */
    private function findAverageTimeByEndpoint(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT t.c_caminho, COALESCE(ROUND(AVG(NULLIF(t.i_tempo_processamento_ms, 0))), 0)::int AS tempo_medio_ms
            FROM t99001 t
            {$whereSql}
            GROUP BY t.c_caminho
            HAVING COUNT(*) > 0
            ORDER BY tempo_medio_ms DESC, t.c_caminho ASC
            LIMIT 5
            SQL,
            $params
        );

        return $rows;
    }

    private function resolveSortColumn(string $sort): string
    {
        return match ($sort) {
            'assinante' => $this->assinanteIdentificadorExpr(),
            'caminho' => 't.c_caminho',
            'status' => 't.si_status_processamento',
            default => 't.dt_hr_recebimento',
        };
    }

    private function assinanteIdentificadorExpr(): string
    {
        return "COALESCE(" . self::ASSINANTE_JSON_EXPR . " ->> 'c_identificador', '')";
    }

    private function assinanteNomeExpr(): string
    {
        return "COALESCE(" . self::ASSINANTE_JSON_EXPR . " ->> 'c_nome', '')";
    }

    private function ensureExtractionColumns(): void
    {
        if ($this->extractionColumnsEnsured || !$this->auditConnection->createSchemaManager()->tablesExist(['t99001'])) {
            return;
        }

        $statements = [
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS si_status_extracao smallint NOT NULL DEFAULT 0",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS dt_hr_ini_extracao timestamp",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS dt_hr_fim_extracao timestamp",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS t_erro_extracao text",
            "CREATE INDEX IF NOT EXISTS t99001_si_status_extracao_idx ON t99001 (si_status_extracao, dt_hr_recebimento)",
        ];

        foreach ($statements as $statement) {
            $this->auditConnection->executeStatement($statement);
        }

        $this->extractionColumnsEnsured = true;
    }

    /**
     * @return array<string, mixed>
     */
    private function findExtractionBundle(int $requestInternalId): array
    {
        if (
            !$this->auditConnection->createSchemaManager()->tablesExist(['t99007'])
            || !$this->auditConnection->createSchemaManager()->tablesExist(['t99008'])
        ) {
            return [
                'executions' => [],
                'documents' => [],
                'nfe_resumo' => [],
                'nfe_proc' => [],
                'nfe_emitente' => [],
                'nfe_destinatario' => [],
                'nfe_itens' => [],
                'nfe_totais' => [],
                'evento_resumo' => [],
                'evento_proc' => [],
                'evento_detalhe' => [],
                'inutilizacao_proc' => [],
            ];
        }

        /** @var list<array<string, mixed>> $executions */
        $executions = $this->auditConnection->fetchAllAssociative(
            'SELECT * FROM t99007 WHERE t99001_id = :id ORDER BY id_t99007 DESC',
            ['id' => $requestInternalId]
        );

        /** @var list<array<string, mixed>> $documents */
        $documents = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT d.*
            FROM t99008 d
            INNER JOIN t99007 e ON e.id_t99007 = d.t99007_id
            WHERE e.t99001_id = :id
            ORDER BY d.id_t99008 DESC
            SQL,
            ['id' => $requestInternalId]
        );

        $documentIds = array_values(array_map(static fn (array $row): int => (int) $row['id_t99008'], $documents));

        return [
            'executions' => $executions,
            'documents' => $documents,
            'nfe_resumo' => $this->fetchExtractionTableMap('t99009', $documentIds),
            'nfe_proc' => $this->fetchExtractionTableMap('t99010', $documentIds),
            'nfe_emitente' => $this->fetchExtractionTableMap('t99011', $documentIds),
            'nfe_destinatario' => $this->fetchExtractionTableMap('t99012', $documentIds),
            'nfe_itens' => $this->fetchExtractionTableRows('t99013', $documentIds),
            'nfe_totais' => $this->fetchExtractionTableMap('t99014', $documentIds),
            'evento_resumo' => $this->fetchExtractionTableMap('t99015', $documentIds),
            'evento_proc' => $this->fetchExtractionTableMap('t99016', $documentIds),
            'evento_detalhe' => $this->fetchExtractionTableMap('t99017', $documentIds),
            'inutilizacao_proc' => $this->fetchExtractionTableMap('t99018', $documentIds),
        ];
    }

    /**
     * @param list<int> $documentIds
     * @return array<string, array<string, mixed>>
     */
    private function fetchExtractionTableMap(string $table, array $documentIds): array
    {
        if ($documentIds === [] || !$this->auditConnection->createSchemaManager()->tablesExist([$table])) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE t99008_id IN (?) ORDER BY t99008_id ASC', $table),
            [$documentIds],
            [Connection::PARAM_INT_ARRAY]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['t99008_id']] = $row;
        }

        return $indexed;
    }

    /**
     * @param list<int> $documentIds
     * @return list<array<string, mixed>>
     */
    private function fetchExtractionTableRows(string $table, array $documentIds): array
    {
        if ($documentIds === [] || !$this->auditConnection->createSchemaManager()->tablesExist([$table])) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE t99008_id IN (?) ORDER BY t99008_id ASC, id_t99013 ASC', $table),
            [$documentIds],
            [Connection::PARAM_INT_ARRAY]
        );

        return $rows;
    }
}
