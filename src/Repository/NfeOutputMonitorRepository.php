<?php

namespace App\Repository;

use App\Support\ApiRequestStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class NfeOutputMonitorRepository
{
    private const MAX_RECORDS = 100;
    private const HOMOLOGATION_PLACEHOLDER = 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL';

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
        if (!$this->tableExists('t99001')) {
            return [];
        }

        [$whereSql, $params, $types] = $this->buildWhereSql($filters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            $this->buildBaseSql() . $whereSql . '
                ORDER BY t.dt_hr_recebimento DESC, t.id_t99001 DESC
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
        if ($requestId === '' || !$this->tableExists('t99001')) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            $this->buildBaseSql() . '
                WHERE t.u_c_request_id = :request_id
                LIMIT 1
            ',
            ['request_id' => $requestId]
        );

        if ($row === false) {
            return null;
        }

        $detail = $this->normalizeRow($row);
        $noteId = isset($row['id_t99019']) ? (int) $row['id_t99019'] : 0;

        $detail['itens'] = $this->findItemsByNoteId($noteId);
        $detail['xml_evento_autorizacao'] = $this->extractAuthorizationEventXml($row['t_corpo_resposta'] ?? null);
        $detail['resposta_bruta'] = $this->stringOrEmpty($row['t_corpo_resposta'] ?? null);

        return $detail;
    }

    /**
     * @return array{clientes:list<string>,emissores:list<string>,assinantes:list<string>}
     */
    public function filterOptions(): array
    {
        if (!$this->tableExists('t99001')) {
            return [
                'clientes' => [],
                'emissores' => [],
                'assinantes' => [],
            ];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            $this->buildBaseSql() . "
                WHERE t.c_cod_programa = :programa
                  AND t.c_caminho LIKE :caminho
                ORDER BY t.dt_hr_recebimento DESC, t.id_t99001 DESC
                LIMIT " . self::MAX_RECORDS,
            [
                'programa' => 'nfe',
                'caminho' => '/nfe/envio/%',
            ]
        );

        $clientes = [];
        $emissores = [];
        $assinantes = [];

        foreach ($rows as $row) {
            $assinante = $this->extractSubscriber($row['t_assinante_json'] ?? null);
            $cliente = $this->displayClientName(
                $this->stringOrEmpty($row['cliente_xml_nome'] ?? null),
                $this->stringOrEmpty($row['cliente'] ?? null),
                $this->firstNonEmpty(
                    $this->stringOrEmpty($row['cliente_xml_documento'] ?? null),
                    $this->stringOrEmpty($row['cliente_documento'] ?? null)
                )
            );
            $emitente = $this->displayIssuerName(
                $this->stringOrEmpty($row['emitente_nome'] ?? null),
                $this->stringOrEmpty($row['emitente_documento'] ?? null),
                $assinante['nome'],
                $assinante['identificador']
            );

            $this->appendOption($clientes, $cliente);
            $this->appendOption($emissores, $emitente);
            $this->appendOption($assinantes, $assinante['nome']);
            $this->appendOption($assinantes, $assinante['identificador']);
        }

        sort($clientes);
        sort($emissores);
        sort($assinantes);

        return [
            'clientes' => array_values(array_unique($clientes)),
            'emissores' => array_values(array_unique($emissores)),
            'assinantes' => array_values(array_unique($assinantes)),
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
            't.c_caminho LIKE :caminho',
        ];
        $params = [
            'programa' => 'nfe',
            'caminho' => '/nfe/envio/%',
        ];
        $types = [];

        $environment = (int) trim((string) ($filters['ambiente'] ?? ''));
        if (in_array($environment, [1, 2], true) && $this->tableExists('t99010')) {
            $where[] = 'COALESCE(nfe_proc.tp_amb, 0) = :ambiente';
            $params['ambiente'] = $environment;
            $types['ambiente'] = ParameterType::INTEGER;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 't.dt_hr_recebimento >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 't.dt_hr_recebimento <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $numeroNota = trim((string) ($filters['numero_nota'] ?? ''));
        if ($numeroNota !== '') {
            $where[] = 'COALESCE(n.n_nf, \'\') LIKE :numero_nota';
            $params['numero_nota'] = '%' . $numeroNota . '%';
        }

        $cliente = trim((string) ($filters['cliente'] ?? ''));
        if ($cliente !== '') {
            $where[] = "(COALESCE(dest_xml.x_nome, '') LIKE :cliente OR COALESCE(dest.nome_razao_social, '') LIKE :cliente OR COALESCE(dest_xml.cnpj, '') LIKE :cliente OR COALESCE(dest_xml.cpf, '') LIKE :cliente OR COALESCE(dest.cnpj, '') LIKE :cliente)";
            $params['cliente'] = '%' . $cliente . '%';
        }

        /** @var list<int> $statusList */
        $statusList = array_values(array_filter(
            array_map(
                static fn (mixed $value): int => (int) $value,
                is_array($filters['status'] ?? null) ? $filters['status'] : [$filters['status'] ?? '']
            ),
            static fn (int $value): bool => $value > 0
        ));
        if ($statusList !== []) {
            $placeholders = [];
            foreach ($statusList as $index => $status) {
                $name = 'status_' . $index;
                $placeholders[] = ':' . $name;
                $params[$name] = $status;
                $types[$name] = ParameterType::INTEGER;
            }
            $where[] = 't.si_status_processamento IN (' . implode(', ', $placeholders) . ')';
        }

        $accessKey = trim((string) ($filters['chave'] ?? ''));
        if ($accessKey !== '') {
            $where[] = 'COALESCE(n.ch_nfe, \'\') LIKE :chave';
            $params['chave'] = '%' . $accessKey . '%';
        }

        $subscriber = trim((string) ($filters['assinante'] ?? ''));
        if ($subscriber !== '') {
            $where[] = 'COALESCE(t.t_assinante_json, \'\') LIKE :assinante';
            $params['assinante'] = '%' . $subscriber . '%';
        }

        $issuer = trim((string) ($filters['emissor'] ?? ''));
        if ($issuer !== '') {
            $where[] = '(COALESCE(emit.nome_razao_social, \'\') LIKE :emissor OR COALESCE(emit.cnpj, \'\') LIKE :emissor OR COALESCE(t.t_assinante_json, \'\') LIKE :emissor)';
            $params['emissor'] = '%' . $issuer . '%';
        }

        return [' WHERE ' . implode(' AND ', $where), $params, $types];
    }

    /**
     * @return list<string>
     */
    private function searchClientOptions(string $query): array
    {
        $options = [];
        $like = '%' . trim($query) . '%';

        if ($this->tableExists('t99021')) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $this->auditConnection->fetchAllAssociative(
                "
                    SELECT nome_razao_social AS nome, cnpj AS documento
                    FROM t99021
                    WHERE COALESCE(nome_razao_social, '') LIKE :query
                       OR COALESCE(cnpj, '') LIKE :query
                    ORDER BY nome_razao_social ASC
                    LIMIT 20
                ",
                ['query' => $like]
            );

            foreach ($rows as $row) {
                $this->appendOption($options, $this->stringOrEmpty($row['nome'] ?? null));
                $this->appendOption($options, $this->stringOrEmpty($row['documento'] ?? null));
            }
        }

        if ($this->tableExists('t99012')) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $this->auditConnection->fetchAllAssociative(
                "
                    SELECT x_nome AS nome, cnpj, cpf
                    FROM t99012
                    WHERE COALESCE(x_nome, '') LIKE :query
                       OR COALESCE(cnpj, '') LIKE :query
                       OR COALESCE(cpf, '') LIKE :query
                    ORDER BY x_nome ASC
                    LIMIT 20
                ",
                ['query' => $like]
            );

            foreach ($rows as $row) {
                $this->appendOption($options, $this->stringOrEmpty($row['nome'] ?? null));
                $this->appendOption($options, $this->stringOrEmpty($row['cnpj'] ?? null));
                $this->appendOption($options, $this->stringOrEmpty($row['cpf'] ?? null));
            }
        }

        return array_slice(array_values(array_unique($options)), 0, 20);
    }

    /**
     * @return list<string>
     */
    private function searchIssuerOptions(string $query): array
    {
        if (!$this->tableExists('t99020')) {
            return [];
        }

        $like = '%' . trim($query) . '%';
        $options = [];

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            "
                SELECT nome_razao_social AS nome, cnpj AS documento
                FROM t99020
                WHERE COALESCE(nome_razao_social, '') LIKE :query
                   OR COALESCE(cnpj, '') LIKE :query
                ORDER BY nome_razao_social ASC
                LIMIT 20
            ",
            ['query' => $like]
        );

        foreach ($rows as $row) {
            $this->appendOption($options, $this->stringOrEmpty($row['nome'] ?? null));
            $this->appendOption($options, $this->stringOrEmpty($row['documento'] ?? null));
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
            [
                'query' => $like,
            ]
        );

        foreach ($rows as $row) {
            $this->appendOption($options, $this->stringOrEmpty($row['c_nome'] ?? null));
            $this->appendOption($options, $this->stringOrEmpty($row['c_identificador'] ?? null));
        }

        return array_values(array_unique($options));
    }

    private function buildBaseSql(): string
    {
        $hasT99008 = $this->tableExists('t99008');
        $hasT99010 = $this->tableExists('t99010');
        $hasT99019 = $this->tableExists('t99019');
        $hasT99020 = $this->tableExists('t99020');
        $hasT99021 = $this->tableExists('t99021');
        $hasT99012 = $this->tableExists('t99012');
        $hasT99023 = $this->tableExists('t99023');
        $hasT99024 = $this->tableExists('t99024');
        $hasT99016 = $this->tableExists('t99016');
        $hasT99032 = $this->tableExists('t99032');
        $hasT99033 = $this->tableExists('t99033');
        $hasFiscalSituation = $hasT99019 && $this->tableHasColumn('t99019', 'situacao_fiscal');

        $docJoin = $hasT99008 ? <<<'SQL'
            LEFT JOIN (
                SELECT d.u_c_request_id, MAX(d.id_t99008) AS last_t99008_id
                FROM t99008 d
                WHERE d.schema_family = 'procNFe'
                GROUP BY d.u_c_request_id
            ) last_doc ON last_doc.u_c_request_id = t.u_c_request_id
        SQL : '';

        $noteJoin = $hasT99019 ? 'LEFT JOIN t99019 n ON n.t99008_id = last_doc.last_t99008_id' : '';
        $processedNfeJoin = $hasT99010 ? 'LEFT JOIN t99010 nfe_proc ON nfe_proc.t99008_id = last_doc.last_t99008_id' : '';
        $environmentSelect = $hasT99010 ? 'nfe_proc.tp_amb AS ambiente' : 'NULL AS ambiente';
        $issuerPivotJoin = $hasT99023 ? 'LEFT JOIN t99023 rel_emit ON rel_emit.t99019_id = n.id_t99019' : '';
        $issuerJoin = $hasT99020 ? 'LEFT JOIN t99020 emit ON emit.id_t99020 = rel_emit.t99020_id' : '';
        $destPivotJoin = $hasT99024 ? 'LEFT JOIN t99024 rel_dest ON rel_dest.t99019_id = n.id_t99019' : '';
        $destJoin = $hasT99021 ? 'LEFT JOIN t99021 dest ON dest.id_t99021 = rel_dest.t99021_id' : '';
        $destExtractJoin = $hasT99012 ? 'LEFT JOIN t99012 dest_xml ON dest_xml.t99008_id = last_doc.last_t99008_id' : '';
        $issuerNameSelect = ($hasT99020 && $hasT99023) ? 'emit.nome_razao_social AS emitente_nome' : "'' AS emitente_nome";
        $issuerDocumentSelect = ($hasT99020 && $hasT99023) ? 'emit.cnpj AS emitente_documento' : "'' AS emitente_documento";
        $clientExtractNameSelect = $hasT99012 ? 'dest_xml.x_nome AS cliente_xml_nome' : "'' AS cliente_xml_nome";
        $clientExtractDocumentSelect = $hasT99012 ? "COALESCE(dest_xml.cnpj, dest_xml.cpf, dest_xml.id_estrangeiro, '') AS cliente_xml_documento" : "'' AS cliente_xml_documento";
        $fiscalSituationSelect = $hasFiscalSituation ? 'n.situacao_fiscal AS situacao_fiscal' : "'' AS situacao_fiscal";
        $cancellationJoin = ($hasT99008 && $hasT99016) ? <<<'SQL'
            LEFT JOIN (
                SELECT
                    evt.ch_nfe,
                    MAX(evt.t99008_id) AS last_cancel_t99008_id
                FROM t99016 evt
                WHERE evt.tp_evento = '110111'
                  AND evt.c_stat IN (101, 135, 155)
                GROUP BY evt.ch_nfe
            ) cancel_ref ON cancel_ref.ch_nfe = n.ch_nfe
            LEFT JOIN t99016 cancel_evt ON cancel_evt.t99008_id = cancel_ref.last_cancel_t99008_id
            LEFT JOIN t99008 cancel_doc ON cancel_doc.id_t99008 = cancel_ref.last_cancel_t99008_id
            LEFT JOIN t99001 cancel_req ON cancel_req.u_c_request_id = cancel_doc.u_c_request_id
        SQL : '';
        $cancellationSelect = ($hasT99008 && $hasT99016) ? <<<'SQL'
                cancel_req.u_c_request_id AS cancelamento_request_id,
                cancel_evt.c_stat AS cancelamento_c_stat,
                cancel_evt.x_motivo AS cancelamento_motivo,
                cancel_evt.n_prot AS cancelamento_protocolo,
                cancel_evt.dh_evento AS cancelamento_data,
        SQL : <<<'SQL'
                NULL AS cancelamento_request_id,
                NULL AS cancelamento_c_stat,
                NULL AS cancelamento_motivo,
                NULL AS cancelamento_protocolo,
                NULL AS cancelamento_data,
        SQL;
        $taxJoin = ($hasT99032 && $hasT99033) ? <<<'SQL'
            LEFT JOIN (
                SELECT
                    t32.t99019_id,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'ICMS' THEN t33.valor END) AS icms_valor,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'ICMS' THEN t33.base_calculo END) AS icms_base,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'ICMS' THEN t33.aliquota END) AS icms_aliquota,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'COFINS' THEN t33.valor END) AS cofins_valor,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'COFINS' THEN t33.base_calculo END) AS cofins_base,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'COFINS' THEN t33.aliquota END) AS cofins_aliquota,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'PIS' THEN t33.valor END) AS pis_valor,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'PIS' THEN t33.base_calculo END) AS pis_base,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'PIS' THEN t33.aliquota END) AS pis_aliquota,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IPI' THEN t33.valor END) AS ipi_valor,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IPI' THEN t33.base_calculo END) AS ipi_base,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IPI' THEN t33.aliquota END) AS ipi_aliquota,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'CBS' THEN t33.valor END) AS cbs_valor,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'CBS' THEN t33.base_calculo END) AS cbs_base,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'CBS' THEN t33.aliquota END) AS cbs_aliquota,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IS' THEN t33.valor END) AS is_valor,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IS' THEN t33.base_calculo END) AS is_base,
                    MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IS' THEN t33.aliquota END) AS is_aliquota,
                    COALESCE(MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IBS' THEN t33.valor END), 0)
                        + COALESCE(SUM(CASE WHEN UPPER(t33.nome_imposto) IN ('IBSUF', 'IBSMUN') THEN t33.valor ELSE 0 END), 0) AS ibs_valor,
                    COALESCE(MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IBS' THEN t33.base_calculo END),
                        MAX(CASE WHEN UPPER(t33.nome_imposto) IN ('IBSUF', 'IBSMUN') THEN t33.base_calculo END)) AS ibs_base,
                    COALESCE(MAX(CASE WHEN UPPER(t33.nome_imposto) = 'IBS' THEN t33.aliquota END), 0)
                        + COALESCE(SUM(CASE WHEN UPPER(t33.nome_imposto) IN ('IBSUF', 'IBSMUN') THEN t33.aliquota ELSE 0 END), 0) AS ibs_aliquota
                FROM t99032 t32
                INNER JOIN t99033 t33 ON t33.t99032_id = t32.id_t99032
                GROUP BY t32.t99019_id
            ) taxes ON taxes.t99019_id = n.id_t99019
        SQL : '';

        return <<<SQL
            SELECT
                t.u_c_request_id AS request_id,
                t.si_status_processamento,
                t.si_status_http,
                t.dt_hr_recebimento,
                t.t_erro,
                t.t_corpo_resposta,
                t.t_assinante_json,
                n.id_t99019,
                n.n_nf AS numero_nota,
                n.ch_nfe AS chave_nfe,
                n.mod AS modelo_nota,
                n.serie AS serie_nota,
                n.dh_emi AS data_emissao,
                n.v_nf AS valor_total,
                n.xml_autorizado,
                n.caminho_danfe,
                {$fiscalSituationSelect},
                {$environmentSelect},
                {$issuerNameSelect},
                {$issuerDocumentSelect},
                {$clientExtractNameSelect},
                {$clientExtractDocumentSelect},
                {$cancellationSelect}
                dest.nome_razao_social AS cliente,
                dest.cnpj AS cliente_documento,
                taxes.icms_valor,
                taxes.icms_base,
                taxes.icms_aliquota,
                taxes.cofins_valor,
                taxes.cofins_base,
                taxes.cofins_aliquota,
                taxes.pis_valor,
                taxes.pis_base,
                taxes.pis_aliquota,
                taxes.ipi_valor,
                taxes.ipi_base,
                taxes.ipi_aliquota,
                taxes.ibs_valor,
                taxes.ibs_base,
                taxes.ibs_aliquota,
                taxes.cbs_valor,
                taxes.cbs_base,
                taxes.cbs_aliquota,
                taxes.is_valor,
                taxes.is_base,
                taxes.is_aliquota
            FROM t99001 t
            {$docJoin}
            {$noteJoin}
            {$processedNfeJoin}
            {$issuerPivotJoin}
            {$issuerJoin}
            {$destPivotJoin}
            {$destJoin}
            {$destExtractJoin}
            {$cancellationJoin}
            {$taxJoin}
        SQL;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $requestId = trim((string) ($row['request_id'] ?? ''));
        $assinante = $this->extractSubscriber($row['t_assinante_json'] ?? null);
        $xmlCompleto = $this->extractAuthorizedXml($row['xml_autorizado'] ?? null, $row['t_corpo_resposta'] ?? null);
        $danfeBase64 = $this->extractDanfeBase64($row['t_corpo_resposta'] ?? null);
        $hasDanfe = $this->stringOrEmpty($row['caminho_danfe'] ?? null) !== '' || $danfeBase64 !== '';
        $clienteDocumento = $this->firstNonEmpty(
            $this->stringOrEmpty($row['cliente_xml_documento'] ?? null),
            $this->stringOrEmpty($row['cliente_documento'] ?? null)
        );
        $emitenteDocumento = $this->stringOrEmpty($row['emitente_documento'] ?? null);
        $cliente = $this->displayClientName(
            $this->stringOrEmpty($row['cliente_xml_nome'] ?? null),
            $this->stringOrEmpty($row['cliente'] ?? null),
            $clienteDocumento
        );
        $emitente = $this->displayIssuerName(
            $this->stringOrEmpty($row['emitente_nome'] ?? null),
            $emitenteDocumento,
            $assinante['nome'],
            $assinante['identificador']
        );
        $noteId = isset($row['id_t99019']) ? (int) $row['id_t99019'] : 0;
        $fiscalEvents = $this->findFiscalEventsByNoteId($noteId);

        return [
            'note_id' => $noteId,
            'request_id' => $requestId,
            'numero_nota' => $this->stringOrEmpty($row['numero_nota'] ?? null),
            'cliente' => $cliente,
            'cliente_documento' => $clienteDocumento,
            'emitente_nome' => $emitente,
            'emitente_documento' => $emitenteDocumento,
            'assinante_identificador' => $assinante['identificador'],
            'assinante_nome' => $assinante['nome'],
            'chave_nfe' => $this->stringOrEmpty($row['chave_nfe'] ?? null),
            'data_envio' => $row['dt_hr_recebimento'] ?? null,
            'data_emissao' => $row['data_emissao'] ?? null,
            'valor_total' => $this->decimalOrEmpty($row['valor_total'] ?? null),
            'ambiente' => $this->stringOrEmpty($row['ambiente'] ?? null),
            'status_envio' => $this->mapStatus((int) ($row['si_status_processamento'] ?? 0)),
            'situacao_nfe' => $this->displayFiscalSituation($row, $fiscalEvents),
            'eventos_nfe' => $fiscalEvents,
            'status_http' => isset($row['si_status_http']) ? (int) $row['si_status_http'] : null,
            'erro' => $this->stringOrEmpty($row['t_erro'] ?? null),
            'xml_autorizado' => $xmlCompleto,
            'caminho_danfe' => $this->stringOrEmpty($row['caminho_danfe'] ?? null),
            'danfe_base64' => $danfeBase64,
            'danfe_url' => $hasDanfe ? '/monitor-saida-nfe/danfe/' . $requestId : '',
            'xml_url' => $xmlCompleto !== '' ? '/monitor-saida-nfe/xml/' . $requestId : '',
            'cancelamento' => $this->cancellationPayload($row),
            'acoes_nfe' => $this->buildNfeActionsPayload($row, $emitenteDocumento),
            'impostos' => [
                'ICMS' => $this->taxPayload($row, 'icms'),
                'COFINS' => $this->taxPayload($row, 'cofins'),
                'PIS' => $this->taxPayload($row, 'pis'),
                'IPI' => $this->taxPayload($row, 'ipi'),
                'IBS' => $this->taxPayload($row, 'ibs'),
                'CBS' => $this->taxPayload($row, 'cbs'),
                'IS' => $this->taxPayload($row, 'is'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function buildNfeActionsPayload(array $row, string $issuerDocument): array
    {
        $numeroNota = $this->stringOrEmpty($row['numero_nota'] ?? null);
        $dataEmissao = $this->stringOrEmpty($row['data_emissao'] ?? null);
        $chave = $this->stringOrEmpty($row['chave_nfe'] ?? null);
        $ano = $this->yearForInutilization($dataEmissao, $chave);
        $isCanceled = $this->isCancellationSuccessful($row);

        return [
            'cancelar_url' => $isCanceled ? '' : '/nfe/eventos/cancelar',
            'inutilizar_url' => '/nfe/inutilizacao/inutilizar',
            'chave' => $chave,
            'cnpj_emitente' => preg_replace('/\D+/', '', $issuerDocument) ?? '',
            'ano' => $ano,
            'modelo' => $this->firstNonEmpty($this->stringOrEmpty($row['modelo_nota'] ?? null), '55'),
            'serie' => $this->stringOrEmpty($row['serie_nota'] ?? null),
            'numero_inicial' => $numeroNota,
            'numero_final' => $numeroNota,
            'lote' => '1',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{cancelada:bool,request_id:string,c_stat:string,motivo:string,protocolo:string,data:string}
     */
    private function cancellationPayload(array $row): array
    {
        return [
            'cancelada' => $this->isCancellationSuccessful($row),
            'request_id' => $this->stringOrEmpty($row['cancelamento_request_id'] ?? null),
            'c_stat' => $this->stringOrEmpty($row['cancelamento_c_stat'] ?? null),
            'motivo' => $this->stringOrEmpty($row['cancelamento_motivo'] ?? null),
            'protocolo' => $this->stringOrEmpty($row['cancelamento_protocolo'] ?? null),
            'data' => $this->stringOrEmpty($row['cancelamento_data'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isCancellationSuccessful(array $row): bool
    {
        return $this->stringOrEmpty($row['situacao_fiscal'] ?? null) === 'Cancelada'
            || in_array((int) ($row['cancelamento_c_stat'] ?? 0), [101, 135, 155], true);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $events
     */
    private function displayFiscalSituation(array $row, array $events): string
    {
        $stored = $this->stringOrEmpty($row['situacao_fiscal'] ?? null);
        if ($stored !== '') {
            return $stored;
        }

        foreach ($events as $event) {
            $situation = $this->stringOrEmpty($event['situacao'] ?? null);
            if (in_array($situation, ['Cancelada', 'Inutilizada'], true)) {
                return $situation;
            }
        }

        return (int) ($row['si_status_processamento'] ?? 0) === ApiRequestStatus::CONCLUIDA ? 'Autorizada' : 'Sem situação';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findFiscalEventsByNoteId(int $noteId): array
    {
        if ($noteId <= 0 || !$this->tableExists('t99034')) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT *
            FROM t99034
            WHERE t99019_id = :note_id
            ORDER BY dh_evento DESC, id_t99034 DESC
            SQL,
            ['note_id' => $noteId]
        );

        return array_map(function (array $event): array {
            return [
                'id' => isset($event['id_t99034']) ? (int) $event['id_t99034'] : null,
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
        }, $rows);
    }

    private function yearForInutilization(string $issueDate, string $accessKey): string
    {
        if ($issueDate !== '') {
            try {
                return (new \DateTimeImmutable($issueDate))->format('y');
            } catch (\Throwable) {
            }
        }

        $digits = preg_replace('/\D+/', '', $accessKey) ?? '';
        if (strlen($digits) >= 4) {
            return substr($digits, 2, 2);
        }

        return '';
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

    private function extractAuthorizedXml(mixed $xmlAutorizado, mixed $responseBody): string
    {
        $value = $this->stringOrEmpty($xmlAutorizado);
        if ($value !== '') {
            return $value;
        }

        $decoded = $this->decodeResponseBody($responseBody);
        if ($decoded === null) {
            return '';
        }

        $result = $decoded['xml_autorizado'] ?? $decoded['resultado']['xml_autorizado'] ?? null;

        return is_string($result) ? trim($result) : '';
    }

    private function extractAuthorizationEventXml(mixed $responseBody): string
    {
        $decoded = $this->decodeResponseBody($responseBody);
        if ($decoded === null) {
            return '';
        }

        $eventXml = $decoded['resultado']['XML'] ?? $decoded['resultado']['xml'] ?? null;
        if (is_string($eventXml) && trim($eventXml) !== '') {
            return trim($eventXml);
        }

        $message = $decoded['resultado']['mensagem'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            return '';
        }

        if (preg_match('/XML=(.+?)(?:\n[a-zA-Z][a-zA-Z0-9]+=|\z)/s', $message, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    private function extractDanfeBase64(mixed $responseBody): string
    {
        $decoded = $this->decodeResponseBody($responseBody);
        if ($decoded === null) {
            return '';
        }

        $value = $decoded['danfe_base64'] ?? $decoded['resultado']['danfe_base64'] ?? null;
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeResponseBody(mixed $responseBody): ?array
    {
        $body = $this->stringOrEmpty($responseBody);
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>|null
     */
    private function taxPayload(array $row, string $prefix): ?array
    {
        $valor = $this->decimalOrEmpty($row[$prefix . '_valor'] ?? null);
        $base = $this->decimalOrEmpty($row[$prefix . '_base'] ?? null);
        $aliquota = $this->decimalOrEmpty($row[$prefix . '_aliquota'] ?? null);

        if ($valor === '' && $base === '' && $aliquota === '') {
            return null;
        }

        return [
            'base_calculo' => $base,
            'aliquota' => $aliquota,
            'valor' => $valor,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findItemsByNoteId(int $noteId): array
    {
        if (
            $noteId <= 0
            || !$this->tableExists('t99026')
            || !$this->tableExists('t99031')
            || !$this->tableExists('t99027')
        ) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            <<<SQL
            SELECT
                item.id_t99026,
                item.num,
                item.descricao,
                item.qtd,
                item.quantidade_comercial,
                item.unidade_comercial,
                item.valor,
                item.valor_unitario_comercializacao,
                item.codigo_produto,
                item.codigo_ncm,
                item.cfop,
                item.valor_desconto,
                item.valor_total_frete,
                item.valor_seguro,
                item.valor_aproximado_tributos,
                mod.id_t99031,
                mod.tag,
                mod.tag_api,
                imp.nome_imposto,
                imp.cst,
                imp.base_calculo,
                imp.aliquota,
                imp.valor
            FROM t99026 item
            LEFT JOIN t99031 mod ON mod.t99026_id = item.id_t99026
            LEFT JOIN t99027 imp ON imp.t99031_id = mod.id_t99031
            WHERE item.t99019_id = :note_id
            ORDER BY item.num ASC, item.id_t99026 ASC, mod.id_t99031 ASC, imp.id_t99027 ASC
            SQL,
            ['note_id' => $noteId],
            ['note_id' => ParameterType::INTEGER]
        );

        $items = [];
        foreach ($rows as $row) {
            $itemId = (int) ($row['id_t99026'] ?? 0);
            if (!isset($items[$itemId])) {
                $items[$itemId] = [
                    'numero' => isset($row['num']) ? (int) $row['num'] : null,
                    'descricao' => $this->stringOrEmpty($row['descricao'] ?? null),
                    'codigo_produto' => $this->stringOrEmpty($row['codigo_produto'] ?? null),
                    'codigo_ncm' => $this->stringOrEmpty($row['codigo_ncm'] ?? null),
                    'cfop' => $this->stringOrEmpty($row['cfop'] ?? null),
                    'quantidade' => $this->decimalOrEmpty($row['quantidade_comercial'] ?? $row['qtd'] ?? null),
                    'unidade' => $this->stringOrEmpty($row['unidade_comercial'] ?? null),
                    'valor_unitario' => $this->decimalOrEmpty($row['valor_unitario_comercializacao'] ?? $row['valor'] ?? null),
                    'valor_total' => $this->decimalOrEmpty($row['valor'] ?? null),
                    'valor_desconto' => $this->decimalOrEmpty($row['valor_desconto'] ?? null),
                    'valor_frete' => $this->decimalOrEmpty($row['valor_total_frete'] ?? null),
                    'valor_seguro' => $this->decimalOrEmpty($row['valor_seguro'] ?? null),
                    'valor_aproximado_tributos' => $this->decimalOrEmpty($row['valor_aproximado_tributos'] ?? null),
                    'impostos' => [],
                ];
            }

            $taxName = $this->stringOrEmpty($row['nome_imposto'] ?? null);
            if ($taxName === '') {
                continue;
            }

            $items[$itemId]['impostos'][] = [
                'nome' => $taxName,
                'cst' => $this->stringOrEmpty($row['cst'] ?? null),
                'base_calculo' => $this->decimalOrEmpty($row['base_calculo'] ?? null),
                'aliquota' => $this->decimalOrEmpty($row['aliquota'] ?? null),
                'valor' => $this->decimalOrEmpty($row['valor'] ?? null),
                'modalidade' => $this->stringOrEmpty($row['tag'] ?? null),
                'modalidade_api' => $this->stringOrEmpty($row['tag_api'] ?? null),
            ];
        }

        return array_values($items);
    }

    private function mapStatus(int $status): string
    {
        return match ($status) {
            ApiRequestStatus::CONCLUIDA => 'Transmitida',
            ApiRequestStatus::FALHA => 'Falha',
            ApiRequestStatus::PROCESSANDO => 'Processando',
            ApiRequestStatus::ENFILEIRADA => 'Enfileirada',
            ApiRequestStatus::NAO_AUTORIZADA => 'Nao autorizada',
            default => 'Recebida',
        };
    }

    private function displayClientName(string $xmlName, string $linkedName, string $document): string
    {
        if ($xmlName !== '' && !$this->isHomologationPlaceholder($xmlName)) {
            return $xmlName;
        }

        if ($linkedName !== '' && !$this->isHomologationPlaceholder($linkedName)) {
            return $linkedName;
        }

        if ($document !== '') {
            return $document;
        }

        return $this->firstNonEmpty($xmlName, $linkedName);
    }

    private function displayIssuerName(string $name, string $document, string $subscriberName, string $subscriberIdentifier): string
    {
        if ($this->isHomologationPlaceholder($name)) {
            return $subscriberName !== '' ? $subscriberName : $subscriberIdentifier;
        }

        if ($name !== '') {
            return $name;
        }

        return $document;
    }

    private function isHomologationPlaceholder(string $value): bool
    {
        return mb_strtoupper(trim($value)) === self::HOMOLOGATION_PLACEHOLDER;
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

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        return $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        return $this->auditConnection->createSchemaManager()->introspectTable($table)->hasColumn($column);
    }
}
