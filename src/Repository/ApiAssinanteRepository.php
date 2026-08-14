<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ApiAssinanteRepository
{
    private ?bool $hasActiveColumn = null;
    private ?string $primaryKeyColumn = null;

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
            ->orderBy($this->primaryKeyColumn(), 'ASC')
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

    private function primaryKeyColumn(): string
    {
        if ($this->primaryKeyColumn !== null) {
            return $this->primaryKeyColumn;
        }

        $table = $this->auditConnection->createSchemaManager()->introspectTable('t00002');
        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey !== null && count($primaryKey->getColumns()) === 1) {
            return $this->primaryKeyColumn = $primaryKey->getColumns()[0];
        }

        foreach (['id_t00002', 'id'] as $candidate) {
            if ($table->hasColumn($candidate)) {
                return $this->primaryKeyColumn = $candidate;
            }
        }

        return $this->primaryKeyColumn = 'c_identificador';
    }
}
