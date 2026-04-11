<?php

namespace App\State\Legacy;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Legacy\AbstractLegacyOperationOutput;
use App\Dto\Nfe\NfeOperationOutput;
use App\Dto\Nfse\NfseOperationOutput;
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

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AbstractLegacyOperationOutput
    {
        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $presetPayload = $extraProperties['acbr_payload'] ?? [];
        $queryParams = $extraProperties['acbr_query_params'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }
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
