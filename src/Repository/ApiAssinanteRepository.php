<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ApiAssinanteRepository
{
    private ?bool $hasActiveColumn = null;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->select('*')
            ->from('t00002')
            ->where('c_token = :c_token')
            ->setParameter('c_token', $token)
            ->setMaxResults(1);

        if ($this->hasActiveColumn()) {
            $queryBuilder->andWhere('log_ativo = TRUE');
        }

        $assinante = $queryBuilder->fetchAssociative();

        return $assinante === false ? null : $assinante;
    }

    private function hasActiveColumn(): bool
    {
        if ($this->hasActiveColumn !== null) {
            return $this->hasActiveColumn;
        }

        $columns = $this->auditConnection->createSchemaManager()->listTableColumns('t00002');
        $this->hasActiveColumn = array_key_exists('log_ativo', $columns);

        return $this->hasActiveColumn;
    }
}
