<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ApiAssinanteRepository
{
    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $assinante = $this->auditConnection->createQueryBuilder()
            ->select('*')
            ->from('t00002')
            ->where('c_token = :c_token')
            ->setParameter('c_token', $token)
            ->setMaxResults(1)
            ->fetchAssociative();

        return $assinante === false ? null : $assinante;
    }
}
