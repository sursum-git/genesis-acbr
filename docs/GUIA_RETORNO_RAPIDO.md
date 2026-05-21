# Guia de Retorno Rápido

Atualizado em `2026-05-21`.

## Objetivo

Este arquivo existe para reduzir o tempo de retomada em uma nova sessão.

## Onde começar

Se a sessão for técnica e precisar retomar rápido:

1. Leia `docs/CONTEXTO_SESSAO_ATUAL.md`
2. Leia `docs/ARQUITETURA_ATUAL.md`
3. Leia `docs/FLUXO_INTEGRACAO_LEGADO.md`

## Arquivos mais sensíveis hoje

### Backend de auditoria

- `src/Service/Api/ApiAuditManager.php`
- `src/Repository/ApiAuditRepository.php`
- `src/Repository/ApiAuditDashboardRepository.php`
- `src/EventSubscriber/ApiAuditRequestSubscriber.php`
- `src/EventSubscriber/ApiAuditResponseSubscriber.php`
- `src/EventSubscriber/ApiTokenAuthSubscriber.php`
- `src/Command/ApiRequestWorkerCommand.php`

### Controllers web

- `src/Controller/HomeController.php`
- `src/Controller/ApiAuditDashboardController.php`
- `src/Controller/ProgramCatalogController.php`
- `src/Controller/TestCatalogController.php`

### Frontend compartilhado

- `templates/catalog/base.html.twig`
- `catalog-assets/catalog.css`
- `templates/admin/base.html.twig`
- `catalog-assets/adminlte-portal.css`

### Templates principais

- `templates/admin/base.html.twig`
- `templates/catalog/api_audit_dashboard.html.twig`
- `templates/catalog/api_audit_overview.html.twig`
- `templates/catalog/program_catalog.html.twig`
- `templates/catalog/test_catalog.html.twig`
- `templates/home/hub.html.twig`
- `templates/home/section.html.twig`

## Comandos úteis de validação

Lint principal:

```bash
php bin/console lint:container
php bin/console lint:twig templates/admin/base.html.twig templates/home/hub.html.twig templates/home/section.html.twig templates/catalog/base.html.twig templates/catalog/api_audit_dashboard.html.twig templates/catalog/api_audit_overview.html.twig templates/catalog/program_catalog.html.twig templates/catalog/test_catalog.html.twig
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

Auditoria:

- `http://157.173.110.195:8089/index.php/auditoria-requisicoes`

Auditoria analítica:

- `http://157.173.110.195:8089/index.php/auditoria-requisicoes/visao-analitica`

Catálogo de programas:

- `http://157.173.110.195:8089/index.php/catalogo-programas`

Catálogo de testes:

- `http://157.173.110.195:8089/index.php/catalogo-testes`

## Banco e operação

PostgreSQL externo:

- database: `dfe`
- uso: auditoria, assinantes, fila, status

SQLite local:

- `var/db/program_catalog.sqlite`
- uso: catálogos e histórico auxiliar

## Boas práticas para continuidade

1. Antes de editar, rode `git status --short`
2. Se mexer em comportamento compartilhado dos catálogos, revise `templates/catalog/base.html.twig`
3. Se mexer em layout, navegação lateral ou header, revise `templates/admin/base.html.twig` e `catalog-assets/adminlte-portal.css`
4. Se mexer em auditoria, valide as duas telas: operacional e analítica
5. Se criar arquivos novos, já deixe documentados e versionados
6. Se concluir um bloco relevante, faça commit explícito
