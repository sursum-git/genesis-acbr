<?php

namespace App\Dto\Legacy;

abstract class AbstractLegacyOperationInput
{
    public function __construct(
        public ?array $payload = null,
    ) {
    }
}
