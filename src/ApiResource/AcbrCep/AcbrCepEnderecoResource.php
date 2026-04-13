<?php

namespace App\ApiResource\AcbrCep;

use App\Dto\AcbrCep\AcbrCepEndereco;

final class AcbrCepEnderecoResource
{
    public function __construct(
        public ?string $bairro = '',
        public ?string $cep = '',
        public ?string $complemento = '',
        public ?string $ibgeMunicipio = '',
        public ?string $ibgeUf = '',
        public ?string $logradouro = '',
        public ?string $municipio = '',
        public ?string $tipoLogradouro = '',
        public ?string $uf = '',
    ) {
    }

    public static function fromDto(AcbrCepEndereco $endereco): self
    {
        return new self(
            bairro: $endereco->bairro,
            cep: $endereco->cep,
            complemento: $endereco->complemento,
            ibgeMunicipio: $endereco->ibgeMunicipio,
            ibgeUf: $endereco->ibgeUf,
            logradouro: $endereco->logradouro,
            municipio: $endereco->municipio,
            tipoLogradouro: $endereco->tipoLogradouro,
            uf: $endereco->uf,
        );
    }
}
