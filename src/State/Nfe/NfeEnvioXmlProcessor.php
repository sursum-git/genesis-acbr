<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Nfe\NfeEnvioOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use DOMDocument;
use Symfony\Component\HttpFoundation\RequestStack;

final class NfeEnvioXmlProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AcbrLegacyScriptExecutor $executor,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NfeEnvioOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para envio de NFe por XML.');
        }

        $rawBody = trim((string) $request->getContent());
        if ($rawBody === '') {
            throw new AcbrLegacyApiException('Informe o XML completo da NF-e no corpo da requisição.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];
        $isAsync = (string) (($presetPayload['ASincrono'] ?? '1')) === '0';

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $xmlDocuments = $this->extractXmlDocuments($rawBody, $isAsync);
        $effectivePayload = is_array($presetPayload) ? $presetPayload : [];
        if ($isAsync && count($xmlDocuments) === 1) {
            // SEFAZ rejeita lote assincrono com apenas uma NF-e; faz fallback automatico para envio sincrono.
            $effectivePayload['ASincrono'] = '1';
        }
        $tempFiles = [];

        try {
            foreach ($xmlDocuments as $index => $xmlDocument) {
                $tempFiles[] = $this->writeTempXml($xmlDocument, $index + 1);
            }

            $lote = trim((string) $request->query->get('ALote', '1'));
            if ($lote === '') {
                $lote = '1';
            }

            $resultado = $this->executor->execute(
                $script,
                $method,
                array_merge(
                    $effectivePayload,
                    [
                        'AeArquivoNFe' => count($tempFiles) === 1 ? $tempFiles[0] : $tempFiles,
                        'ALote' => $lote,
                    ]
                )
            );
        } finally {
            foreach ($tempFiles as $tempFile) {
                @unlink($tempFile);
            }
        }

        return new NfeEnvioOutput(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    /**
     * @return list<string>
     */
    private function extractXmlDocuments(string $rawBody, bool $allowMultiple): array
    {
        $rawBody = trim($rawBody);
        if ($rawBody === '') {
            throw new AcbrLegacyApiException('Informe o XML completo da NF-e no corpo da requisição.');
        }

        if ($this->isValidXml($rawBody)) {
            return [$rawBody];
        }

        if (!$allowMultiple) {
            throw new AcbrLegacyApiException('O corpo enviado nao contem um XML valido de NF-e.');
        }

        $chunks = preg_split('/(?=<\?xml\b)/i', $rawBody, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chunks) || count($chunks) < 2) {
            throw new AcbrLegacyApiException('Para envio assíncrono com múltiplos arquivos, envie XMLs concatenados no corpo, cada um com sua declaração XML.');
        }

        $documents = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (!$this->isValidXml($chunk)) {
                throw new AcbrLegacyApiException('Um dos XMLs enviados no corpo da requisição nao e valido.');
            }

            $documents[] = $chunk;
        }

        if ($documents === []) {
            throw new AcbrLegacyApiException('Nenhum XML valido foi encontrado no corpo da requisição.');
        }

        return $documents;
    }

    private function writeTempXml(string $xml, int $index): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'nfe-envio-');
        if ($tempFile === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para o XML da NF-e.');
        }

        $xmlFile = $tempFile . '-' . $index . '.xml';
        @unlink($tempFile);

        if (file_put_contents($xmlFile, $xml) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel gravar o XML temporario da NF-e.');
        }

        return $xmlFile;
    }

    private function isValidXml(string $xml): bool
    {
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            return $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
    }
}
