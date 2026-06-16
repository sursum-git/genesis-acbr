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
            'caminho_origem' => $this->nullableTrim($payload['caminho_origem'] ?? null),
            'tipo_consulta' => $this->nullableTrim($payload['tipo_consulta'] ?? null),
            'documento_consulta' => $this->nullableTrim($payload['documento_consulta'] ?? null),
            'nsu_entrada' => $this->nullableTrim($payload['nsu_entrada'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'ver_aplic' => $this->nullableTrim($payload['ver_aplic'] ?? null),
            'c_stat' => $this->nullableInt($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'dh_resp' => $this->normalizeDateTime($payload['dh_resp'] ?? null),
            'ult_nsu' => $this->nullableTrim($payload['ult_nsu'] ?? null),
            'max_nsu' => $this->nullableTrim($payload['max_nsu'] ?? null),
            'q_doc_zip' => $this->nullableInt($payload['q_doc_zip'] ?? null) ?? 0,
            'xml_envelope' => $payload['xml_envelope'] ?? null,
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

        $schemaName = $this->nullableTrim($payload['schema_name'] ?? null);
        $nsu = $this->nullableTrim($payload['nsu'] ?? null);
        $hash = $this->nullableTrim($payload['hash_xml'] ?? null);

        if ($schemaName !== null && $nsu !== null && $hash !== null) {
            $existingId = $this->auditConnection->fetchOne(
                'SELECT id_t99008 FROM t99008 WHERE schema_name = :schema AND nsu = :nsu AND hash_xml = :hash LIMIT 1',
                ['schema' => $schemaName, 'nsu' => $nsu, 'hash' => $hash]
            );

            if ($existingId !== false) {
                $this->auditConnection->update('t99008', [
                    't99007_id' => (int) ($payload['t99007_id'] ?? 0),
                    'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
                    'caminho_origem' => $this->nullableTrim($payload['caminho_origem'] ?? null),
                    'schema_family' => $this->nullableTrim($payload['schema_family'] ?? null),
                    'ch_nfe' => $this->nullableTrim($payload['ch_nfe'] ?? null),
                    'tp_evento' => $this->nullableTrim($payload['tp_evento'] ?? null),
                    'n_seq_evento' => $this->nullableInt($payload['n_seq_evento'] ?? null),
                    'n_prot' => $this->nullableTrim($payload['n_prot'] ?? null),
                    'xml_gzip_base64' => $payload['xml_gzip_base64'] ?? null,
                    'xml_descompactado' => $payload['xml_descompactado'] ?? null,
                    'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
                    'emit_cnpj_cpf' => $this->nullableTrim($payload['emit_cnpj_cpf'] ?? null),
                    'dest_cnpj_cpf' => $this->nullableTrim($payload['dest_cnpj_cpf'] ?? null),
                    'dt_hr_processado_em' => date('c'),
                    'dt_hr_atu' => date('c'),
                ], ['id_t99008' => (int) $existingId]);

                return (int) $existingId;
            }
        }

        $this->auditConnection->insert('t99008', [
            't99007_id' => (int) ($payload['t99007_id'] ?? 0),
            'u_c_request_id' => (string) ($payload['u_c_request_id'] ?? ''),
            'caminho_origem' => $this->nullableTrim($payload['caminho_origem'] ?? null),
            'nsu' => $nsu,
            'schema_name' => $schemaName,
            'schema_family' => $this->nullableTrim($payload['schema_family'] ?? null),
            'ch_nfe' => $this->nullableTrim($payload['ch_nfe'] ?? null),
            'tp_evento' => $this->nullableTrim($payload['tp_evento'] ?? null),
            'n_seq_evento' => $this->nullableInt($payload['n_seq_evento'] ?? null),
            'n_prot' => $this->nullableTrim($payload['n_prot'] ?? null),
            'xml_gzip_base64' => $payload['xml_gzip_base64'] ?? null,
            'xml_descompactado' => $payload['xml_descompactado'] ?? null,
            'hash_xml' => $hash,
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'emit_cnpj_cpf' => $this->nullableTrim($payload['emit_cnpj_cpf'] ?? null),
            'dest_cnpj_cpf' => $this->nullableTrim($payload['dest_cnpj_cpf'] ?? null),
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
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'cpf' => $this->nullableTrim($payload['cpf'] ?? null),
            'x_nome' => $this->nullableTrim($payload['x_nome'] ?? null),
            'ie' => $this->nullableTrim($payload['ie'] ?? null),
            'dh_emi' => $this->normalizeDateTime($payload['dh_emi'] ?? null),
            'tp_nf' => $this->nullableInt($payload['tp_nf'] ?? null),
            'v_nf' => $this->nullableDecimal($payload['v_nf'] ?? null),
            'dig_val' => $this->nullableTrim($payload['dig_val'] ?? null),
            'dh_recbto' => $this->normalizeDateTime($payload['dh_recbto'] ?? null),
            'c_sit_nfe' => $this->nullableTrim($payload['c_sit_nfe'] ?? null),
            'versao' => $this->nullableTrim($payload['versao'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeProc(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99010', 't99008_id', $documentId, [
            'versao' => $this->nullableTrim($payload['versao'] ?? null),
            'id_nfe' => $this->nullableTrim($payload['id_nfe'] ?? null),
            'c_uf' => $this->nullableTrim($payload['c_uf'] ?? null),
            'n_nf' => $this->nullableTrim($payload['n_nf'] ?? null),
            'serie' => $this->nullableTrim($payload['serie'] ?? null),
            'mod' => $this->nullableTrim($payload['mod'] ?? null),
            'dh_emi' => $this->normalizeDateTime($payload['dh_emi'] ?? null),
            'dh_saida_entrada' => $this->normalizeDateTime($payload['dh_saida_entrada'] ?? null),
            'tp_nf' => $this->nullableInt($payload['tp_nf'] ?? null),
            'id_dest' => $this->nullableTrim($payload['id_dest'] ?? null),
            'c_mun_fg' => $this->nullableTrim($payload['c_mun_fg'] ?? null),
            'tp_emis' => $this->nullableTrim($payload['tp_emis'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'fin_nfe' => $this->nullableTrim($payload['fin_nfe'] ?? null),
            'ind_final' => $this->nullableTrim($payload['ind_final'] ?? null),
            'ind_pres' => $this->nullableTrim($payload['ind_pres'] ?? null),
            'proc_emi' => $this->nullableTrim($payload['proc_emi'] ?? null),
            'ver_proc' => $this->nullableTrim($payload['ver_proc'] ?? null),
            'n_prot' => $this->nullableTrim($payload['n_prot'] ?? null),
            'ch_nfe' => $this->nullableTrim($payload['ch_nfe'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeEmitente(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99011', 't99008_id', $documentId, [
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'cpf' => $this->nullableTrim($payload['cpf'] ?? null),
            'x_nome' => $this->nullableTrim($payload['x_nome'] ?? null),
            'x_fant' => $this->nullableTrim($payload['x_fant'] ?? null),
            'ie' => $this->nullableTrim($payload['ie'] ?? null),
            'iest' => $this->nullableTrim($payload['iest'] ?? null),
            'im' => $this->nullableTrim($payload['im'] ?? null),
            'cnae' => $this->nullableTrim($payload['cnae'] ?? null),
            'crt' => $this->nullableTrim($payload['crt'] ?? null),
            'x_lgr' => $this->nullableTrim($payload['x_lgr'] ?? null),
            'nro' => $this->nullableTrim($payload['nro'] ?? null),
            'x_bairro' => $this->nullableTrim($payload['x_bairro'] ?? null),
            'c_mun' => $this->nullableTrim($payload['c_mun'] ?? null),
            'x_mun' => $this->nullableTrim($payload['x_mun'] ?? null),
            'uf' => $this->nullableTrim($payload['uf'] ?? null),
            'cep' => $this->nullableTrim($payload['cep'] ?? null),
            'c_pais' => $this->nullableTrim($payload['c_pais'] ?? null),
            'x_pais' => $this->nullableTrim($payload['x_pais'] ?? null),
            'fone' => $this->nullableTrim($payload['fone'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeDestinatario(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99012', 't99008_id', $documentId, [
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'cpf' => $this->nullableTrim($payload['cpf'] ?? null),
            'id_estrangeiro' => $this->nullableTrim($payload['id_estrangeiro'] ?? null),
            'x_nome' => $this->nullableTrim($payload['x_nome'] ?? null),
            'ind_ie_dest' => $this->nullableTrim($payload['ind_ie_dest'] ?? null),
            'ie' => $this->nullableTrim($payload['ie'] ?? null),
            'isuf' => $this->nullableTrim($payload['isuf'] ?? null),
            'im' => $this->nullableTrim($payload['im'] ?? null),
            'email' => $this->nullableTrim($payload['email'] ?? null),
            'x_lgr' => $this->nullableTrim($payload['x_lgr'] ?? null),
            'nro' => $this->nullableTrim($payload['nro'] ?? null),
            'x_bairro' => $this->nullableTrim($payload['x_bairro'] ?? null),
            'c_mun' => $this->nullableTrim($payload['c_mun'] ?? null),
            'x_mun' => $this->nullableTrim($payload['x_mun'] ?? null),
            'uf' => $this->nullableTrim($payload['uf'] ?? null),
            'cep' => $this->nullableTrim($payload['cep'] ?? null),
            'c_pais' => $this->nullableTrim($payload['c_pais'] ?? null),
            'x_pais' => $this->nullableTrim($payload['x_pais'] ?? null),
            'fone' => $this->nullableTrim($payload['fone'] ?? null),
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
                'n_item' => $this->nullableInt($item['n_item'] ?? null),
                'c_prod' => $this->nullableTrim($item['c_prod'] ?? null),
                'c_ean' => $this->nullableTrim($item['c_ean'] ?? null),
                'x_prod' => $this->nullableTrim($item['x_prod'] ?? null),
                'c_ncm' => $this->nullableTrim($item['c_ncm'] ?? null),
                'c_cest' => $this->nullableTrim($item['c_cest'] ?? null),
                'c_cfop' => $this->nullableTrim($item['c_cfop'] ?? null),
                'u_com' => $this->nullableTrim($item['u_com'] ?? null),
                'q_com' => $this->nullableDecimal($item['q_com'] ?? null),
                'v_un_com' => $this->nullableDecimal($item['v_un_com'] ?? null),
                'v_prod' => $this->nullableDecimal($item['v_prod'] ?? null),
                'c_ean_trib' => $this->nullableTrim($item['c_ean_trib'] ?? null),
                'u_trib' => $this->nullableTrim($item['u_trib'] ?? null),
                'q_trib' => $this->nullableDecimal($item['q_trib'] ?? null),
                'v_un_trib' => $this->nullableDecimal($item['v_un_trib'] ?? null),
                'v_frete' => $this->nullableDecimal($item['v_frete'] ?? null),
                'v_seg' => $this->nullableDecimal($item['v_seg'] ?? null),
                'v_desc' => $this->nullableDecimal($item['v_desc'] ?? null),
                'ind_tot' => $this->nullableInt($item['ind_tot'] ?? null),
                'inf_ad_prod' => $item['inf_ad_prod'] ?? null,
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
    public function upsertNfeDadosGerais(int $documentId, array $payload): int
    {
        $this->ensureSchema();

        $row = [
            'ch_nfe' => $this->nullableTrim($payload['ch_nfe'] ?? null),
            'n_nf' => $this->nullableTrim($payload['n_nf'] ?? null),
            'versao' => $this->nullableTrim($payload['versao'] ?? null),
            'mod' => $this->nullableTrim($payload['mod'] ?? null),
            'serie' => $this->nullableTrim($payload['serie'] ?? null),
            'dh_emi' => $this->normalizeDateTime($payload['dh_emi'] ?? null),
            'dh_sai_ent' => $this->normalizeDateTime($payload['dh_sai_ent'] ?? null),
            'v_prod' => $this->nullableDecimal($payload['v_prod'] ?? null),
            'v_frete' => $this->nullableDecimal($payload['v_frete'] ?? null),
            'v_seg' => $this->nullableDecimal($payload['v_seg'] ?? null),
            'v_desc' => $this->nullableDecimal($payload['v_desc'] ?? null),
            'v_ii' => $this->nullableDecimal($payload['v_ii'] ?? null),
            'v_ipi' => $this->nullableDecimal($payload['v_ipi'] ?? null),
            'v_ipi_devol' => $this->nullableDecimal($payload['v_ipi_devol'] ?? null),
            'v_pis' => $this->nullableDecimal($payload['v_pis'] ?? null),
            'v_outro' => $this->nullableDecimal($payload['v_outro'] ?? null),
            'v_nf' => $this->nullableDecimal($payload['v_nf'] ?? null),
            'tp_imp' => $this->nullableTrim($payload['tp_imp'] ?? null),
            'inf_cpl' => $payload['inf_cpl'] ?? null,
            'proc_emi' => $this->nullableTrim($payload['proc_emi'] ?? null),
            'ver_proc' => $this->nullableTrim($payload['ver_proc'] ?? null),
            'tp_emis' => $this->nullableTrim($payload['tp_emis'] ?? null),
            'fin_nfe' => $this->nullableTrim($payload['fin_nfe'] ?? null),
            'nat_op' => $this->nullableTrim($payload['nat_op'] ?? null),
            'ind_intermed' => $this->nullableTrim($payload['ind_intermed'] ?? null),
            'tp_nf' => $this->nullableInt($payload['tp_nf'] ?? null),
            'dig_val' => $this->nullableTrim($payload['dig_val'] ?? null),
            'dt_hr_atu' => date('c'),
        ];

        $existingId = $this->auditConnection->fetchOne(
            'SELECT id_t99019 FROM t99019 WHERE t99008_id = :t99008_id LIMIT 1',
            ['t99008_id' => $documentId]
        );

        if ($existingId === false) {
            $this->auditConnection->insert('t99019', ['t99008_id' => $documentId] + $row);

            return (int) $this->auditConnection->lastInsertId();
        }

        $this->auditConnection->update('t99019', $row, ['id_t99019' => (int) $existingId]);

        return (int) $existingId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertNfeCobranca(int $t99019Id, array $payload): ?int
    {
        $this->ensureSchema();

        if (!$this->hasMeaningfulValues($payload, ['n_fat', 'v_orig', 'v_desc', 'v_liq'])) {
            $this->auditConnection->delete('t99028', ['t99019_id' => $t99019Id]);

            return null;
        }

        $row = [
            'n_fat' => $this->nullableTrim($payload['n_fat'] ?? null),
            'v_orig' => $this->nullableDecimal($payload['v_orig'] ?? null),
            'v_desc' => $this->nullableDecimal($payload['v_desc'] ?? null),
            'v_liq' => $this->nullableDecimal($payload['v_liq'] ?? null),
            'dt_hr_atu' => date('c'),
        ];

        $existingId = $this->auditConnection->fetchOne(
            'SELECT id_t99028 FROM t99028 WHERE t99019_id = :t99019_id LIMIT 1',
            ['t99019_id' => $t99019Id]
        );

        if ($existingId === false) {
            $this->auditConnection->insert('t99028', ['t99019_id' => $t99019Id] + $row);

            return (int) $this->auditConnection->lastInsertId();
        }

        $this->auditConnection->update('t99028', $row, ['id_t99028' => (int) $existingId]);

        return (int) $existingId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function replaceNfePagamentos(int $t99019Id, array $payload): void
    {
        $this->ensureSchema();
        $this->auditConnection->delete('t99029', ['t99019_id' => $t99019Id]);

        $detalhes = is_array($payload['detalhes'] ?? null) ? $payload['detalhes'] : [];
        $vTroco = $this->nullableDecimal($payload['v_troco'] ?? null);

        foreach ($detalhes as $detalhe) {
            if (!is_array($detalhe)) {
                continue;
            }

            $this->auditConnection->insert('t99029', [
                't99019_id' => $t99019Id,
                'ind_pag' => $this->nullableTrim($detalhe['ind_pag'] ?? null),
                't_pag' => $this->nullableTrim($detalhe['t_pag'] ?? null),
                'x_pag' => $this->nullableTrim($detalhe['x_pag'] ?? null),
                'v_pag' => $this->nullableDecimal($detalhe['v_pag'] ?? null),
                'd_pag' => $this->normalizeDateTime($detalhe['d_pag'] ?? null),
                'cnpj_pag' => $this->nullableTrim($detalhe['cnpj_pag'] ?? null),
                'uf_pag' => $this->nullableTrim($detalhe['uf_pag'] ?? null),
                'tp_integra' => $this->nullableTrim($detalhe['tp_integra'] ?? null),
                'cnpj_cred' => $this->nullableTrim($detalhe['cnpj_cred'] ?? null),
                't_band' => $this->nullableTrim($detalhe['t_band'] ?? null),
                'c_aut' => $this->nullableTrim($detalhe['c_aut'] ?? null),
                'cnpj_receb' => $this->nullableTrim($detalhe['cnpj_receb'] ?? null),
                'id_term_pag' => $this->nullableTrim($detalhe['id_term_pag'] ?? null),
                'v_troco' => $vTroco,
                'dt_hr_atu' => date('c'),
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $duplicatas
     */
    public function replaceNfeDuplicatas(?int $t99028Id, array $duplicatas): void
    {
        $this->ensureSchema();

        if ($t99028Id === null) {
            return;
        }

        $this->auditConnection->delete('t99030', ['t99028_id' => $t99028Id]);

        foreach ($duplicatas as $duplicata) {
            $this->auditConnection->insert('t99030', [
                't99028_id' => $t99028Id,
                'n_dup' => $this->nullableTrim($duplicata['n_dup'] ?? null),
                'd_venc' => $this->normalizeDateTime($duplicata['d_venc'] ?? null),
                'v_dup' => $this->nullableDecimal($duplicata['v_dup'] ?? null),
                'dt_hr_atu' => date('c'),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertEventoResumo(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99015', 't99008_id', $documentId, [
            'c_orgao' => $this->nullableTrim($payload['c_orgao'] ?? null),
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'cpf' => $this->nullableTrim($payload['cpf'] ?? null),
            'ch_nfe' => $this->nullableTrim($payload['ch_nfe'] ?? null),
            'dh_evento' => $this->normalizeDateTime($payload['dh_evento'] ?? null),
            'tp_evento' => $this->nullableTrim($payload['tp_evento'] ?? null),
            'n_seq_evento' => $this->nullableInt($payload['n_seq_evento'] ?? null),
            'x_evento' => $this->nullableTrim($payload['x_evento'] ?? null),
            'dh_recbto' => $this->normalizeDateTime($payload['dh_recbto'] ?? null),
            'n_prot' => $this->nullableTrim($payload['n_prot'] ?? null),
            'versao' => $this->nullableTrim($payload['versao'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertEventoProc(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99016', 't99008_id', $documentId, [
            'versao' => $this->nullableTrim($payload['versao'] ?? null),
            'id_evento' => $this->nullableTrim($payload['id_evento'] ?? null),
            'c_orgao' => $this->nullableTrim($payload['c_orgao'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'cpf' => $this->nullableTrim($payload['cpf'] ?? null),
            'ch_nfe' => $this->nullableTrim($payload['ch_nfe'] ?? null),
            'dh_evento' => $this->normalizeDateTime($payload['dh_evento'] ?? null),
            'tp_evento' => $this->nullableTrim($payload['tp_evento'] ?? null),
            'n_seq_evento' => $this->nullableInt($payload['n_seq_evento'] ?? null),
            'ver_evento' => $this->nullableTrim($payload['ver_evento'] ?? null),
            'desc_evento' => $this->nullableTrim($payload['desc_evento'] ?? null),
            'c_stat' => $this->nullableInt($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'n_prot' => $this->nullableTrim($payload['n_prot'] ?? null),
        ]);
    }

    public function upsertEventoDetalhe(int $documentId, ?string $xmlDetEvento, ?string $jsonDetEvento): void
    {
        $this->upsertSingleRow('t99017', 't99008_id', $documentId, [
            'xml_det_evento' => $xmlDetEvento,
            'json_det_evento' => $jsonDetEvento,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertInutilizacaoProc(int $documentId, array $payload): void
    {
        $this->upsertSingleRow('t99018', 't99008_id', $documentId, [
            'versao' => $this->nullableTrim($payload['versao'] ?? null),
            'tp_amb' => $this->nullableInt($payload['tp_amb'] ?? null),
            'x_serv' => $this->nullableTrim($payload['x_serv'] ?? null),
            'c_uf' => $this->nullableTrim($payload['c_uf'] ?? null),
            'ano' => $this->nullableTrim($payload['ano'] ?? null),
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'mod' => $this->nullableTrim($payload['mod'] ?? null),
            'serie' => $this->nullableTrim($payload['serie'] ?? null),
            'n_nf_ini' => $this->nullableTrim($payload['n_nf_ini'] ?? null),
            'n_nf_fin' => $this->nullableTrim($payload['n_nf_fin'] ?? null),
            'x_just' => $this->nullableTrim($payload['x_just'] ?? null),
            'ver_aplic' => $this->nullableTrim($payload['ver_aplic'] ?? null),
            'c_stat' => $this->nullableInt($payload['c_stat'] ?? null),
            'x_motivo' => $this->nullableTrim($payload['x_motivo'] ?? null),
            'dh_recbto' => $this->normalizeDateTime($payload['dh_recbto'] ?? null),
            'n_prot' => $this->nullableTrim($payload['n_prot'] ?? null),
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
            'nfe_dados_gerais' => $this->fetchByDocumentIds('t99019', $documentIds),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function versionEmitente(array $payload): int
    {
        return $this->versionEntityByCnpj('t99020', 'id_t99020', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function versionDestinatario(array $payload): int
    {
        return $this->versionEntityByCnpj('t99021', 'id_t99021', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function versionTransporte(array $payload): int
    {
        return $this->versionEntityByCnpj('t99022', 'id_t99022', $payload);
    }

    public function saveT99019EmitentePivot(int $t99019Id, int $t99020Id): int
    {
        return $this->saveSingleRelationPivot('t99023', 'id_t99023', 't99020_id', $t99019Id, $t99020Id);
    }

    public function saveT99019DestinatarioPivot(int $t99019Id, int $t99021Id): int
    {
        return $this->saveSingleRelationPivot('t99024', 'id_t99024', 't99021_id', $t99019Id, $t99021Id);
    }

    public function saveT99019TransportePivot(int $t99019Id, int $t99022Id): int
    {
        return $this->saveSingleRelationPivot('t99025', 'id_t99025', 't99022_id', $t99019Id, $t99022Id);
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
     * @param array<string, mixed> $payload
     */
    private function versionEntityByCnpj(string $table, string $idColumn, array $payload): int
    {
        $this->ensureSchema();

        $cnpj = $this->nullableTrim($payload['cnpj'] ?? null);
        if ($cnpj === null) {
            throw new \InvalidArgumentException(sprintf('Campo cnpj e obrigatorio para versionar %s.', $table));
        }

        $this->auditConnection->beginTransaction();

        try {
            $current = $this->auditConnection->fetchAssociative(
                sprintf(
                    'SELECT %s, versao FROM %s WHERE cnpj = :cnpj AND data_fim IS NULL ORDER BY versao DESC, %s DESC LIMIT 1 FOR UPDATE',
                    $idColumn,
                    $table,
                    $idColumn
                ),
                ['cnpj' => $cnpj]
            );

            $nextVersion = 1;
            if ($current !== false) {
                $nextVersion = ((int) ($current['versao'] ?? 0)) + 1;
                $this->auditConnection->update($table, [
                    'data_fim' => date('c'),
                    'dt_hr_atu' => date('c'),
                ], [
                    $idColumn => (int) $current[$idColumn],
                ]);
            }

            $insertPayload = $this->normalizeVersionedPayload($payload) + [
                'cnpj' => $cnpj,
                'versao' => $nextVersion,
                'data_inicio' => date('c'),
                'data_fim' => null,
                'dt_hr_atu' => date('c'),
            ];

            $this->auditConnection->insert($table, $insertPayload);
            $id = (int) $this->auditConnection->lastInsertId();

            $this->auditConnection->commit();

            return $id;
        } catch (\Throwable $throwable) {
            $this->auditConnection->rollBack();
            throw $throwable;
        }
    }

    private function saveSingleRelationPivot(string $table, string $idColumn, string $relationColumn, int $t99019Id, int $relationId): int
    {
        $this->ensureSchema();

        $payload = [
            $relationColumn => $relationId,
            'dt_hr_atu' => date('c'),
        ];

        $existingId = $this->auditConnection->fetchOne(
            sprintf('SELECT %s FROM %s WHERE t99019_id = :t99019_id LIMIT 1', $idColumn, $table),
            ['t99019_id' => $t99019Id]
        );

        if ($existingId === false) {
            $this->auditConnection->insert($table, $payload + ['t99019_id' => $t99019Id]);

            return (int) $this->auditConnection->lastInsertId();
        }

        $this->auditConnection->update($table, $payload, [$idColumn => (int) $existingId]);

        return (int) $existingId;
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

        if (
            ($this->tableExists('t99007') && $this->tableHasColumn('t99007', 'c_tipo_documento'))
            || ($this->tableExists('t99007') && $this->tableHasColumn('t99007', 'c_caminho_origem'))
        ) {
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
                caminho_origem varchar(255),
                tipo_consulta varchar(40),
                documento_consulta varchar(20),
                nsu_entrada char(15),
                tp_amb smallint,
                ver_aplic varchar(120),
                c_stat integer,
                x_motivo varchar(255),
                dh_resp timestamptz,
                ult_nsu char(15),
                max_nsu char(15),
                q_doc_zip integer NOT NULL DEFAULT 0,
                xml_envelope text,
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
                caminho_origem varchar(255),
                nsu char(15),
                schema_name varchar(100) NOT NULL,
                schema_family varchar(30),
                ch_nfe char(44),
                tp_evento varchar(20),
                n_seq_evento integer,
                n_prot varchar(20),
                xml_gzip_base64 text,
                xml_descompactado text,
                hash_xml varchar(64),
                tp_amb smallint,
                emit_cnpj_cpf varchar(14),
                dest_cnpj_cpf varchar(14),
                dt_hr_processado_em timestamptz NOT NULL DEFAULT now(),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE UNIQUE INDEX IF NOT EXISTS t99008_schema_nsu_hash_uidx ON t99008 (schema_name, nsu, hash_xml)",
            "CREATE INDEX IF NOT EXISTS t99008_t99007_id_idx ON t99008 (t99007_id, id_t99008 DESC)",
            "CREATE INDEX IF NOT EXISTS t99008_chave_idx ON t99008 (ch_nfe)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99009 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                cnpj varchar(14),
                cpf varchar(11),
                x_nome varchar(255),
                ie varchar(20),
                dh_emi timestamptz,
                tp_nf smallint,
                v_nf numeric(18,2),
                dig_val varchar(128),
                dh_recbto timestamptz,
                c_sit_nfe varchar(20),
                versao varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99010 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                versao varchar(20),
                id_nfe varchar(80),
                c_uf varchar(4),
                n_nf varchar(20),
                serie varchar(10),
                mod varchar(10),
                dh_emi timestamptz,
                dh_saida_entrada timestamptz,
                tp_nf smallint,
                id_dest varchar(4),
                c_mun_fg varchar(10),
                tp_emis varchar(10),
                tp_amb smallint,
                fin_nfe varchar(10),
                ind_final varchar(10),
                ind_pres varchar(10),
                proc_emi varchar(10),
                ver_proc varchar(60),
                n_prot varchar(20),
                ch_nfe char(44),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99011 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                cnpj varchar(14),
                cpf varchar(11),
                x_nome varchar(255),
                x_fant varchar(255),
                ie varchar(20),
                iest varchar(20),
                im varchar(20),
                cnae varchar(20),
                crt varchar(10),
                x_lgr varchar(255),
                nro varchar(20),
                x_bairro varchar(255),
                c_mun varchar(10),
                x_mun varchar(255),
                uf varchar(4),
                cep varchar(12),
                c_pais varchar(10),
                x_pais varchar(120),
                fone varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99012 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                cnpj varchar(14),
                cpf varchar(11),
                id_estrangeiro varchar(40),
                x_nome varchar(255),
                ind_ie_dest varchar(10),
                ie varchar(20),
                isuf varchar(20),
                im varchar(20),
                email varchar(255),
                x_lgr varchar(255),
                nro varchar(20),
                x_bairro varchar(255),
                c_mun varchar(10),
                x_mun varchar(255),
                uf varchar(4),
                cep varchar(12),
                c_pais varchar(10),
                x_pais varchar(120),
                fone varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99013 (
                id_t99013 bigserial PRIMARY KEY,
                t99008_id bigint NOT NULL REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                n_item integer,
                c_prod varchar(80),
                c_ean varchar(30),
                x_prod varchar(255),
                c_ncm varchar(20),
                c_cest varchar(20),
                c_cfop varchar(10),
                u_com varchar(20),
                q_com numeric(18,4),
                v_un_com numeric(18,6),
                v_prod numeric(18,2),
                c_ean_trib varchar(30),
                u_trib varchar(20),
                q_trib numeric(18,4),
                v_un_trib numeric(18,6),
                v_frete numeric(18,2),
                v_seg numeric(18,2),
                v_desc numeric(18,2),
                ind_tot smallint,
                inf_ad_prod text,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99013_t99008_id_idx ON t99013 (t99008_id, n_item)",
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
                cnpj varchar(14),
                cpf varchar(11),
                ch_nfe char(44),
                dh_evento timestamptz,
                tp_evento varchar(20),
                n_seq_evento integer,
                x_evento varchar(255),
                dh_recbto timestamptz,
                n_prot varchar(20),
                versao varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99016 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                versao varchar(20),
                id_evento varchar(80),
                c_orgao varchar(10),
                tp_amb smallint,
                cnpj varchar(14),
                cpf varchar(11),
                ch_nfe char(44),
                dh_evento timestamptz,
                tp_evento varchar(20),
                n_seq_evento integer,
                ver_evento varchar(20),
                desc_evento varchar(255),
                c_stat integer,
                x_motivo varchar(255),
                n_prot varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99017 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                xml_det_evento text,
                json_det_evento text,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99018 (
                t99008_id bigint PRIMARY KEY REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                versao varchar(20),
                tp_amb smallint,
                x_serv varchar(60),
                c_uf varchar(4),
                ano varchar(4),
                cnpj varchar(14),
                mod varchar(10),
                serie varchar(10),
                n_nf_ini varchar(20),
                n_nf_fin varchar(20),
                x_just text,
                ver_aplic varchar(120),
                c_stat integer,
                x_motivo varchar(255),
                dh_recbto timestamptz,
                n_prot varchar(20),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99019 (
                id_t99019 bigserial PRIMARY KEY,
                t99008_id bigint UNIQUE REFERENCES t99008 (id_t99008) ON DELETE CASCADE,
                ch_nfe char(44),
                n_nf varchar(20),
                versao varchar(20),
                mod varchar(10),
                serie varchar(10),
                dh_emi timestamptz,
                dh_sai_ent timestamptz,
                v_prod numeric(18,2),
                v_frete numeric(18,2),
                v_seg numeric(18,2),
                v_desc numeric(18,2),
                v_ii numeric(18,2),
                v_ipi numeric(18,2),
                v_ipi_devol numeric(18,2),
                v_pis numeric(18,2),
                v_outro numeric(18,2),
                v_nf numeric(18,2),
                tp_imp varchar(10),
                inf_cpl text,
                proc_emi varchar(20),
                ver_proc varchar(60),
                tp_emis varchar(10),
                fin_nfe varchar(10),
                nat_op varchar(255),
                ind_intermed varchar(10),
                tp_nf smallint,
                dig_val varchar(128),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99019_t99008_id_idx ON t99019 (t99008_id)",
            "CREATE INDEX IF NOT EXISTS t99019_ch_nfe_idx ON t99019 (ch_nfe)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99020 (
                id_t99020 bigserial PRIMARY KEY,
                nome_razao_social varchar(255),
                nome_fantasia varchar(255),
                cnpj varchar(14) NOT NULL,
                endereco varchar(255),
                bairro_distrito varchar(255),
                cep varchar(12),
                municipio varchar(255),
                telefone varchar(20),
                uf varchar(4),
                pais varchar(120),
                inscricao_estadual varchar(20),
                inscricao_estadual_st varchar(20),
                inscricao_municipal varchar(20),
                municipio_ocorrencia_fato_gerador_icms varchar(255),
                cnae_fiscal varchar(20),
                codigo_regime_tributario varchar(10),
                versao integer NOT NULL,
                data_inicio timestamptz NOT NULL DEFAULT now(),
                data_fim timestamptz,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99020_cnpj_idx ON t99020 (cnpj, versao DESC)",
            "CREATE UNIQUE INDEX IF NOT EXISTS t99020_cnpj_ativo_uidx ON t99020 (cnpj) WHERE data_fim IS NULL",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99021 (
                id_t99021 bigserial PRIMARY KEY,
                nome_razao_social varchar(255),
                cnpj varchar(14) NOT NULL,
                endereco varchar(255),
                bairro_distrito varchar(255),
                cep varchar(12),
                municipio varchar(255),
                telefone varchar(20),
                uf varchar(4),
                pais varchar(120),
                indicador_ie varchar(10),
                inscricao_estadual varchar(20),
                inscricao_suframa varchar(20),
                im varchar(20),
                email varchar(255),
                versao integer NOT NULL,
                data_inicio timestamptz NOT NULL DEFAULT now(),
                data_fim timestamptz,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99021_cnpj_idx ON t99021 (cnpj, versao DESC)",
            "CREATE UNIQUE INDEX IF NOT EXISTS t99021_cnpj_ativo_uidx ON t99021 (cnpj) WHERE data_fim IS NULL",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99022 (
                id_t99022 bigserial PRIMARY KEY,
                modalidade_frete varchar(20),
                cnpj varchar(14) NOT NULL,
                nome_razao_social varchar(255),
                inscricao_estadual varchar(20),
                endereco_completo varchar(255),
                municipio varchar(255),
                uf varchar(4),
                volumes varchar(40),
                quantidade numeric(18,4),
                especie varchar(120),
                marca_volumes varchar(255),
                numeracao varchar(120),
                peso_liquido numeric(18,4),
                peso_bruto numeric(18,4),
                versao integer NOT NULL,
                data_inicio timestamptz NOT NULL DEFAULT now(),
                data_fim timestamptz,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99022_cnpj_idx ON t99022 (cnpj, versao DESC)",
            "CREATE UNIQUE INDEX IF NOT EXISTS t99022_cnpj_ativo_uidx ON t99022 (cnpj) WHERE data_fim IS NULL",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99023 (
                id_t99023 bigserial PRIMARY KEY,
                t99019_id bigint NOT NULL REFERENCES t99019 (id_t99019) ON DELETE CASCADE,
                t99020_id bigint NOT NULL REFERENCES t99020 (id_t99020) ON DELETE CASCADE,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE UNIQUE INDEX IF NOT EXISTS t99023_t99019_uidx ON t99023 (t99019_id)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99024 (
                id_t99024 bigserial PRIMARY KEY,
                t99019_id bigint NOT NULL REFERENCES t99019 (id_t99019) ON DELETE CASCADE,
                t99021_id bigint NOT NULL REFERENCES t99021 (id_t99021) ON DELETE CASCADE,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE UNIQUE INDEX IF NOT EXISTS t99024_t99019_uidx ON t99024 (t99019_id)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99025 (
                id_t99025 bigserial PRIMARY KEY,
                t99019_id bigint NOT NULL REFERENCES t99019 (id_t99019) ON DELETE CASCADE,
                t99022_id bigint NOT NULL REFERENCES t99022 (id_t99022) ON DELETE CASCADE,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE UNIQUE INDEX IF NOT EXISTS t99025_t99019_uidx ON t99025 (t99019_id)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99026 (
                id_t99026 bigserial PRIMARY KEY,
                t99019_id bigint NOT NULL REFERENCES t99019 (id_t99019) ON DELETE CASCADE,
                num integer,
                descricao text,
                qtd numeric(18,4),
                unidade_comercial varchar(20),
                valor numeric(18,6),
                codigo_produto varchar(80),
                codigo_ncm varchar(20),
                codigo_cest varchar(20),
                indicador_escala_relevante varchar(10),
                cnpj_fabricante_mercadoria varchar(14),
                codigo_beneficio_fiscal_uf varchar(40),
                codigo_ex_tipi varchar(20),
                cfop varchar(10),
                outras_despesas_acessorias numeric(18,2),
                valor_desconto numeric(18,2),
                valor_total_frete numeric(18,2),
                valor_seguro numeric(18,2),
                indicador_composicao_valor_total_nfe varchar(10),
                codigo_ean_comercial varchar(30),
                quantidade_comercial numeric(18,4),
                codigo_ean_tributavel varchar(30),
                unidade_tributavel varchar(20),
                quantidade_tributavel numeric(18,4),
                valor_unitario_comercializacao numeric(18,6),
                valor_unitario_tributacao numeric(18,6),
                numero_pedido_compra varchar(40),
                item_pedido_compra varchar(40),
                valor_aproximado_tributos numeric(18,2),
                numero_fci varchar(40),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99026_t99019_id_idx ON t99026 (t99019_id, num)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99027 (
                id_t99027 bigserial PRIMARY KEY,
                t99026_id bigint NOT NULL REFERENCES t99026 (id_t99026) ON DELETE CASCADE,
                nome_imposto varchar(120),
                cst varchar(20),
                base_calculo numeric(18,2),
                aliquota numeric(10,4),
                valor numeric(18,2),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99027_t99026_id_idx ON t99027 (t99026_id, nome_imposto)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99028 (
                id_t99028 bigserial PRIMARY KEY,
                t99019_id bigint NOT NULL REFERENCES t99019 (id_t99019) ON DELETE CASCADE,
                n_fat varchar(40),
                v_orig numeric(18,2),
                v_desc numeric(18,2),
                v_liq numeric(18,2),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE UNIQUE INDEX IF NOT EXISTS t99028_t99019_uidx ON t99028 (t99019_id)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99029 (
                id_t99029 bigserial PRIMARY KEY,
                t99019_id bigint NOT NULL REFERENCES t99019 (id_t99019) ON DELETE CASCADE,
                ind_pag varchar(10),
                t_pag varchar(10),
                x_pag varchar(255),
                v_pag numeric(18,2),
                d_pag timestamptz,
                cnpj_pag varchar(14),
                uf_pag varchar(4),
                tp_integra varchar(10),
                cnpj_cred varchar(14),
                t_band varchar(10),
                c_aut varchar(255),
                cnpj_receb varchar(14),
                id_term_pag varchar(40),
                v_troco numeric(18,2),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99029_t99019_id_idx ON t99029 (t99019_id, id_t99029)",
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99030 (
                id_t99030 bigserial PRIMARY KEY,
                t99028_id bigint NOT NULL REFERENCES t99028 (id_t99028) ON DELETE CASCADE,
                n_dup varchar(40),
                d_venc timestamptz,
                v_dup numeric(18,2),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL,
            "CREATE INDEX IF NOT EXISTS t99030_t99028_id_idx ON t99030 (t99028_id, id_t99030)",
        ];

        foreach ($createStatements as $statement) {
            $this->auditConnection->executeStatement($statement);
        }

        foreach (['t99007', 't99008', 't99009', 't99010', 't99011', 't99012', 't99013', 't99014', 't99015', 't99016', 't99017', 't99018', 't99019', 't99020', 't99021', 't99022', 't99023', 't99024', 't99025', 't99026', 't99027', 't99028', 't99029', 't99030'] as $table) {
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeVersionedPayload(array $payload): array
    {
        unset($payload['versao'], $payload['data_inicio'], $payload['data_fim'], $payload['dt_hr_atu']);

        foreach ($payload as $key => $value) {
            if ($key === 'quantidade' || $key === 'peso_liquido' || $key === 'peso_bruto') {
                $payload[$key] = $this->nullableDecimal($value);
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $payload[$key] = $value->format('c');
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $payload[$key] = $this->nullableTrim($value);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function hasMeaningfulValues(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->nullableTrim($payload[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . "\n...[truncado]";
    }
}
