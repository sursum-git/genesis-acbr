<?php

namespace App\Service\Api;

use App\Repository\ApiExtractionRepository;
use DOMDocument;
use DOMElement;
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

        $nfeRows = [];
        $nsuRows = [];

        foreach ($this->collectCandidatePayloads($decodedResponse, $responseBody) as $candidate) {
            $this->extractFromCandidate($candidate, $baseContext, $queryParams, $nfeRows, $nsuRows);
        }

        if ($requestBody !== '') {
            $this->extractFromCandidate($requestBody, $baseContext, $queryParams, $nfeRows, $nsuRows);
        }

        if ($this->isNfeConsultPath($path) && $nfeRows === []) {
            $fallbackKey = $this->extractFallbackAccessKey($queryParams, $requestBody, $responseBody);
            $nfeRows[] = $baseContext + [
                'c_tipo_documento' => 'consulta_nfe',
                'c_chave_acesso' => $fallbackKey,
                't_payload_bruto' => $responseBody !== '' ? $responseBody : $requestBody,
            ];
        }

        $nfeRows = $this->deduplicateRows($nfeRows, ['c_chave_acesso', 'c_nsu_relacionado', 'c_tipo_documento', 't_payload_bruto']);
        $nsuRows = $this->deduplicateRows($nsuRows, ['c_nsu', 'c_ult_nsu', 'c_max_nsu', 'c_schema', 't_payload_bruto']);

        foreach ($nfeRows as $row) {
            $this->extractionRepository->storeNfeExtraction($row);
        }

        foreach ($nsuRows as $row) {
            $this->extractionRepository->storeNsuExtraction($row);
        }

        return [
            'nfe_count' => count($nfeRows),
            'nsu_count' => count($nsuRows),
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
     * @param array<string, mixed> $baseContext
     * @param array<string, string> $queryParams
     * @param list<array<string, mixed>> $nfeRows
     * @param list<array<string, mixed>> $nsuRows
     */
    private function extractFromCandidate(string $candidate, array $baseContext, array $queryParams, array &$nfeRows, array &$nsuRows): void
    {
        $consultaTextRow = $this->extractConsultaTextRow($candidate, $baseContext);
        if ($consultaTextRow !== null) {
            $nfeRows[] = $consultaTextRow;

            return;
        }

        if (!$this->looksLikeXml($candidate)) {
            return;
        }

        $document = $this->loadXml($candidate);
        if ($document === null) {
            return;
        }

        $xpath = new DOMXPath($document);
        $rootName = $document->documentElement?->localName ?? '';

        if (in_array($rootName, ['procInutNFe', 'inutNFe', 'retInutNFe'], true)) {
            return;
        }

        if ($rootName === 'retDistDFeInt' || $xpath->query('//*[local-name()="docZip"]')->length > 0) {
            $this->extractDistributionXml($candidate, $document, $xpath, $baseContext, $queryParams, $nfeRows, $nsuRows);

            return;
        }

        $nfeRow = $this->extractNfeRowFromXml($candidate, $document, $xpath, $baseContext, null);
        if ($nfeRow !== null) {
            $nfeRows[] = $nfeRow;
        }
    }

    /**
     * @param array<string, mixed> $baseContext
     * @param array<string, string> $queryParams
     * @param list<array<string, mixed>> $nfeRows
     * @param list<array<string, mixed>> $nsuRows
     */
    private function extractDistributionXml(string $rawXml, DOMDocument $document, DOMXPath $xpath, array $baseContext, array $queryParams, array &$nfeRows, array &$nsuRows): void
    {
        $wrapper = [
            'c_tipo_item' => 'distdfe',
            'c_nsu_consultado' => $queryParams['AeNSU'] ?? $queryParams['AeultNSU'] ?? null,
            'c_ult_nsu' => $this->xpathValue($xpath, 'string((//*[local-name()="ultNSU"])[1])'),
            'c_max_nsu' => $this->xpathValue($xpath, 'string((//*[local-name()="maxNSU"])[1])'),
            'c_stat' => $this->xpathValue($xpath, 'string((//*[local-name()="cStat"])[1])'),
            'x_motivo' => $this->xpathValue($xpath, 'string((//*[local-name()="xMotivo"])[1])'),
            'c_situacao' => $this->xpathValue($xpath, 'string((//*[local-name()="xMotivo"])[1])'),
            't_payload_bruto' => $rawXml,
        ];

        $docZipNodes = $xpath->query('//*[local-name()="docZip"]');
        if ($docZipNodes === false || $docZipNodes->length === 0) {
            $nsuRows[] = $baseContext + $wrapper;

            return;
        }

        /** @var DOMElement $node */
        foreach ($docZipNodes as $node) {
            $nsu = trim((string) $node->getAttribute('NSU'));
            $schema = trim((string) $node->getAttribute('schema'));
            $decodedXml = $this->decodeDocZip(trim((string) $node->textContent));
            $nsuRows[] = $baseContext + $wrapper + [
                'c_tipo_item' => $schema !== '' ? $schema : 'docZip',
                'c_nsu' => $nsu !== '' ? $nsu : null,
                'c_schema' => $schema !== '' ? $schema : null,
                'c_chave_acesso' => $this->extractAccessKeyFromString($decodedXml),
                't_payload_bruto' => $decodedXml,
            ];

            $decodedDoc = $this->loadXml($decodedXml);
            if ($decodedDoc === null) {
                continue;
            }

            $decodedXpath = new DOMXPath($decodedDoc);
            $nfeRow = $this->extractNfeRowFromXml($decodedXml, $decodedDoc, $decodedXpath, $baseContext, $nsu !== '' ? $nsu : null);
            if ($nfeRow !== null) {
                $nfeRows[] = $nfeRow;
            }
        }
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return array<string, mixed>|null
     */
    private function extractNfeRowFromXml(string $rawXml, DOMDocument $document, DOMXPath $xpath, array $baseContext, ?string $nsu): ?array
    {
        $rootName = $document->documentElement?->localName ?? '';
        if (in_array($rootName, ['procInutNFe', 'inutNFe', 'retInutNFe'], true)) {
            return null;
        }

        $accessKey = $this->xpathValue($xpath, 'string((//*[local-name()="chNFe"])[1])');
        if ($accessKey === null) {
            $id = $this->xpathValue($xpath, 'string((//*[local-name()="infNFe"]/@Id)[1])');
            if ($id !== null && str_starts_with($id, 'NFe')) {
                $accessKey = substr($id, 3);
            }
        }

        $number = $this->xpathValue($xpath, 'string((//*[local-name()="nNF"])[1])');
        $series = $this->xpathValue($xpath, 'string((//*[local-name()="serie"])[1])');
        $model = $this->xpathValue($xpath, 'string((//*[local-name()="mod"])[1])');
        $emit = $this->xpathValue($xpath, 'string((//*[local-name()="emit"]/*[local-name()="CNPJ" or local-name()="CPF"])[1])');
        $dest = $this->xpathValue($xpath, 'string((//*[local-name()="dest"]/*[local-name()="CNPJ" or local-name()="CPF"])[1])');
        $interested = $this->xpathValue($xpath, 'string((//*[local-name()="autXML"]/*[local-name()="CNPJ" or local-name()="CPF"])[1])');
        $cStat = $this->xpathValue($xpath, 'string((//*[local-name()="cStat"])[1])');
        $xMotivo = $this->xpathValue($xpath, 'string((//*[local-name()="xMotivo"])[1])');
        $dhEmi = $this->xpathValue($xpath, 'string((//*[local-name()="dhEmi" or local-name()="dEmi"])[1])');
        $dhAutorizacao = $this->xpathValue($xpath, 'string((//*[local-name()="dhRecbto"])[1])');

        if ($accessKey === null && $number === null && $emit === null && $dest === null) {
            return null;
        }

        return $baseContext + [
            'c_tipo_documento' => $rootName !== '' ? $rootName : 'nfe',
            'c_chave_acesso' => $accessKey,
            'c_nsu_relacionado' => $nsu,
            'c_numero' => $number,
            'c_serie' => $series,
            'c_modelo' => $model,
            'c_emitente_documento' => $emit,
            'c_destinatario_documento' => $dest,
            'c_interessado_documento' => $interested,
            'c_stat' => $cStat,
            'x_motivo' => $xMotivo,
            'c_situacao' => $xMotivo,
            'dt_emissao' => $dhEmi,
            'dt_autorizacao' => $dhAutorizacao,
            't_payload_bruto' => $rawXml,
        ];
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

    /**
     * @param array<string, mixed> $baseContext
     * @return array<string, mixed>|null
     */
    private function extractConsultaTextRow(string $candidate, array $baseContext): ?array
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
            if ($key === '') {
                continue;
            }

            $fields[$key] = trim($value);
        }

        if ($fields === []) {
            return null;
        }

        $accessKey = $this->nullableString($fields['ChNFe'] ?? null);
        $receivedAt = $this->normalizeBrazilianDateTime($fields['DhRecbto'] ?? null);
        $statusText = $this->nullableString($fields['XMotivo'] ?? $fields['Msg'] ?? null);

        if ($accessKey === null && $statusText === null) {
            return null;
        }

        return $baseContext + [
            'c_tipo_documento' => 'consulta_texto',
            'c_chave_acesso' => $accessKey,
            'c_stat' => $this->nullableString($fields['CStat'] ?? null),
            'x_motivo' => $statusText,
            'c_situacao' => $statusText,
            'dt_autorizacao' => $receivedAt,
            't_payload_bruto' => $candidate,
        ];
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

        return $dateTime->format('Y-m-d H:i:s');
    }

    private function looksLikeXml(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== ''
            && str_contains($trimmed, '<')
            && str_contains($trimmed, '>')
            && (
                str_starts_with($trimmed, '<?xml')
                || str_starts_with($trimmed, '<')
            );
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
}
