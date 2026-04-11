<?php

namespace App\State\Legacy;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Legacy\AbstractAcbrLegacyOperationResource;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use Symfony\Component\HttpFoundation\RequestStack;

final class AcbrLegacyOperationProvider implements ProviderInterface
{
    public function __construct(
        private readonly AcbrLegacyScriptExecutor $executor,
        private readonly RequestStack $requestStack
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AbstractAcbrLegacyOperationResource
    {
        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];
        $queryParams = $extraProperties['acbr_query_params'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $class = $operation->getClass();
        if (!is_string($class) || !is_subclass_of($class, AbstractAcbrLegacyOperationResource::class)) {
            throw new AcbrLegacyApiException('Classe de recurso inválida para operação legado ACBr.');
        }

        /** @var AbstractAcbrLegacyOperationResource $data */
        $data = new $class();
        $data->payload = null;
        $request = $this->requestStack->getCurrentRequest();
        $queryPayload = [];

        if ($request !== null && is_array($queryParams)) {
            foreach ($queryParams as $name) {
                if (!is_string($name) || $name === '') {
                    continue;
                }

                $value = $request->query->get($name);
                if ($value !== null && $value !== '') {
                    $queryPayload[$name] = $value;
                }
            }
        }

        $resultado = $this->executor->execute(
            $script,
            $method,
            array_merge(
                is_array($presetPayload) ? $presetPayload : [],
                $queryPayload
            )
        );

        $data->resultado = $resultado;
        $data->mensagem = isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null;

        return $data;
    }
}
