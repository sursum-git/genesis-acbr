<?php

declare(strict_types=1);

namespace App\Service\Nfe;

use App\Repository\ApiExtractionRepository;
use App\Repository\ApiSuccessParameterRepository;
use App\Service\Api\ApiFunctionalSuccessMatcher;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class NfeAuthorizedArtifactService
{
    public function __construct(
        private readonly ApiSuccessParameterRepository $successParameterRepository,
        private readonly ApiFunctionalSuccessMatcher $successMatcher,
        private readonly NfeAuthorizedArtifactLocator $artifactLocator,
        private readonly AcbrLegacyScriptExecutor $executor,
        private readonly ApiExtractionRepository $extractionRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     * @return array{c_stat_receita?: string, xml_autorizado?: string, caminho_danfe?: string, danfe_base64?: string}
     */
    public function enrich(array $result, int $statusCode = 200): array
    {
        $parameters = $this->successParameterRepository->findActive();
        if ($parameters === null) {
            return [];
        }

        $decision = $this->successMatcher->match(
            $statusCode,
            $result,
            (string) ($parameters['c_codigos_sucesso_http'] ?? ''),
            (string) ($parameters['c_codigos_sucesso_receita'] ?? '')
        );
        if ($decision === null) {
            return [];
        }

        $artifacts = ['c_stat_receita' => $decision['c_stat_receita']];
        $accessKey = $this->artifactLocator->extractAccessKeyFromResult($result);
        if ($accessKey === null) {
            return $artifacts;
        }

        $xmlPath = $this->artifactLocator->locateAuthorizedXmlPath($accessKey);
        if ($xmlPath === null) {
            return $artifacts;
        }

        $xml = @file_get_contents($xmlPath);
        if (!is_string($xml) || trim($xml) === '') {
            return $artifacts;
        }

        $this->extractionRepository->upsertAuthorizedNfeArtifacts($accessKey, $xml, null);

        try {
            $pdfResult = $this->executor->execute('NFe/MT/ACBrNFeServicosMT.php', 'SalvarPDF', [
                'AeArquivoXmlNFe' => $xmlPath,
            ]);
            $pdfPath = $this->artifactLocator->extractPdfPath((string) ($pdfResult['mensagem'] ?? ''));
            if ($pdfPath === null || !is_file($pdfPath)) {
                return $artifacts + ['xml_autorizado' => $xml];
            }

            $pdfBinary = @file_get_contents($pdfPath);
            if (!is_string($pdfBinary) || $pdfBinary === '') {
                return $artifacts + ['xml_autorizado' => $xml];
            }

            $this->extractionRepository->upsertAuthorizedNfeArtifacts($accessKey, $xml, $pdfPath);

            return $artifacts + [
                'xml_autorizado' => $xml,
                'caminho_danfe' => $pdfPath,
                'danfe_base64' => base64_encode($pdfBinary),
            ];
        } catch (\Throwable) {
            return $artifacts + ['xml_autorizado' => $xml];
        }
    }
}
