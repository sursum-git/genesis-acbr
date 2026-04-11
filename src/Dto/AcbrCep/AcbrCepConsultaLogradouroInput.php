<?php

namespace App\Dto\AcbrCep;

final class AcbrCepConsultaLogradouroInput
{
    public function __construct(
        public readonly string $cidade,
        public readonly string $tipo = '',
        public readonly string $logradouro = '',
        public readonly string $uf = '',
        public readonly string $bairro = '',
        public readonly string $webservice = '0',
    ) {
    }

    public function toLegacyPayload(): array
    {
        return [
            'cepcons' => '',
            'tipocons' => $this->tipo,
            'logradourocons' => $this->logradouro,
            'bairrocons' => $this->bairro,
            'cidadecons' => $this->cidade,
            'ufcons' => $this->uf,
            'webservice' => $this->webservice,
        ];
    }
}
