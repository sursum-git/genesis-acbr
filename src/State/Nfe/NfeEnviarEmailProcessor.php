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
        $this->applyInlineEmailConfigIfPresent($script, $payload);

        $xmlSource = $payload['AeArquivoXmlNFe'] ?? null;
        $xmlContent = $this->resolveXmlContent($xmlSource);
        $emailXmlPath = $this->persistXmlForAcbr($xmlContent, (string) ($payload['AeChaveNFe'] ?? ''));
        $payload['AeArquivoXmlNFe'] = $emailXmlPath;

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
            $this->cleanupTempXmlIfNeeded($emailXmlPath);
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

    private function applyInlineEmailConfigIfPresent(string $script, array $payload): void
    {
        $configKeys = [
            'emailNome',
            'emailConta',
            'emailServidor',
            'emailPorta',
            'emailSSL',
            'emailTLS',
            'emailUsuario',
            'emailSenha',
        ];

        $configPayload = [];
        foreach ($configKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $configPayload[$key] = (string) $payload[$key];
            }
        }

        if ($configPayload === []) {
            return;
        }

        $this->executor->execute($script, 'salvarConfiguracoesEmail', $configPayload);
    }

    private function persistXmlForAcbr(string $xml, string $fallbackChave): string
    {
        $metadata = $this->extractXmlMetadata($xml, $fallbackChave);
        if ($metadata !== null) {
            $targetPath = $this->buildAcbrXmlPath($metadata['cnpj'], $metadata['chave'], $metadata['yyyymm']);
            $directory = dirname($targetPath);
            if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new AcbrLegacyApiException('Nao foi possivel preparar o diretorio padrao da ACBr para envio de e-mail.');
            }

            if (file_put_contents($targetPath, $xml) === false) {
                throw new AcbrLegacyApiException('Nao foi possivel gravar o XML no caminho padrao da ACBr para envio de e-mail.');
            }

            return $targetPath;
        }

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

    private function cleanupTempXmlIfNeeded(string $path): void
    {
        if (str_starts_with($path, sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nfe-email-')) {
            @unlink($path);
        }
    }

    /**
     * @return array{chave: string, cnpj: string, yyyymm: string}|null
     */
    private function extractXmlMetadata(string $xml, string $fallbackChave): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            return null;
        }

        $nfeNs = 'http://www.portalfiscal.inf.br/nfe';
        $document->registerXPathNamespace('nfe', $nfeNs);

        $chave = trim((string) ($document->xpath('string(//nfe:protNFe/nfe:infProt/nfe:chNFe)')[0] ?? ''));
        if ($chave === '') {
            $id = trim((string) ($document->xpath('string(//nfe:infNFe/@Id)')[0] ?? ''));
            if ($id !== '' && str_starts_with($id, 'NFe')) {
                $chave = substr($id, 3);
            }
        }
        if ($chave === '') {
            $chave = trim($fallbackChave);
        }

        $cnpj = trim((string) ($document->xpath('string(//nfe:emit/nfe:CNPJ)')[0] ?? ''));
        $dhEmi = trim((string) ($document->xpath('string(//nfe:ide/nfe:dhEmi)')[0] ?? ''));
        $yyyymm = $this->toYearMonth($dhEmi);

        if ($chave === '' || $cnpj === '' || $yyyymm === '') {
            return null;
        }

        return [
            'chave' => preg_replace('/\D+/', '', $chave) ?? '',
            'cnpj' => preg_replace('/\D+/', '', $cnpj) ?? '',
            'yyyymm' => $yyyymm,
        ];
    }

    private function buildAcbrXmlPath(string $cnpj, string $chave, string $yyyymm): string
    {
        return dirname(__DIR__, 3) . "/NFe/arqs/{$cnpj}/NFe/{$yyyymm}/NFe/{$chave}-nfe.xml";
    }

    private function toYearMonth(string $dateTime): string
    {
        if ($dateTime === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($dateTime))->format('Ym');
        } catch (\Throwable) {
            return '';
        }
    }
}
