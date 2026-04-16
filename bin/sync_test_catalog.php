#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$projectRoot = dirname(__DIR__);
$dbDir = $projectRoot . '/var/db';
$dbPath = $dbDir . '/program_catalog.sqlite';

if (!is_dir($dbDir) && !mkdir($dbDir, 0777, true) && !is_dir($dbDir)) {
    fwrite(STDERR, "Nao foi possivel criar o diretorio {$dbDir}.\n");
    exit(1);
}

@chmod($dbDir, 0777);

$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => $dbPath,
]);

$connection->executeStatement('PRAGMA foreign_keys = ON');

$connection->executeStatement(
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS test_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        module TEXT NOT NULL,
        description TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )
    SQL
);

$connection->executeStatement(
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS api_tests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        description TEXT NOT NULL,
        method TEXT NOT NULL,
        path TEXT NOT NULL,
        request_body TEXT DEFAULT NULL,
        headers_json TEXT DEFAULT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (group_id) REFERENCES test_groups(id) ON DELETE CASCADE
    )
    SQL
);

$connection->executeStatement(
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS test_run_batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_uuid TEXT NOT NULL UNIQUE,
        run_mode TEXT NOT NULL,
        label TEXT NOT NULL,
        base_url TEXT NOT NULL,
        total_tests INTEGER NOT NULL DEFAULT 0,
        passed_tests INTEGER NOT NULL DEFAULT 0,
        failed_tests INTEGER NOT NULL DEFAULT 0,
        started_at TEXT NOT NULL,
        finished_at TEXT DEFAULT NULL
    )
    SQL
);

$connection->executeStatement(
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS test_run_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_id INTEGER NOT NULL,
        test_id INTEGER NOT NULL,
        execution_order INTEGER NOT NULL,
        request_method TEXT NOT NULL,
        request_url TEXT NOT NULL,
        request_body TEXT DEFAULT NULL,
        response_status_code INTEGER DEFAULT NULL,
        response_body TEXT DEFAULT NULL,
        response_headers TEXT DEFAULT NULL,
        duration_ms INTEGER DEFAULT NULL,
        success INTEGER NOT NULL DEFAULT 0,
        error_message TEXT DEFAULT NULL,
        executed_at TEXT NOT NULL,
        FOREIGN KEY (batch_id) REFERENCES test_run_batches(id) ON DELETE CASCADE,
        FOREIGN KEY (test_id) REFERENCES api_tests(id) ON DELETE CASCADE
    )
    SQL
);

ensureColumn($connection, 'api_tests', 'query_string', 'TEXT DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'request_signature', 'TEXT DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'request_count', 'INTEGER NOT NULL DEFAULT 0');
ensureColumn($connection, 'api_tests', 'last_status_code', 'INTEGER DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'last_response_body', 'TEXT DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'last_response_headers', 'TEXT DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'last_duration_ms', 'INTEGER DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'first_recorded_at', 'TEXT DEFAULT NULL');
ensureColumn($connection, 'api_tests', 'last_recorded_at', 'TEXT DEFAULT NULL');

$connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_api_tests_group_id ON api_tests (group_id)');
$connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS idx_api_tests_request_signature ON api_tests (request_signature)');
$connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_test_run_history_batch_id ON test_run_history (batch_id)');
$connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_test_run_history_test_id ON test_run_history (test_id)');

// Limpa o modelo antigo de testes predefinidos para deixar apenas cenarios reais gravados pelo API Platform.
$connection->executeStatement('DELETE FROM test_run_history WHERE test_id IN (SELECT id FROM api_tests WHERE request_signature IS NULL OR request_signature = \'\')');
$connection->executeStatement('DELETE FROM test_run_batches WHERE id NOT IN (SELECT DISTINCT batch_id FROM test_run_history)');
$connection->executeStatement('DELETE FROM api_tests WHERE request_signature IS NULL OR request_signature = \'\'');
$connection->executeStatement('DELETE FROM test_groups WHERE id NOT IN (SELECT DISTINCT group_id FROM api_tests)');

fwrite(STDOUT, "Catalogo de testes criado/atualizado em {$dbPath}\n");

@chmod($dbPath, 0666);

/**
 * @param \Doctrine\DBAL\Connection $connection
 */
function ensureColumn($connection, string $table, string $column, string $definition): void
{
    $columns = $connection->fetchFirstColumn(sprintf("SELECT name FROM pragma_table_info('%s')", $table));
    if (in_array($column, $columns, true)) {
        return;
    }

    $connection->executeStatement(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
}
