<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Nfe\NfeOperationOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use Symfony\Component\HttpFoundation\RequestStack;

final class NfeXmlValidationProcessor implements ProcessorInterface
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
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para validacao de XML da NF-e.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $xmlSource = trim((string) $request->getContent());
        if ($xmlSource === '') {
            throw new AcbrLegacyApiException('Informe o XML completo da NF-e no corpo da requisição.');
        }

        $xmlPath = $this->persistXmlForAcbr($this->resolveXmlContent($xmlSource));

        try {
            $resultado = $this->executor->execute(
                $script,
                $method,
                array_merge(
                    is_array($presetPayload) ? $presetPayload : [],
                    ['AeArquivoXmlNFe' => $xmlPath]
                )
            );
        } finally {
            @unlink($xmlPath);
        }

        return new NfeOperationOutput(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    private function resolveXmlContent(string $source): string
    {
        if ($this->looksLikeXml($source)) {
            return $source;
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new AcbrLegacyApiException('O corpo deve conter o XML completo da NF-e ou um caminho legivel neste ambiente.');
        }

        $content = file_get_contents($source);
        if (!is_string($content) || trim($content) === '') {
            throw new AcbrLegacyApiException('Nao foi possivel ler o conteudo do XML informado.');
        }

        return $content;
    }

    private function looksLikeXml(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '<?xml')
            || str_starts_with($trimmed, '<nfeProc')
            || str_starts_with($trimmed, '<NFe');
    }

    private function persistXmlForAcbr(string $xml): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'nfe-validar-xml-');
        if ($tempFile === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para validacao da NF-e.');
        }

        $xmlFile = $tempFile . '.xml';
        @unlink($tempFile);

        if (file_put_contents($xmlFile, $xml) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel gravar o XML temporario da NF-e.');
        }

        return $xmlFile;
    }
}
