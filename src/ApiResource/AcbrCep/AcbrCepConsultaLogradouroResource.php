<?php

namespace App\ApiResource\AcbrCep;

final class AcbrCepConsultaLogradouroResource
{
    public function __construct(
        public ?string $cidade = null,
        public ?string $tipo = '',
        public ?string $logradouro = '',
        public ?string $uf = '',
        public ?string $bairro = '',
        public ?string $webservice = '0',
        public ?int $retorno = null,
        public ?string $mensagem = null,
        public ?AcbrCepEnderecoResource $dados = null,
    ) {
    }
}
