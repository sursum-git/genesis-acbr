<?php

namespace App\Service\Api;

use App\Repository\ProgramCatalogRepository;

final class ApiProgramVersionResolver
{
    /**
     * @var array<string, string>
     */
    private const PROGRAMS_BY_PREFIX = [
        '/nfe' => 'nfe',
        '/nfse' => 'nfse',
        '/acbr-cep' => 'consulta_cep',
        '/requests' => 'src_api_auditoria',
    ];

    public function __construct(private readonly ProgramCatalogRepository $repository)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveByPath(string $path): ?array
    {
        foreach (self::PROGRAMS_BY_PREFIX as $prefix => $programCode) {
            if (str_starts_with($path, $prefix)) {
                return $this->repository->findProgramVersionByCode($programCode);
            }
        }

        return null;
    }
}
