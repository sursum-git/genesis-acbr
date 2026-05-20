<?php

namespace App\Service\Api;

final class ApiTokenHasher
{
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
