<?php

namespace App\Dto\AcbrCep;

final class AcbrCepConsultaResultado
{
    public function __construct(
        public readonly int|string $retorno,
        public readonly string $mensagem = '',
        public readonly ?AcbrCepEndereco $dados = null,
    ) {
    }

    public static function fromLegacyPayload(array $data): self
    {
        $dados = null;

        if (isset($data['dados']) && is_array($data['dados'])) {
            $dados = AcbrCepEndereco::fromLegacyPayload($data['dados']);
        }

        return new self(
            retorno: $data['retorno'] ?? '',
            mensagem: (string) ($data['mensagem'] ?? ''),
            dados: $dados,
        );
    }
}
