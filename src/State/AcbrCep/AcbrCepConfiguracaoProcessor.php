<?php

namespace App\State\AcbrCep;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AcbrCep\AcbrCepConfiguracaoResource;
use App\Dto\AcbrCep\AcbrCepConfiguracao;
use App\Service\AcbrCep\AcbrCepMtService;

final class AcbrCepConfiguracaoProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrCepMtService $service)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AcbrCepConfiguracaoResource
    {
        if (!$data instanceof AcbrCepConfiguracaoResource) {
            return new AcbrCepConfiguracaoResource(mensagem: 'Payload invalido.');
        }

        $mensagem = $this->service->salvarConfiguracoes(new AcbrCepConfiguracao(
            usuario: (string) ($data->usuario ?? ''),
            senha: (string) ($data->senha ?? ''),
            chaveAcesso: (string) ($data->chaveAcesso ?? ''),
            webservice: (string) ($data->webservice ?? '0'),
        ));

        return new AcbrCepConfiguracaoResource(
            usuario: (string) ($data->usuario ?? ''),
            senha: (string) ($data->senha ?? ''),
            chaveAcesso: (string) ($data->chaveAcesso ?? ''),
            webservice: (string) ($data->webservice ?? '0'),
            mensagem: $mensagem,
        );
    }
}
