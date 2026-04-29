<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Legacy\AbstractLegacyOperationInput;
use App\Dto\Nfe\NfeOperationOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class NfeEnviarEmailProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrLegacyScriptExecutor $executor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NfeOperationOutput
    {
        if (!$data instanceof AbstractLegacyOperationInput || !is_array($data->payload)) {
            throw new AcbrLegacyApiException('Payload inválido para envio de e-mail da NFe.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $payload = $data->payload;
        $xmlSource = $payload['AeArquivoXmlNFe'] ?? null;
        $xmlContent = $this->resolveXmlContent($xmlSource);
        $tempFile = $this->writeTempXml($xmlContent);
        $payload['AeArquivoXmlNFe'] = $tempFile;

        try {
            $resultado = $this->executor->execute(
                $script,
                $method,
                array_merge(
                    is_array($presetPayload) ? $presetPayload : [],
                    $payload
                )
            );
        } finally {
            @unlink($tempFile);
        }

        return new NfeOperationOutput(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    private function resolveXmlContent(mixed $source): string
    {
        if (!is_string($source)) {
            throw new AcbrLegacyApiException('Informe AeArquivoXmlNFe com o caminho do arquivo XML ou o conteúdo XML completo.');
        }

        $source = trim($source);
        if ($source === '') {
            throw new AcbrLegacyApiException('AeArquivoXmlNFe não pode ser vazio.');
        }

        if ($this->looksLikeXml($source)) {
            return $source;
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new AcbrLegacyApiException('AeArquivoXmlNFe não aponta para um arquivo XML legível neste ambiente.');
        }

        $content = file_get_contents($source);
        if (!is_string($content) || trim($content) === '') {
            throw new AcbrLegacyApiException('Não foi possível ler o conteúdo do XML informado em AeArquivoXmlNFe.');
        }

        return $content;
    }

    private function looksLikeXml(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<nfeProc') || str_starts_with($trimmed, '<NFe');
    }

    private function writeTempXml(string $xml): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'nfe-email-');
        if ($tempFile === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para envio de e-mail da NFe.');
        }

        $xmlFile = $tempFile . '.xml';
        @unlink($tempFile);

        if (file_put_contents($xmlFile, $xml) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel gravar o XML temporario para envio de e-mail da NFe.');
        }

        return $xmlFile;
    }
}
