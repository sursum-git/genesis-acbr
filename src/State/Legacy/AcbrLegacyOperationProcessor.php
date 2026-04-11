<?php

namespace App\State\Legacy;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Legacy\AbstractLegacyOperationInput;
use App\Dto\Legacy\AbstractLegacyOperationOutput;
use App\Dto\Nfe\NfeOperationOutput;
use App\Dto\Nfse\NfseOperationOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class AcbrLegacyOperationProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrLegacyScriptExecutor $executor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AbstractLegacyOperationOutput
    {
        if (!$data instanceof AbstractLegacyOperationInput) {
            throw new AcbrLegacyApiException('Payload inválido para operação legado ACBr.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $resultado = $this->executor->execute(
            $script,
            $method,
            array_merge(
                is_array($presetPayload) ? $presetPayload : [],
                is_array($data->payload) ? $data->payload : []
            )
        );

        $outputClass = $this->resolveOutputClass($extraProperties, $script);

        return new $outputClass(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    /**
     * @param array<string, mixed> $extraProperties
     * @return class-string<AbstractLegacyOperationOutput>
     */
    private function resolveOutputClass(array $extraProperties, string $script): string
    {
        $outputClass = $extraProperties['acbr_output_class'] ?? null;

        if (is_string($outputClass) && is_subclass_of($outputClass, AbstractLegacyOperationOutput::class)) {
            return $outputClass;
        }

        if (str_starts_with($script, 'NFe/')) {
            return NfeOperationOutput::class;
        }

        if (str_starts_with($script, 'NFSe/')) {
            return NfseOperationOutput::class;
        }

        throw new AcbrLegacyApiException('Nao foi possivel determinar o DTO de saida da operacao legado ACBr.');
    }
}
