<?php

namespace App\Repository;

use App\Support\ApiExtractionStatus;
use App\Support\ApiRequestStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class NfeMonitorRepository
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];
    private bool $extractionColumnsEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLatest(int $limit = 100): array
    {
        if (!$this->tableExists('t99001')) {
            return [];
        }

        $this->ensureExtractionColumns();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            $this->buildBaseSql() . '
                WHERE t.c_cod_programa = :programa
                  AND t.c_caminho LIKE :caminho
                ORDER BY t.dt_hr_recebimento DESC, t.id_t99001 DESC
                LIMIT :limit
            ',
            [
                'programa' => 'nfe',
                'caminho' => '/nfe/envio/%',
                'limit' => max(1, min($limit, 100)),
            ],
            [
                'limit' => ParameterType::INTEGER,
            ]
        );

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetailByRequestId(string $requestId): ?array
    {
        if ($requestId === '' || !$this->tableExists('t99001')) {
            return null;
        }

        $this->ensureExtractionColumns();

        $row = $this->auditConnection->fetchAssociative(
            $this->buildBaseSql() . '
                WHERE t.u_c_request_id = :request_id
                ORDER BY t.id_t99001 DESC
                LIMIT 1
            ',
            ['request_id' => $requestId]
        );

        if ($row === false) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    private function buildBaseSql(): string
    {
        $hasT99008 = $this->tableExists('t99008');
        $hasT99019 = $this->tableExists('t99019');
        $hasT99024 = $this->tableExists('t99024');
        $hasT99021 = $this->tableExists('t99021');
        $hasT99012 = $this->tableExists('t99012');

        $latestDocJoin = $hasT99008 ? <<<'SQL'
            LEFT JOIN (
                SELECT d.u_c_request_id, MAX(d.id_t99008) AS last_t99008_id
                FROM t99008 d
                GROUP BY d.u_c_request_id
            ) latest_doc ON latest_doc.u_c_request_id = t.u_c_request_id
        SQL : '';

        $nfeJoin = $hasT99019 ? 'LEFT JOIN t99019 n ON n.t99008_id = latest_doc.last_t99008_id' : '';
        $destPivotJoin = $hasT99024 ? 'LEFT JOIN t99024 rel_dest ON rel_dest.t99019_id = n.id_t99019' : '';
        $destJoin = $hasT99021 ? 'LEFT JOIN t99021 dest ON dest.id_t99021 = rel_dest.t99021_id' : '';
        $destDocJoin = $hasT99012 ? 'LEFT JOIN t99012 dest_doc ON dest_doc.t99008_id = latest_doc.last_t99008_id' : '';
        $numeroNotaExpr = $hasT99019 ? 'n.n_nf' : "''";
        $chaveNfeExpr = $hasT99019 ? 'n.ch_nfe' : "''";
        $valorTotalExpr = $hasT99019 ? 'n.v_nf' : "''";
        $dataEmissaoExpr = $hasT99019 ? 'n.dh_emi' : 'NULL';
        $xmlAutorizadoExpr = $hasT99019 ? 'n.xml_autorizado' : "''";
        $caminhoDanfeExpr = $hasT99019 ? 'n.caminho_danfe' : "''";
        $clienteExpr = match (true) {
            $hasT99021 && $hasT99012 => 'COALESCE(dest.nome_razao_social, dest_doc.x_nome, \'\')',
            $hasT99021 => 'COALESCE(dest.nome_razao_social, \'\')',
            $hasT99012 => 'COALESCE(dest_doc.x_nome, \'\')',
            default => "''",
        };
        $clienteDocumentoExpr = match (true) {
            $hasT99021 && $hasT99012 => 'COALESCE(dest.cnpj, dest_doc.cnpj, dest_doc.cpf, \'\')',
            $hasT99021 => 'COALESCE(dest.cnpj, \'\')',
            $hasT99012 => 'COALESCE(dest_doc.cnpj, dest_doc.cpf, \'\')',
            default => "''",
        };

        return <<<SQL
            SELECT
                t.id_t99001,
                t.u_c_request_id AS request_id,
                t.c_caminho,
                t.c_nome_operacao,
                t.c_cod_programa,
                t.si_status_processamento,
                t.si_status_http,
                t.si_status_extracao,
                t.t_erro AS erro_execucao,
                t.t_erro_extracao AS erro_extracao,
                t.t_corpo_resposta,
                t.t_corpo_requisicao,
                t.dt_hr_recebimento,
                t.dt_hr_ini_processamento,
                t.dt_hr_fim_processamento,
                t.dt_hr_ini_extracao,
                t.dt_hr_fim_extracao,
                {$numeroNotaExpr} AS numero_nota,
                {$chaveNfeExpr} AS chave_nfe,
                {$valorTotalExpr} AS valor_total,
                {$dataEmissaoExpr} AS data_emissao,
                {$xmlAutorizadoExpr} AS xml_autorizado,
                {$caminhoDanfeExpr} AS caminho_danfe,
                {$clienteExpr} AS cliente,
                {$clienteDocumentoExpr} AS cliente_documento
            FROM t99001 t
            {$latestDocJoin}
            {$nfeJoin}
            {$destPivotJoin}
            {$destJoin}
            {$destDocJoin}
        SQL;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['numero_nota'] = $this->normalizeScalar($row['numero_nota'] ?? null);
        $row['cliente'] = $this->normalizeScalar($row['cliente'] ?? null);
        $row['cliente_documento'] = $this->normalizeScalar($row['cliente_documento'] ?? null);
        $row['valor_total'] = $this->normalizeDecimal($row['valor_total'] ?? null);
        $row['chave_nfe'] = $this->normalizeScalar($row['chave_nfe'] ?? null);
        $row['situacao'] = $this->resolveSituacao($row);
        $row['ocorrencia'] = $this->resolveOcorrencia($row);

        if ($row['cliente'] === '') {
            $row['cliente'] = $row['cliente_documento'];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveSituacao(array $row): string
    {
        $statusProcessamento = (int) ($row['si_status_processamento'] ?? ApiRequestStatus::RECEBIDA);
        $statusExtracao = (int) ($row['si_status_extracao'] ?? ApiExtractionStatus::NAO_SE_APLICA);
        $numeroNota = $this->normalizeScalar($row['numero_nota'] ?? null);

        if ($statusProcessamento === ApiRequestStatus::FALHA || $statusProcessamento === ApiRequestStatus::NAO_AUTORIZADA) {
            return 'Enviado com falha';
        }

        if ($statusExtracao === ApiExtractionStatus::FALHA) {
            return 'Erro de extracao';
        }

        if ($statusProcessamento === ApiRequestStatus::PROCESSANDO || $statusExtracao === ApiExtractionStatus::PROCESSANDO) {
            return 'Em processamento';
        }

        if ($statusProcessamento === ApiRequestStatus::ENFILEIRADA || $statusExtracao === ApiExtractionStatus::PENDENTE) {
            return 'Pendente';
        }

        if ($statusProcessamento === ApiRequestStatus::CONCLUIDA && $numeroNota === '') {
            return 'Sem nota vinculada';
        }

        if ($statusProcessamento === ApiRequestStatus::CONCLUIDA) {
            return 'Enviado com sucesso';
        }

        return 'Pendente';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveOcorrencia(array $row): string
    {
        $situacao = $this->resolveSituacao($row);

        return match ($situacao) {
            'Enviado com falha', 'Erro de extracao' => 'erro',
            'Em processamento', 'Pendente' => 'processando',
            default => 'envio',
        };
    }

    private function normalizeScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return trim((string) $value);
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        return $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
    }

    private function ensureExtractionColumns(): void
    {
        if ($this->extractionColumnsEnsured) {
            return;
        }

        if ($this->auditConnection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->extractionColumnsEnsured = true;

            return;
        }

        $statements = [
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS si_status_extracao smallint NOT NULL DEFAULT 0",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS dt_hr_ini_extracao timestamp",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS dt_hr_fim_extracao timestamp",
            "ALTER TABLE t99001 ADD COLUMN IF NOT EXISTS t_erro_extracao text",
        ];

        foreach ($statements as $statement) {
            $this->auditConnection->executeStatement($statement);
        }

        $this->extractionColumnsEnsured = true;
    }
}
