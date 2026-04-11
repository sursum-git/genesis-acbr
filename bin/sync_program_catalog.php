#!/usr/bin/env php
<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$dbDir = $projectRoot . '/var/db';
$dbPath = $dbDir . '/program_catalog.sqlite';

if (!is_dir($dbDir) && !mkdir($dbDir, 0777, true) && !is_dir($dbDir)) {
    fwrite(STDERR, "Nao foi possivel criar o diretorio {$dbDir}.\n");
    exit(1);
}

$programs = [
    [
        'code' => 'symfony_api_gateway',
        'name' => 'Gateway Symfony/API Platform',
        'path' => 'src',
        'category' => 'core',
        'description' => 'Camada moderna HTTP e OpenAPI do projeto.',
        'detailed_explanation' => 'Organiza os endpoints atuais em Symfony 7 e API Platform 4.2, concentrando roteamento, serializacao, tratamento de excecoes, documentacao e a camada de adaptacao para os modulos legados ACBr. E o ponto de entrada principal para a API moderna e para a documentacao filtrada por modulo.',
    ],
    [
        'code' => 'boleto',
        'name' => 'ACBr Boleto',
        'path' => 'Boleto',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para operacoes de boleto.',
        'detailed_explanation' => 'Contem base, demos MT/ST e servicos do ACBr para emissao e manipulacao de boletos. E um modulo legado mantido no projeto para uso direto e como referencia para futuras integracoes via API.',
    ],
    [
        'code' => 'cte',
        'name' => 'ACBr CTe',
        'path' => 'CTe',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para Conhecimento de Transporte Eletronico.',
        'detailed_explanation' => 'Agrupa os arquivos base, demos e servicos MT/ST para operacoes de CTe. Mantem o fluxo legado para configuracao, consulta e transmissao do documento fiscal de transporte dentro da estrutura tradicional do ACBr.',
    ],
    [
        'code' => 'consulta_cep',
        'name' => 'ACBr Consulta CEP',
        'path' => 'ConsultaCEP',
        'category' => 'legacy_module',
        'description' => 'Modulo de consulta de CEP com integracao legada e API moderna.',
        'detailed_explanation' => 'E o modulo mais integrado a camada Symfony atual. Possui implementacao legada em ConsultaCEP/MT e ST, mas tambem uma camada de DTOs, providers, processors e servico dedicado em src/ para configuracoes e consultas por CEP ou logradouro.',
    ],
    [
        'code' => 'consulta_cnpj',
        'name' => 'ACBr Consulta CNPJ',
        'path' => 'ConsultaCNPJ',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consulta de dados de CNPJ.',
        'detailed_explanation' => 'Reune demos e servicos ACBr para pesquisa de dados cadastrais via CNPJ. Atualmente permanece como modulo legado acessivel diretamente, sem uma camada nova de API equivalente a do modulo CEP.',
    ],
    [
        'code' => 'extrato_api',
        'name' => 'ACBr Extrato API',
        'path' => 'ExtratoAPI',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para operacoes ligadas a extrato via API.',
        'detailed_explanation' => 'Mantem a implementacao ACBr de ExtratoAPI com demos e servicos MT/ST. Funciona como componente legado especializado, ainda separado da fachada moderna Symfony/API Platform.',
    ],
    [
        'code' => 'gtin',
        'name' => 'ACBr GTIN',
        'path' => 'GTIN',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consultas e validacoes de GTIN.',
        'detailed_explanation' => 'Contem os artefatos ACBr para operacoes relacionadas a GTIN, incluindo demos e servicos por variante MT/ST. O modulo permanece no formato legado e serve tanto para execucao direta quanto para referencia de integracoes futuras.',
    ],
    [
        'code' => 'ibge',
        'name' => 'ACBr IBGE',
        'path' => 'IBGE',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consultas de dados do IBGE.',
        'detailed_explanation' => 'Fornece a estrutura classica ACBr para consultas relacionadas ao IBGE. Inclui classes base, demos e servicos separados por variante operacional, mantendo o comportamento historico do projeto.',
    ],
    [
        'code' => 'mdfe',
        'name' => 'ACBr MDFe',
        'path' => 'MDFe',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para Manifesto Eletronico de Documentos Fiscais.',
        'detailed_explanation' => 'Reune demos, classes base e servicos do ACBr voltados ao MDFe. Continua como modulo legado autocontido, preparado para operacoes especificas do documento fiscal de transporte e manifestacao.',
    ],
    [
        'code' => 'ncms',
        'name' => 'ACBr NCMs',
        'path' => 'NCMs',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consulta e manipulacao de NCMs.',
        'detailed_explanation' => 'Centraliza os componentes ACBr usados para trabalhar com NCMs. O conteudo inclui demos e servicos nas variantes MT/ST, preservando a estrutura original do conjunto legado.',
    ],
    [
        'code' => 'nfcom',
        'name' => 'ACBr NFCom',
        'path' => 'NFCom',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para Nota Fiscal de Comunicacao Eletronica.',
        'detailed_explanation' => 'Mantem a implementacao ACBr para NFCom com demos e servicos dedicados. E um modulo especializado que permanece fora da camada atual de API moderna, mas faz parte do inventario funcional do projeto.',
    ],
    [
        'code' => 'nfse',
        'name' => 'ACBr NFSe',
        'path' => 'NFSe',
        'category' => 'legacy_module',
        'description' => 'Modulo legado de Nota Fiscal de Servico Eletronica.',
        'detailed_explanation' => 'Contem os scripts e servicos legados usados hoje pela camada Symfony/API Platform para expor endpoints de NFSe. A API moderna nao reimplementa a regra fiscal; ela delega a execucao para ACBrNFSeServicosMT.php e organiza a exposicao via recursos, processors e OpenAPI.',
    ],
    [
        'code' => 'nfe',
        'name' => 'ACBr NFe',
        'path' => 'NFe',
        'category' => 'legacy_module',
        'description' => 'Modulo legado de Nota Fiscal Eletronica.',
        'detailed_explanation' => 'E um dos modulos centrais do projeto. A camada moderna exposta em Symfony/API Platform consome o legado de NFe por meio de um adaptador generico que aciona ACBrNFeServicosMT.php com metodos e payloads declarados em ApiResources. O diretorio tambem mantem demos, configuracoes e logs operacionais.',
    ],
    [
        'code' => 'reinf',
        'name' => 'ACBr Reinf',
        'path' => 'Reinf',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para eventos e operacoes da Reinf.',
        'detailed_explanation' => 'Agrupa base, demos e servicos ACBr para Reinf. Atualmente permanece em formato legado, sem uma fachada equivalente a dos modulos NFe/NFSe/CEP na camada moderna.',
    ],
    [
        'code' => 'schemas',
        'name' => 'Pacote de Schemas',
        'path' => 'Schemas',
        'category' => 'support',
        'description' => 'Repositorio local de XSDs e artefatos de validacao.',
        'detailed_explanation' => 'Armazena os schemas XML usados pelos modulos fiscais e de integracao, incluindo NFe, NFSe, CTe, MDFe, Reinf e outros dominios. Funciona como dependencia estrutural do projeto para validacoes, montagem e compatibilidade de mensagens fiscais.',
    ],
];

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS programs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'deprecated', 'ended')),
    description TEXT NOT NULL,
    detailed_explanation TEXT NOT NULL,
    started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS program_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    program_id INTEGER NOT NULL,
    event_type TEXT NOT NULL CHECK (event_type IN ('created', 'updated', 'ended')),
    event_summary TEXT NOT NULL,
    description_snapshot TEXT NOT NULL,
    detailed_explanation_snapshot TEXT NOT NULL,
    status_snapshot TEXT NOT NULL,
    started_at_snapshot TEXT NOT NULL,
    ended_at_snapshot TEXT NULL,
    event_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);

CREATE TRIGGER IF NOT EXISTS trg_programs_updated_at
AFTER UPDATE OF name, path, category, status, description, detailed_explanation, started_at, ended_at ON programs
FOR EACH ROW
BEGIN
    UPDATE programs
       SET updated_at = CURRENT_TIMESTAMP
     WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_programs_history_update
AFTER UPDATE OF name, path, category, status, description, detailed_explanation, started_at ON programs
FOR EACH ROW
WHEN (
    NEW.name IS NOT OLD.name OR
    NEW.path IS NOT OLD.path OR
    NEW.category IS NOT OLD.category OR
    NEW.status IS NOT OLD.status OR
    NEW.description IS NOT OLD.description OR
    NEW.detailed_explanation IS NOT OLD.detailed_explanation OR
    NEW.started_at IS NOT OLD.started_at
)
BEGIN
    INSERT INTO program_history (
        program_id,
        event_type,
        event_summary,
        description_snapshot,
        detailed_explanation_snapshot,
        status_snapshot,
        started_at_snapshot,
        ended_at_snapshot
    )
    VALUES (
        NEW.id,
        'updated',
        'Programa atualizado; descricao e explicacao devem refletir o estado atual do codigo.',
        NEW.description,
        NEW.detailed_explanation,
        NEW.status,
        NEW.started_at,
        NEW.ended_at
    );
END;

CREATE TRIGGER IF NOT EXISTS trg_programs_history_ended
AFTER UPDATE OF ended_at ON programs
FOR EACH ROW
WHEN OLD.ended_at IS NULL AND NEW.ended_at IS NOT NULL
BEGIN
    INSERT INTO program_history (
        program_id,
        event_type,
        event_summary,
        description_snapshot,
        detailed_explanation_snapshot,
        status_snapshot,
        started_at_snapshot,
        ended_at_snapshot
    )
    VALUES (
        NEW.id,
        'ended',
        'Programa encerrado; o registro principal foi mantido com data de termino.',
        NEW.description,
        NEW.detailed_explanation,
        NEW.status,
        NEW.started_at,
        NEW.ended_at
    );
END;

CREATE TRIGGER IF NOT EXISTS trg_programs_prevent_delete
BEFORE DELETE ON programs
FOR EACH ROW
BEGIN
    SELECT RAISE(ABORT, 'Nao delete programas fisicamente. Atualize ended_at e status para encerrar o programa.');
END;
SQL);

$selectProgram = $pdo->prepare('SELECT id, description, detailed_explanation, status, started_at, ended_at FROM programs WHERE code = :code');
$insertProgram = $pdo->prepare(
    'INSERT INTO programs (code, name, path, category, status, description, detailed_explanation)
     VALUES (:code, :name, :path, :category, :status, :description, :detailed_explanation)'
);
$updateProgram = $pdo->prepare(
    'UPDATE programs
        SET name = :name,
            path = :path,
            category = :category,
            status = :status,
            description = :description,
            detailed_explanation = :detailed_explanation
      WHERE code = :code'
);
$insertHistory = $pdo->prepare(
    'INSERT INTO program_history (
        program_id,
        event_type,
        event_summary,
        description_snapshot,
        detailed_explanation_snapshot,
        status_snapshot,
        started_at_snapshot,
        ended_at_snapshot
    ) VALUES (
        :program_id,
        :event_type,
        :event_summary,
        :description_snapshot,
        :detailed_explanation_snapshot,
        :status_snapshot,
        :started_at_snapshot,
        :ended_at_snapshot
    )'
);

$pdo->beginTransaction();

foreach ($programs as $program) {
    $selectProgram->execute(['code' => $program['code']]);
    $existing = $selectProgram->fetch(PDO::FETCH_ASSOC);

    if ($existing === false) {
        $insertProgram->execute([
            'code' => $program['code'],
            'name' => $program['name'],
            'path' => $program['path'],
            'category' => $program['category'],
            'status' => 'active',
            'description' => $program['description'],
            'detailed_explanation' => $program['detailed_explanation'],
        ]);

        $programId = (int) $pdo->lastInsertId();
        $insertHistory->execute([
            'program_id' => $programId,
            'event_type' => 'created',
            'event_summary' => 'Cadastro inicial do programa no catalogo local do projeto.',
            'description_snapshot' => $program['description'],
            'detailed_explanation_snapshot' => $program['detailed_explanation'],
            'status_snapshot' => 'active',
            'started_at_snapshot' => date('Y-m-d H:i:s'),
            'ended_at_snapshot' => null,
        ]);

        continue;
    }

    $updateProgram->execute([
        'code' => $program['code'],
        'name' => $program['name'],
        'path' => $program['path'],
        'category' => $program['category'],
        'status' => $existing['ended_at'] === null ? 'active' : $existing['status'],
        'description' => $program['description'],
        'detailed_explanation' => $program['detailed_explanation'],
    ]);
}

$pdo->commit();

echo "Banco criado/atualizado em {$dbPath}\n";
