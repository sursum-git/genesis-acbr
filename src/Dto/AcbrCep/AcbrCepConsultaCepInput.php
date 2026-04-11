<?php

namespace App\Dto\AcbrCep;

final class AcbrCepConsultaCepInput
{
    public function __construct(
        public readonly string $cep,
        public readonly string $webservice = '0',
    ) {
    }

    public function toLegacyPayload(): array
    {
        return [
            'cepcons' => $this->cep,
            'tipocons' => '',
            'logradourocons' => '',
            'bairrocons' => '',
            'cidadecons' => '',
            'ufcons' => '',
            'webservice' => $this->webservice,
        ];
    }
}
