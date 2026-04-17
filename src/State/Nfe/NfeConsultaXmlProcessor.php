<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Nfe\NfeOperationOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use DOMDocument;
use DOMXPath;
use Symfony\Component\HttpFoundation\RequestStack;

final class NfeConsultaXmlProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AcbrLegacyScriptExecutor $executor,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NfeOperationOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para consulta de NFe por XML.');
        }

        $xml = trim((string) $request->getContent());
        if ($xml === '') {
            throw new AcbrLegacyApiException('Informe o XML completo da NF-e no corpo da requisição.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $chave = $this->extractAccessKey($xml);
        $resultado = $this->executor->execute(
            $script,
            $method,
            array_merge(
                is_array($presetPayload) ? $presetPayload : [],
                ['eChaveOuNFe' => $chave]
            )
        );

        return new NfeOperationOutput(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    private function extractAccessKey(string $xml): string
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

        $xpath = new DOMXPath($document);

        $chave = trim((string) $xpath->evaluate('string((//*[local-name()="chNFe"])[1])'));
        if ($this->isAccessKey($chave)) {
            return $chave;
        }

        $id = trim((string) $xpath->evaluate('string((//*[local-name()="infNFe"]/@Id)[1])'));
        if (str_starts_with($id, 'NFe')) {
            $id = substr($id, 3);
        }

        if ($this->isAccessKey($id)) {
            return $id;
        }

        throw new AcbrLegacyApiException('Nao foi possivel localizar a chave de acesso no XML informado.');
    }

    private function isAccessKey(string $value): bool
    {
        return (bool) preg_match('/^\d{44}$/', $value);
    }
}
