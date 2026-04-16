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
        is_automated INTEGER NOT NULL DEFAULT 1,
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
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

$connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_api_tests_group_id ON api_tests (group_id)');
$connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_test_run_history_batch_id ON test_run_history (batch_id)');
$connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_test_run_history_test_id ON test_run_history (test_id)');

$groups = [
    [
        'code' => 'cep',
        'name' => 'CEP',
        'module' => 'cep',
        'description' => 'Testes de consulta de CEP e logradouro no modulo ACBrCEP.',
        'sort_order' => 10,
    ],
    [
        'code' => 'nfe_consultas',
        'name' => 'NFe Consultas',
        'module' => 'nfe',
        'description' => 'Testes de status e consultas principais de NFe.',
        'sort_order' => 20,
    ],
    [
        'code' => 'nfe_distribuicao_dfe',
        'name' => 'NFe Distribuicao DFe',
        'module' => 'nfe',
        'description' => 'Testes do ambiente nacional de distribuicao de DFe da NFe.',
        'sort_order' => 30,
    ],
    [
        'code' => 'nfe_ferramentas',
        'name' => 'NFe Ferramentas',
        'module' => 'nfe',
        'description' => 'Testes rapidos de ferramentas auxiliares de NFe.',
        'sort_order' => 40,
    ],
    [
        'code' => 'nfse_ferramentas',
        'name' => 'NFSe Ferramentas',
        'module' => 'nfse',
        'description' => 'Testes rapidos de ferramentas auxiliares de NFSe.',
        'sort_order' => 50,
    ],
];

$tests = [
    [
        'group_code' => 'cep',
        'code' => 'cep_consulta_cep',
        'name' => 'CEP - Consulta por CEP',
        'description' => 'Consulta o CEP 29103091 com o webservice padrao do legado.',
        'method' => 'POST',
        'path' => '/acbr-cep/consulta-cep',
        'request_body' => '{"cep":"29103091","webservice":"0"}',
        'headers_json' => json_encode(['Accept: application/ld+json', 'Content-Type: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 10,
    ],
    [
        'group_code' => 'cep',
        'code' => 'cep_consulta_logradouro',
        'name' => 'CEP - Consulta por logradouro',
        'description' => 'Consulta logradouro em Vila Velha com dados fixos de referencia.',
        'method' => 'POST',
        'path' => '/acbr-cep/consulta-logradouro',
        'request_body' => '{"cidade":"Vila Velha","tipo":"ROD","logradouro":"Darly Santos","uf":"ES","bairro":"Aracas","webservice":"0"}',
        'headers_json' => json_encode(['Accept: application/ld+json', 'Content-Type: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 20,
    ],
    [
        'group_code' => 'nfe_consultas',
        'code' => 'nfe_status_servico',
        'name' => 'NFe - Status do servico',
        'description' => 'Verifica se a comunicacao de status da NFe responde corretamente.',
        'method' => 'GET',
        'path' => '/nfe/consultas/status-servico',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 10,
    ],
    [
        'group_code' => 'nfe_consultas',
        'code' => 'nfe_consulta_cadastro_cnpj',
        'name' => 'NFe - Consulta cadastro por CNPJ',
        'description' => 'Consulta cadastro da empresa de referencia usando CNPJ.',
        'method' => 'GET',
        'path' => '/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 20,
    ],
    [
        'group_code' => 'nfe_consultas',
        'code' => 'nfe_consulta_cadastro_ie',
        'name' => 'NFe - Consulta cadastro por inscricao estadual',
        'description' => 'Consulta cadastro da empresa de referencia usando inscricao estadual.',
        'method' => 'GET',
        'path' => '/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=inscricao_estadual',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 30,
    ],
    [
        'group_code' => 'nfe_consultas',
        'code' => 'nfe_consultar_com_chave',
        'name' => 'NFe - Consultar com chave',
        'description' => 'Consulta uma NFe autorizada em producao pela chave de acesso.',
        'method' => 'POST',
        'path' => '/nfe/consultas/consultar-com-chave',
        'request_body' => '{"payload":{"eChaveOuNFe":"32260406013812000158550030001955901308939122"}}',
        'headers_json' => json_encode(['Accept: application/ld+json', 'Content-Type: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 40,
    ],
    [
        'group_code' => 'nfe_distribuicao_dfe',
        'code' => 'nfe_distribuicao_por_chave',
        'name' => 'NFe - Distribuicao DFe por chave',
        'description' => 'Executa a distribuicao de DFe por chave com os dados de referencia do ambiente nacional.',
        'method' => 'POST',
        'path' => '/nfe/distribuicao-dfe/por-chave',
        'request_body' => '{"payload":{"AcUFAutor":"ES","AeCNPJCPF":"06013812000158","AechNFe":"32260406013812000158550030001955901308939122"}}',
        'headers_json' => json_encode(['Accept: application/ld+json', 'Content-Type: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 10,
    ],
    [
        'group_code' => 'nfe_distribuicao_dfe',
        'code' => 'nfe_distribuicao_por_ult_nsu',
        'name' => 'NFe - Distribuicao DFe por ultimo NSU',
        'description' => 'Consulta o ambiente nacional a partir do ultimo NSU conhecido.',
        'method' => 'POST',
        'path' => '/nfe/distribuicao-dfe/por-ult-nsu',
        'request_body' => '{"payload":{"AcUFAutor":"ES","AeCNPJCPF":"06013812000158","AeultNSU":"000000000000000"}}',
        'headers_json' => json_encode(['Accept: application/ld+json', 'Content-Type: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 20,
    ],
    [
        'group_code' => 'nfe_ferramentas',
        'code' => 'nfe_openssl_info',
        'name' => 'NFe - OpenSSL info',
        'description' => 'Valida a execucao do endpoint auxiliar de informacoes OpenSSL do modulo NFe.',
        'method' => 'GET',
        'path' => '/nfe/ferramentas/openssl-info',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 10,
    ],
    [
        'group_code' => 'nfe_ferramentas',
        'code' => 'nfe_obter_certificados',
        'name' => 'NFe - Obter certificados',
        'description' => 'Valida a listagem de certificados acessivel pelo modulo NFe.',
        'method' => 'GET',
        'path' => '/nfe/ferramentas/obter-certificados',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 20,
    ],
    [
        'group_code' => 'nfse_ferramentas',
        'code' => 'nfse_openssl_info',
        'name' => 'NFSe - OpenSSL info',
        'description' => 'Valida a execucao do endpoint auxiliar de informacoes OpenSSL do modulo NFSe.',
        'method' => 'GET',
        'path' => '/nfse/ferramentas/openssl-info',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 10,
    ],
    [
        'group_code' => 'nfse_ferramentas',
        'code' => 'nfse_obter_certificados',
        'name' => 'NFSe - Obter certificados',
        'description' => 'Valida a listagem de certificados acessivel pelo modulo NFSe.',
        'method' => 'GET',
        'path' => '/nfse/ferramentas/obter-certificados',
        'request_body' => null,
        'headers_json' => json_encode(['Accept: application/ld+json'], JSON_UNESCAPED_SLASHES),
        'is_automated' => 1,
        'is_active' => 1,
        'sort_order' => 20,
    ],
];

$now = date('c');

$connection->beginTransaction();

try {
    $groupCodes = [];

    foreach ($groups as $group) {
        $groupCodes[] = $group['code'];

        $existing = $connection->createQueryBuilder()
            ->select('id', 'created_at')
            ->from('test_groups')
            ->where('code = :code')
            ->setParameter('code', $group['code'])
            ->fetchAssociative();

        $payload = [
            'code' => $group['code'],
            'name' => $group['name'],
            'module' => $group['module'],
            'description' => $group['description'],
            'sort_order' => $group['sort_order'],
            'updated_at' => $now,
        ];

        if ($existing === false) {
            $payload['created_at'] = $now;
            $connection->insert('test_groups', $payload);
            continue;
        }

        $connection->update('test_groups', $payload, ['id' => $existing['id']]);
    }

    $testCodes = [];

    foreach ($tests as $test) {
        $testCodes[] = $test['code'];

        $groupId = $connection->createQueryBuilder()
            ->select('id')
            ->from('test_groups')
            ->where('code = :code')
            ->setParameter('code', $test['group_code'])
            ->fetchOne();

        if ($groupId === false) {
            throw new RuntimeException(sprintf('Grupo de teste inexistente: %s', $test['group_code']));
        }

        $existing = $connection->createQueryBuilder()
            ->select('id', 'created_at')
            ->from('api_tests')
            ->where('code = :code')
            ->setParameter('code', $test['code'])
            ->fetchAssociative();

        $payload = [
            'group_id' => (int) $groupId,
            'code' => $test['code'],
            'name' => $test['name'],
            'description' => $test['description'],
            'method' => $test['method'],
            'path' => $test['path'],
            'request_body' => $test['request_body'],
            'headers_json' => $test['headers_json'],
            'is_automated' => $test['is_automated'],
            'is_active' => $test['is_active'],
            'sort_order' => $test['sort_order'],
            'updated_at' => $now,
        ];

        if ($existing === false) {
            $payload['created_at'] = $now;
            $connection->insert('api_tests', $payload);
            continue;
        }

        $connection->update('api_tests', $payload, ['id' => $existing['id']]);
    }

    if ($groupCodes !== []) {
        $placeholders = implode(', ', array_fill(0, count($groupCodes), '?'));
        $connection->executeStatement(
            sprintf('DELETE FROM test_groups WHERE code NOT IN (%s)', $placeholders),
            $groupCodes
        );
    }

    if ($testCodes !== []) {
        $placeholders = implode(', ', array_fill(0, count($testCodes), '?'));
        $connection->executeStatement(
            sprintf('DELETE FROM api_tests WHERE code NOT IN (%s)', $placeholders),
            $testCodes
        );
    }

    $connection->commit();
} catch (Throwable $exception) {
    $connection->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Catalogo de testes criado/atualizado em {$dbPath}\n");
