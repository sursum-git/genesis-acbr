<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ProgramCatalogRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPrograms(string $search = ''): array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('code', 'name', 'path', 'physical_path', 'category', 'status', 'description', 'detailed_explanation', 'started_at', 'ended_at', 'updated_at')
            ->from('programs')
            ->orderBy('name', 'ASC');

        if ($search !== '') {
            $queryBuilder
                ->andWhere('name LIKE :term OR code LIKE :term OR path LIKE :term OR physical_path LIKE :term OR description LIKE :term OR detailed_explanation LIKE :term')
                ->setParameter('term', '%' . $search . '%');
        }

        return $queryBuilder->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProgramByCode(string $code): ?array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('id', 'code', 'name', 'path', 'physical_path', 'category', 'status', 'description', 'detailed_explanation', 'started_at', 'ended_at', 'created_at', 'updated_at')
            ->from('programs')
            ->where('code = :code')
            ->setParameter('code', $code);

        $program = $queryBuilder->fetchAssociative();

        return $program === false ? null : $program;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findHistoryByProgramId(int $programId): array
    {
        return $this->connection->createQueryBuilder()
            ->select('event_type', 'event_summary', 'physical_path_snapshot', 'description_snapshot', 'detailed_explanation_snapshot', 'status_snapshot', 'started_at_snapshot', 'ended_at_snapshot', 'event_at')
            ->from('program_history')
            ->where('program_id = :program_id')
            ->setParameter('program_id', $programId)
            ->orderBy('event_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->fetchAllAssociative();
    }
}
