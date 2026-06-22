<?php

namespace App\Service\Api;

use App\Support\ApiExtractionStatus;

final class ApiExtractionPlanResolver
{
    /** @var list<string> */
    private const EXTRACTABLE_PATHS = [
        '/nfe/consultas/consultar-com-chave',
        '/nfe/consultas/consultar-com-chave-xml',
        '/nfe/distribuicao-dfe/por-chave',
        '/nfe/distribuicao-dfe/por-nsu',
        '/nfe/distribuicao-dfe/por-ult-nsu',
        '/nfe/envio/enviar-sincrono-xml',
        '/nfe/envio/enviar-assincrono-xml',
        '/nfe/envio/enviar-sincrono-ini',
        '/nfe/envio/enviar-assincrono-ini',
    ];

    public function resolveInitialStatus(string $path): int
    {
        return $this->isExtractablePath($path)
            ? ApiExtractionStatus::PENDENTE
            : ApiExtractionStatus::NAO_SE_APLICA;
    }

    public function isExtractablePath(string $path): bool
    {
        $normalized = '/' . ltrim(trim($path), '/');

        if (str_starts_with($normalized, '/nfe/inutilizacao/')) {
            return false;
        }

        return in_array($normalized, self::EXTRACTABLE_PATHS, true);
    }
}
