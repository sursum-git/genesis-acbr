<?php

namespace App\ApiResource\AcbrCep;

use App\Dto\AcbrCep\AcbrCepEndereco;
use Symfony\Component\Serializer\Attribute\Groups;

final class AcbrCepEnderecoResource
{
    public function __construct(
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $bairro = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $cep = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $complemento = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $ibgeMunicipio = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $ibgeUf = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $logradouro = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $municipio = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $tipoLogradouro = '',
        #[Groups(['acbr_cep_consulta_cep:read', 'acbr_cep_consulta_logradouro:read'])]
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
