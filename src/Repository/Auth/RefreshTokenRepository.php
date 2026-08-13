<?php

namespace App\Repository\Auth;

use Doctrine\DBAL\Connection;

final class RefreshTokenRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function store(int $userId, string $hash, string $family, string $expiresAt): void
    {
        $now = date('c');
        $this->connection->insert('t00009', [
            't00005_id' => $userId,
            'c_token_hash' => $hash,
            'c_family' => $family,
            'dt_hr_expira' => $expiresAt,
            'dt_hr_criacao' => $now,
            'dt_hr_atu' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function consume(string $hash): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM t00009 WHERE c_token_hash = :hash AND dt_hr_revogado IS NULL AND dt_hr_expira > :now LIMIT 1",
            ['hash' => $hash, 'now' => date('c')]
        );
        if ($row === false) {
            return null;
        }

        $this->connection->update('t00009', ['dt_hr_revogado' => date('c'), 'dt_hr_atu' => date('c')], ['id_t00009' => $row['id_t00009']]);

        return $row;
    }
}
