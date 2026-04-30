<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Nfe\NfeEnvioOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use Symfony\Component\HttpFoundation\RequestStack;

final class NfeEnvioIniProcessor implements ProcessorInterface
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
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para envio de NFe por INI.');
        }

        $rawBody = trim((string) $request->getContent());
        if ($rawBody === '') {
            throw new AcbrLegacyApiException('Informe o conteudo completo do arquivo INI no corpo da requisição.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];
        $isAsync = (string) (($presetPayload['ASincrono'] ?? '1')) === '0';

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $contents = $this->extractIniDocuments($rawBody, $isAsync);
        $effectivePayload = is_array($presetPayload) ? $presetPayload : [];
        if ($isAsync && count($contents) === 1) {
            // SEFAZ rejeita lote assincrono com apenas uma NF-e; faz fallback automatico para envio sincrono.
            $effectivePayload['ASincrono'] = '1';
        }
        $tempFiles = [];

        try {
            foreach ($contents as $index => $content) {
                $tempFiles[] = $this->writeTempIni($content, $index + 1);
            }

            $lote = $this->normalizeLote($request->query->get('ALote', '1'));

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
    private function extractIniDocuments(string $rawBody, bool $allowMultiple): array
    {
        $rawBody = trim($rawBody);
        if ($rawBody === '') {
            throw new AcbrLegacyApiException('Informe o conteudo completo do arquivo INI no corpo da requisição.');
        }

        if (!$allowMultiple || substr_count($rawBody, '[NFE]') <= 1) {
            $this->assertIniContent($rawBody);

            return [$rawBody];
        }

        $chunks = preg_split('/(?=^\[NFE\]\s*$)/mi', $rawBody, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chunks) || count($chunks) < 2) {
            $this->assertIniContent($rawBody);

            return [$rawBody];
        }

        $contents = [];
        foreach ($chunks as $content) {
            $content = trim($content);
            if ($content === '') {
                continue;
            }

            $this->assertIniContent($content);
            $contents[] = $content;
        }

        if ($contents === []) {
            throw new AcbrLegacyApiException('Nenhum arquivo INI valido foi encontrado no corpo da requisição.');
        }

        return $contents;
    }

    private function normalizeLote(mixed $rawLote): string
    {
        $lote = trim((string) $rawLote);

        return $lote === '' ? '1' : $lote;
    }

    private function assertIniContent(string $content): void
    {
        if (!preg_match('/^\[[^\]]+\]\s*$/m', $content)) {
            throw new AcbrLegacyApiException('O corpo enviado nao contem um arquivo INI valido de NF-e.');
        }

        if (!preg_match('/^\[NFE\]\s*$/mi', $content)) {
            throw new AcbrLegacyApiException('O arquivo INI enviado deve comecar com a secao [NFE].');
        }
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
