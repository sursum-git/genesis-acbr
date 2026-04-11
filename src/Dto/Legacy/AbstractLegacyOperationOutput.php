<?php

namespace App\Dto\Legacy;

use Symfony\Component\Serializer\Attribute\Groups;

abstract class AbstractLegacyOperationOutput
{
    public function __construct(
        #[Groups(['acbr_legacy_operation:read'])]
        public ?array $resultado = null,
        #[Groups(['acbr_legacy_operation:read'])]
        public ?string $mensagem = null,
    ) {
    }
}
