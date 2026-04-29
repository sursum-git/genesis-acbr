<?php

namespace App\ApiResource\Nfe;

final class NfeEmailConfiguracaoResource
{
    public function __construct(
        public ?string $emailNome = '',
        public ?string $emailConta = '',
        public ?string $emailServidor = '',
        public ?string $emailPorta = '',
        public ?string $emailSSL = '0',
        public ?string $emailTLS = '0',
        public ?string $emailUsuario = '',
        public ?string $emailSenha = '',
        public ?string $mensagem = null,
    ) {
    }
}
