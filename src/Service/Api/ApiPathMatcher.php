<?php

namespace App\Service\Api;

final class ApiPathMatcher
{
    public function isManagedPath(string $path): bool
    {
        foreach (['/nfe', '/nfse', '/acbr-cep', '/requests'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
