<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class ApiTestCatalogRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findGroups(): array
    {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                g.id,
                g.code,
                g.name,
                g.module,
                g.description,
                g.sort_order,
                COUNT(t.id) AS total_tests,
                SUM(CASE WHEN t.is_active = 1 THEN 1 ELSE 0 END) AS active_tests,
                SUM(CASE WHEN t.is_automated = 1 AND t.is_active = 1 THEN 1 ELSE 0 END) AS automated_tests
            FROM test_groups g
            LEFT JOIN api_tests t ON t.group_id = g.id
            GROUP BY g.id, g.code, g.name, g.module, g.description, g.sort_order
            ORDER BY g.sort_order ASC, g.name ASC
            SQL
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findTests(string $search = '', string $groupCode = ''): array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(
                't.id',
                't.code',
                't.name',
                't.description',
                't.method',
                't.path',
                't.request_body',
                't.is_automated',
                't.is_active',
                't.sort_order',
                'g.code AS group_code',
                'g.name AS group_name',
                'g.module AS group_module'
            )
            ->from('api_tests', 't')
            ->innerJoin('t', 'test_groups', 'g', 'g.id = t.group_id')
            ->orderBy('g.sort_order', 'ASC')
            ->addOrderBy('t.sort_order', 'ASC')
            ->addOrderBy('t.name', 'ASC');

        if ($search !== '') {
            $queryBuilder
                ->andWhere('t.name LIKE :term OR t.code LIKE :term OR t.path LIKE :term OR t.description LIKE :term OR g.name LIKE :term')
                ->setParameter('term', '%' . $search . '%');
        }

        if ($groupCode !== '') {
            $queryBuilder
                ->andWhere('g.code = :group_code')
                ->setParameter('group_code', $groupCode);
        }

        /** @var list<array<string, mixed>> $tests */
        $tests = $queryBuilder->fetchAllAssociative();

        foreach ($tests as &$test) {
            $test['last_run'] = $this->findLatestRunByTestId((int) $test['id']);
        }

        return $tests;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTestByCode(string $code): ?array
    {
        $test = $this->connection->createQueryBuilder()
            ->select(
                't.id',
                't.code',
                't.name',
                't.description',
                't.method',
                't.path',
                't.request_body',
                't.headers_json',
                't.is_automated',
                't.is_active',
                't.sort_order',
                'g.code AS group_code',
                'g.name AS group_name',
                'g.module AS group_module',
                'g.description AS group_description'
            )
            ->from('api_tests', 't')
            ->innerJoin('t', 'test_groups', 'g', 'g.id = t.group_id')
            ->where('t.code = :code')
            ->setParameter('code', $code)
            ->fetchAssociative();

        if ($test === false) {
            return null;
        }

        $test['headers'] = $this->decodeHeaders($test['headers_json'] ?? null);
        $test['last_run'] = $this->findLatestRunByTestId((int) $test['id']);

        return $test;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGroupByCode(string $code): ?array
    {
        $group = $this->connection->createQueryBuilder()
            ->select(
                'g.id',
                'g.code',
                'g.name',
                'g.module',
                'g.description',
                'g.sort_order',
                'COUNT(t.id) AS total_tests',
                'SUM(CASE WHEN t.is_active = 1 THEN 1 ELSE 0 END) AS active_tests',
                'SUM(CASE WHEN t.is_automated = 1 AND t.is_active = 1 THEN 1 ELSE 0 END) AS automated_tests'
            )
            ->from('test_groups', 'g')
            ->leftJoin('g', 'api_tests', 't', 't.group_id = g.id')
            ->where('g.code = :code')
            ->setParameter('code', $code)
            ->groupBy('g.id', 'g.code', 'g.name', 'g.module', 'g.description', 'g.sort_order')
            ->fetchAssociative();

        return $group === false ? null : $group;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAutomatedTestsByGroupCode(string $groupCode): array
    {
        return $this->connection->createQueryBuilder()
            ->select(
                't.id',
                't.code',
                't.name',
                't.description',
                't.method',
                't.path',
                't.request_body',
                't.headers_json',
                'g.code AS group_code',
                'g.name AS group_name'
            )
            ->from('api_tests', 't')
            ->innerJoin('t', 'test_groups', 'g', 'g.id = t.group_id')
            ->where('g.code = :group_code')
            ->andWhere('t.is_active = 1')
            ->andWhere('t.is_automated = 1')
            ->setParameter('group_code', $groupCode)
            ->orderBy('t.sort_order', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllAutomatedTests(): array
    {
        return $this->connection->createQueryBuilder()
            ->select(
                't.id',
                't.code',
                't.name',
                't.description',
                't.method',
                't.path',
                't.request_body',
                't.headers_json',
                'g.code AS group_code',
                'g.name AS group_name'
            )
            ->from('api_tests', 't')
            ->innerJoin('t', 'test_groups', 'g', 'g.id = t.group_id')
            ->where('t.is_active = 1')
            ->andWhere('t.is_automated = 1')
            ->orderBy('g.sort_order', 'ASC')
            ->addOrderBy('t.sort_order', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->fetchAllAssociative();
    }

    public function createBatch(string $batchUuid, string $runMode, string $label, string $baseUrl, int $totalTests): int
    {
        $this->connection->insert('test_run_batches', [
            'batch_uuid' => $batchUuid,
            'run_mode' => $runMode,
            'label' => $label,
            'base_url' => $baseUrl,
            'total_tests' => $totalTests,
            'passed_tests' => 0,
            'failed_tests' => 0,
            'started_at' => date('c'),
            'finished_at' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $result
     */
    public function recordRun(int $batchId, int $testId, int $order, array $result): void
    {
        $this->connection->insert('test_run_history', [
            'batch_id' => $batchId,
            'test_id' => $testId,
            'execution_order' => $order,
            'request_method' => $result['request_method'],
            'request_url' => $result['request_url'],
            'request_body' => $result['request_body'],
            'response_status_code' => $result['response_status_code'],
            'response_body' => $result['response_body'],
            'response_headers' => $result['response_headers'],
            'duration_ms' => $result['duration_ms'],
            'success' => $result['success'] ? 1 : 0,
            'error_message' => $result['error_message'],
            'executed_at' => $result['executed_at'],
        ]);
    }

    public function finalizeBatch(int $batchId, int $passedTests, int $failedTests): void
    {
        $this->connection->update('test_run_batches', [
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'finished_at' => date('c'),
        ], ['id' => $batchId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBatchById(int $batchId): ?array
    {
        $batch = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('test_run_batches')
            ->where('id = :id')
            ->setParameter('id', $batchId)
            ->fetchAssociative();

        return $batch === false ? null : $batch;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestBatch(): ?array
    {
        $batch = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('test_run_batches')
            ->orderBy('id', 'DESC')
            ->setMaxResults(1)
            ->fetchAssociative();

        return $batch === false ? null : $batch;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecentBatches(int $limit = 12): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('test_run_batches')
            ->orderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRunsByBatchId(int $batchId): array
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'r.id',
                'r.execution_order',
                'r.request_method',
                'r.request_url',
                'r.request_body',
                'r.response_status_code',
                'r.response_body',
                'r.response_headers',
                'r.duration_ms',
                'r.success',
                'r.error_message',
                'r.executed_at',
                't.code AS test_code',
                't.name AS test_name',
                'g.code AS group_code',
                'g.name AS group_name'
            )
            ->from('test_run_history', 'r')
            ->innerJoin('r', 'api_tests', 't', 't.id = r.test_id')
            ->innerJoin('t', 'test_groups', 'g', 'g.id = t.group_id')
            ->where('r.batch_id = :batch_id')
            ->setParameter('batch_id', $batchId)
            ->orderBy('r.execution_order', 'ASC')
            ->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $tests = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS total_tests, SUM(CASE WHEN is_automated = 1 AND is_active = 1 THEN 1 ELSE 0 END) AS automated_tests FROM api_tests'
        );
        $groups = $this->connection->fetchOne('SELECT COUNT(*) FROM test_groups');
        $runs = $this->connection->fetchOne('SELECT COUNT(*) FROM test_run_history');

        return [
            'total_tests' => (int) ($tests['total_tests'] ?? 0),
            'automated_tests' => (int) ($tests['automated_tests'] ?? 0),
            'total_groups' => (int) $groups,
            'total_runs' => (int) $runs,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLatestRunByTestId(int $testId): ?array
    {
        $run = $this->connection->createQueryBuilder()
            ->select('success', 'response_status_code', 'executed_at', 'duration_ms', 'batch_id')
            ->from('test_run_history')
            ->where('test_id = :test_id')
            ->setParameter('test_id', $testId)
            ->orderBy('id', 'DESC')
            ->setMaxResults(1)
            ->fetchAssociative();

        return $run === false ? null : $run;
    }

    /**
     * @return list<string>
     */
    private function decodeHeaders(?string $headersJson): array
    {
        if ($headersJson === null || $headersJson === '') {
            return [];
        }

        $headers = json_decode($headersJson, true);
        if (!is_array($headers)) {
            throw new RuntimeException('headers_json invalido no catalogo de testes.');
        }

        return array_values(array_filter($headers, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
