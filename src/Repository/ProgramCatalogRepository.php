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
    public function findPrograms(string $search = '', int $limit = 40, int $offset = 0, string $sort = 'name', string $direction = 'asc'): array
    {
        $sortMap = [
            'code' => 'code',
            'name' => 'name',
            'category' => 'category',
            'status' => 'status',
            'updated_at' => 'updated_at',
            'last_updated_at' => 'last_updated_at',
        ];
        $sortColumn = $sortMap[$sort] ?? 'name';
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('code', 'name', 'path', 'physical_path', 'category', 'status', 'description', 'detailed_explanation', 'version', 'version_source', 'reference_commit', 'last_updated_at', 'started_at', 'ended_at', 'updated_at')
            ->from('programs')
            ->orderBy($sortColumn, $sortDirection)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($search !== '') {
            $queryBuilder
                ->andWhere('name LIKE :term OR code LIKE :term OR path LIKE :term OR physical_path LIKE :term OR description LIKE :term OR detailed_explanation LIKE :term')
                ->setParameter('term', '%' . $search . '%');
        }

        return $queryBuilder->fetchAllAssociative();
    }

    public function countPrograms(string $search = ''): int
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('programs');

        if ($search !== '') {
            $queryBuilder
                ->andWhere('name LIKE :term OR code LIKE :term OR path LIKE :term OR physical_path LIKE :term OR description LIKE :term OR detailed_explanation LIKE :term')
                ->setParameter('term', '%' . $search . '%');
        }

        return (int) $queryBuilder->fetchOne();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProgramByCode(string $code): ?array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('id', 'code', 'name', 'path', 'physical_path', 'category', 'status', 'description', 'detailed_explanation', 'version', 'version_source', 'reference_commit', 'last_updated_at', 'started_at', 'ended_at', 'created_at', 'updated_at')
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
            ->select('event_type', 'event_summary', 'physical_path_snapshot', 'description_snapshot', 'detailed_explanation_snapshot', 'status_snapshot', 'version_snapshot', 'reference_commit_snapshot', 'last_updated_at_snapshot', 'started_at_snapshot', 'ended_at_snapshot', 'event_at')
            ->from('program_history')
            ->where('program_id = :program_id')
            ->setParameter('program_id', $programId)
            ->orderBy('event_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProgramVersionByCode(string $code): ?array
    {
        $program = $this->connection->createQueryBuilder()
            ->select('code', 'name', 'physical_path', 'version', 'version_source', 'reference_commit', 'last_updated_at')
            ->from('programs')
            ->where('code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->fetchAssociative();

        return $program === false ? null : $program;
    }
}
