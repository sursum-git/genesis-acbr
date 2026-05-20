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

$programs = [
    [
        'code' => 'symfony_api_gateway',
        'name' => 'Gateway Symfony/API Platform',
        'path' => 'src',
        'physical_path' => 'src/Controller/HomeController.php',
        'category' => 'core',
        'description' => 'Camada moderna HTTP e OpenAPI do projeto.',
        'detailed_explanation' => 'Organiza os endpoints atuais em Symfony 7 e API Platform 4.2, concentrando roteamento, serializacao, tratamento de excecoes, documentacao e a camada de adaptacao para os modulos legados ACBr. E o ponto de entrada principal para a API moderna e para a documentacao filtrada por modulo.',
    ],
    [
        'code' => 'src_api_resource_cep',
        'name' => 'API Resources CEP',
        'path' => 'src/ApiResource/AcbrCep',
        'physical_path' => 'src/ApiResource/AcbrCep/AcbrCepConsultaCepResource.php',
        'category' => 'src_module',
        'description' => 'Recursos de API do modulo CEP.',
        'detailed_explanation' => 'Define os contratos expostos pelo API Platform para configuracoes e consultas de CEP. Os recursos permanecem como classes de contrato do modulo, mas a publicacao da metadata do API Platform e dos grupos de serializacao foi movida para XML, mantendo processors e providers dedicados.',
    ],
    [
        'code' => 'src_api_resource_legacy',
        'name' => 'API Resources Legado',
        'path' => 'src/ApiResource/Legacy',
        'physical_path' => 'src/ApiResource/Legacy/AbstractAcbrLegacyOperationResource.php',
        'category' => 'src_module',
        'description' => 'Contrato base das operacoes legadas expostas pela API.',
        'detailed_explanation' => 'Fornece o recurso abstrato comum usado pelos endpoints modernos que delegam a execucao aos scripts legados ACBr. Padroniza payload, resultado e mensagem para NFe e NFSe.',
    ],
    [
        'code' => 'src_api_resource_nfe',
        'name' => 'API Resources NFe',
        'path' => 'src/ApiResource/Nfe',
        'physical_path' => 'src/ApiResource/Nfe/NfeEnvioResource.php',
        'category' => 'src_module',
        'description' => 'Recursos de API do modulo NFe.',
        'detailed_explanation' => 'Agrupa as operacoes modernas de NFe publicadas pelo API Platform, organizadas em envio, consultas, eventos, distribuicao de DFe, inutilizacao e ferramentas. A metadata das operacoes foi migrada para XML por modulo, e cada grupo agora aponta para DTOs explicitos em vez de depender apenas do contrato generico do modulo.',
    ],
    [
        'code' => 'src_api_resource_nfse',
        'name' => 'API Resources NFSe',
        'path' => 'src/ApiResource/Nfse',
        'physical_path' => 'src/ApiResource/Nfse/NfsePadraoNacionalResource.php',
        'category' => 'src_module',
        'description' => 'Recursos de API do modulo NFSe.',
        'detailed_explanation' => 'Reune os endpoints modernos de NFSe para padrao nacional, demais provedores, consultas, envio, cancelamento, servicos tomados/prestados e ferramentas. A camada modela a API em metadata XML, usa DTOs explicitos por grupo de operacao e continua repassando a execucao para os servicos legados do ACBr.',
    ],
    [
        'code' => 'src_controller',
        'name' => 'Controllers Web',
        'path' => 'src/Controller',
        'physical_path' => 'src/Controller/ProgramCatalogController.php',
        'category' => 'src_module',
        'description' => 'Controllers web e paginas auxiliares do projeto.',
        'detailed_explanation' => 'Contem os controllers Symfony responsaveis pelo shell administrativo, dashboard principal, paginas focadas de documentacao, demos, operacao por modulo, monitoramento e os catalogos HTML do projeto. Essa camada foi reorganizada para reduzir a sobrecarga do hub inicial e separar cada pagina por funcao operacional.',
    ],
    [
        'code' => 'src_dto_cep',
        'name' => 'DTOs CEP',
        'path' => 'src/Dto/AcbrCep',
        'physical_path' => 'src/Dto/AcbrCep/AcbrCepConsultaResultado.php',
        'category' => 'src_module',
        'description' => 'DTOs usados pela integracao moderna do modulo CEP.',
        'detailed_explanation' => 'Define os objetos de transferencia usados para mapear configuracao, entrada de consulta e resposta do ACBrCEP. Essa camada deixa o modulo CEP mais tipado e mais preparado para evolucoes sem depender apenas de arrays.',
    ],
    [
        'code' => 'src_dto_legacy',
        'name' => 'DTOs Base Legado',
        'path' => 'src/Dto/Legacy',
        'physical_path' => 'src/Dto/Legacy/AbstractLegacyOperationInput.php',
        'category' => 'src_module',
        'description' => 'DTOs base compartilhados pela adaptacao legada moderna.',
        'detailed_explanation' => 'Define os contratos abstratos de entrada e saida usados como base pelos DTOs de NFe e NFSe. Essa camada permite manter uma estrutura comum sem expor diretamente o recurso legado antigo como contrato publico da API.',
    ],
    [
        'code' => 'src_dto_nfe',
        'name' => 'DTOs NFe',
        'path' => 'src/Dto/Nfe',
        'physical_path' => 'src/Dto/Nfe/NfeConsultaCadastroInput.php',
        'category' => 'src_module',
        'description' => 'DTOs usados pela camada moderna das operacoes NFe.',
        'detailed_explanation' => 'Define os DTOs de entrada e saida usados pelos recursos NFe no API Platform. Essa camada substitui a exposicao direta do contrato legado generico por objetos explicitamente dedicados ao modulo NFe. A operacao consulta-cadastro usa DTO especifico com validacao em XML, metadados do API Platform em XML e o contrato publico baseado em AnDocumento + TipoDocumento.',
    ],
    [
        'code' => 'src_dto_nfse',
        'name' => 'DTOs NFSe',
        'path' => 'src/Dto/Nfse',
        'physical_path' => 'src/Dto/Nfse/NfseOperationInput.php',
        'category' => 'src_module',
        'description' => 'DTOs usados pela camada moderna das operacoes NFSe.',
        'detailed_explanation' => 'Define os DTOs de entrada e saida usados pelos recursos NFSe no API Platform. Essa camada substitui a exposicao direta do contrato legado generico por objetos explicitamente dedicados aos grupos de operacao do modulo NFSe, com os recursos publicados por metadados XML.',
    ],
    [
        'code' => 'src_event_subscriber',
        'name' => 'Event Subscribers',
        'path' => 'src/EventSubscriber',
        'physical_path' => 'src/EventSubscriber/AcbrLegacyApiExceptionSubscriber.php',
        'category' => 'src_module',
        'description' => 'Subscribers de excecao e padronizacao de resposta.',
        'detailed_explanation' => 'Centraliza o tratamento de excecoes de CEP e da camada de integracao legada, convertendo erros tecnicos em respostas JSON previsiveis para a API. Ajuda a manter consistencia de contrato diante de falhas operacionais.',
    ],
    [
        'code' => 'src_http_exception',
        'name' => 'Exceções HTTP Internas',
        'path' => 'src/Http/Exception',
        'physical_path' => 'src/Http/Exception/AcbrLegacyApiException.php',
        'category' => 'src_module',
        'description' => 'Excecoes de dominio tecnico usadas pela API.',
        'detailed_explanation' => 'Define as excecoes customizadas usadas pela camada moderna para sinalizar erros de integracao com CEP e com o legado ACBr. Trabalha em conjunto com os subscribers para padronizar o retorno HTTP.',
    ],
    [
        'code' => 'src_openapi',
        'name' => 'Customização OpenAPI',
        'path' => 'src/OpenApi',
        'physical_path' => 'src/OpenApi/LegacyOptionalRequestBodyOpenApiFactory.php',
        'category' => 'src_module',
        'description' => 'Ajustes de documentacao OpenAPI do projeto.',
        'detailed_explanation' => 'Contem a customizacao da fabrica OpenAPI que adapta a documentacao dos endpoints legados, especialmente para nao marcar request body como obrigatorio em caminhos que usam a camada de adaptacao NFe/NFSe.',
    ],
    [
        'code' => 'src_repository',
        'name' => 'Repositories',
        'path' => 'src/Repository',
        'physical_path' => 'src/Repository/ProgramCatalogRepository.php',
        'category' => 'src_module',
        'description' => 'Repositórios de acesso a dados da aplicação.',
        'detailed_explanation' => 'Contem a camada de repositório usada para acessar o catálogo de programas via Doctrine DBAL. Essa estrutura remove o acesso direto ao banco do controller e agora tambem convive com a manutencao do catalogo feita sem PDO, mantendo o acesso SQLite centralizado em DBAL.',
    ],
    [
        'code' => 'src_service_cep',
        'name' => 'Service CEP',
        'path' => 'src/Service/AcbrCep',
        'physical_path' => 'src/Service/AcbrCep/AcbrCepMtService.php',
        'category' => 'src_module',
        'description' => 'Servico moderno de integracao com ACBrCEP.',
        'detailed_explanation' => 'Executa a ponte tipada entre a camada Symfony/API Platform e a biblioteca legada ACBrCEP. Concentra a chamada ao ACBrCEPApiMT e converte respostas e erros para a estrutura esperada pelos recursos modernos.',
    ],
    [
        'code' => 'src_service_legacy',
        'name' => 'Service Adaptador Legado',
        'path' => 'src/Service/Legacy',
        'physical_path' => 'src/Service/Legacy/AcbrLegacyScriptExecutor.php',
        'category' => 'src_module',
        'description' => 'Executor generico de scripts legados ACBr.',
        'detailed_explanation' => 'Responsavel por acionar scripts legados NFe/NFSe via HTTP interno com cURL, montando o payload a partir dos metadados declarados nos ApiResources. E uma das pecas centrais da fachada moderna sobre o legado.',
    ],
    [
        'code' => 'src_state_cep',
        'name' => 'State CEP',
        'path' => 'src/State/AcbrCep',
        'physical_path' => 'src/State/AcbrCep/AcbrCepConsultaCepProcessor.php',
        'category' => 'src_module',
        'description' => 'Providers e processors do modulo CEP.',
        'detailed_explanation' => 'Implementa a logica de leitura e processamento dos recursos de CEP no API Platform. E a camada que conecta os recursos expostos aos DTOs e ao servico moderno de CEP.',
    ],
    [
        'code' => 'src_state_legacy',
        'name' => 'State Legado',
        'path' => 'src/State/Legacy',
        'physical_path' => 'src/State/Legacy/AcbrLegacyOperationProcessor.php',
        'category' => 'src_module',
        'description' => 'Providers e processors da adaptacao legada.',
        'detailed_explanation' => 'Implementa a execucao generica das operacoes legadas de NFe e NFSe no API Platform. Lê metadados do recurso, monta payloads, aciona o executor legado e devolve o resultado padronizado.',
    ],
    [
        'code' => 'src_state_nfe',
        'name' => 'State NFe',
        'path' => 'src/State/Nfe',
        'physical_path' => 'src/State/Nfe/NfeConsultaCadastroProvider.php',
        'category' => 'src_module',
        'description' => 'Providers e regras especificas das operacoes modernas de NFe.',
        'detailed_explanation' => 'Abriga providers especificos para operacoes NFe que precisam de contrato tipado e validacao dedicada. A consulta-cadastro passou a usar um provider proprio que mapeia query params para DTO, interpreta TipoDocumento e so entao chama o legado preenchendo AnIE=1 apenas para inscricao estadual.',
    ],
    [
        'code' => 'src_api_auditoria',
        'name' => 'Infra de Auditoria da API',
        'path' => 'src/Service/Api',
        'physical_path' => 'src/Service/Api/ApiAuditManager.php',
        'category' => 'src_module',
        'description' => 'Autenticacao por token, auditoria no PostgreSQL e fila async das APIs.',
        'detailed_explanation' => 'Centraliza a gravacao das requisicoes nas tabelas t99001 a t99004, autentica assinantes via t00002, decide execucao sync ou async e permite consulta posterior por request_id. Esse modulo representa a infraestrutura operacional nova da API.',
    ],
    [
        'code' => 'boleto',
        'name' => 'ACBr Boleto',
        'path' => 'Boleto',
        'physical_path' => 'Boleto/ACBrBoletoBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para operacoes de boleto.',
        'detailed_explanation' => 'Contem base, demos MT/ST e servicos do ACBr para emissao e manipulacao de boletos. A base web foi repaginada com tema Bootstrap compartilhado para alinhar a experiencia visual das demos legadas do projeto. E um modulo legado mantido no projeto para uso direto e como referencia para futuras integracoes via API.',
    ],
    [
        'code' => 'cte',
        'name' => 'ACBr CTe',
        'path' => 'CTe',
        'physical_path' => 'CTe/ACBrCTeBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para Conhecimento de Transporte Eletronico.',
        'detailed_explanation' => 'Agrupa os arquivos base, demos e servicos MT/ST para operacoes de CTe. A interface base das demos usa agora o mesmo tema Bootstrap aplicado aos demais modulos legados, preservando o fluxo tradicional de configuracao, consulta e transmissao do documento fiscal de transporte.',
    ],
    [
        'code' => 'consulta_cep',
        'name' => 'ACBr Consulta CEP',
        'path' => 'ConsultaCEP',
        'physical_path' => 'ConsultaCEP/ACBrCEPBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo de consulta de CEP com integracao legada e API moderna.',
        'detailed_explanation' => 'E o modulo mais integrado a camada Symfony atual. Possui implementacao legada em ConsultaCEP/MT e ST, mas tambem uma camada de DTOs, providers, processors e servico dedicado em src/ para configuracoes e consultas por CEP ou logradouro. Sua demo Bootstrap passou a servir de referencia visual para a repaginacao das demais demos ACBr do repositorio.',
    ],
    [
        'code' => 'consulta_cnpj',
        'name' => 'ACBr Consulta CNPJ',
        'path' => 'ConsultaCNPJ',
        'physical_path' => 'ConsultaCNPJ/ACBrConsultaCNPJBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consulta de dados de CNPJ.',
        'detailed_explanation' => 'Reune demos e servicos ACBr para pesquisa de dados cadastrais via CNPJ. A base da demo foi modernizada com o tema Bootstrap compartilhado adotado nas demais telas ACBr, mantendo os fluxos legados de consulta e configuracao. Atualmente permanece como modulo legado acessivel diretamente, sem uma camada nova de API equivalente a do modulo CEP.',
    ],
    [
        'code' => 'extrato_api',
        'name' => 'ACBr Extrato API',
        'path' => 'ExtratoAPI',
        'physical_path' => 'ExtratoAPI/ACBrExtratoAPIBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para operacoes ligadas a extrato via API.',
        'detailed_explanation' => 'Mantem a implementacao ACBr de ExtratoAPI com demos e servicos MT/ST. A base visual das demos foi alinhada ao tema Bootstrap compartilhado dos modulos ACBr legados. Funciona como componente legado especializado, ainda separado da fachada moderna Symfony/API Platform.',
    ],
    [
        'code' => 'gtin',
        'name' => 'ACBr GTIN',
        'path' => 'GTIN',
        'physical_path' => 'GTIN/ACBrGTINBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consultas e validacoes de GTIN.',
        'detailed_explanation' => 'Contem os artefatos ACBr para operacoes relacionadas a GTIN, incluindo demos e servicos por variante MT/ST. As telas base usam agora o tema Bootstrap compartilhado das demos ACBr. O modulo permanece no formato legado e serve tanto para execucao direta quanto para referencia de integracoes futuras.',
    ],
    [
        'code' => 'ibge',
        'name' => 'ACBr IBGE',
        'path' => 'IBGE',
        'physical_path' => 'IBGE/ACBrIBGEBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consultas de dados do IBGE.',
        'detailed_explanation' => 'Fornece a estrutura classica ACBr para consultas relacionadas ao IBGE. Inclui classes base, demos e servicos separados por variante operacional, agora com repaginacao Bootstrap compartilhada entre as demos legadas, mantendo o comportamento historico do projeto.',
    ],
    [
        'code' => 'mdfe',
        'name' => 'ACBr MDFe',
        'path' => 'MDFe',
        'physical_path' => 'MDFe/ACBrMDFeBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para Manifesto Eletronico de Documentos Fiscais.',
        'detailed_explanation' => 'Reune demos, classes base e servicos do ACBr voltados ao MDFe. A interface base das demos foi unificada visualmente com o tema Bootstrap comum aos modulos ACBr legados. Continua como modulo legado autocontido, preparado para operacoes especificas do documento fiscal de transporte e manifestacao.',
    ],
    [
        'code' => 'ncms',
        'name' => 'ACBr NCMs',
        'path' => 'NCMs',
        'physical_path' => 'NCMs/ACBrNCMsBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para consulta e manipulacao de NCMs.',
        'detailed_explanation' => 'Centraliza os componentes ACBr usados para trabalhar com NCMs. O conteudo inclui demos e servicos nas variantes MT/ST, agora com tema Bootstrap compartilhado nas telas base, preservando a estrutura original do conjunto legado.',
    ],
    [
        'code' => 'nfcom',
        'name' => 'ACBr NFCom',
        'path' => 'NFCom',
        'physical_path' => 'NFCom/ACBrNFComBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para Nota Fiscal de Comunicacao Eletronica.',
        'detailed_explanation' => 'Mantem a implementacao ACBr para NFCom com demos e servicos dedicados. As demos passaram a compartilhar o mesmo tema Bootstrap aplicado aos demais modulos ACBr legados. E um modulo especializado que permanece fora da camada atual de API moderna, mas faz parte do inventario funcional do projeto.',
    ],
    [
        'code' => 'nfse',
        'name' => 'ACBr NFSe',
        'path' => 'NFSe',
        'physical_path' => 'NFSe/ACBrNFSeBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado de Nota Fiscal de Servico Eletronica.',
        'detailed_explanation' => 'Contem os scripts e servicos legados usados hoje pela camada Symfony/API Platform para expor endpoints de NFSe. A base da demo web foi alinhada ao tema Bootstrap compartilhado das demos ACBr, preservando os formularios e abas legadas. A API moderna nao reimplementa a regra fiscal; ela delega a execucao para ACBrNFSeServicosMT.php e organiza a exposicao via recursos, processors e OpenAPI.',
    ],
    [
        'code' => 'nfe',
        'name' => 'ACBr NFe',
        'path' => 'NFe',
        'physical_path' => 'NFe/ACBrNFeBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado de Nota Fiscal Eletronica.',
        'detailed_explanation' => 'E um dos modulos centrais do projeto. A camada moderna exposta em Symfony/API Platform consome o legado de NFe por meio de um adaptador generico que aciona ACBrNFeServicosMT.php com metodos e payloads declarados em ApiResources. O diretorio tambem mantem demos, configuracoes e logs operacionais, e sua base web agora compartilha o mesmo tema Bootstrap das demais demos ACBr.',
    ],
    [
        'code' => 'reinf',
        'name' => 'ACBr Reinf',
        'path' => 'Reinf',
        'physical_path' => 'Reinf/ACBrReinfBase.php',
        'category' => 'legacy_module',
        'description' => 'Modulo legado para eventos e operacoes da Reinf.',
        'detailed_explanation' => 'Agrupa base, demos e servicos ACBr para Reinf. A base visual das demos foi atualizada para o tema Bootstrap compartilhado entre os modulos ACBr legados. Atualmente permanece em formato legado, sem uma fachada equivalente a dos modulos NFe/NFSe/CEP na camada moderna.',
    ],
    [
        'code' => 'schemas',
        'name' => 'Pacote de Schemas',
        'path' => 'Schemas',
        'physical_path' => 'Schemas/NFe/consSitNFe_v4.00.xsd',
        'category' => 'support',
        'description' => 'Repositorio local de XSDs e artefatos de validacao.',
        'detailed_explanation' => 'Armazena os schemas XML usados pelos modulos fiscais e de integracao, incluindo NFe, NFSe, CTe, MDFe, Reinf e outros dominios. Funciona como dependencia estrutural do projeto para validacoes, montagem e compatibilidade de mensagens fiscais.',
    ],
];

$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => $dbPath,
]);

$connection->executeStatement('PRAGMA foreign_keys = ON');

$schemaStatements = [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS programs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    physical_path TEXT NOT NULL DEFAULT '',
    category TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'deprecated', 'ended')),
    description TEXT NOT NULL,
    detailed_explanation TEXT NOT NULL,
    version TEXT NULL,
    version_source TEXT NULL,
    reference_commit TEXT NULL,
    last_updated_at TEXT NULL,
    started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS program_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    program_id INTEGER NOT NULL,
    event_type TEXT NOT NULL CHECK (event_type IN ('created', 'updated', 'ended')),
    event_summary TEXT NOT NULL,
    physical_path_snapshot TEXT NOT NULL DEFAULT '',
    description_snapshot TEXT NOT NULL,
    detailed_explanation_snapshot TEXT NOT NULL,
    status_snapshot TEXT NOT NULL,
    version_snapshot TEXT NULL,
    reference_commit_snapshot TEXT NULL,
    last_updated_at_snapshot TEXT NULL,
    started_at_snapshot TEXT NOT NULL,
    ended_at_snapshot TEXT NULL,
    event_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
)
SQL,
    <<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_programs_updated_at
AFTER UPDATE OF name, path, physical_path, category, status, description, detailed_explanation, version, version_source, reference_commit, last_updated_at, started_at, ended_at ON programs
FOR EACH ROW
BEGIN
    UPDATE programs
       SET updated_at = CURRENT_TIMESTAMP
     WHERE id = NEW.id;
END
SQL,
    <<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_programs_history_update
AFTER UPDATE OF name, path, physical_path, category, status, description, detailed_explanation, version, version_source, reference_commit, last_updated_at, started_at ON programs
FOR EACH ROW
WHEN (
    NEW.name IS NOT OLD.name OR
    NEW.path IS NOT OLD.path OR
    NEW.physical_path IS NOT OLD.physical_path OR
    NEW.category IS NOT OLD.category OR
    NEW.status IS NOT OLD.status OR
    NEW.description IS NOT OLD.description OR
    NEW.detailed_explanation IS NOT OLD.detailed_explanation OR
    NEW.version IS NOT OLD.version OR
    NEW.version_source IS NOT OLD.version_source OR
    NEW.reference_commit IS NOT OLD.reference_commit OR
    NEW.last_updated_at IS NOT OLD.last_updated_at OR
    NEW.started_at IS NOT OLD.started_at
)
BEGIN
    INSERT INTO program_history (
        program_id,
        event_type,
        event_summary,
        physical_path_snapshot,
        description_snapshot,
        detailed_explanation_snapshot,
        status_snapshot,
        version_snapshot,
        reference_commit_snapshot,
        last_updated_at_snapshot,
        started_at_snapshot,
        ended_at_snapshot
    )
    VALUES (
        NEW.id,
        'updated',
        'Programa atualizado; descricao e explicacao devem refletir o estado atual do codigo.',
        NEW.physical_path,
        NEW.description,
        NEW.detailed_explanation,
        NEW.status,
        NEW.version,
        NEW.reference_commit,
        NEW.last_updated_at,
        NEW.started_at,
        NEW.ended_at
    );
END
SQL,
    <<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_programs_history_ended
AFTER UPDATE OF ended_at ON programs
FOR EACH ROW
WHEN OLD.ended_at IS NULL AND NEW.ended_at IS NOT NULL
BEGIN
    INSERT INTO program_history (
        program_id,
        event_type,
        event_summary,
        physical_path_snapshot,
        description_snapshot,
        detailed_explanation_snapshot,
        status_snapshot,
        version_snapshot,
        reference_commit_snapshot,
        last_updated_at_snapshot,
        started_at_snapshot,
        ended_at_snapshot
    )
    VALUES (
        NEW.id,
        'ended',
        'Programa encerrado; o registro principal foi mantido com data de termino.',
        NEW.physical_path,
        NEW.description,
        NEW.detailed_explanation,
        NEW.status,
        NEW.version,
        NEW.reference_commit,
        NEW.last_updated_at,
        NEW.started_at,
        NEW.ended_at
    );
END
SQL,
    <<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_programs_prevent_delete
BEFORE DELETE ON programs
FOR EACH ROW
BEGIN
    SELECT RAISE(ABORT, 'Nao delete programas fisicamente. Atualize ended_at e status para encerrar o programa.');
END
SQL,
];

foreach ($schemaStatements as $statement) {
    $connection->executeStatement($statement);
}

$programColumnNames = array_column($connection->fetchAllAssociative('PRAGMA table_info(programs)'), 'name');
if (!in_array('physical_path', $programColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE programs ADD COLUMN physical_path TEXT NOT NULL DEFAULT ''");
}
if (!in_array('version', $programColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE programs ADD COLUMN version TEXT NULL");
}
if (!in_array('version_source', $programColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE programs ADD COLUMN version_source TEXT NULL");
}
if (!in_array('reference_commit', $programColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE programs ADD COLUMN reference_commit TEXT NULL");
}
if (!in_array('last_updated_at', $programColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE programs ADD COLUMN last_updated_at TEXT NULL");
}

$historyColumnNames = array_column($connection->fetchAllAssociative('PRAGMA table_info(program_history)'), 'name');
if (!in_array('physical_path_snapshot', $historyColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE program_history ADD COLUMN physical_path_snapshot TEXT NOT NULL DEFAULT ''");
}
if (!in_array('version_snapshot', $historyColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE program_history ADD COLUMN version_snapshot TEXT NULL");
}
if (!in_array('reference_commit_snapshot', $historyColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE program_history ADD COLUMN reference_commit_snapshot TEXT NULL");
}
if (!in_array('last_updated_at_snapshot', $historyColumnNames, true)) {
    $connection->executeStatement("ALTER TABLE program_history ADD COLUMN last_updated_at_snapshot TEXT NULL");
}

$connection->executeStatement(
    "UPDATE program_history
        SET physical_path_snapshot = (
            SELECT programs.physical_path
            FROM programs
            WHERE programs.id = program_history.program_id
        )
      WHERE physical_path_snapshot = ''"
);

$connection->beginTransaction();

try {
    foreach ($programs as $program) {
        $versionData = resolveProgramVersionData($program['physical_path'], $projectRoot);
        $existing = $connection->fetchAssociative(
            'SELECT id, description, detailed_explanation, physical_path, status, started_at, ended_at FROM programs WHERE code = :code',
            ['code' => $program['code']]
        );

        if ($existing === false) {
            $connection->insert('programs', [
                'code' => $program['code'],
                'name' => $program['name'],
                'path' => $program['path'],
                'physical_path' => $program['physical_path'],
                'category' => $program['category'],
                'status' => 'active',
                'description' => $program['description'],
                'detailed_explanation' => $program['detailed_explanation'],
                'version' => $versionData['version'],
                'version_source' => $versionData['version_source'],
                'reference_commit' => $versionData['reference_commit'],
                'last_updated_at' => $versionData['last_updated_at'],
            ]);

            $programId = (int) $connection->lastInsertId();

            $connection->insert('program_history', [
                'program_id' => $programId,
                'event_type' => 'created',
                'event_summary' => 'Cadastro inicial do programa no catalogo local do projeto.',
                'physical_path_snapshot' => $program['physical_path'],
                'description_snapshot' => $program['description'],
                'detailed_explanation_snapshot' => $program['detailed_explanation'],
                'status_snapshot' => 'active',
                'version_snapshot' => $versionData['version'],
                'reference_commit_snapshot' => $versionData['reference_commit'],
                'last_updated_at_snapshot' => $versionData['last_updated_at'],
                'started_at_snapshot' => date('Y-m-d H:i:s'),
                'ended_at_snapshot' => null,
            ]);

            continue;
        }

        $connection->update('programs', [
            'name' => $program['name'],
            'path' => $program['path'],
            'physical_path' => $program['physical_path'],
            'category' => $program['category'],
            'status' => $existing['ended_at'] === null ? 'active' : $existing['status'],
            'description' => $program['description'],
            'detailed_explanation' => $program['detailed_explanation'],
            'version' => $versionData['version'],
            'version_source' => $versionData['version_source'],
            'reference_commit' => $versionData['reference_commit'],
            'last_updated_at' => $versionData['last_updated_at'],
        ], [
            'code' => $program['code'],
        ]);
    }

    $connection->commit();
} catch (\Throwable $exception) {
    $connection->rollBack();

    throw $exception;
}

echo "Banco criado/atualizado em {$dbPath}\n";

@chmod($dbPath, 0666);

/**
 * @return array{version:?string,version_source:?string,reference_commit:?string,last_updated_at:?string}
 */
function resolveProgramVersionData(string $physicalPath, string $projectDir): array
{
    $absolutePath = $projectDir . DIRECTORY_SEPARATOR . $physicalPath;
    $gitOutput = null;

    if (is_file($absolutePath)) {
        $command = sprintf(
            'cd %s && git log -1 --format=%s -- %s 2>/dev/null',
            escapeshellarg($projectDir),
            escapeshellarg('%H|%h|%cI'),
            escapeshellarg($physicalPath)
        );
        $gitOutput = trim((string) shell_exec($command));
    }

    if ($gitOutput !== '') {
        [$commitHash, $shortHash, $committedAt] = array_pad(explode('|', $gitOutput), 3, null);

        return [
            'version' => $shortHash === null || $shortHash === '' ? null : 'git-' . $shortHash,
            'version_source' => 'git',
            'reference_commit' => $commitHash ?: null,
            'last_updated_at' => $committedAt ?: null,
        ];
    }

    if (is_file($absolutePath)) {
        $mtime = @filemtime($absolutePath);
        if ($mtime !== false) {
            return [
                'version' => 'arquivo-' . date('YmdHis', $mtime),
                'version_source' => 'filesystem',
                'reference_commit' => null,
                'last_updated_at' => date('c', $mtime),
            ];
        }
    }

    return [
        'version' => null,
        'version_source' => null,
        'reference_commit' => null,
        'last_updated_at' => null,
    ];
}
