<?php

namespace App\Repository\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class AuthSchemaManager
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function ensureSchema(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->ensureSqliteSchema();

            return;
        }

        $this->ensurePostgresSchema();
    }

    private function ensureSqliteSchema(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t00005 (
                id_t00005 INTEGER PRIMARY KEY AUTOINCREMENT,
                c_usuario TEXT NOT NULL,
                c_usuario_normalizado TEXT NOT NULL,
                c_senha_hash TEXT NOT NULL,
                c_nome TEXT,
                c_tipo TEXT NOT NULL DEFAULT 'common',
                i_token_version INTEGER NOT NULL DEFAULT 1,
                log_trocar_senha INTEGER NOT NULL DEFAULT 0,
                log_ativo INTEGER NOT NULL DEFAULT 1,
                dt_hr_criacao TEXT NOT NULL,
                dt_hr_ult_login TEXT,
                dt_hr_atu TEXT NOT NULL
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00005_usuario_uidx ON t00005 (c_usuario_normalizado)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t00006 (
                id_t00006 INTEGER PRIMARY KEY AUTOINCREMENT,
                c_nome TEXT NOT NULL,
                c_cnpj TEXT NOT NULL,
                log_ativo INTEGER NOT NULL DEFAULT 1,
                dt_hr_criacao TEXT NOT NULL,
                dt_hr_atu TEXT NOT NULL
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00006_cnpj_uidx ON t00006 (c_cnpj)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t00007 (
                id_t00007 INTEGER PRIMARY KEY AUTOINCREMENT,
                t00005_id INTEGER NOT NULL,
                t00006_id INTEGER NOT NULL,
                c_role TEXT NOT NULL DEFAULT 'common',
                log_ativo INTEGER NOT NULL DEFAULT 1,
                dt_hr_atu TEXT NOT NULL
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00007_user_company_uidx ON t00007 (t00005_id, t00006_id)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t00008 (
                id_t00008 INTEGER PRIMARY KEY AUTOINCREMENT,
                t00006_id INTEGER NOT NULL,
                t00002_id INTEGER NOT NULL,
                log_ativo INTEGER NOT NULL DEFAULT 1,
                dt_hr_atu TEXT NOT NULL
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00008_company_subscriber_uidx ON t00008 (t00006_id, t00002_id)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS t00009 (
                id_t00009 INTEGER PRIMARY KEY AUTOINCREMENT,
                t00005_id INTEGER NOT NULL,
                c_token_hash TEXT NOT NULL,
                c_family TEXT NOT NULL,
                dt_hr_expira TEXT NOT NULL,
                dt_hr_revogado TEXT,
                dt_hr_criacao TEXT NOT NULL,
                dt_hr_atu TEXT NOT NULL
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00009_token_hash_uidx ON t00009 (c_token_hash)');
    }

    private function ensurePostgresSchema(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS public.t00005 (
                id_t00005 bigserial PRIMARY KEY,
                c_usuario varchar(80) NOT NULL,
                c_usuario_normalizado varchar(80) NOT NULL,
                c_senha_hash varchar(255) NOT NULL,
                c_nome varchar(255),
                c_tipo varchar(30) NOT NULL DEFAULT 'common',
                i_token_version integer NOT NULL DEFAULT 1,
                log_trocar_senha boolean NOT NULL DEFAULT false,
                log_ativo boolean NOT NULL DEFAULT true,
                dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
                dt_hr_ult_login timestamptz,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00005_usuario_uidx ON public.t00005 (c_usuario_normalizado)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS public.t00006 (
                id_t00006 bigserial PRIMARY KEY,
                c_nome varchar(255) NOT NULL,
                c_cnpj char(14) NOT NULL,
                log_ativo boolean NOT NULL DEFAULT true,
                dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00006_cnpj_uidx ON public.t00006 (c_cnpj)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS public.t00007 (
                id_t00007 bigserial PRIMARY KEY,
                t00005_id bigint NOT NULL REFERENCES public.t00005 (id_t00005) ON DELETE CASCADE,
                t00006_id bigint NOT NULL REFERENCES public.t00006 (id_t00006) ON DELETE CASCADE,
                c_role varchar(30) NOT NULL DEFAULT 'common',
                log_ativo boolean NOT NULL DEFAULT true,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00007_user_company_uidx ON public.t00007 (t00005_id, t00006_id)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS public.t00008 (
                id_t00008 bigserial PRIMARY KEY,
                t00006_id bigint NOT NULL REFERENCES public.t00006 (id_t00006) ON DELETE CASCADE,
                t00002_id bigint NOT NULL,
                log_ativo boolean NOT NULL DEFAULT true,
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00008_company_subscriber_uidx ON public.t00008 (t00006_id, t00002_id)');

        $this->connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS public.t00009 (
                id_t00009 bigserial PRIMARY KEY,
                t00005_id bigint NOT NULL REFERENCES public.t00005 (id_t00005) ON DELETE CASCADE,
                c_token_hash char(64) NOT NULL,
                c_family varchar(64) NOT NULL,
                dt_hr_expira timestamptz NOT NULL,
                dt_hr_revogado timestamptz,
                dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
                dt_hr_atu timestamptz NOT NULL DEFAULT now()
            )
            SQL
        );
        $this->connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS t00009_token_hash_uidx ON public.t00009 (c_token_hash)');
        $this->connection->executeStatement('ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS t00005_id bigint');
        $this->connection->executeStatement('ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS t00006_id bigint');
        $this->connection->executeStatement('ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS t00002_id bigint');
        $this->connection->executeStatement('ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS c_auth_type varchar(30)');
    }
}
