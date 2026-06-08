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
    public function storeDistributionExecution(array $payload): int
    {
        $this->ensureSchema();

        $this->auditConnection->insert('t99007', [
            't99001_id' => (int) ($payload['t99001_id'] ?? 0),
            'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
            'c_caminho_origem' => $this->nullableTrim($payload['c_caminho_origem'] ?? null),
            'c_tipo_consulta' => $this->nullableTrim($payload['c_tipo_consulta'] ?? null),
            'c_documento_consulta' => $this->nullableTrim($payload['c_documento_consulta'] ?? null),
            'c_nsu_entrada' => $this->nullableTrim($payload['c_nsu_entrada'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'c_ver_aplic' => $this->nullableTrim($payload['c_ver_aplic'] ?? null),
            'c_stat' => $this->nullableInt($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'dh_resp' => $this->normalizeDateTime($payload['dh_resp'] ?? null),
            'c_ult_nsu' => $this->nullableTrim($payload['c_ult_nsu'] ?? null),
            'c_max_nsu' => $this->nullableTrim($payload['c_max_nsu'] ?? null),
            'q_doc_zip' => $this->nullableInt($payload['q_doc_zip'] ?? null) ?? 0,
            't_xml_envelope' => $payload['t_xml_envelope'] ?? null,
            'dt_hr_atu' => date('c'),
        ]);

        return (int) $this->auditConnection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function storeDistributionDocument(array $payload): int
    {
        $this->ensureSchema();

        $schemaName = $this->nullableTrim($payload['c_schema_name'] ?? null);
        $nsu = $this->nullableTrim($payload['c_nsu'] ?? null);
        $hash = $this->nullableTrim($payload['c_hash_xml'] ?? null);

        if ($schemaName !== null && $nsu !== null && $hash !== null) {
            $existingId = $this->auditConnection->fetchOne(
                'SELECT id_t99008 FROM t99008 WHERE c_schema_name = :schema AND c_nsu = :nsu AND c_hash_xml = :hash LIMIT 1',
                ['schema' => $schemaName, 'nsu' => $nsu, 'hash' => $hash]
            );

            if ($existingId !== false) {
                $this->auditConnection->update('t99008', [
                    't99007_id' => (int) ($payload['t99007_id'] ?? 0),
                    'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
                    'c_caminho_origem' => $this->nullableTrim($payload['c_caminho_origem'] ?? null),
                    'c_schema_family' => $this->nullableTrim($payload['c_schema_family'] ?? null),
                    'c_ch_nfe' => $this->nullableTrim($payload['c_ch_nfe'] ?? null),
                    'c_tp_evento' => $this->nullableTrim($payload['c_tp_evento'] ?? null),
                    'i_n_seq_evento' => $this->nullableInt($payload['i_n_seq_evento'] ?? null),
                    'c_n_prot' => $this->nullableTrim($payload['c_n_prot'] ?? null),
                    't_xml_gzip_base64' => $payload['t_xml_gzip_base64'] ?? null,
                    't_xml_descompactado' => $payload['t_xml_descompactado'] ?? null,
                    'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
                    'c_emit_cnpj_cpf' => $this->nullableTrim($payload['c_emit_cnpj_cpf'] ?? null),
                    'c_dest_cnpj_cpf' => $this->nullableTrim($payload['c_dest_cnpj_cpf'] ?? null),
                    'dt_hr_processado_em' => date('c'),
                    'dt_hr_atu' => date('c'),
                ], ['id_t99008' => (int) $existingId]);

                return (int) $existingId;
            }
        }

        $this->auditConnection->insert('t99008', [
            't99007_id' => (int) ($payload['t99007_id'] ?? 0),
            'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
            'c_caminho_origem' => $this->nullableTrim($payload['c_caminho_origem'] ?? null),
            'c_nsu' => $nsu,
            'c_schema_name' => $schemaName,
            'c_schema_family' => $this->nullableTrim($payload['c_schema_family'] ?? null),
            'c_ch_nfe' => $this->nullableTrim($payload['c_ch_nfe'] ?? null),
            'c_tp_evento' => $this->nullableTrim($payload['c_tp_evento'] ?? null),
            'i_n_seq_evento' => $this->nullableInt($payload['i_n_seq_evento'] ?? null),
            'c_n_prot' => $this->nullableTrim($payload['c_n_prot'] ?? null),
            't_xml_gzip_base64' => $payload['t_xml_gzip_base64'] ?? null,
            't_xml_descompactado' => $payload['t_xml_descompactado'] ?? null,
            'c_hash_xml' => $hash,
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'c_emit_cnpj_cpf' => $this->nullableTrim($payload['c_emit_cnpj_cpf'] ?? null),
            'c_dest_cnpj_cpf' => $this->nullableTrim($payload['c_dest_cnpj_cpf'] ?? null),
            'dt_hr_processado_em' => date('c'),
            'dt_hr_atu' => date('c'),
        ]);

        return (int) $this->auditConnection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeResumo(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99009', 't99008_id', $documentId, [
            'c_emit_cnpj' => $this->nullableTrim($payload['c_emit_cnpj'] ?? null),
            'c_emit_cpf' => $this->nullableTrim($payload['c_emit_cpf'] ?? null),
            'x_emit_nome' => $this->nullableTrim($payload['x_emit_nome'] ?? null),
            'c_emit_ie' => $this->nullableTrim($payload['c_emit_ie'] ?? null),
            'dh_emi' => $this->normalizeDateTime($payload['dh_emi'] ?? null),
            'tp_nf' => $this->nullableInt($payload['tp_nf'] ?? null),
            'v_nf' => $this->nullableDecimal($payload['v_nf'] ?? null),
            'c_dig_val' => $this->nullableTrim($payload['c_dig_val'] ?? null),
            'dh_recbto' => $this->normalizeDateTime($payload['dh_recbto'] ?? null),
            'c_sit_nfe' => $this->nullableTrim($payload['c_sit_nfe'] ?? null),
            'c_versao' => $this->nullableTrim($payload['c_versao'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeProc(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99010', 't99008_id', $documentId, [
            'c_versao' => $this->nullableTrim($payload['c_versao'] ?? null),
            'c_id_nfe' => $this->nullableTrim($payload['c_id_nfe'] ?? null),
            'c_uf' => $this->nullableTrim($payload['c_uf'] ?? null),
            'c_nnf' => $this->nullableTrim($payload['c_nnf'] ?? null),
            'c_serie' => $this->nullableTrim($payload['c_serie'] ?? null),
            'c_mod' => $this->nullableTrim($payload['c_mod'] ?? null),
            'dh_emi' => $this->normalizeDateTime($payload['dh_emi'] ?? null),
            'dh_saida_entrada' => $this->normalizeDateTime($payload['dh_saida_entrada'] ?? null),
            'tp_nf' => $this->nullableInt($payload['tp_nf'] ?? null),
            'c_id_dest' => $this->nullableTrim($payload['c_id_dest'] ?? null),
            'c_mun_fg' => $this->nullableTrim($payload['c_mun_fg'] ?? null),
            'c_tp_emis' => $this->nullableTrim($payload['c_tp_emis'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'c_fin_nfe' => $this->nullableTrim($payload['c_fin_nfe'] ?? null),
            'c_ind_final' => $this->nullableTrim($payload['c_ind_final'] ?? null),
            'c_ind_pres' => $this->nullableTrim($payload['c_ind_pres'] ?? null),
            'c_proc_emi' => $this->nullableTrim($payload['c_proc_emi'] ?? null),
            'c_ver_proc' => $this->nullableTrim($payload['c_ver_proc'] ?? null),
            'c_n_prot' => $this->nullableTrim($payload['c_n_prot'] ?? null),
            'c_ch_nfe' => $this->nullableTrim($payload['c_ch_nfe'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeEmitente(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99011', 't99008_id', $documentId, [
            'c_cnpj' => $this->nullableTrim($payload['c_cnpj'] ?? null),
            'c_cpf' => $this->nullableTrim($payload['c_cpf'] ?? null),
            'x_nome' => $this->nullableTrim($payload['x_nome'] ?? null),
            'x_fant' => $this->nullableTrim($payload['x_fant'] ?? null),
            'c_ie' => $this->nullableTrim($payload['c_ie'] ?? null),
            'c_iest' => $this->nullableTrim($payload['c_iest'] ?? null),
            'c_im' => $this->nullableTrim($payload['c_im'] ?? null),
            'c_cnae' => $this->nullableTrim($payload['c_cnae'] ?? null),
            'c_crt' => $this->nullableTrim($payload['c_crt'] ?? null),
            'x_lgr' => $this->nullableTrim($payload['x_lgr'] ?? null),
            'c_nro' => $this->nullableTrim($payload['c_nro'] ?? null),
            'x_bairro' => $this->nullableTrim($payload['x_bairro'] ?? null),
            'c_mun' => $this->nullableTrim($payload['c_mun'] ?? null),
            'x_mun' => $this->nullableTrim($payload['x_mun'] ?? null),
            'c_uf' => $this->nullableTrim($payload['c_uf'] ?? null),
            'c_cep' => $this->nullableTrim($payload['c_cep'] ?? null),
            'c_pais' => $this->nullableTrim($payload['c_pais'] ?? null),
            'x_pais' => $this->nullableTrim($payload['x_pais'] ?? null),
            'c_fone' => $this->nullableTrim($payload['c_fone'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeDestinatario(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99012', 't99008_id', $documentId, [
            'c_cnpj' => $this->nullableTrim($payload['c_cnpj'] ?? null),
            'c_cpf' => $this->nullableTrim($payload['c_cpf'] ?? null),
            'c_id_estrangeiro' => $this->nullableTrim($payload['c_id_estrangeiro'] ?? null),
            'x_nome' => $this->nullableTrim($payload['x_nome'] ?? null),
            'c_ind_ie_dest' => $this->nullableTrim($payload['c_ind_ie_dest'] ?? null),
            'c_ie' => $this->nullableTrim($payload['c_ie'] ?? null),
            'c_isuf' => $this->nullableTrim($payload['c_isuf'] ?? null),
            'c_im' => $this->nullableTrim($payload['c_im'] ?? null),
            'c_email' => $this->nullableTrim($payload['c_email'] ?? null),
            'x_lgr' => $this->nullableTrim($payload['x_lgr'] ?? null),
            'c_nro' => $this->nullableTrim($payload['c_nro'] ?? null),
            'x_bairro' => $this->nullableTrim($payload['x_bairro'] ?? null),
            'c_mun' => $this->nullableTrim($payload['c_mun'] ?? null),
            'x_mun' => $this->nullableTrim($payload['x_mun'] ?? null),
            'c_uf' => $this->nullableTrim($payload['c_uf'] ?? null),
            'c_cep' => $this->nullableTrim($payload['c_cep'] ?? null),
            'c_pais' => $this->nullableTrim($payload['c_pais'] ?? null),
            'x_pais' => $this->nullableTrim($payload['x_pais'] ?? null),
            'c_fone' => $this->nullableTrim($payload['c_fone'] ?? null),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function replaceNfeItens(int $documentId, array $items): void
    {
        $this->ensureSchema();
        $this->auditConnection->delete('t99013', ['t99008_id' => $documentId]);

        foreach ($items as $item) {
            $this->auditConnection->insert('t99013', [
                't99008_id' => $documentId,
                'i_n_item' => $this->nullableInt($item['i_n_item'] ?? null),
                'c_prod' => $this->nullableTrim($item['c_prod'] ?? null),
                'c_ean' => $this->nullableTrim($item['c_ean'] ?? null),
                'x_prod' => $this->nullableTrim($item['x_prod'] ?? null),
                'c_ncm' => $this->nullableTrim($item['c_ncm'] ?? null),
                'c_cest' => $this->nullableTrim($item['c_cest'] ?? null),
                'c_cfop' => $this->nullableTrim($item['c_cfop'] ?? null),
                'c_ucom' => $this->nullableTrim($item['c_ucom'] ?? null),
                'q_com' => $this->nullableDecimal($item['q_com'] ?? null),
                'v_un_com' => $this->nullableDecimal($item['v_un_com'] ?? null),
                'v_prod' => $this->nullableDecimal($item['v_prod'] ?? null),
                'c_ean_trib' => $this->nullableTrim($item['c_ean_trib'] ?? null),
                'c_utrib' => $this->nullableTrim($item['c_utrib'] ?? null),
                'q_trib' => $this->nullableDecimal($item['q_trib'] ?? null),
                'v_un_trib' => $this->nullableDecimal($item['v_un_trib'] ?? null),
                'v_frete' => $this->nullableDecimal($item['v_frete'] ?? null),
                'v_seg' => $this->nullableDecimal($item['v_seg'] ?? null),
                'v_desc' => $this->nullableDecimal($item['v_desc'] ?? null),
                'i_ind_tot' => $this->nullableInt($item['i_ind_tot'] ?? null),
                't_inf_ad_prod' => $item['t_inf_ad_prod'] ?? null,
                'dt_hr_atu' => date('c'),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeTotal(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99014', 't99008_id', $documentId, [
            'v_bc' => $this->nullableDecimal($payload['v_bc'] ?? null),
            'v_icms' => $this->nullableDecimal($payload['v_icms'] ?? null),
            'v_icms_deson' => $this->nullableDecimal($payload['v_icms_deson'] ?? null),
            'v_fcp' => $this->nullableDecimal($payload['v_fcp'] ?? null),
            'v_bcst' => $this->nullableDecimal($payload['v_bcst'] ?? null),
            'v_st' => $this->nullableDecimal($payload['v_st'] ?? null),
            'v_fcpst' => $this->nullableDecimal($payload['v_fcpst'] ?? null),
            'v_prod' => $this->nullableDecimal($payload['v_prod'] ?? null),
            'v_frete' => $this->nullableDecimal($payload['v_frete'] ?? null),
            'v_seg' => $this->nullableDecimal($payload['v_seg'] ?? null),
            'v_desc' => $this->nullableDecimal($payload['v_desc'] ?? null),
            'v_ii' => $this->nullableDecimal($payload['v_ii'] ?? null),
            'v_ipi' => $this->nullableDecimal($payload['v_ipi'] ?? null),
            'v_ipi_devol' => $this->nullableDecimal($payload['v_ipi_devol'] ?? null),
            'v_pis' => $this->nullableDecimal($payload['v_pis'] ?? null),
            'v_cofins' => $this->nullableDecimal($payload['v_cofins'] ?? null),
            'v_outro' => $this->nullableDecimal($payload['v_outro'] ?? null),
            'v_nf' => $this->nullableDecimal($payload['v_nf'] ?? null),
            'v_tot_trib' => $this->nullableDecimal($payload['v_tot_trib'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertEventoResumo(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99015', 't99008_id', $documentId, [
            'c_orgao' => $this->nullableTrim($payload['c_orgao'] ?? null),
            'c_cnpj' => $this->nullableTrim($payload['c_cnpj'] ?? null),
            'c_cpf' => $this->nullableTrim($payload['c_cpf'] ?? null),
            'c_ch_nfe' => $this->nullableTrim($payload['c_ch_nfe'] ?? null),
            'dh_evento' => $this->normalizeDateTime($payload['dh_evento'] ?? null),
            'c_tp_evento' => $this->nullableTrim($payload['c_tp_evento'] ?? null),
            'i_n_seq_evento' => $this->nullableInt($payload['i_n_seq_evento'] ?? null),
            'x_evento' => $this->nullableTrim($payload['x_evento'] ?? null),
            'dh_recbto' => $this->normalizeDateTime($payload['dh_recbto'] ?? null),
            'c_n_prot' => $this->nullableTrim($payload['c_n_prot'] ?? null),
            'c_versao' => $this->nullableTrim($payload['c_versao'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertEventoProc(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99016', 't99008_id', $documentId, [
            'c_versao' => $this->nullableTrim($payload['c_versao'] ?? null),
            'c_id_evento' => $this->nullableTrim($payload['c_id_evento'] ?? null),
            'c_orgao' => $this->nullableTrim($payload['c_orgao'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'c_cnpj' => $this->nullableTrim($payload['c_cnpj'] ?? null),
            'c_cpf' => $this->nullableTrim($payload['c_cpf'] ?? null),
            'c_ch_nfe' => $this->nullableTrim($payload['c_ch_nfe'] ?? null),
            'dh_evento' => $this->normalizeDateTime($payload['dh_evento'] ?? null),
            'c_tp_evento' => $this->nullableTrim($payload['c_tp_evento'] ?? null),
            'i_n_seq_evento' => $this->nullableInt($payload['i_n_seq_evento'] ?? null),
            'c_ver_evento' => $this->nullableTrim($payload['c_ver_evento'] ?? null),
            'x_desc_evento' => $this->nullableTrim($payload['x_desc_evento'] ?? null),
            'c_stat' => $this->nullableInt($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'c_n_prot' => $this->nullableTrim($payload['c_n_prot'] ?? null),
        ]);
    }

    public function upsertEventoDetalhe(int $documentId, ?string $xmlDetEvento, ?string $jsonDetEvento): void
    {
        $this->upsertSingleRow('t99017', 't99008_id', $documentId, [
            't_xml_det_evento' => $xmlDetEvento,
            't_json_det_evento' => $jsonDetEvento,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertInutilizacaoProc(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99018', 't99008_id', $documentId, [
            'c_versao' => $this->nullableTrim($payload['c_versao'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'x_serv' => $this->nullableTrim($payload['x_serv'] ?? null),
            'c_uf' => $this->nullableTrim($payload['c_uf'] ?? null),
            'c_ano' => $this->nullableTrim($payload['c_ano'] ?? null),
            'c_cnpj' => $this->nullableTrim($payload['c_cnpj'] ?? null),
            'c_mod' => $this->nullableTrim($payload['c_mod'] ?? null),
            'c_serie' => $this->nullableTrim($payload['c_serie'] ?? null),
            'c_nnf_ini' => $this->nullableTrim($payload['c_nnf_ini'] ?? null),
            'c_nnf_fin' => $this->nullableTrim($payload['c_nnf_fin'] ?? null),
            'x_just' => $this->nullableTrim($payload['x_just'] ?? null),
            'c_ver_aplic' => $this->nullableTrim($payload['c_ver_aplic'] ?? null),
            'c_stat' => $this->nullableInt($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'dh_recbto' => $this->normalizeDateTime($payload['dh_recbto'] ?? null),
            'c_n_prot' => $this->nullableTrim($payload['c_n_prot'] ?? null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function findExtractionBundleByRequest(int $requestInternalId): array
    {
        $this->ensureSchema();

        $executions = [];
        if ($this->tableExists('t99007')) {
            $executions = $this->auditConnection->fetchAllAssociative(
                'SELECT * FROM t99007 WHERE t99001_id = :id ORDER BY id_t99007 DESC',
                ['id' => $requestInternalId]
            );
        }

        $documents = [];
        if ($this->tableExists('t99007') && $this->tableExists('t99008')) {
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
        }

        $documentIds = array_values(array_map(static fn (array $row): int => (int) $row['id_t99008'], $documents));

        return [
            'executions' => $executions,
            'documents' => $documents,
            'nfe_resumo' => $this->fetchByDocumentIds('t99009', $documentIds),
            'nfe_proc' => $this->fetchByDocumentIds('t99010', $documentIds),
            'nfe_emitente' => $this->fetchByDocumentIds('t99011', $documentIds),
            'nfe_destinatario' => $this->fetchByDocumentIds('t99012', $documentIds),
            'nfe_itens' => $this->fetchByDocumentIds('t99013', $documentIds, false),
            'nfe_totais' => $this->fetchByDocumentIds('t99014', $documentIds),
            'evento_resumo' => $this->fetchByDocumentIds('t99015', $documentIds),
            'evento_proc' => $this->fetchByDocumentIds('t99016', $documentIds),
            'evento_detalhe' => $this->fetchByDocumentIds('t99017', $documentIds),
            'inutilizacao_proc' => $this->fetchByDocumentIds('t99018', $documentIds),
        ];
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
        ];

        foreach ($statements as $statement) {
            $this->auditConnection->executeStatement($statement);
        }

        if ($this->tableExists('t99007') && $this->tableHasColumn('t99007', 'c_tipo_documento')) {
            foreach (['t99018', 't99017', 't99016', 't99015', 't99014', 't99013', 't99012', 't99011', 't99010', 't99009', 't99008', 't99007'] as $table) {
                $this->auditConnection->executeStatement(sprintf('DROP TABLE IF EXISTS %s CASCADE', $table));
                $this->tableExistsCache[$table] = false;
            }
        }

        $createStatements = [
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99007 (
                id_t99007 bigserial PRIMARY KEY,
                t99001_id bigint NOT NULL REFERENCES t99001 (id_t99001) ON DELETE CASCADE,
                u_c_request_id varchar(36) NOT NULL,
                c_caminho_origem varchar(255),
                c_tipo_consulta varchar(40),
                c_documento_consulta varchar(20),
                c_nsu_entrada char(15),
                tp_amb smallint,
                c_ver_aplic varchar(120),
                c_stat integer,
                x_motivo varchar(255),
                dh_resp timestamptz,
                c_ult_nsu char(15),
                c_max_nsu char(15),
                q_doc_zip integer NOT NULL DEFAULT 0,
                t_xml_envelope text,
                dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99007_t99001_id_idx ON t99007 (t99001_id, id_t99007 DESC)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99008 (
                id_t99008 bigserial PRIMARY KEY,
                t99007_id bigint NOT NULL REFERENCES t99007 (id_t99007) ON DELETE CASCADE,
                u_c_request_id varchar(36) NOT NULL,
                c_caminho_origem varchar(255),
                c_nsu char(15),
                c_schema_name varchar(100) NOT NULL,
                c_schema_family varchar(30),
                c_ch_nfe char(44),
                c_tp_evento varchar(20),
                i_n_seq_evento integer,
                c_n_prot varchar(20),
                t_xml_gzip_base64 text,
                t_xml_descompactado text,
                c_hash_xml varchar(64),
                tp_amb smallint,
                c_emit_cnpj_cpf varchar(14),
                c_dest_cnpj_cpf varchar(14),
                dt_hr_processado_em timestamptz NOT NULL DEFAULT now(),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE UNIQUE INDEX IF NOT EXISTS t99008_schema_nsu_hash_uidx ON t99008 (c_schema_name, c_nsu, c_hash_xml)",
            "CREATE INDEX IF NOT EXISTS t99008_t99007_id_idx ON t99008 (t99007_id, id_t99008 DESC)",
            "CREATE INDEX IF NOT EXISTS t99008_chave_idx ON t99008 (c_ch_nfe)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99009 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_emit_cnpj varchar(14),
                c_emit_cpf varchar(11),
                x_emit_nome varchar(255),
                c_emit_ie varchar(20),
                dh_emi timestamptz,
                tp_nf smallint,
                v_nf numeric(18,2),
                c_dig_val varchar(128),
                dh_recbto timestamptz,
                c_sit_nfe varchar(20),
                c_versao varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99010 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_versao varchar(20),
                c_id_nfe varchar(80),
                c_uf varchar(4),
                c_nnf varchar(20),
                c_serie varchar(10),
                c_mod varchar(10),
                dh_emi timestamptz,
                dh_saida_entrada timestamptz,
                tp_nf smallint,
                c_id_dest varchar(4),
                c_mun_fg varchar(10),
                c_tp_emis varchar(10),
                tp_amb smallint,
                c_fin_nfe varchar(10),
                c_ind_final varchar(10),
                c_ind_pres varchar(10),
                c_proc_emi varchar(10),
                c_ver_proc varchar(60),
                c_n_prot varchar(20),
                c_ch_nfe char(44),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99011 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_cnpj varchar(14),
                c_cpf varchar(11),
                x_nome varchar(255),
                x_fant varchar(255),
                c_ie varchar(20),
                c_iest varchar(20),
                c_im varchar(20),
                c_cnae varchar(20),
                c_crt varchar(10),
                x_lgr varchar(255),
                c_nro varchar(20),
                x_bairro varchar(255),
                c_mun varchar(10),
                x_mun varchar(255),
                c_uf varchar(4),
                c_cep varchar(12),
                c_pais varchar(10),
                x_pais varchar(120),
                c_fone varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99012 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_cnpj varchar(14),
                c_cpf varchar(11),
                c_id_estrangeiro varchar(40),
                x_nome varchar(255),
                c_ind_ie_dest varchar(10),
                c_ie varchar(20),
                c_isuf varchar(20),
                c_im varchar(20),
                c_email varchar(255),
                x_lgr varchar(255),
                c_nro varchar(20),
                x_bairro varchar(255),
                c_mun varchar(10),
                x_mun varchar(255),
                c_uf varchar(4),
                c_cep varchar(12),
                c_pais varchar(10),
                x_pais varchar(120),
                c_fone varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99013 (
                id_t99013 bigserial PRIMARY KEY,
                t99008_id bigint NOT NULL REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                i_n_item integer,
                c_prod varchar(80),
                c_ean varchar(30),
                x_prod varchar(255),
                c_ncm varchar(20),
                c_cest varchar(20),
                c_cfop varchar(10),
                c_ucom varchar(20),
                q_com numeric(18,4),
                v_un_com numeric(18,6),
                v_prod numeric(18,2),
                c_ean_trib varchar(30),
                c_utrib varchar(20),
                q_trib numeric(18,4),
                v_un_trib numeric(18,6),
                v_frete numeric(18,2),
                v_seg numeric(18,2),
                v_desc numeric(18,2),
                i_ind_tot smallint,
                t_inf_ad_prod text,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99013_t99008_id_idx ON t99013 (t99008_id, i_n_item)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99014 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                v_bc numeric(18,2),
                v_icms numeric(18,2),
                v_icms_deson numeric(18,2),
                v_fcp numeric(18,2),
                v_bcst numeric(18,2),
                v_st numeric(18,2),
                v_fcpst numeric(18,2),
                v_prod numeric(18,2),
                v_frete numeric(18,2),
                v_seg numeric(18,2),
                v_desc numeric(18,2),
                v_ii numeric(18,2),
                v_ipi numeric(18,2),
                v_ipi_devol numeric(18,2),
                v_pis numeric(18,2),
                v_cofins numeric(18,2),
                v_outro numeric(18,2),
                v_nf numeric(18,2),
                v_tot_trib numeric(18,2),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99015 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_orgao varchar(10),
                c_cnpj varchar(14),
                c_cpf varchar(11),
                c_ch_nfe char(44),
                dh_evento timestamptz,
                c_tp_evento varchar(20),
                i_n_seq_evento integer,
                x_evento varchar(255),
                dh_recbto timestamptz,
                c_n_prot varchar(20),
                c_versao varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99016 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_versao varchar(20),
                c_id_evento varchar(80),
                c_orgao varchar(10),
                tp_amb smallint,
                c_cnpj varchar(14),
                c_cpf varchar(11),
                c_ch_nfe char(44),
                dh_evento timestamptz,
                c_tp_evento varchar(20),
                i_n_seq_evento integer,
                c_ver_evento varchar(20),
                x_desc_evento varchar(255),
                c_stat integer,
                x_motivo varchar(255),
                c_n_prot varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99017 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                t_xml_det_evento text,
                t_json_det_evento text,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99018 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                c_versao varchar(20),
                tp_amb smallint,
                x_serv varchar(60),
                c_uf varchar(4),
                c_ano varchar(4),
                c_cnpj varchar(14),
                c_mod varchar(10),
                c_serie varchar(10),
                c_nnf_ini varchar(20),
                c_nnf_fin varchar(20),
                x_just text,
                c_ver_aplic varchar(120),
                c_stat integer,
                x_motivo varchar(255),
                dh_recbto timestamptz,
                c_n_prot varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
        ];

        foreach ($createStatements as $statement) {
            $this->auditConnection->executeStatement($statement);
        }

        foreach (['t99007', 't99008', 't99009', 't99010', 't99011', 't99012', 't99013', 't99014', 't99015', 't99016', 't99017', 't99018'] as $table) {
            $this->tableExistsCache[$table] = true;
        }

        $this->schemaEnsured = true;
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function fetchByDocumentIds(string $table, array $documentIds, bool $singleRow = true): array
    {
        if ($documentIds === [] || !$this->tableExists($table)) {
            return [];
        }

        $rows = $this->auditConnection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE t99008_id IN (?) ORDER BY t99008_id ASC', $table),
            [$documentIds],
            [Connection::PARAM_INT_ARRAY]
        );

        if (!$singleRow) {
            return $rows;
        }

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['t99008_id']] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertSingleRow(string $table, string $pkColumn, int $pkValue, array $payload): void
    {
        $this->ensureSchema();

        $payload[$pkColumn] = $pkValue;
        $payload['dt_hr_atu'] = date('c');
        $exists = $this->auditConnection->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE %s = :id', $table, $pkColumn),
            ['id' => $pkValue]
        );

        if ($exists === false) {
            $this->auditConnection->insert($table, $payload);

            return;
        }

        unset($payload[$pkColumn]);
        $this->auditConnection->update($table, $payload, [$pkColumn => $pkValue]);
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = $this->auditConnection->createSchemaManager()->tablesExist([$table]);
        }

        return $this->tableExistsCache[$table];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        return $this->auditConnection->createSchemaManager()->introspectTable($table)->hasColumn($column);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        $trimmed = $this->nullableTrim($value);

        return $trimmed === null || !is_numeric($trimmed) ? null : (int) $trimmed;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        $trimmed = $this->nullableTrim($value);
        if ($trimmed === null) {
            return null;
        }

        return str_replace(',', '.', $trimmed);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $trimmed = $this->nullableTrim($value);
        if ($trimmed === null) {
            return null;
        }

        $timestamp = strtotime($trimmed);

        return $timestamp === false ? $trimmed : date('c', $timestamp);
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . "\n...[truncado]";
    }
}
