<?php

namespace App\State\AcbrCep;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AcbrCep\AcbrCepConsultaLogradouroResource;
use App\ApiResource\AcbrCep\AcbrCepEnderecoResource;
use App\Dto\AcbrCep\AcbrCepConsultaLogradouroInput;
use App\Service\AcbrCep\AcbrCepMtService;

final class AcbrCepConsultaLogradouroProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrCepMtService $service)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AcbrCepConsultaLogradouroResource
    {
        if (!$data instanceof AcbrCepConsultaLogradouroResource) {
            return new AcbrCepConsultaLogradouroResource(mensagem: 'Payload invalido.');
        }

        $resultado = $this->service->buscarPorLogradouro(new AcbrCepConsultaLogradouroInput(
            cidade: (string) ($data->cidade ?? ''),
            tipo: (string) ($data->tipo ?? ''),
            logradouro: (string) ($data->logradouro ?? ''),
            uf: (string) ($data->uf ?? ''),
            bairro: (string) ($data->bairro ?? ''),
            webservice: (string) ($data->webservice ?? '0'),
        ));

        return new AcbrCepConsultaLogradouroResource(
            cidade: (string) ($data->cidade ?? ''),
            tipo: (string) ($data->tipo ?? ''),
            logradouro: (string) ($data->logradouro ?? ''),
            uf: (string) ($data->uf ?? ''),
            bairro: (string) ($data->bairro ?? ''),
            webservice: (string) ($data->webservice ?? '0'),
            retorno: (int) $resultado->retorno,
            mensagem: $resultado->mensagem,
            dados: $resultado->dados ? AcbrCepEnderecoResource::fromDto($resultado->dados) : null,
        );
    }
}
