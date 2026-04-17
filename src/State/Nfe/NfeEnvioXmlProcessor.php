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

        $xml = trim((string) $request->getContent());
        if ($xml === '') {
            throw new AcbrLegacyApiException('Informe o XML completo da NF-e no corpo da requisição.');
        }

        $this->assertValidXml($xml);

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'nfe-envio-');
        if ($tempFile === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para o XML da NF-e.');
        }

        $xmlFile = $tempFile . '.xml';
        @unlink($tempFile);

        if (file_put_contents($xmlFile, $xml) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel gravar o XML temporario da NF-e.');
        }

        try {
            $lote = trim((string) $request->query->get('ALote', '1'));
            if ($lote === '') {
                $lote = '1';
            }

            $resultado = $this->executor->execute(
                $script,
                $method,
                array_merge(
                    is_array($presetPayload) ? $presetPayload : [],
                    [
                        'AeArquivoNFe' => $xmlFile,
                        'ALote' => $lote,
                    ]
                )
            );
        } finally {
            @unlink($xmlFile);
        }

        return new NfeEnvioOutput(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    private function assertValidXml(string $xml): void
    {
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
                throw new AcbrLegacyApiException('O corpo enviado nao contem um XML valido de NF-e.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
    }
}
