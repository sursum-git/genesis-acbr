<?php

namespace App\State\AcbrCep;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AcbrCep\AcbrCepConsultaCepResource;
use App\ApiResource\AcbrCep\AcbrCepEnderecoResource;
use App\Dto\AcbrCep\AcbrCepConsultaCepInput;
use App\Service\AcbrCep\AcbrCepMtService;

final class AcbrCepConsultaCepProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrCepMtService $service)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AcbrCepConsultaCepResource
    {
        if (!$data instanceof AcbrCepConsultaCepResource) {
            return new AcbrCepConsultaCepResource(mensagem: 'Payload invalido.');
        }

        $resultado = $this->service->buscarPorCep(new AcbrCepConsultaCepInput(
            cep: (string) ($data->cep ?? ''),
            webservice: (string) ($data->webservice ?? '0'),
        ));

        return new AcbrCepConsultaCepResource(
            cep: (string) ($data->cep ?? ''),
            webservice: (string) ($data->webservice ?? '0'),
            retorno: (int) $resultado->retorno,
            mensagem: $resultado->mensagem,
            dados: $resultado->dados ? AcbrCepEnderecoResource::fromDto($resultado->dados) : null,
        );
    }
}
