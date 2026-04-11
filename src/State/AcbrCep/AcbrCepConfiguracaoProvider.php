<?php

namespace App\State\AcbrCep;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AcbrCep\AcbrCepConfiguracaoResource;
use App\Service\AcbrCep\AcbrCepMtService;

final class AcbrCepConfiguracaoProvider implements ProviderInterface
{
    public function __construct(private readonly AcbrCepMtService $service)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AcbrCepConfiguracaoResource
    {
        $configuracao = $this->service->carregarConfiguracoes();

        return new AcbrCepConfiguracaoResource(
            usuario: $configuracao->usuario,
            senha: $configuracao->senha,
            chaveAcesso: $configuracao->chaveAcesso,
            webservice: $configuracao->webservice,
        );
    }
}
