<?php

namespace App\Dto\AcbrCep;

final class AcbrCepConfiguracao
{
    public function __construct(
        public readonly string $usuario = '',
        public readonly string $senha = '',
        public readonly string $chaveAcesso = '',
        public readonly string $webservice = '0',
    ) {
    }

    public function toLegacyPayload(): array
    {
        return [
            'usuario' => $this->usuario,
            'senha' => $this->senha,
            'chaveacesso' => $this->chaveAcesso,
            'webservice' => $this->webservice,
        ];
    }

    public static function fromLegacyPayload(array $data): self
    {
        return new self(
            usuario: (string) ($data['usuario'] ?? ''),
            senha: (string) ($data['senha'] ?? ''),
            chaveAcesso: (string) ($data['chaveacesso'] ?? ''),
            webservice: (string) ($data['webservice'] ?? '0'),
        );
    }
}
