<?php

namespace App\Dto\Legacy;

abstract class AbstractLegacyOperationOutput
{
    public function __construct(
        public ?array $resultado = null,
        public ?string $mensagem = null,
    ) {
    }
}
