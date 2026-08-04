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

    public function findFirstToken(): ?string
    {
        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->select('c_token')
            ->from('t00002')
            ->where("COALESCE(c_token, '') <> ''")
            ->orderBy('id_t00002', 'ASC')
            ->setMaxResults(1);

        if ($this->hasActiveColumn()) {
            $queryBuilder->andWhere('log_ativo = TRUE');
        }

        $token = $queryBuilder->fetchOne();

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
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
