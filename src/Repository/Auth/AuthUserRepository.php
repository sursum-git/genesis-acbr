<?php

namespace App\Repository\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;

final class AuthUserRepository
{
    private ?string $subscriberPrimaryKey = null;
    private ?bool $subscriberHasActiveColumn = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function createUser(string $username, string $passwordHash, string $type = 'common', bool $active = true, string $name = ''): int
    {
        $normalized = $this->normalizeUsername($username);
        if (!in_array($type, ['super_admin', 'company_admin', 'common'], true)) {
            throw new InvalidArgumentException('Tipo de usuario invalido.');
        }

        $now = date('c');
        $this->connection->insert('t00005', [
            'c_usuario' => trim($username),
            'c_usuario_normalizado' => $normalized,
            'c_senha_hash' => $passwordHash,
            'c_nome' => $name !== '' ? $name : trim($username),
            'c_tipo' => $type,
            'log_ativo' => $active,
            'dt_hr_criacao' => $now,
            'dt_hr_atu' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function createCompany(string $name, string $cnpj, bool $active = true): int
    {
        $document = preg_replace('/\D+/', '', $cnpj) ?? '';
        if (strlen($document) !== 14) {
            throw new InvalidArgumentException('CNPJ da empresa deve ter 14 digitos.');
        }

        $now = date('c');
        $this->connection->insert('t00006', [
            'c_nome' => trim($name),
            'c_cnpj' => $document,
            'log_ativo' => $active,
            'dt_hr_criacao' => $now,
            'dt_hr_atu' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUsers(int $limit = 200): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id_t00005, c_usuario, c_nome, c_tipo, log_ativo, dt_hr_criacao, dt_hr_ult_login FROM t00005 ORDER BY c_usuario_normalizado ASC LIMIT :limit',
            ['limit' => max(1, $limit)],
            ['limit' => ParameterType::INTEGER]
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCompanies(int $limit = 500): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id_t00006, c_nome, c_cnpj, log_ativo FROM t00006 WHERE log_ativo = TRUE ORDER BY c_nome ASC LIMIT :limit',
            ['limit' => max(1, $limit)],
            ['limit' => ParameterType::INTEGER]
        );

        return $rows;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function companiesForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT uc.t00005_id, c.id_t00006, c.c_nome, c.c_cnpj, uc.c_role
             FROM t00007 uc
             INNER JOIN t00006 c ON c.id_t00006 = uc.t00006_id AND c.log_ativo = TRUE
             WHERE uc.t00005_id IN (:user_ids)
               AND uc.log_ativo = TRUE
             ORDER BY uc.t00005_id ASC, c.c_nome ASC',
            ['user_ids' => $userIds],
            ['user_ids' => ArrayParameterType::INTEGER]
        );

        $companiesByUser = [];
        foreach ($rows as $row) {
            $userId = (int) $row['t00005_id'];
            $companiesByUser[$userId] ??= [];
            $companiesByUser[$userId][] = $row;
        }

        return $companiesByUser;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByUsername(string $username): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM t00005 WHERE c_usuario_normalizado = :username AND log_ativo = TRUE LIMIT 1',
            ['username' => $this->normalizeUsername($username)]
        );

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM t00005 WHERE id_t00005 = :id AND log_ativo = TRUE LIMIT 1',
            ['id' => $userId],
            ['id' => ParameterType::INTEGER]
        );

        return $row === false ? null : $row;
    }

    public function assignUserCompany(int $userId, int $companyId, string $role = 'common'): void
    {
        if (!in_array($role, ['company_admin', 'common'], true)) {
            throw new InvalidArgumentException('Role de empresa invalido.');
        }

        $this->connection->executeStatement(
            'INSERT INTO t00007 (t00005_id, t00006_id, c_role, log_ativo, dt_hr_atu) VALUES (:user_id, :company_id, :role, :active, :now)',
            ['user_id' => $userId, 'company_id' => $companyId, 'role' => $role, 'active' => true, 'now' => date('c')],
            ['user_id' => ParameterType::INTEGER, 'company_id' => ParameterType::INTEGER, 'active' => ParameterType::BOOLEAN]
        );
    }

    /**
     * @param list<int> $companyIds
     */
    public function replaceUserCompanies(int $userId, array $companyIds, string $role = 'common'): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuario invalido.');
        }

        if (!in_array($role, ['company_admin', 'common'], true)) {
            throw new InvalidArgumentException('Role de empresa invalido.');
        }

        $companyIds = array_values(array_unique(array_filter(array_map('intval', $companyIds), static fn (int $id): bool => $id > 0)));
        $now = date('c');

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                'UPDATE t00007 SET log_ativo = :active, dt_hr_atu = :now WHERE t00005_id = :user_id',
                ['active' => false, 'now' => $now, 'user_id' => $userId],
                ['active' => ParameterType::BOOLEAN, 'user_id' => ParameterType::INTEGER]
            );

            foreach ($companyIds as $companyId) {
                $updated = $this->connection->executeStatement(
                    'UPDATE t00007
                     SET c_role = :role, log_ativo = :active, dt_hr_atu = :now
                     WHERE t00005_id = :user_id AND t00006_id = :company_id',
                    ['role' => $role, 'active' => true, 'now' => $now, 'user_id' => $userId, 'company_id' => $companyId],
                    ['active' => ParameterType::BOOLEAN, 'user_id' => ParameterType::INTEGER, 'company_id' => ParameterType::INTEGER]
                );

                if ($updated === 0) {
                    $this->assignUserCompany($userId, $companyId, $role);
                }
            }

            $this->connection->commit();
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }
    }

    public function assignCompanySubscriber(int $companyId, int $subscriberId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO t00008 (t00006_id, t00002_id, log_ativo, dt_hr_atu) VALUES (:company_id, :subscriber_id, :active, :now)',
            ['company_id' => $companyId, 'subscriber_id' => $subscriberId, 'active' => true, 'now' => date('c')],
            ['company_id' => ParameterType::INTEGER, 'subscriber_id' => ParameterType::INTEGER, 'active' => ParameterType::BOOLEAN]
        );
    }

    /**
     * @return list<string>
     */
    public function rolesForUser(int $userId): array
    {
        $userType = (string) $this->connection->fetchOne('SELECT c_tipo FROM t00005 WHERE id_t00005 = :id', ['id' => $userId], ['id' => ParameterType::INTEGER]);
        $roles = $userType !== '' ? [$userType] : ['common'];

        /** @var list<string> $companyRoles */
        $companyRoles = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT c_role FROM t00007 WHERE t00005_id = :id AND log_ativo = TRUE',
            ['id' => $userId],
            ['id' => ParameterType::INTEGER]
        );

        $roles = array_merge($roles, array_map('strval', $companyRoles));
        $roles = array_values(array_unique(array_filter($roles)));
        sort($roles);

        return $roles;
    }

    /**
     * @return list<int>
     */
    public function subscriberIdsForUser(int $userId): array
    {
        $user = $this->findActiveById($userId);
        $subscriberPk = $this->subscriberPrimaryKey();
        $subscriberActiveSql = $this->subscriberActiveSql('s');
        if (($user['c_tipo'] ?? '') === 'super_admin') {
            /** @var list<int|string> $ids */
            $ids = $this->connection->fetchFirstColumn(sprintf(
                'SELECT %s FROM t00002 s WHERE %s ORDER BY %s ASC',
                $this->connection->quoteIdentifier($subscriberPk),
                $subscriberActiveSql,
                $this->connection->quoteIdentifier($subscriberPk)
            ));

            return array_map('intval', $ids);
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT DISTINCT cs.t00002_id
            FROM t00007 uc
            INNER JOIN t00006 c ON c.id_t00006 = uc.t00006_id AND c.log_ativo = TRUE
            INNER JOIN t00008 cs ON cs.t00006_id = c.id_t00006 AND cs.log_ativo = TRUE
            INNER JOIN t00002 s ON s.%s = cs.t00002_id AND %s
            WHERE uc.t00005_id = :user_id
              AND uc.log_ativo = TRUE
            ORDER BY cs.t00002_id ASC
            SQL,
            $this->connection->quoteIdentifier($subscriberPk),
            $subscriberActiveSql
        );

        /** @var list<int|string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            $sql,
            ['user_id' => $userId],
            ['user_id' => ParameterType::INTEGER]
        );

        return array_map('intval', $ids);
    }

    public function subscriberTokenForUser(int $userId, int $subscriberId): ?string
    {
        if (!in_array($subscriberId, $this->subscriberIdsForUser($userId), true)) {
            return null;
        }

        $subscriberPk = $this->subscriberPrimaryKey();
        $token = $this->connection->fetchOne(
            sprintf(
                "SELECT c_token FROM t00002 WHERE %s = :id AND COALESCE(c_token, '') <> '' LIMIT 1",
                $this->connection->quoteIdentifier($subscriberPk)
            ),
            ['id' => $subscriberId],
            ['id' => ParameterType::INTEGER]
        );

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function subscriberPrimaryKey(): string
    {
        if ($this->subscriberPrimaryKey !== null) {
            return $this->subscriberPrimaryKey;
        }

        $table = $this->connection->createSchemaManager()->introspectTable('t00002');
        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey !== null && count($primaryKey->getColumns()) === 1) {
            return $this->subscriberPrimaryKey = $primaryKey->getColumns()[0];
        }

        foreach (['id_t00002', 'id'] as $candidate) {
            if ($table->hasColumn($candidate)) {
                return $this->subscriberPrimaryKey = $candidate;
            }
        }

        throw new InvalidArgumentException('Nao foi possivel identificar a chave primaria da t00002.');
    }

    private function subscriberActiveSql(string $alias): string
    {
        if ($this->subscriberHasActiveColumn === null) {
            $this->subscriberHasActiveColumn = $this->connection->createSchemaManager()->introspectTable('t00002')->hasColumn('log_ativo');
        }

        return $this->subscriberHasActiveColumn ? sprintf('%s.log_ativo = TRUE', $alias) : '1 = 1';
    }

    private function normalizeUsername(string $username): string
    {
        $normalized = strtolower(trim($username));
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $normalized)) {
            throw new InvalidArgumentException('Usuario deve ter 3 a 80 caracteres e usar letras, numeros, ponto, hifen ou underline.');
        }

        return $normalized;
    }
}
