# Contexto da Sessao Atual

Atualizado em `2026-05-26`.

Commit de referencia do estado atual:

- `fa4d607` - `Remove catalog hero descriptions`

## Objetivo prático deste arquivo

Este arquivo existe para permitir que uma nova sessão de trabalho retome o projeto sem depender da memória da conversa anterior.

Ele responde rapidamente:

- qual é o estado atual do sistema
- quais módulos importam mais
- onde ficam os pontos principais de código
- quais mudanças recentes já foram feitas
- quais riscos e pendências merecem atenção

## Resumo executivo

O projeto é uma fachada moderna em `Symfony 7 + API Platform 4.2` sobre módulos legados do `ACBr`.

Hoje existem quatro áreas centrais:

1. APIs modernas para `CEP`, `NFe` e `NFSe`
2. Auditoria completa de requisições em `PostgreSQL`
3. Catálogos operacionais em `SQLite`
4. Portal administrativo em `Twig` com shell `AdminLTE 4`

Além disso, a aplicação já suporta:

- autenticação por token
- auditoria de request e response
- execução síncrona ou assíncrona por endpoint
- worker para fila assíncrona
- layout administrativo com menu lateral, header enxuto e paleta azul/branco
- consulta de requisições como tela operacional única
- auditoria analítica separada da consulta
- navegação parcial sem reload completo nos catálogos

## Bancos de dados em uso

### 1. PostgreSQL externo

Banco de auditoria e operação:

- database: `dfe`
- função: assinantes, tokens, requisições auditadas, tentativas do worker, configuração de execução

Tabelas mais importantes:

- `t00002`: assinantes com `c_token`
- `t99001`: requisição principal
- `t99002`: tentativas/processamento
- `t99003`: configuração de execução
- `t99004`: eventos operacionais

Conexão Symfony:

- `config/services/audit_connection.xml`

Camada principal:

- `src/Repository/ApiAssinanteRepository.php`
- `src/Repository/ApiAuditRepository.php`
- `src/Repository/ApiExecutionModeRepository.php`
- `src/Repository/ApiAuditDashboardRepository.php`

### 2. SQLite local

Banco local versionado no projeto:

- `var/db/program_catalog.sqlite`

Funções:

- catálogo de programas
- catálogo de testes
- histórico e observabilidade auxiliar local

## Fluxos principais do backend

### Autenticação

Headers aceitos:

- `Authorization: Bearer ...`
- `X-Api-Token: ...`

Classe principal:

- `src/EventSubscriber/ApiTokenAuthSubscriber.php`

Lookup de assinante/token:

- `src/Repository/ApiAssinanteRepository.php`

### Auditoria de requisições

Subscribers principais:

- `src/EventSubscriber/ApiAuditRequestSubscriber.php`
- `src/EventSubscriber/ApiAuditResponseSubscriber.php`

Serviço central:

- `src/Service/Api/ApiAuditManager.php`

Status e atributos:

- `src/Support/ApiRequestStatus.php`
- `src/Support/ApiRequestAttributes.php`

### Execução síncrona e assíncrona

Resolver de modo:

- `src/Service/Api/ApiExecutionModeResolver.php`

Resposta assíncrona:

- `src/Service/Api/ApiAsyncResponder.php`

Execução interna da requisição:

- `src/Service/Api/InternalApiRequestRunner.php`

Worker:

- `src/Command/ApiRequestWorkerCommand.php`

Consulta de status:

- `src/Controller/ApiRequestStatusController.php`

### Versionamento de programa atendente

Resolver:

- `src/Service/Api/ApiProgramVersionResolver.php`

Esse serviço alimenta a auditoria com:

- código do programa
- nome
- versão
- revisão
- última atualização
- fonte da versão

## Estado atual das interfaces web

### Hub administrativo

Arquivo principal:

- `templates/home/hub.html.twig`
- `templates/admin/base.html.twig`

Controller:

- `src/Controller/HomeController.php`

O hub agora oferece:

- cards de navegação por área
- acesso direto a docs, demos, auditoria e catálogos
- atalhos por módulo `CEP`, `NFe` e `NFSe`
- blocos de acompanhamento com falhas e testes recentes

Shell visual compartilhado:

- `templates/admin/base.html.twig`
- `catalog-assets/adminlte-portal.css`

### Consulta de requisições e auditoria analítica

Arquivos principais:

- `src/Controller/ApiAuditDashboardController.php`
- `templates/catalog/api_audit_dashboard.html.twig`
- `templates/catalog/api_audit_overview.html.twig`
- `src/Repository/ApiAuditDashboardRepository.php`

Já implementado:

- página de consulta focada em filtros, datagrid e detalhe lateral
- página analítica focada em métricas, alertas e gráficos
- exportação CSV/XLSX
- filtros salvos
- detalhe pesquisável
- troca parcial do detalhe sem reload completo
- abertura do detalhe apenas quando houver `requisicao=` explícita na URL

Estado atual importante:

- a rota operacional única é `/consulta-requisicoes`
- a rota `/auditoria-requisicoes` foi removida
- a visão analítica permanece em `/auditoria-requisicoes/visao-analitica`
- o menu lateral expõe apenas `Consulta de requisições` e `Auditoria analítica`
- a consulta abre com a janela lateral fechada por padrão

### Catálogo de programas

Arquivos principais:

- `src/Controller/ProgramCatalogController.php`
- `src/Repository/ProgramCatalogRepository.php`
- `templates/catalog/program_catalog.html.twig`

Já implementado:

- filtro
- exportação CSV/XLSX
- detalhe pesquisável
- troca parcial do detalhe sem reload completo

### Catálogo de testes

Arquivos principais:

- `src/Controller/TestCatalogController.php`
- `src/Repository/ApiTestCatalogRepository.php`
- `templates/catalog/test_catalog.html.twig`

Já implementado:

- filtro
- rerun geral, por grupo e por teste
- histórico de execuções
- exportação CSV/XLSX
- troca parcial do detalhe sem reload completo

## Helpers globais de frontend

O arquivo central para comportamento das telas de catálogo é:

- `templates/catalog/base.html.twig`

Hoje ele concentra helpers reutilizáveis para:

- persistência de filtros
- destaque de busca interna
- exportação de detalhe
- impressão
- memória de scroll
- navegação parcial do painel direito
- filtros salvos

Se for mexer em comportamento comum dos catálogos, o ponto principal agora é esse arquivo. Se for mexer no shell visual e na navegação lateral do portal, o ponto principal passa a ser `templates/admin/base.html.twig` junto de `catalog-assets/adminlte-portal.css`.

## Testes e validação já preparados

Pasta principal:

- `testes_api_platform/`

Arquivos relevantes:

- `testes_api_platform/auditoria_requisicoes.sh`
- `testes_api_platform/nfe_diagnostico.sh`
- `testes_api_platform/nfe_rede_externa.sh`
- `testes_api_platform/cep.sh`
- `testes_api_platform/nfe.sh`
- `testes_api_platform/nfse.sh`
- `testes_api_platform/README.md`

Os testes mais úteis para continuidade imediata são:

- `php bin/console lint:container`
- `php bin/console lint:twig templates/admin/base.html.twig templates/home/hub.html.twig templates/home/section.html.twig templates/catalog/base.html.twig templates/catalog/program_catalog.html.twig templates/catalog/test_catalog.html.twig templates/catalog/api_audit_dashboard.html.twig templates/catalog/api_audit_overview.html.twig`

1. `bash testes_api_platform/auditoria_requisicoes.sh`
2. `bash testes_api_platform/nfe_diagnostico.sh`
3. `bash testes_api_platform/nfe_rede_externa.sh`

## Restrições e pontos de atenção atuais

### 1. O projeto convive com muito legado ACBr

Grande parte da lógica ainda depende dos diretórios legados como:

- `NFe/`
- `NFSe/`
- `ConsultaCEP/`

Mudanças nesses pontos devem sempre considerar compatibilidade com o fluxo legado.

### 2. Existem muitos arquivos alterados de demos e artefatos

O commit `0cd4056` registrou também uma grande quantidade de mudanças de ambiente e arquivos auxiliares. Em sessões futuras, é importante validar se novos ajustes devem continuar nesse mesmo histórico ou ser isolados em commits menores.

### 3. O `base.html.twig` virou infraestrutura compartilhada

Ele é hoje um arquivo sensível. Pequenos bugs nele afetam:

- auditoria
- catálogo de programas
- catálogo de testes

### 4. O PostgreSQL fica fora do container

Isso impacta:

- configuração
- testes
- diagnóstico de conexão
- reprodutibilidade em novos ambientes

Arquivos SQL/bootstrapping importantes:

- `sql/dfe_schema.sql`
- `sql/dfe_bootstrap.sh`
- `sql/configure_postgres_host_access.sh`

## Próximos passos naturais

Se outra sessão precisar continuar, os caminhos mais naturais são:

1. revisar o dashboard de auditoria com dados reais e ajustar UX fina
2. revisar os scripts de teste NFe/NFSe conforme ambiente externo
3. separar commits futuros em lotes menores
4. limpar ou organizar melhor artefatos operacionais hoje versionados
5. consolidar mais documentação de operação do PostgreSQL externo

## Ordem mínima de leitura para retomada

1. `docs/README_CONTEXTO.md`
2. `docs/CONTEXTO_SESSAO_ATUAL.md`
3. `docs/GUIA_RETORNO_RAPIDO.md`
4. `docs/ARQUITETURA_ATUAL.md`
5. `docs/FLUXO_INTEGRACAO_LEGADO.md`
