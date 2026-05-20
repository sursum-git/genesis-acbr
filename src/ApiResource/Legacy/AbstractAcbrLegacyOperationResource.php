<?php

namespace App\ApiResource\Legacy;

abstract class AbstractAcbrLegacyOperationResource
{
    public function __construct(
        public ?array $payload = null,
        public ?array $resultado = null,
        public ?string $mensagem = null,
    ) {
    }
}
