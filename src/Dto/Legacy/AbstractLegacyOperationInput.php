<?php

namespace App\Dto\Legacy;

use Symfony\Component\Serializer\Attribute\Groups;

abstract class AbstractLegacyOperationInput
{
    public function __construct(
        #[Groups(['acbr_legacy_operation:write'])]
        public ?array $payload = null,
    ) {
    }
}
