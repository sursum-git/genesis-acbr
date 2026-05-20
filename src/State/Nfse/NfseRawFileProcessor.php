<?php

namespace App\State\Nfse;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Legacy\AbstractLegacyOperationOutput;
use App\Dto\Nfse\NfseOperationOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Api\ApiAsyncResponder;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use DOMDocument;
use Symfony\Component\HttpFoundation\RequestStack;

final class NfseRawFileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AcbrLegacyScriptExecutor $executor,
        private readonly RequestStack $requestStack,
        private readonly ApiAsyncResponder $asyncResponder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AbstractLegacyOperationOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para operação de NFSe com arquivo bruto.');
        }

        $rawBody = trim((string) $request->getContent());
        if ($rawBody === '') {
            throw new AcbrLegacyApiException('Informe o conteudo completo do arquivo no corpo da requisição.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $fileParamName = (string) ($extraProperties['acbr_file_param_name'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];
        $includeLote = (bool) ($extraProperties['acbr_include_lote'] ?? false);

        if ($script === '' || $method === '' || $fileParamName === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados suficientes para arquivo bruto de NFSe.');
        }

        $normalizedBody = $this->normalizeEncoding($rawBody);
        $this->assertSupportedBody($normalizedBody);

        $payload = array_merge(
            is_array($presetPayload) ? $presetPayload : [],
            [
                $fileParamName => $normalizedBody,
            ]
        );

        if ($includeLote) {
            $payload['ALote'] = $this->normalizeLote($request->query->get('ALote', '1'));
        }

        if ($this->asyncResponder->shouldQueue($operation, $request)) {
            $outputClass = $this->resolveOutputClass($operation);

            return new $outputClass(
                $this->asyncResponder->accept($request, $operation, [
                    'parametro_arquivo' => $fileParamName,
                    'ALote' => $payload['ALote'] ?? null,
                ]),
                'Requisicao aceita para processamento assincrono.'
            );
        }

        $resultado = $this->executor->execute($script, $method, $payload);

        $outputClass = $this->resolveOutputClass($operation);

        return new $outputClass(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    private function normalizeLote(mixed $rawLote): string
    {
        $lote = trim((string) $rawLote);

        return $lote === '' ? '1' : $lote;
    }

    private function assertSupportedBody(string $content): void
    {
        if (!$this->isValidXml($content) && !$this->looksLikeIni($content)) {
            throw new AcbrLegacyApiException('O corpo enviado nao contem um XML nem um INI valido de NFSe.');
        }
    }

    private function normalizeEncoding(string $content): string
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $normalized = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252, ISO-8859-1');

        if (!is_string($normalized) || $normalized === '') {
            return $content;
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
    }

    private function looksLikeIni(string $content): bool
    {
        return preg_match('/^\[[^\]]+\]\s*$/m', $content) === 1;
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

    /**
     * @return class-string<AbstractLegacyOperationOutput>
     */
    private function resolveOutputClass(Operation $operation): string
    {
        $output = $operation->getOutput();

        if (is_array($output)) {
            $class = $output['class'] ?? null;
            if (is_string($class) && is_subclass_of($class, AbstractLegacyOperationOutput::class)) {
                return $class;
            }
        }

        return NfseOperationOutput::class;
    }
}
