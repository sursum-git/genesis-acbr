<?php

namespace App\ApiResource\Legacy;

use Symfony\Component\Serializer\Attribute\Groups;

abstract class AbstractAcbrLegacyOperationResource
{
    public function __construct(
        #[Groups(['acbr_legacy_operation:write'])]
        public ?array $payload = null,
        #[Groups(['acbr_legacy_operation:read'])]
        public ?array $resultado = null,
        #[Groups(['acbr_legacy_operation:read'])]
        public ?string $mensagem = null,
    ) {
    }
}
