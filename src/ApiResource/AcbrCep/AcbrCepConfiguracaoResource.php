<?php

namespace App\ApiResource\AcbrCep;

final class AcbrCepConfiguracaoResource
{
    public function __construct(
        public ?string $usuario = '',
        public ?string $senha = '',
        public ?string $chaveAcesso = '',
        public ?string $webservice = '0',
        public ?string $mensagem = null,
    ) {
    }
}
