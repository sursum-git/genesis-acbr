<?php

namespace App\Service\Api;

use App\Repository\ApiExtractionRepository;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class ApiExtractionProcessor
{
    public function __construct(
        private readonly ApiExtractionRepository $extractionRepository,
        private readonly ApiExtractionPlanResolver $planResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $requestRow
     * @return array{nfe_count:int, nsu_count:int}
     */
    public function extract(array $requestRow): array
    {
        $path = trim((string) ($requestRow['c_caminho'] ?? ''));
        if (!$this->planResolver->isExtractablePath($path)) {
            return ['nfe_count' => 0, 'nsu_count' => 0];
        }

        $baseContext = [
            't99001_id' => (int) ($requestRow['id_t99001'] ?? 0),
            'u_c_request_id' => (string) ($requestRow['u_c_request_id'] ?? ''),
            'c_caminho_origem' => $path,
        ];

        $responseBody = (string) ($requestRow['t_corpo_resposta'] ?? '');
        $requestBody = (string) ($requestRow['t_corpo_requisicao'] ?? '');
        $queryParams = $this->parseQueryString((string) ($requestRow['t_query_string'] ?? ''));
        $decodedResponse = $this->decodeJsonObject($responseBody);

        $nfeCount = 0;
        $nsuCount = 0;
        $distributionHandled = false;

        foreach ($this->collectCandidatePayloads($decodedResponse, $responseBody) as $candidate) {
            $distributionStats = $this->tryExtractDistributionPayload($candidate, $baseContext, $queryParams);
            if ($distributionStats !== null) {
                $distributionHandled = true;
                $nfeCount += $distributionStats['nfe_count'];
                $nsuCount += $distributionStats['nsu_count'];
            }
        }

        if ($requestBody !== '') {
            $distributionStats = $this->tryExtractDistributionPayload($requestBody, $baseContext, $queryParams);
            if ($distributionStats !== null) {
                $distributionHandled = true;
                $nfeCount += $distributionStats['nfe_count'];
                $nsuCount += $distributionStats['nsu_count'];
            }
        }

        if ($distributionHandled) {
            return ['nfe_count' => $nfeCount, 'nsu_count' => $nsuCount];
        }

        $nfeRows = [];
        foreach ($this->collectCandidatePayloads($decodedResponse, $responseBody) as $candidate) {
            $row = $this->extractSimpleNfeCandidate($candidate, $baseContext);
            if ($row !== null) {
                $nfeRows[] = $row;
            }
        }

        if ($requestBody !== '') {
            $row = $this->extractSimpleNfeCandidate($requestBody, $baseContext);
            if ($row !== null) {
                $nfeRows[] = $row;
            }
        }

        if ($this->isNfeConsultPath($path) && $nfeRows === []) {
            $fallbackKey = $this->extractFallbackAccessKey($queryParams, $requestBody, $responseBody);
            if ($fallbackKey !== null) {
                $nfeRows[] = [
                    'c_ch_nfe' => $fallbackKey,
                    'c_schema_name' => 'consulta_texto_fallback',
                    'c_schema_family' => 'resNFe',
                    'c_hash_xml' => hash('sha256', $fallbackKey),
                    't_xml_descompactado' => $responseBody !== '' ? $responseBody : $requestBody,
                    't_xml_gzip_base64' => null,
                    'c_emit_cnpj_cpf' => null,
                    'c_dest_cnpj_cpf' => null,
                    'tp_amb' => null,
                    'normalized' => [
                        'type' => 'resNFe',
                        'resumo' => [
                            'c_versao' => null,
                            'c_emit_cnpj' => null,
                            'c_emit_cpf' => null,
                            'x_emit_nome' => null,
                            'c_emit_ie' => null,
                            'dh_emi' => null,
                            'tp_nf' => null,
                            'v_nf' => null,
                            'c_dig_val' => null,
                            'dh_recbto' => null,
                            'c_sit_nfe' => null,
                        ],
                    ],
                ];
            }
        }

        $nfeRows = $this->deduplicateRows($nfeRows, ['c_ch_nfe', 'c_schema_name', 'c_hash_xml']);
        $executionId = $this->extractionRepository->storeDistributionExecution($baseContext + [
            'c_tipo_consulta' => 'consulta_nfe',
            'c_documento_consulta' => $queryParams['eChaveOuNFe'] ?? $queryParams['AechNFe'] ?? null,
            'c_nsu_entrada' => $queryParams['AeNSU'] ?? $queryParams['AeultNSU'] ?? null,
            'q_doc_zip' => count($nfeRows),
            't_xml_envelope' => $responseBody !== '' ? $responseBody : $requestBody,
        ]);

        foreach ($nfeRows as $row) {
            $documentId = $this->extractionRepository->storeDistributionDocument($baseContext + $row + [
                't99007_id' => $executionId,
            ]);

            $this->persistNormalizedDocument($documentId, $row['normalized'] ?? null);
            $nfeCount++;
        }

        return ['nfe_count' => $nfeCount, 'nsu_count' => 0];
    }

    /**
     * @param array<string, mixed> $baseContext
     * @param array<string, string> $queryParams
     * @return array{nfe_count:int, nsu_count:int}|null
     */
    private function tryExtractDistributionPayload(string $candidate, array $baseContext, array $queryParams): ?array
    {
        if (!$this->looksLikeXml($candidate)) {
            return null;
        }

        $document = $this->loadXml($candidate);
        if ($document === null) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $rootName = $document->documentElement?->localName ?? '';
        if ($rootName !== 'retDistDFeInt' && $xpath->query('//*[local-name()="docZip"]')->length === 0) {
            return null;
        }

        return $this->extractDistributionXml($candidate, $document, $xpath, $baseContext, $queryParams);
    }

    /**
     * @param array<string, mixed> $baseContext
     * @param array<string, string> $queryParams
     * @return array{nfe_count:int, nsu_count:int}
     */
    private function extractDistributionXml(string $rawXml, DOMDocument $document, DOMXPath $xpath, array $baseContext, array $queryParams): array
    {
        $docZipNodes = $xpath->query('//*[local-name()="docZip"]');
        $docs = $docZipNodes === false ? [] : iterator_to_array($docZipNodes);

        $executionId = $this->extractionRepository->storeDistributionExecution($baseContext + [
            'c_tipo_consulta' => $this->resolveQueryType($baseContext['c_caminho_origem'] ?? ''),
            'c_documento_consulta' => $queryParams['AeCNPJCPF'] ?? $queryParams['AechNFe'] ?? null,
            'c_nsu_entrada' => $queryParams['AeNSU'] ?? $queryParams['AeultNSU'] ?? null,
            'tp_amb' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="tpAmb"])[1])'),
            'c_ver_aplic' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="verAplic"])[1])'),
            'c_stat' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="cStat"])[1])'),
            'x_motivo' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="xMotivo"])[1])'),
            'dh_resp' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="dhResp"])[1])'),
            'c_ult_nsu' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="ultNSU"])[1])'),
            'c_max_nsu' => $this->xpathValue($xpath, 'string((/*[local-name()="retDistDFeInt"]/*[local-name()="maxNSU"])[1])'),
            'q_doc_zip' => count($docs),
            't_xml_envelope' => $rawXml,
        ]);

        $nfeCount = 0;
        $nsuCount = 0;

        foreach ($docs as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $compressedPayload = trim((string) $node->textContent);
            $decodedXml = $this->decodeDocZip($compressedPayload);
            $decodedDoc = $this->loadXml($decodedXml);
            if ($decodedDoc === null) {
                continue;
            }

            $decodedXpath = new DOMXPath($decodedDoc);
            $documentRow = $this->extractDocumentRow(
                $decodedXml,
                $compressedPayload,
                $decodedDoc,
                $decodedXpath,
                trim((string) $node->getAttribute('NSU')),
                trim((string) $node->getAttribute('schema'))
            );

            $documentId = $this->extractionRepository->storeDistributionDocument($baseContext + $documentRow + [
                't99007_id' => $executionId,
            ]);

            $this->persistNormalizedDocument($documentId, $documentRow['normalized'] ?? null);

            $nsuCount++;
            if (($documentRow['c_schema_family'] ?? null) !== 'procEventoNFe') {
                $nfeCount++;
            }
        }

        return ['nfe_count' => $nfeCount, 'nsu_count' => $nsuCount];
    }

    /**
     * @param array<string, mixed>|null $normalized
     */
    private function persistNormalizedDocument(int $documentId, ?array $normalized): void
    {
        if ($normalized === null) {
            return;
        }

        switch ($normalized['type'] ?? null) {
            case 'resNFe':
                $this->extractionRepository->upsertNfeResumo($documentId, $normalized['resumo'] ?? []);
                break;

            case 'procNFe':
                $this->extractionRepository->upsertNfeProc($documentId, $normalized['proc'] ?? []);
                $this->extractionRepository->upsertNfeEmitente($documentId, $normalized['emitente'] ?? []);
                $this->extractionRepository->upsertNfeDestinatario($documentId, $normalized['destinatario'] ?? []);
                $this->extractionRepository->replaceNfeItens($documentId, $normalized['itens'] ?? []);
                $this->extractionRepository->upsertNfeTotal($documentId, $normalized['total'] ?? []);
                break;

            case 'resEvento':
                $this->extractionRepository->upsertEventoResumo($documentId, $normalized['resumo'] ?? []);
                break;

            case 'procEventoNFe':
                $this->extractionRepository->upsertEventoProc($documentId, $normalized['evento'] ?? []);
                $this->extractionRepository->upsertEventoDetalhe(
                    $documentId,
                    $normalized['xml_det_evento'] ?? null,
                    $normalized['json_det_evento'] ?? null
                );
                break;

            case 'procInutNFe':
                $this->extractionRepository->upsertInutilizacaoProc($documentId, $normalized['inutilizacao'] ?? []);
                break;
        }
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return array<string, mixed>|null
     */
    private function extractSimpleNfeCandidate(string $candidate, array $baseContext): ?array
    {
        $consultaText = $this->extractConsultaTextRow($candidate);
        if ($consultaText !== null) {
            return $consultaText;
        }

        if (!$this->looksLikeXml($candidate)) {
            return null;
        }

        $document = $this->loadXml($candidate);
        if ($document === null) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $rootName = $document->documentElement?->localName ?? '';
        if (in_array($rootName, ['retDistDFeInt', 'procEventoNFe', 'resEvento', 'procInutNFe', 'inutNFe', 'retInutNFe'], true)) {
            return null;
        }

        return $this->extractDocumentRow($candidate, null, $document, $xpath, null, $rootName !== '' ? $rootName : 'xml');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractConsultaTextRow(string $candidate): ?array
    {
        $trimmed = trim($candidate);
        if (!str_starts_with($trimmed, '[Consulta]')) {
            return null;
        }

        $fields = [];
        foreach (preg_split('/\r\n|\r|\n/', $trimmed) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line === '[Consulta]') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            if ($key !== '') {
                $fields[$key] = trim($value);
            }
        }

        if ($fields === []) {
            return null;
        }

        $accessKey = $this->nullableString($fields['ChNFe'] ?? null);
        $statusText = $this->nullableString($fields['XMotivo'] ?? $fields['Msg'] ?? null);
        if ($accessKey === null && $statusText === null) {
            return null;
        }

        return [
            'c_nsu' => null,
            'c_schema_name' => 'consulta_texto',
            'c_schema_family' => 'resNFe',
            'c_ch_nfe' => $accessKey,
            'c_tp_evento' => null,
            'i_n_seq_evento' => null,
            'c_n_prot' => $this->nullableString($fields['NProt'] ?? null),
            't_xml_gzip_base64' => null,
            't_xml_descompactado' => $candidate,
            'c_hash_xml' => hash('sha256', $candidate),
            'tp_amb' => null,
            'c_emit_cnpj_cpf' => null,
            'c_dest_cnpj_cpf' => null,
            'normalized' => [
                'type' => 'resNFe',
                'resumo' => [
                    'c_emit_cnpj' => null,
                    'c_emit_cpf' => null,
                    'x_emit_nome' => null,
                    'c_emit_ie' => null,
                    'dh_emi' => null,
                    'tp_nf' => null,
                    'v_nf' => null,
                    'c_dig_val' => null,
                    'dh_recbto' => $this->normalizeBrazilianDateTime($fields['DhRecbto'] ?? null),
                    'c_sit_nfe' => $this->nullableString($fields['CStat'] ?? null),
                    'c_versao' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDocumentRow(
        string $decodedXml,
        ?string $compressedPayload,
        DOMDocument $document,
        DOMXPath $xpath,
        ?string $nsu,
        string $schemaName
    ): array {
        $rootName = $document->documentElement?->localName ?? '';
        $schemaFamily = $this->resolveSchemaFamily($schemaName, $rootName);

        $payload = [
            'c_nsu' => $nsu,
            'c_schema_name' => $schemaName,
            'c_schema_family' => $schemaFamily,
            'c_ch_nfe' => $this->firstNonEmpty(
                $this->xpathValue($xpath, 'string((//*[local-name()="chNFe"])[1])'),
                $this->extractAccessKeyFromString($decodedXml)
            ),
            'c_tp_evento' => $this->xpathValue($xpath, 'string((//*[local-name()="tpEvento"])[1])'),
            'i_n_seq_evento' => $this->xpathValue($xpath, 'string((//*[local-name()="nSeqEvento"])[1])'),
            'c_n_prot' => $this->xpathValue($xpath, 'string((//*[local-name()="nProt"])[1])'),
            't_xml_gzip_base64' => $compressedPayload,
            't_xml_descompactado' => $decodedXml,
            'c_hash_xml' => hash('sha256', $decodedXml),
            'tp_amb' => $this->xpathValue($xpath, 'string((//*[local-name()="tpAmb"])[1])'),
            'c_emit_cnpj_cpf' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="CNPJ" or local-name()="CPF"])[1] | (//*[local-name()="CNPJ"])[1] | (//*[local-name()="CPF"])[1])'),
            'c_dest_cnpj_cpf' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="CNPJ" or local-name()="CPF"])[1])'),
        ];

        $payload['normalized'] = match ($schemaFamily) {
            'resNFe' => $this->buildResNfeNormalized($xpath),
            'procNFe' => $this->buildProcNfeNormalized($document, $xpath),
            'resEvento' => $this->buildResEventoNormalized($xpath),
            'procEventoNFe' => $this->buildProcEventoNormalized($document, $xpath),
            'procInutNFe' => $this->buildProcInutNormalized($xpath),
            default => null,
        };

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResNfeNormalized(DOMXPath $xpath): array
    {
        return [
            'type' => 'resNFe',
            'resumo' => [
                'c_emit_cnpj' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="CNPJ"])[1])'),
                'c_emit_cpf' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="CPF"])[1])'),
                'x_emit_nome' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="xNome"])[1])'),
                'c_emit_ie' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="IE"])[1])'),
                'dh_emi' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="dhEmi"])[1])'),
                'tp_nf' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="tpNF"])[1])'),
                'v_nf' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="vNF"])[1])'),
                'c_dig_val' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="digVal"])[1])'),
                'dh_recbto' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="dhRecbto"])[1])'),
                'c_sit_nfe' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/*[local-name()="cSitNFe"])[1])'),
                'c_versao' => $this->xpathValue($xpath, 'string((/*[local-name()="resNFe"]/@versao)[1])'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProcNfeNormalized(DOMDocument $document, DOMXPath $xpath): array
    {
        $items = [];
        $itemNodes = $xpath->query('//*[local-name()="NFe"]/*[local-name()="infNFe"]/*[local-name()="det"]');
        if ($itemNodes !== false) {
            foreach ($itemNodes as $itemNode) {
                if (!$itemNode instanceof DOMElement) {
                    continue;
                }

                $items[] = [
                    'i_n_item' => $itemNode->getAttribute('nItem'),
                    'c_prod' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="cProd"]', $itemNode),
                    'c_ean' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="cEAN"]', $itemNode),
                    'x_prod' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="xProd"]', $itemNode),
                    'c_ncm' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="NCM"]', $itemNode),
                    'c_cest' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="CEST"]', $itemNode),
                    'c_cfop' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="CFOP"]', $itemNode),
                    'c_ucom' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="uCom"]', $itemNode),
                    'q_com' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="qCom"]', $itemNode),
                    'v_un_com' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="vUnCom"]', $itemNode),
                    'v_prod' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="vProd"]', $itemNode),
                    'c_ean_trib' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="cEANTrib"]', $itemNode),
                    'c_utrib' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="uTrib"]', $itemNode),
                    'q_trib' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="qTrib"]', $itemNode),
                    'v_un_trib' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="vUnTrib"]', $itemNode),
                    'v_frete' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="vFrete"]', $itemNode),
                    'v_seg' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="vSeg"]', $itemNode),
                    'v_desc' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="vDesc"]', $itemNode),
                    'i_ind_tot' => $this->queryNodeString($xpath, './*[local-name()="prod"]/*[local-name()="indTot"]', $itemNode),
                    't_inf_ad_prod' => $this->queryNodeString($xpath, './*[local-name()="infAdProd"]', $itemNode),
                ];
            }
        }

        return [
            'type' => 'procNFe',
            'proc' => [
                'c_versao' => $this->xpathValue($xpath, 'string((/*[local-name()="nfeProc"]/@versao)[1])'),
                'c_id_nfe' => $this->xpathValue($xpath, 'string((//*[local-name()="infNFe"]/@Id)[1])'),
                'c_uf' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="cUF"])[1])'),
                'c_nnf' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="nNF"])[1])'),
                'c_serie' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="serie"])[1])'),
                'c_mod' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="mod"])[1])'),
                'dh_emi' => $this->firstNonEmpty(
                    $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="dhEmi"])[1])'),
                    $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="dEmi"])[1])')
                ),
                'dh_saida_entrada' => $this->firstNonEmpty(
                    $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="dhSaiEnt"])[1])'),
                    $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="dSaiEnt"])[1])')
                ),
                'tp_nf' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="tpNF"])[1])'),
                'c_id_dest' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="idDest"])[1])'),
                'c_mun_fg' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="cMunFG"])[1])'),
                'c_tp_emis' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="tpEmis"])[1])'),
                'tp_amb' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="tpAmb"])[1])'),
                'c_fin_nfe' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="finNFe"])[1])'),
                'c_ind_final' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="indFinal"])[1])'),
                'c_ind_pres' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="indPres"])[1])'),
                'c_proc_emi' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="procEmi"])[1])'),
                'c_ver_proc' => $this->xpathValue($xpath, 'string((//*[local-name()="ide"]/*[local-name()="verProc"])[1])'),
                'c_n_prot' => $this->xpathValue($xpath, 'string((//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="nProt"])[1])'),
                'c_ch_nfe' => $this->xpathValue($xpath, 'string((//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="chNFe"])[1])'),
            ],
            'emitente' => [
                'c_cnpj' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="CNPJ"])[1])'),
                'c_cpf' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="CPF"])[1])'),
                'x_nome' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="xNome"])[1])'),
                'x_fant' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="xFant"])[1])'),
                'c_ie' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="IE"])[1])'),
                'c_iest' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="IEST"])[1])'),
                'c_im' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="IM"])[1])'),
                'c_cnae' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="CNAE"])[1])'),
                'c_crt' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="CRT"])[1])'),
                'x_lgr' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="xLgr"])[1])'),
                'c_nro' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="nro"])[1])'),
                'x_bairro' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="xBairro"])[1])'),
                'c_mun' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="cMun"])[1])'),
                'x_mun' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="xMun"])[1])'),
                'c_uf' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="UF"])[1])'),
                'c_cep' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="CEP"])[1])'),
                'c_pais' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="cPais"])[1])'),
                'x_pais' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="xPais"])[1])'),
                'c_fone' => $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="enderEmit"]/*[local-name()="fone"])[1])'),
            ],
            'destinatario' => [
                'c_cnpj' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="CNPJ"])[1])'),
                'c_cpf' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="CPF"])[1])'),
                'c_id_estrangeiro' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="idEstrangeiro"])[1])'),
                'x_nome' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="xNome"])[1])'),
                'c_ind_ie_dest' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="indIEDest"])[1])'),
                'c_ie' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="IE"])[1])'),
                'c_isuf' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="ISUF"])[1])'),
                'c_im' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="IM"])[1])'),
                'c_email' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="email"])[1])'),
                'x_lgr' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="xLgr"])[1])'),
                'c_nro' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="nro"])[1])'),
                'x_bairro' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="xBairro"])[1])'),
                'c_mun' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="cMun"])[1])'),
                'x_mun' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="xMun"])[1])'),
                'c_uf' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="UF"])[1])'),
                'c_cep' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="CEP"])[1])'),
                'c_pais' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="cPais"])[1])'),
                'x_pais' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="xPais"])[1])'),
                'c_fone' => $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="fone"])[1])'),
            ],
            'itens' => $items,
            'total' => [
                'v_bc' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vBC"])[1])'),
                'v_icms' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vICMS"])[1])'),
                'v_icms_deson' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vICMSDeson"])[1])'),
                'v_fcp' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vFCP"])[1])'),
                'v_bcst' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vBCST"])[1])'),
                'v_st' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vST"])[1])'),
                'v_fcpst' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vFCPST"])[1])'),
                'v_prod' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vProd"])[1])'),
                'v_frete' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vFrete"])[1])'),
                'v_seg' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vSeg"])[1])'),
                'v_desc' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vDesc"])[1])'),
                'v_ii' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vII"])[1])'),
                'v_ipi' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vIPI"])[1])'),
                'v_ipi_devol' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vIPIDevol"])[1])'),
                'v_pis' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vPIS"])[1])'),
                'v_cofins' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vCOFINS"])[1])'),
                'v_outro' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vOutro"])[1])'),
                'v_nf' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vNF"])[1])'),
                'v_tot_trib' => $this->xpathValue($xpath, 'string((//*[local-name()="ICMSTot"]/*[local-name()="vTotTrib"])[1])'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResEventoNormalized(DOMXPath $xpath): array
    {
        return [
            'type' => 'resEvento',
            'resumo' => [
                'c_orgao' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="cOrgao"])[1])'),
                'c_cnpj' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="CNPJ"])[1])'),
                'c_cpf' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="CPF"])[1])'),
                'c_ch_nfe' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="chNFe"])[1])'),
                'dh_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="dhEvento"])[1])'),
                'c_tp_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="tpEvento"])[1])'),
                'i_n_seq_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="nSeqEvento"])[1])'),
                'x_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="xEvento"])[1])'),
                'dh_recbto' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="dhRecbto"])[1])'),
                'c_n_prot' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/*[local-name()="nProt"])[1])'),
                'c_versao' => $this->xpathValue($xpath, 'string((/*[local-name()="resEvento"]/@versao)[1])'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProcEventoNormalized(DOMDocument $document, DOMXPath $xpath): array
    {
        $detNode = $xpath->query('(/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="detEvento"])[1]')->item(0);

        return [
            'type' => 'procEventoNFe',
            'evento' => [
                'c_versao' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/@versao)[1])'),
                'c_id_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/@Id)[1])'),
                'c_orgao' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="cOrgao"])[1])'),
                'tp_amb' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="tpAmb"])[1])'),
                'c_cnpj' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="CNPJ"])[1])'),
                'c_cpf' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="CPF"])[1])'),
                'c_ch_nfe' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="chNFe"])[1])'),
                'dh_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="dhEvento"])[1])'),
                'c_tp_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="tpEvento"])[1])'),
                'i_n_seq_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="nSeqEvento"])[1])'),
                'c_ver_evento' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="evento"]/*[local-name()="infEvento"]/*[local-name()="verEvento"])[1])'),
                'x_desc_evento' => $this->firstNonEmpty(
                    $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="xEvento"])[1])'),
                    $this->queryNodeString($xpath, './*[local-name()="descEvento"]', $detNode)
                ),
                'c_stat' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="cStat"])[1])'),
                'x_motivo' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="xMotivo"])[1])'),
                'c_n_prot' => $this->xpathValue($xpath, 'string((/*[local-name()="procEventoNFe"]/*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="nProt"])[1])'),
            ],
            'xml_det_evento' => $detNode !== null ? $document->saveXML($detNode) : null,
            'json_det_evento' => $detNode !== null ? json_encode($this->domNodeToArray($detNode), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProcInutNormalized(DOMXPath $xpath): array
    {
        return [
            'type' => 'procInutNFe',
            'inutilizacao' => [
                'c_versao' => $this->xpathValue($xpath, 'string((/*[local-name()="ProcInutNFe"]/@versao | /*[local-name()="procInutNFe"]/@versao)[1])'),
                'tp_amb' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="tpAmb"])[1])'),
                'x_serv' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="xServ"])[1])'),
                'c_uf' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="cUF"])[1])'),
                'c_ano' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="ano"])[1])'),
                'c_cnpj' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="CNPJ"])[1])'),
                'c_mod' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="mod"])[1])'),
                'c_serie' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="serie"])[1])'),
                'c_nnf_ini' => $this->firstNonEmpty(
                    $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="nNFIni"])[1])'),
                    $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="nNFIni"])[1])')
                ),
                'c_nnf_fin' => $this->firstNonEmpty(
                    $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="nNFFin"])[1])'),
                    $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="nNFFin"])[1])')
                ),
                'x_just' => $this->xpathValue($xpath, 'string((//*[local-name()="infInut"]/*[local-name()="xJust"])[1])'),
                'c_ver_aplic' => $this->xpathValue($xpath, 'string((//*[local-name()="retInutNFe"]/*[local-name()="infInut"]/*[local-name()="verAplic"])[1])'),
                'c_stat' => $this->xpathValue($xpath, 'string((//*[local-name()="retInutNFe"]/*[local-name()="infInut"]/*[local-name()="cStat"])[1])'),
                'x_motivo' => $this->xpathValue($xpath, 'string((//*[local-name()="retInutNFe"]/*[local-name()="infInut"]/*[local-name()="xMotivo"])[1])'),
                'dh_recbto' => $this->xpathValue($xpath, 'string((//*[local-name()="retInutNFe"]/*[local-name()="infInut"]/*[local-name()="dhRecbto"])[1])'),
                'c_n_prot' => $this->xpathValue($xpath, 'string((//*[local-name()="retInutNFe"]/*[local-name()="infInut"]/*[local-name()="nProt"])[1])'),
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $decodedResponse
     * @return list<string>
     */
    private function collectCandidatePayloads(?array $decodedResponse, string $rawResponse): array
    {
        $payloads = [];
        $seen = [];

        $collector = function (mixed $value) use (&$collector, &$payloads, &$seen): void {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '' && !isset($seen[$trimmed])) {
                    $seen[$trimmed] = true;
                    $payloads[] = $trimmed;
                }

                return;
            }

            if (!is_array($value)) {
                return;
            }

            foreach ($value as $item) {
                $collector($item);
            }
        };

        $collector($decodedResponse);

        $trimmedRaw = trim($rawResponse);
        if ($trimmedRaw !== '' && !isset($seen[$trimmedRaw])) {
            $payloads[] = $trimmedRaw;
        }

        return $payloads;
    }

    /**
     * @return array<string, string>
     */
    private function parseQueryString(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        parse_str($value, $parsed);

        return array_filter($parsed, static fn (mixed $item): bool => is_string($item)) ?: [];
    }

    private function decodeDocZip(string $value): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return $value;
        }

        $inflated = @gzdecode($decoded);
        if ($inflated === false || trim($inflated) === '') {
            return $decoded;
        }

        return $inflated;
    }

    private function resolveSchemaFamily(string $schemaName, string $rootName): string
    {
        $lowerSchema = strtolower($schemaName);

        return match (true) {
            str_contains($lowerSchema, 'resnfe') || $rootName === 'resNFe' => 'resNFe',
            str_contains($lowerSchema, 'procnfe') || $rootName === 'nfeProc' => 'procNFe',
            str_contains($lowerSchema, 'resevento') || $rootName === 'resEvento' => 'resEvento',
            str_contains($lowerSchema, 'proceventonfe') || $rootName === 'procEventoNFe' => 'procEventoNFe',
            str_contains($lowerSchema, 'procinutnfe') || in_array($rootName, ['ProcInutNFe', 'procInutNFe'], true) => 'procInutNFe',
            default => $rootName !== '' ? $rootName : $schemaName,
        };
    }

    private function resolveQueryType(string $path): string
    {
        return match ($path) {
            '/nfe/distribuicao-dfe/por-chave' => 'por_chave',
            '/nfe/distribuicao-dfe/por-nsu' => 'por_nsu',
            '/nfe/distribuicao-dfe/por-ult-nsu' => 'por_ult_nsu',
            default => 'consulta_nfe',
        };
    }

    private function extractFallbackAccessKey(array $queryParams, string $requestBody, string $responseBody): ?string
    {
        foreach (['eChaveOuNFe', 'AechNFe'] as $key) {
            $value = trim((string) ($queryParams[$key] ?? ''));
            if (preg_match('/^\d{44}$/', $value) === 1) {
                return $value;
            }
        }

        foreach ([$requestBody, $responseBody] as $payload) {
            $key = $this->extractAccessKeyFromString($payload);
            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }

    private function extractAccessKeyFromString(string $value): ?string
    {
        if (preg_match('/\b\d{44}\b/', $value, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeBrazilianDateTime(mixed $value): ?string
    {
        $trimmed = $this->nullableString($value);
        if ($trimmed === null) {
            return null;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $trimmed);
        if ($dateTime === false) {
            return $trimmed;
        }

        return $dateTime->format('c');
    }

    private function looksLikeXml(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== ''
            && str_contains($trimmed, '<')
            && str_contains($trimmed, '>')
            && (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<'));
    }

    private function loadXml(string $xml): ?DOMDocument
    {
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
                return null;
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
    }

    private function xpathValue(DOMXPath $xpath, string $expression): ?string
    {
        $value = trim((string) $xpath->evaluate($expression));

        return $value === '' ? null : $value;
    }

    private function queryNodeString(DOMXPath $xpath, string $expression, ?DOMNode $contextNode): ?string
    {
        if ($contextNode === null) {
            return null;
        }

        $value = trim((string) $xpath->evaluate(sprintf('string(%s)', $expression), $contextNode));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $value): ?array
    {
        if (trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function isNfeConsultPath(string $path): bool
    {
        return in_array($path, ['/nfe/consultas/consultar-com-chave', '/nfe/consultas/consultar-com-chave-xml'], true);
    }

    private function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $keys
     * @return list<array<string, mixed>>
     */
    private function deduplicateRows(array $rows, array $keys): array
    {
        $unique = [];
        $seen = [];

        foreach ($rows as $row) {
            $signatureParts = [];
            foreach ($keys as $key) {
                $signatureParts[] = (string) ($row[$key] ?? '');
            }

            $signature = implode('|', $signatureParts);
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function domNodeToArray(DOMNode $node): array
    {
        $result = ['name' => $node->localName ?? $node->nodeName];

        if ($node instanceof DOMElement && $node->attributes->length > 0) {
            $attributes = [];
            foreach ($node->attributes as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue;
            }
            $result['attributes'] = $attributes;
        }

        $children = [];
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $children[] = $this->domNodeToArray($childNode);
            }
        }

        $text = trim($node->textContent ?? '');
        if ($children === []) {
            $result['value'] = $text;

            return $result;
        }

        if ($text !== '') {
            $result['value'] = $text;
        }

        $result['children'] = $children;

        return $result;
    }
}
