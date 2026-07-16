<?php

namespace App\Repository;

use App\Support\ApiExtractionStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class NfeInputMonitorRepository
{
    private const MAX_RECORDS = 100;

    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters): array
    {
        if (!$this->tableExists('t99007') || !$this->tableExists('t99008')) {
            return [];
        }

        [$whereSql, $params, $types] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            $this->buildBaseSql() . $whereSql . '
                ORDER BY COALESCE(d.dt_hr_processado_em, e.dh_resp, t.dt_hr_recebimento) DESC, d.id_t99008 DESC
                LIMIT ' . self::MAX_RECORDS,
            $params,
            $types
        );

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByRequestId(string $requestId): ?array
    {
        $documentId = (int) $requestId;
        if ($documentId <= 0 || !$this->tableExists('t99008')) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            $this->buildBaseSql() . '
                WHERE d.id_t99008 = :document_id
                  AND COALESCE(d.schema_family, \'\') = \'resNFe\'
                LIMIT 1
            ',
            ['document_id' => $documentId],
            ['document_id' => ParameterType::INTEGER]
        );

        if ($row === false) {
            return null;
        }

        $itemsDocumentId = isset($row['documento_completo_id']) ? (int) $row['documento_completo_id'] : 0;
        if ($itemsDocumentId <= 0) {
            $itemsDocumentId = $documentId;
        }

        $detail = $this->normalizeRow($row);
        $detail['itens'] = $this->findItemsByDocumentId($itemsDocumentId);
        $detail['xml_evento_autorizacao'] = $this->stringOrEmpty($row['xml_evento_autorizacao'] ?? null);
        $detail['resposta_bruta'] = $this->stringOrEmpty($row['xml_envelope'] ?? null);

        return $detail;
    }

    /**
     * @return array{clientes:list<string>,emissores:list<string>,assinantes:list<string>}
     */
    public function filterOptions(): array
    {
        return [
            'clientes' => [],
            'emissores' => [],
            'assinantes' => [],
        ];
    }

    /**
     * @return list<string>
     */
    public function searchFilterOptions(string $type, string $query): array
    {
        $normalizedType = trim($type);
        $normalizedQuery = trim($query);

        if ($normalizedType === '') {
            return [];
        }

        return match ($normalizedType) {
            'cliente' => $this->searchClientOptions($normalizedQuery),
            'emissor' => $this->searchIssuerOptions($normalizedQuery),
            'assinante' => $this->searchSubscriberOptions($normalizedQuery),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>,2:array<string,int>}
     */
    private function buildWhereSql(array $filters): array
    {
        $where = [
            't.c_cod_programa = :programa',
            "(COALESCE(t.c_caminho, '') LIKE :caminho OR COALESCE(e.caminho_origem, '') LIKE :caminho)",
            "COALESCE(d.schema_family, '') = 'resNFe'",
        ];
        $params = [
            'programa' => 'nfe',
            'caminho' => '/nfe/distribuicao-dfe/%',
        ];
        $types = [];

        $environment = (int) trim((string) ($filters['ambiente'] ?? ''));
        if (in_array($environment, [1, 2], true)) {
            $where[] = 'COALESCE(d.tp_amb, e.tp_amb, 0) = :ambiente';
            $params['ambiente'] = $environment;
            $types['ambiente'] = ParameterType::INTEGER;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'COALESCE(d.dt_hr_processado_em, e.dh_resp, t.dt_hr_recebimento) >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 'COALESCE(d.dt_hr_processado_em, e.dh_resp, t.dt_hr_recebimento) <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $numeroNota = trim((string) ($filters['numero_nota'] ?? ''));
        if ($numeroNota !== '') {
            $where[] = "(COALESCE(nfe_proc.n_nf, '') LIKE :numero_nota OR COALESCE(d.ch_nfe, '') LIKE :numero_nota OR COALESCE(d.nsu, '') LIKE :numero_nota)";
            $params['numero_nota'] = '%' . $numeroNota . '%';
        }

        $cliente = trim((string) ($filters['cliente'] ?? ''));
        if ($cliente !== '') {
            $where[] = "(COALESCE(dest.x_nome, '') LIKE :cliente OR COALESCE(dest.cnpj, '') LIKE :cliente OR COALESCE(dest.cpf, '') LIKE :cliente OR COALESCE(dest.id_estrangeiro, '') LIKE :cliente)";
            $params['cliente'] = '%' . $cliente . '%';
        }

        /** @var list<int> $statusList */
        $statusList = array_values(array_filter(
            array_map(
                static fn (mixed $value): int => (int) $value,
                array_filter(
                    is_array($filters['status'] ?? null) ? $filters['status'] : [$filters['status'] ?? ''],
                    static fn (mixed $value): bool => trim((string) $value) !== ''
                )
            ),
            static fn (int $value): bool => $value >= 0
        ));
        if ($statusList !== []) {
            $placeholders = [];
            foreach ($statusList as $index => $status) {
                $name = 'status_' . $index;
                $placeholders[] = ':' . $name;
                $params[$name] = $status;
                $types[$name] = ParameterType::INTEGER;
            }
            $where[] = 'COALESCE(t.si_status_extracao, 0) IN (' . implode(', ', $placeholders) . ')';
        }

        $accessKey = trim((string) ($filters['chave'] ?? ''));
        if ($accessKey !== '') {
            $where[] = "(COALESCE(nfe_proc.ch_nfe, '') LIKE :chave OR COALESCE(d.ch_nfe, '') LIKE :chave)";
            $params['chave'] = '%' . $accessKey . '%';
        }

        $subscriber = trim((string) ($filters['assinante'] ?? ''));
        if ($subscriber !== '') {
            $where[] = "COALESCE(t.t_assinante_json, '') LIKE :assinante";
            $params['assinante'] = '%' . $subscriber . '%';
        }

        $issuer = trim((string) ($filters['emissor'] ?? ''));
        if ($issuer !== '') {
            $where[] = "(COALESCE(emit.x_nome, '') LIKE :emissor OR COALESCE(emit.cnpj, '') LIKE :emissor OR COALESCE(emit.cpf, '') LIKE :emissor)";
            $params['emissor'] = '%' . $issuer . '%';
        }

        return [' WHERE ' . implode(' AND ', $where), $params, $types];
    }

    /**
     * @return list<string>
     */
    private function searchClientOptions(string $query): array
    {
        if (!$this->tableExists('t99012')) {
            return [];
        }

        $like = '%' . trim($query) . '%';
        $options = [];

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            "
                SELECT x_nome, cnpj, cpf, id_estrangeiro
                FROM t99012
                WHERE COALESCE(x_nome, '') LIKE :query
                   OR COALESCE(cnpj, '') LIKE :query
                   OR COALESCE(cpf, '') LIKE :query
                   OR COALESCE(id_estrangeiro, '') LIKE :query
                ORDER BY x_nome ASC
                LIMIT 20
            ",
            ['query' => $like]
        );

        foreach ($rows as $row) {
            $this->appendOption($options, $this->stringOrEmpty($row['x_nome'] ?? null));
        }

        return array_values(array_unique($options));
    }

    /**
     * @return list<string>
     */
    private function searchIssuerOptions(string $query): array
    {
        if (!$this->tableExists('t99011')) {
            return [];
        }

        $like = '%' . trim($query) . '%';
        $options = [];

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            "
                SELECT x_nome, cnpj, cpf
                FROM t99011
                WHERE COALESCE(x_nome, '') LIKE :query
                   OR COALESCE(cnpj, '') LIKE :query
                   OR COALESCE(cpf, '') LIKE :query
                ORDER BY x_nome ASC
                LIMIT 20
            ",
            ['query' => $like]
        );

        foreach ($rows as $row) {
            $this->appendOption($options, $this->stringOrEmpty($row['x_nome'] ?? null));
        }

        return array_values(array_unique($options));
    }

    /**
     * @return list<string>
     */
    private function searchSubscriberOptions(string $query): array
    {
        if (!$this->tableExists('t00002')) {
            return [];
        }

        $like = '%' . trim($query) . '%';
        $options = [];

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            "
                SELECT c_nome, c_identificador
                FROM public.t00002
                WHERE COALESCE(c_nome, '') LIKE :query
                   OR COALESCE(c_identificador, '') LIKE :query
                ORDER BY c_nome ASC, c_identificador ASC
                LIMIT 20
            ",
            ['query' => $like]
        );

        foreach ($rows as $row) {
            $this->appendOption($options, $this->stringOrEmpty($row['c_nome'] ?? null));
        }

        return array_values(array_unique($options));
    }

    private function buildBaseSql(): string
    {
        $hasT99009 = $this->tableExists('t99009');
        $hasT99010 = $this->tableExists('t99010');
        $hasT99011 = $this->tableExists('t99011');
        $hasT99012 = $this->tableExists('t99012');
        $hasT99014 = $this->tableExists('t99014');
        $hasT99015 = $this->tableExists('t99015');

        $nfeResumoJoin = $hasT99009 ? 'LEFT JOIN t99009 nfe_resumo ON nfe_resumo.t99008_id = d.id_t99008' : '';
        $completeDocJoin = <<<'SQL'
            LEFT JOIN (
                SELECT ch_nfe, MAX(id_t99008) AS complete_t99008_id
                FROM t99008
                WHERE COALESCE(schema_family, '') = 'procNFe'
                  AND COALESCE(ch_nfe, '') <> ''
                GROUP BY ch_nfe
            ) complete_link ON complete_link.ch_nfe = d.ch_nfe
            LEFT JOIN t99008 complete_doc ON complete_doc.id_t99008 = complete_link.complete_t99008_id
        SQL;
        $nfeProcJoin = $hasT99010 ? 'LEFT JOIN t99010 nfe_proc ON nfe_proc.t99008_id = COALESCE(complete_doc.id_t99008, d.id_t99008)' : '';
        $emitJoin = $hasT99011 ? 'LEFT JOIN t99011 emit ON emit.t99008_id = COALESCE(complete_doc.id_t99008, d.id_t99008)' : '';
        $destJoin = $hasT99012 ? 'LEFT JOIN t99012 dest ON dest.t99008_id = COALESCE(complete_doc.id_t99008, d.id_t99008)' : '';
        $totalsJoin = $hasT99014 ? 'LEFT JOIN t99014 tot ON tot.t99008_id = COALESCE(complete_doc.id_t99008, d.id_t99008)' : '';
        $eventJoin = $hasT99015 ? 'LEFT JOIN t99015 evt ON evt.t99008_id = COALESCE(complete_doc.id_t99008, d.id_t99008)' : '';

        return <<<SQL
            SELECT
                d.id_t99008 AS document_id,
                t.u_c_request_id AS original_request_id,
                t.si_status_http,
                t.si_status_extracao,
                t.t_erro_extracao,
                t.t_assinante_json,
                t.dt_hr_recebimento,
                e.documento_consulta,
                e.tipo_consulta,
                e.nsu_entrada,
                e.ult_nsu,
                e.max_nsu,
                e.q_doc_zip,
                e.xml_envelope,
                e.dh_resp,
                COALESCE(d.tp_amb, e.tp_amb) AS ambiente,
                d.schema_name,
                d.schema_family,
                d.ch_nfe,
                d.nsu,
                d.n_prot,
                d.emit_cnpj_cpf,
                d.xml_descompactado,
                complete_doc.id_t99008 AS documento_completo_id,
                complete_doc.xml_descompactado AS xml_completo,
                d.dt_hr_processado_em,
                nfe_proc.n_nf AS numero_nota,
                COALESCE(nfe_proc.dh_emi, nfe_resumo.dh_emi) AS data_emissao,
                nfe_proc.ch_nfe AS chave_nfe_proc,
                nfe_proc.n_prot AS protocolo_nfe,
                nfe_resumo.x_nome AS cliente_resumo_nome,
                nfe_resumo.cnpj AS cliente_resumo_cnpj,
                nfe_resumo.cpf AS cliente_resumo_cpf,
                dest.x_nome AS cliente_nome,
                dest.cnpj AS cliente_cnpj,
                dest.cpf AS cliente_cpf,
                dest.id_estrangeiro AS cliente_id_estrangeiro,
                emit.x_nome AS emitente_nome,
                emit.cnpj AS emitente_cnpj,
                emit.cpf AS emitente_cpf,
                COALESCE(tot.v_nf, nfe_resumo.v_nf) AS valor_total,
                tot.v_icms,
                tot.v_cofins,
                tot.v_pis,
                tot.v_ipi,
                evt.x_evento AS xml_evento_autorizacao
            FROM t99008 d
            INNER JOIN t99007 e ON e.id_t99007 = d.t99007_id
            INNER JOIN t99001 t ON t.id_t99001 = e.t99001_id
            {$nfeResumoJoin}
            {$completeDocJoin}
            {$nfeProcJoin}
            {$emitJoin}
            {$destJoin}
            {$totalsJoin}
            {$eventJoin}
        SQL;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $documentId = (string) ((int) ($row['document_id'] ?? 0));
        $assinante = $this->extractSubscriber($row['t_assinante_json'] ?? null);
        $clienteDocumento = $this->firstNonEmpty(
            $this->stringOrEmpty($row['cliente_cnpj'] ?? null),
            $this->stringOrEmpty($row['cliente_cpf'] ?? null),
            $this->stringOrEmpty($row['cliente_id_estrangeiro'] ?? null),
            $this->stringOrEmpty($row['documento_consulta'] ?? null),
            $this->stringOrEmpty($row['cliente_resumo_cnpj'] ?? null),
            $this->stringOrEmpty($row['cliente_resumo_cpf'] ?? null)
        );
        $emitenteDocumento = $this->firstNonEmpty(
            $this->stringOrEmpty($row['cliente_resumo_cnpj'] ?? null),
            $this->stringOrEmpty($row['cliente_resumo_cpf'] ?? null),
            $this->stringOrEmpty($row['emitente_cnpj'] ?? null),
            $this->stringOrEmpty($row['emitente_cpf'] ?? null),
            $this->stringOrEmpty($row['emit_cnpj_cpf'] ?? null)
        );
        $emitente = $this->firstNonEmpty(
            $this->stringOrEmpty($row['emitente_nome'] ?? null),
            $this->stringOrEmpty($row['cliente_resumo_nome'] ?? null),
            $emitenteDocumento
        );
        $cliente = $this->firstNonEmpty(
            $this->stringOrEmpty($row['cliente_nome'] ?? null),
            $assinante['nome'],
            $assinante['identificador'],
            $clienteDocumento
        );
        $chaveNfe = $this->firstNonEmpty(
            $this->stringOrEmpty($row['chave_nfe_proc'] ?? null),
            $this->stringOrEmpty($row['ch_nfe'] ?? null)
        );
        $xmlCompleto = $this->firstNonEmpty(
            $this->stringOrEmpty($row['xml_completo'] ?? null),
            $this->stringOrEmpty($row['xml_descompactado'] ?? null)
        );

        return [
            'request_id' => $documentId,
            'numero_nota' => $this->firstNonEmpty(
                $this->stringOrEmpty($row['numero_nota'] ?? null),
                $this->extractNoteNumberFromAccessKey($chaveNfe)
            ),
            'cliente' => $cliente,
            'cliente_documento' => $clienteDocumento,
            'emitente_nome' => $emitente,
            'emitente_documento' => $emitenteDocumento,
            'assinante_identificador' => $assinante['identificador'],
            'assinante_nome' => $assinante['nome'],
            'chave_nfe' => $chaveNfe,
            'data_envio' => $row['dt_hr_processado_em'] ?? $row['dh_resp'] ?? $row['dt_hr_recebimento'] ?? null,
            'data_emissao' => $row['data_emissao'] ?? null,
            'valor_total' => $this->decimalOrEmpty($row['valor_total'] ?? null),
            'ambiente' => $this->stringOrEmpty($row['ambiente'] ?? null),
            'status_envio' => $this->mapStatus((int) ($row['si_status_extracao'] ?? 0)),
            'status_http' => isset($row['si_status_http']) ? (int) $row['si_status_http'] : null,
            'erro' => $this->stringOrEmpty($row['t_erro_extracao'] ?? null),
            'xml_autorizado' => $xmlCompleto,
            'xml_url' => $xmlCompleto !== '' ? '/monitor-entrada-nfe/xml/' . $documentId : '',
            'impostos' => [
                'ICMS' => $this->simpleTaxPayload($row['v_icms'] ?? null),
                'COFINS' => $this->simpleTaxPayload($row['v_cofins'] ?? null),
                'PIS' => $this->simpleTaxPayload($row['v_pis'] ?? null),
                'IPI' => $this->simpleTaxPayload($row['v_ipi'] ?? null),
                'IBS' => null,
                'CBS' => null,
                'IS' => null,
            ],
            'tipo_consulta' => $this->stringOrEmpty($row['tipo_consulta'] ?? null),
            'documento_consulta' => $this->stringOrEmpty($row['documento_consulta'] ?? null),
            'nsu' => $this->stringOrEmpty($row['nsu'] ?? null),
            'nsu_entrada' => $this->stringOrEmpty($row['nsu_entrada'] ?? null),
            'ult_nsu' => $this->stringOrEmpty($row['ult_nsu'] ?? null),
            'max_nsu' => $this->stringOrEmpty($row['max_nsu'] ?? null),
            'schema_name' => $this->stringOrEmpty($row['schema_name'] ?? null),
            'schema_family' => $this->stringOrEmpty($row['schema_family'] ?? null),
            'protocolo' => $this->firstNonEmpty(
                $this->stringOrEmpty($row['protocolo_nfe'] ?? null),
                $this->stringOrEmpty($row['n_prot'] ?? null)
            ),
        ];
    }

    /**
     * @return array{identificador:string,nome:string}
     */
    private function extractSubscriber(mixed $subscriberJson): array
    {
        $body = $this->stringOrEmpty($subscriberJson);
        if ($body === '') {
            return ['identificador' => '', 'nome' => ''];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['identificador' => '', 'nome' => ''];
        }

        return [
            'identificador' => trim((string) ($decoded['c_identificador'] ?? '')),
            'nome' => trim((string) ($decoded['c_nome'] ?? '')),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function simpleTaxPayload(mixed $value): ?array
    {
        $decimal = $this->decimalOrEmpty($value);
        if ($decimal === '') {
            return null;
        }

        return [
            'base_calculo' => '',
            'aliquota' => '',
            'valor' => $decimal,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findItemsByDocumentId(int $documentId): array
    {
        if ($documentId <= 0 || !$this->tableExists('t99013')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            "
                SELECT
                    n_item,
                    c_prod,
                    x_prod,
                    c_ncm,
                    c_cfop,
                    q_com,
                    u_com,
                    v_un_com,
                    v_prod,
                    v_desc,
                    v_frete,
                    v_seg
                FROM t99013
                WHERE t99008_id = :document_id
                ORDER BY n_item ASC, id_t99013 ASC
            ",
            ['document_id' => $documentId],
            ['document_id' => ParameterType::INTEGER]
        );

        return array_map(function (array $row): array {
            return [
                'numero' => isset($row['n_item']) ? (int) $row['n_item'] : null,
                'descricao' => $this->stringOrEmpty($row['x_prod'] ?? null),
                'codigo_produto' => $this->stringOrEmpty($row['c_prod'] ?? null),
                'codigo_ncm' => $this->stringOrEmpty($row['c_ncm'] ?? null),
                'cfop' => $this->stringOrEmpty($row['c_cfop'] ?? null),
                'quantidade' => $this->decimalOrEmpty($row['q_com'] ?? null),
                'unidade' => $this->stringOrEmpty($row['u_com'] ?? null),
                'valor_unitario' => $this->decimalOrEmpty($row['v_un_com'] ?? null),
                'valor_total' => $this->decimalOrEmpty($row['v_prod'] ?? null),
                'valor_desconto' => $this->decimalOrEmpty($row['v_desc'] ?? null),
                'valor_frete' => $this->decimalOrEmpty($row['v_frete'] ?? null),
                'valor_seguro' => $this->decimalOrEmpty($row['v_seg'] ?? null),
                'valor_aproximado_tributos' => '',
                'impostos' => [],
            ];
        }, $rows);
    }

    private function mapStatus(int $status): string
    {
        return match ($status) {
            ApiExtractionStatus::CONCLUIDO => 'Concluído',
            ApiExtractionStatus::FALHA => 'Falha',
            ApiExtractionStatus::PROCESSANDO => 'Processando',
            ApiExtractionStatus::PENDENTE => 'Pendente',
            default => 'Não iniciado',
        };
    }

    /**
     * @param array<int, string> $options
     */
    private function appendOption(array &$options, string $value): void
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        $options[] = $trimmed;
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function stringOrEmpty(mixed $value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function decimalOrEmpty(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return trim((string) $value);
    }

    private function extractNoteNumberFromAccessKey(string $accessKey): string
    {
        $digits = preg_replace('/\D+/', '', $accessKey) ?? '';
        if (strlen($digits) !== 44) {
            return '';
        }

        $number = ltrim(substr($digits, 25, 9), '0');

        return $number === '' ? '0' : $number;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        return $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
    }
}
