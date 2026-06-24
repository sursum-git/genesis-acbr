<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ApiSuccessParameterRepository
{
    private bool $schemaEnsured = false;

    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActive(\DateTimeImmutable $at = new \DateTimeImmutable()): ?array
    {
        $this->ensureSchema();

        $row = $this->auditConnection->fetchAssociative(
            <<<'SQL'
            SELECT *
            FROM t99034
            WHERE dt_inicio_vigencia <= :now
              AND dt_fim_vigencia > :now
            ORDER BY dt_inicio_vigencia DESC, id_t99034 DESC
            LIMIT 1
            SQL,
            ['now' => $at->format('Y-m-d H:i:s')]
        );

        return $row === false ? null : $row;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->auditConnection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t99034 (
                id_t99034 bigserial PRIMARY KEY,
                dt_inicio_vigencia timestamp NOT NULL,
                dt_fim_vigencia timestamp NOT NULL,
                c_codigos_sucesso_http varchar(255) NOT NULL,
                c_codigos_sucesso_receita varchar(255) NOT NULL,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL
        );
        $this->auditConnection->executeStatement(
            'CREATE INDEX IF NOT EXISTS t99034_vigencia_idx ON t99034 (dt_inicio_vigencia, dt_fim_vigencia)'
        );

        $count = (int) $this->auditConnection->fetchOne('SELECT COUNT(*) FROM t99034');
        if ($count === 0) {
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d 00:00:00');
            $this->auditConnection->insert('t99034', [
                'dt_inicio_vigencia' => $today,
                'dt_fim_vigencia' => '2999-01-01 00:00:00',
                'c_codigos_sucesso_http' => '200,201',
                'c_codigos_sucesso_receita' => '150',
                'dt_hr_atu' => date('c'),
            ]);
        }

        $this->schemaEnsured = true;
    }
}
