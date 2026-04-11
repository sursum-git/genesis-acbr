<?php

namespace App\Dto\AcbrCep;

final class AcbrCepEndereco
{
    public function __construct(
        public readonly string $bairro = '',
        public readonly string $cep = '',
        public readonly string $complemento = '',
        public readonly string $ibgeMunicipio = '',
        public readonly string $ibgeUf = '',
        public readonly string $logradouro = '',
        public readonly string $municipio = '',
        public readonly string $tipoLogradouro = '',
        public readonly string $uf = '',
    ) {
    }

    public static function fromLegacyPayload(array $data): self
    {
        return new self(
            bairro: (string) ($data['bairro'] ?? ''),
            cep: (string) ($data['cep'] ?? ''),
            complemento: (string) ($data['complemento'] ?? ''),
            ibgeMunicipio: (string) ($data['ibgemunicipio'] ?? ''),
            ibgeUf: (string) ($data['ibgeuf'] ?? ''),
            logradouro: (string) ($data['logradouro'] ?? ''),
            municipio: (string) ($data['municipio'] ?? ''),
            tipoLogradouro: (string) ($data['tipologradouro'] ?? ''),
            uf: (string) ($data['UF'] ?? ''),
        );
    }
}
