<?php

namespace App\Repository\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;

final class AuthUserRepository
{
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
            'log_ativo' => $active ? 1 : 0,
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
            'log_ativo' => $active ? 1 : 0,
            'dt_hr_criacao' => $now,
            'dt_hr_atu' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
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
            ['user_id' => $userId, 'company_id' => $companyId, 'role' => $role, 'active' => 1, 'now' => date('c')],
            ['user_id' => ParameterType::INTEGER, 'company_id' => ParameterType::INTEGER, 'active' => ParameterType::INTEGER]
        );
    }

    public function assignCompanySubscriber(int $companyId, int $subscriberId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO t00008 (t00006_id, t00002_id, log_ativo, dt_hr_atu) VALUES (:company_id, :subscriber_id, :active, :now)',
            ['company_id' => $companyId, 'subscriber_id' => $subscriberId, 'active' => 1, 'now' => date('c')],
            ['company_id' => ParameterType::INTEGER, 'subscriber_id' => ParameterType::INTEGER, 'active' => ParameterType::INTEGER]
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
        if (($user['c_tipo'] ?? '') === 'super_admin') {
            /** @var list<int|string> $ids */
            $ids = $this->connection->fetchFirstColumn('SELECT id_t00002 FROM t00002 WHERE COALESCE(log_ativo, TRUE) = TRUE ORDER BY id_t00002 ASC');

            return array_map('intval', $ids);
        }

        /** @var list<int|string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            <<<'SQL'
            SELECT DISTINCT cs.t00002_id
            FROM t00007 uc
            INNER JOIN t00006 c ON c.id_t00006 = uc.t00006_id AND c.log_ativo = TRUE
            INNER JOIN t00008 cs ON cs.t00006_id = c.id_t00006 AND cs.log_ativo = TRUE
            INNER JOIN t00002 s ON s.id_t00002 = cs.t00002_id AND COALESCE(s.log_ativo, TRUE) = TRUE
            WHERE uc.t00005_id = :user_id
              AND uc.log_ativo = TRUE
            ORDER BY cs.t00002_id ASC
            SQL,
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

        $token = $this->connection->fetchOne(
            "SELECT c_token FROM t00002 WHERE id_t00002 = :id AND COALESCE(c_token, '') <> '' LIMIT 1",
            ['id' => $subscriberId],
            ['id' => ParameterType::INTEGER]
        );

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
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
