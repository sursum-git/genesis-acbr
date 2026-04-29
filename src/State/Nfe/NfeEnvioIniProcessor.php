<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Legacy\AbstractLegacyOperationInput;
use App\Dto\Nfe\NfeEnvioOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class NfeEnvioIniProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrLegacyScriptExecutor $executor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NfeEnvioOutput
    {
        if (!$data instanceof AbstractLegacyOperationInput || !is_array($data->payload)) {
            throw new AcbrLegacyApiException('Payload inválido para envio de NFe por INI.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $contents = $this->normalizeIniContents($data->payload['AeArquivoNFe'] ?? null);
        $effectivePayload = is_array($presetPayload) ? $presetPayload : [];
        if ((string) (($effectivePayload['ASincrono'] ?? '1')) === '0' && count($contents) === 1) {
            // SEFAZ rejeita lote assincrono com apenas uma NF-e; faz fallback automatico para envio sincrono.
            $effectivePayload['ASincrono'] = '1';
        }
        $payload = $data->payload;
        unset($payload['AeArquivoNFe']);

        $tempFiles = [];

        try {
            foreach ($contents as $index => $content) {
                $tempFiles[] = $this->writeTempIni($content, $index + 1);
            }

            $payload['AeArquivoNFe'] = count($tempFiles) === 1 ? $tempFiles[0] : $tempFiles;
            $payload['ALote'] = $this->normalizeLote($payload['ALote'] ?? null);

            $resultado = $this->executor->execute(
                $script,
                $method,
                array_merge(
                    $effectivePayload,
                    $payload
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
    private function normalizeIniContents(mixed $rawContents): array
    {
        if (is_string($rawContents)) {
            $content = trim($rawContents);

            if ($content === '') {
                throw new AcbrLegacyApiException('Informe o conteúdo do arquivo INI em payload.AeArquivoNFe.');
            }

            return [$content];
        }

        if (!is_array($rawContents)) {
            throw new AcbrLegacyApiException('payload.AeArquivoNFe deve ser uma string com o conteúdo INI ou uma lista de conteúdos.');
        }

        $contents = [];

        foreach ($rawContents as $content) {
            if (!is_string($content)) {
                continue;
            }

            $content = trim($content);
            if ($content !== '') {
                $contents[] = $content;
            }
        }

        if ($contents === []) {
            throw new AcbrLegacyApiException('Informe ao menos um conteúdo INI válido em payload.AeArquivoNFe.');
        }

        return $contents;
    }

    private function normalizeLote(mixed $rawLote): string
    {
        $lote = trim((string) $rawLote);

        return $lote === '' ? '1' : $lote;
    }

    private function writeTempIni(string $content, int $index): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'nfe-envio-ini-');
        if ($tempFile === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para o INI da NF-e.');
        }

        $iniFile = $tempFile . '-' . $index . '.ini';
        @unlink($tempFile);

        if (file_put_contents($iniFile, $content) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel gravar o INI temporario da NF-e.');
        }

        return $iniFile;
    }
}
