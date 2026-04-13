<?php

namespace App\ApiResource\AcbrCep;

final class AcbrCepConsultaCepResource
{
    public function __construct(
        public ?string $cep = null,
        public ?string $webservice = '0',
        public ?int $retorno = null,
        public ?string $mensagem = null,
        public ?AcbrCepEnderecoResource $dados = null,
    ) {
    }
}
