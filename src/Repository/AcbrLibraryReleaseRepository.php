<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use RuntimeException;

final class AcbrLibraryReleaseRepository
{
    private const TABLE = 't99037_acbr_library_release';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function ensureSchema(): void
    {
        $idColumn = $this->connection->getDatabasePlatform() instanceof SQLitePlatform
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'bigserial PRIMARY KEY';

        $this->connection->executeStatement(sprintf(<<<'SQL'
            CREATE TABLE IF NOT EXISTS %s (
                id_t99037 %s,
                c_modulo varchar(32) NOT NULL,
                c_ambiente varchar(16) NOT NULL,
                c_release varchar(80) NOT NULL,
                c_caminho_so text NOT NULL,
                c_sha256 char(64) NOT NULL,
                c_perfil_ini varchar(64) NOT NULL,
                dt_hr_inicio_vigencia timestamp NOT NULL,
                dt_hr_fim_vigencia timestamp NULL,
                log_ativo boolean NOT NULL DEFAULT TRUE,
                t_observacao text NULL,
                dt_hr_criacao timestamp NOT NULL,
                dt_hr_atu timestamp NOT NULL
            )
            SQL, self::TABLE, $idColumn));
    }

    /** @param array<string, mixed> $payload */
    public function save(array $payload): int
    {
        $this->ensureSchema();
        $data = $this->normalize($payload);
        $this->assertNoActiveOverlap($data);
        $this->connection->insert(self::TABLE, $data);

        return (int) $this->connection->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $this->ensureSchema();
        return $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s ORDER BY c_modulo, c_ambiente, dt_hr_inicio_vigencia DESC', self::TABLE));
    }

    /** @return array<string, mixed>|null */
    public function findActive(string $module, string $environment, DateTimeInterface $at): ?array
    {
        $this->ensureSchema();
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE c_modulo = :module AND c_ambiente = :environment AND log_ativo = TRUE AND dt_hr_inicio_vigencia <= :at AND (dt_hr_fim_vigencia IS NULL OR dt_hr_fim_vigencia > :at) ORDER BY dt_hr_inicio_vigencia DESC', self::TABLE),
            ['module' => $module, 'environment' => $environment, 'at' => $at->format('Y-m-d H:i:s')]
        );

        if (count($rows) > 1) {
            throw new RuntimeException('Há mais de uma release ACBr ativa para o módulo e ambiente informados.');
        }

        return $rows[0] ?? null;
    }

    /** @param array<string, mixed> $data */
    private function assertNoActiveOverlap(array $data): void
    {
        if (!$data['log_ativo']) {
            return;
        }

        $count = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE c_modulo = :module AND c_ambiente = :environment AND log_ativo = TRUE AND dt_hr_inicio_vigencia < COALESCE(:end_at, :infinity) AND (dt_hr_fim_vigencia IS NULL OR dt_hr_fim_vigencia > :start_at)', self::TABLE),
            [
                'module' => $data['c_modulo'],
                'environment' => $data['c_ambiente'],
                'start_at' => $data['dt_hr_inicio_vigencia'],
                'end_at' => $data['dt_hr_fim_vigencia'],
                'infinity' => '9999-12-31 23:59:59',
            ]
        );

        if ($count > 0) {
            throw new RuntimeException('A vigência da release ACBr sobrepõe uma release ativa existente.');
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalize(array $payload): array
    {
        $environment = strtolower(trim((string) ($payload['c_ambiente'] ?? '')));
        if (!in_array($environment, ['homologacao', 'producao'], true)) {
            throw new RuntimeException('Ambiente ACBr inválido.');
        }

        $hash = strtolower(trim((string) ($payload['c_sha256'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new RuntimeException('SHA-256 da biblioteca ACBr inválido.');
        }

        $start = trim((string) ($payload['dt_hr_inicio_vigencia'] ?? ''));
        $end = trim((string) ($payload['dt_hr_fim_vigencia'] ?? '')) ?: null;
        if ($start === '' || ($end !== null && $end <= $start)) {
            throw new RuntimeException('Período de vigência da biblioteca ACBr inválido.');
        }

        $now = date('Y-m-d H:i:s');
        return [
            'c_modulo' => trim((string) ($payload['c_modulo'] ?? '')),
            'c_ambiente' => $environment,
            'c_release' => trim((string) ($payload['c_release'] ?? '')),
            'c_caminho_so' => trim((string) ($payload['c_caminho_so'] ?? '')),
            'c_sha256' => $hash,
            'c_perfil_ini' => trim((string) ($payload['c_perfil_ini'] ?? '')),
            'dt_hr_inicio_vigencia' => $start,
            'dt_hr_fim_vigencia' => $end,
            'log_ativo' => (bool) ($payload['log_ativo'] ?? false),
            't_observacao' => trim((string) ($payload['t_observacao'] ?? '')) ?: null,
            'dt_hr_criacao' => $now,
            'dt_hr_atu' => $now,
        ];
    }
}
