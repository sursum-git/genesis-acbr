<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Nfe\NfeOperationOutput;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Api\ApiAsyncResponder;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use Symfony\Component\HttpFoundation\RequestStack;

final class NfeGerarChaveProvider implements ProviderInterface
{
    public function __construct(
        private readonly AcbrLegacyScriptExecutor $executor,
        private readonly RequestStack $requestStack,
        private readonly ApiAsyncResponder $asyncResponder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NfeOperationOutput
    {
        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');
        $method = (string) ($extraProperties['acbr_method'] ?? '');
        $queryParams = $extraProperties['acbr_query_params'] ?? [];

        if ($script === '' || $method === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new AcbrLegacyApiException('Requisição HTTP atual indisponível para gerar a chave da NF-e.');
        }

        $payload = [];
        foreach (is_array($queryParams) ? $queryParams : [] as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $value = $request->query->get($name);
            if ($value === null || $value === '') {
                continue;
            }

            $payload[$name] = $name === 'AEmissao'
                ? $this->normalizeDate((string) $value)
                : (string) $value;
        }

        if ($this->asyncResponder->shouldQueue($operation, $request)) {
            return new NfeOperationOutput(
                $this->asyncResponder->accept($request, $operation, $payload),
                'Requisicao aceita para processamento assincrono.'
            );
        }

        $resultado = $this->executor->execute($script, $method, $payload);

        return new NfeOperationOutput(
            $resultado,
            isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null
        );
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            return $value;
        }

        try {
            return (new \DateTimeImmutable($value))->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
