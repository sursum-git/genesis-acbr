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
            $pdfMessage = (string) ($pdfResult['mensagem'] ?? '');
            $pdfPath = $this->artifactLocator->extractPdfPath($pdfMessage);
            $pdfBinary = null;

            if ($pdfPath !== null && is_file($pdfPath)) {
                $pdfBinary = @file_get_contents($pdfPath);
            } else {
                $pdfBinary = $this->artifactLocator->extractPdfBinary($pdfMessage);
                if (is_string($pdfBinary) && $pdfBinary !== '') {
                    $pdfPath = $this->artifactLocator->buildDanfePath($accessKey);
                    $pdfDirectory = dirname($pdfPath);
                    if (!is_dir($pdfDirectory) && !@mkdir($pdfDirectory, 0777, true) && !is_dir($pdfDirectory)) {
                        $pdfPath = null;
                    } elseif (@file_put_contents($pdfPath, $pdfBinary) === false) {
                        $pdfPath = null;
                    }
                }
            }

            if (!is_string($pdfBinary) || $pdfBinary === '') {
                return $artifacts + ['xml_autorizado' => $xml];
            }

            $this->extractionRepository->upsertAuthorizedNfeArtifacts($accessKey, $xml, $pdfPath);

            $response = [
                'xml_autorizado' => $xml,
                'danfe_base64' => base64_encode($pdfBinary),
            ];

            if (is_string($pdfPath) && $pdfPath !== '') {
                $response['caminho_danfe'] = $pdfPath;
            }

            return $artifacts + $response;
        } catch (\Throwable) {
            return $artifacts + ['xml_autorizado' => $xml];
        }
    }
}
