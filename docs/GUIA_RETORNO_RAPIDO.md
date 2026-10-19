# Guia de Retorno Rápido

Atualizado em `2026-08-20`.

## Objetivo

Este arquivo existe para reduzir o tempo de retomada em uma nova sessão.

## Onde começar

Se a sessão for técnica e precisar retomar rápido:

1. Leia `docs/CONTEXTO_SESSAO_ATUAL.md`
2. Leia `docs/MANUAL_OPERACIONAL_PROJETO.md`
3. Leia `docs/ARQUITETURA_ATUAL.md`
4. Leia `docs/FLUXO_INTEGRACAO_LEGADO.md`

## Arquivos mais sensíveis hoje

### Backend de auditoria

- `src/Service/Api/ApiAuditManager.php`
- `src/Repository/ApiAuditRepository.php`
- `src/Repository/ApiAuditDashboardRepository.php`
- `src/EventSubscriber/ApiAuditRequestSubscriber.php`
- `src/EventSubscriber/ApiAuditResponseSubscriber.php`
- `src/EventSubscriber/ApiTokenAuthSubscriber.php`
- `src/Command/ApiRequestWorkerCommand.php`

### Autenticação e usuários

- `src/EventSubscriber/AuthGateSubscriber.php`
- `src/Service/Auth/CurrentUserContext.php`
- `src/Repository/Auth/AuthUserRepository.php`
- `src/Repository/Auth/AuthSchemaManager.php`
- `src/Controller/AuthController.php`
- `src/Controller/UserAdminController.php`

### Controllers web

- `src/Controller/HomeController.php`
- `src/Controller/ApiAuditDashboardController.php`
- `src/Controller/ProgramCatalogController.php`
- `src/Controller/TestCatalogController.php`
- `src/Controller/NfeOutputMonitorController.php`
- `src/Controller/NfeInputMonitorController.php`

### Frontend compartilhado

- `templates/catalog/base.html.twig`
- `catalog-assets/catalog.css`
- `templates/admin/base.html.twig`
- `catalog-assets/adminlte-portal.css`
- `catalog-assets/admin/user-company-search.js`
- `catalog-assets/monitor/nfe-output-monitor.js`
- `catalog-assets/monitor/nfe-output-monitor.css`

### Templates principais

- `templates/admin/base.html.twig`
- `templates/catalog/api_audit_dashboard.html.twig`
- `templates/catalog/api_audit_overview.html.twig`
- `templates/catalog/program_catalog.html.twig`
- `templates/catalog/test_catalog.html.twig`
- `templates/home/hub.html.twig`
- `templates/home/section.html.twig`
- `templates/admin/users.html.twig`
- `templates/admin/nfe_output_monitor.html.twig`
- `templates/admin/nfe_input_monitor.html.twig`

## Comandos úteis de validação

Lint principal:

```bash
php bin/console lint:container
php bin/console lint:twig templates/admin/base.html.twig templates/home/hub.html.twig templates/home/section.html.twig templates/catalog/base.html.twig templates/catalog/api_audit_dashboard.html.twig templates/catalog/api_audit_overview.html.twig templates/catalog/program_catalog.html.twig templates/catalog/test_catalog.html.twig
```

Testes recentes de autenticação/usuários:

```bash
php tests/Repository/AuthUserRepositoryTest.php
php tests/Repository/AuthUserCompanyBindingTest.php
php tests/Repository/AuthSubscriberPrimaryKeyCompatibilityTest.php
php tests/Service/AuthTokenServiceTest.php
php tests/AuthSessionPolicyTest.php
php tests/Controller/UserAdminPageTest.php
php tests/Controller/CompanyAdminMenuTest.php
php tests/Controller/CommonUserMenuTest.php
php tests/Controller/UserAdminTemplateEditTest.php
node --check catalog-assets/admin/user-company-search.js
```

Teste operacional de auditoria:

```bash
bash testes_api_platform/auditoria_requisicoes.sh
```

Teste NFe resumido:

```bash
bash testes_api_platform/nfe_diagnostico.sh
```

Teste de rede externa NFe:

```bash
bash testes_api_platform/nfe_rede_externa.sh
```

## URLs úteis do ambiente atual

Home:

- `http://157.173.110.195:8089/index.php/`

Docs:

- `http://157.173.110.195:8089/index.php/docs`

Consulta de requisições:

- `http://157.173.110.195:8089/index.php/consulta-requisicoes`

Auditoria analítica:

- `http://157.173.110.195:8089/index.php/auditoria-requisicoes/visao-analitica`

Catálogo de programas:

- `http://157.173.110.195:8089/index.php/catalogo-programas`

Catálogo de testes:

- `http://157.173.110.195:8089/index.php/catalogo-testes`

Usuários:

- `http://157.173.110.195:8089/index.php/usuarios`

Monitores:

- `http://157.173.110.195:8089/index.php/monitor-saida-nfe`
- `http://157.173.110.195:8089/index.php/monitor-entrada-nfe`

## Banco e operação

PostgreSQL externo:

- database: `dfe`
- uso: autenticação, assinantes, auditoria, fila, status, extrações e monitores

SQLite local:

- `var/db/program_catalog.sqlite`
- uso: catálogos e histórico auxiliar

## Boas práticas para continuidade

1. Antes de editar, rode `git status --short`
2. Se mexer em comportamento compartilhado dos catálogos, revise `templates/catalog/base.html.twig`
3. Se mexer em layout, navegação lateral ou header, revise `templates/admin/base.html.twig` e `catalog-assets/adminlte-portal.css`
4. Se mexer em auditoria, valide as duas telas: consulta e analítica
5. Se criar arquivos novos, já deixe documentados e versionados
6. Se concluir um bloco relevante, faça commit explícito
7. Se mexer em usuários/permissões, valide como `super_admin`, `company_admin` e `common`
8. Se mexer em monitores, valide o filtro por empresas vinculadas ao usuário
