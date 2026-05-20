<?php

namespace App\State\AcbrCep;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AcbrCep\AcbrCepConfiguracaoResource;
use App\Service\Api\ApiAsyncResponder;
use App\Service\AcbrCep\AcbrCepMtService;
use Symfony\Component\HttpFoundation\RequestStack;

final class AcbrCepConfiguracaoProvider implements ProviderInterface
{
    public function __construct(
        private readonly AcbrCepMtService $service,
        private readonly ApiAsyncResponder $asyncResponder,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AcbrCepConfiguracaoResource
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $this->asyncResponder->shouldQueue($operation, $request)) {
            return new AcbrCepConfiguracaoResource(
                mensagem: 'Requisicao aceita para processamento assincrono.',
                resultado: $this->asyncResponder->accept($request, $operation, []),
            );
        }

        $configuracao = $this->service->carregarConfiguracoes();

        return new AcbrCepConfiguracaoResource(
            usuario: $configuracao->usuario,
            senha: $configuracao->senha,
            chaveAcesso: $configuracao->chaveAcesso,
            webservice: $configuracao->webservice,
        );
    }
}
