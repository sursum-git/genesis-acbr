<?php

namespace App\State\Legacy;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Legacy\AbstractAcbrLegacyOperationResource;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class AcbrLegacyOperationProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrLegacyScriptExecutor $executor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AbstractAcbrLegacyOperationResource
    {
        if (!$data instanceof AbstractAcbrLegacyOperationResource) {
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

        $data->resultado = $resultado;
        $data->mensagem = isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null;

        return $data;
    }
}
