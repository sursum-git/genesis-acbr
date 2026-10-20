# Contexto da Sessão Atual

Atualizado em `2026-10-20`.

## Objetivo deste arquivo

Permitir que uma nova sessão retome o projeto sem depender do histórico do chat.

Este arquivo resume:

- estado atual do sistema;
- mudanças recentes implementadas;
- arquivos sensíveis;
- testes executados;
- pontos pendentes/de atenção.

## Resumo executivo

O projeto é uma fachada moderna em `Symfony 7 + API Platform 4.2` sobre módulos legados do `ACBr`.

O sistema hoje combina:

1. APIs públicas para `CEP`, `NFe` e `NFSe`;
2. portal administrativo em Twig/AdminLTE;
3. autenticação própria por `usuario + senha`;
4. permissões por papel (`super_admin`, `company_admin`, `common`);
5. relação usuário × empresa × assinante;
6. auditoria operacional em PostgreSQL;
7. workers para API assíncrona, extração e webhooks;
8. monitores de NFe de saída e entrada;
9. catálogos auxiliares em SQLite.

Manual operacional consolidado do projeto:

```text
docs/MANUAL_OPERACIONAL_PROJETO.md
```

## Ambiente

Raiz do projeto:

```text
/dados_containers/www
```

Endpoint web padrão:

```text
http://157.173.110.195:8089
```

### Acesso SSH administrativo

- Host: `157.173.110.195`, porta `22`, usuário `root`.
- Autenticação por chave pública `ed25519`; a chave privada permanece somente no Windows em `%USERPROFILE%\.ssh\genesis-acbr`.
- Comando de acesso: `ssh -i "%USERPROFILE%\.ssh\genesis-acbr" root@157.173.110.195`.
- Não registrar, copiar ou versionar a chave privada, senhas, tokens ou certificados.

Entrada Symfony:

```text
/index.php
```

## Estado recente da autenticação

Foi implementado sistema de login próprio.

Características:

- login por `usuario + senha`;
- tela de login fora do layout administrativo, sem iframe central;
- redirecionamento para `/index.php/login` quando não autenticado;
- botão `Sair`;
- header mostra usuário logado e tipo;
- sessão expira após 1 hora de inatividade;
- token da API passa a ser resolvido pelo usuário/assinante atual, não mais por token fixo global.

Papéis:

- `super_admin`: administrador geral/desenvolvedores;
- `company_admin`: administrador do assinante;
- `common`: usuário comum.

Regras:

- `super_admin` acessa tudo;
- `company_admin` acessa usuários e monitores, mas não áreas internas de operação nem assinantes;
- `common` acessa somente monitores permitidos pelo vínculo de empresas;
- usuários não `super_admin` não veem telas de operação no menu.

## Usuários, empresas e assinantes

Tabelas:

- `t00005`: usuários/logins;
- `t00006`: empresas;
- `t00007`: vínculo usuário × empresa;
- `t00008`: vínculo empresa × assinante;
- `t00009`: tokens/sessões;
- `t00002`: assinantes.

Relação:

```text
usuario -> empresas -> assinantes
```

O usuário pode ter várias empresas.

A empresa pode estar ligada a vários assinantes.

O assinante pode ter várias empresas.

## Tela de usuários

Caminho:

```text
/index.php/usuarios
```

Estado atual:

- `super_admin` cria/edita `super_admin`, `company_admin` e `common`;
- `company_admin` cria/edita apenas `company_admin` e `common`;
- `company_admin` não vê nem consegue enviar `super_admin`;
- `company_admin` não edita registros `super_admin`;
- a listagem possui edição inline de:
  - nome;
  - tipo;
  - ativo/inativo;
  - nova senha opcional;
  - empresas vinculadas;
- criação de usuário permite buscar empresas por nome/CNPJ;
- edição inline também usa busca dinâmica de empresas.

Arquivos principais:

- `src/Controller/UserAdminController.php`
- `src/Repository/Auth/AuthUserRepository.php`
- `src/Service/Auth/CurrentUserContext.php`
- `src/EventSubscriber/AuthGateSubscriber.php`
- `templates/admin/users.html.twig`
- `catalog-assets/admin/user-company-search.js`

## Monitores NFe

Monitores:

- saída: `/index.php/monitor-saida-nfe`;
- entrada: `/index.php/monitor-entrada-nfe`.

Estado atual:

- monitores respeitam escopo do usuário;
- `super_admin` vê tudo;
- usuários não `super_admin` filtram por:
  - assinantes vinculados às empresas do usuário;
  - CNPJs das empresas vinculadas;
- usuário sem empresa/assinante vinculado não vê dados;
- menu de ações do monitor de saída foi trocado para popup/fallback próprio, evitando dependência problemática do DropDownButton Kendo.

Arquivos principais:

- `src/Controller/NfeOutputMonitorController.php`
- `src/Controller/NfeInputMonitorController.php`
- `src/Repository/NfeOutputMonitorRepository.php`
- `src/Repository/NfeInputMonitorRepository.php`
- `templates/admin/nfe_output_monitor.html.twig`
- `catalog-assets/monitor/nfe-output-monitor.js`
- `catalog-assets/monitor/nfe-output-monitor.css`

## Limpeza da base de exemplos

Foi feita limpeza destrutiva confirmada pelo usuário na base PostgreSQL `dfe`.

Critério mantido:

- programa `nfe`;
- caminho:
  - `/nfe/envio/enviar-sincrono-xml`;
  - `/nfe/envio/enviar-assincrono-xml`;
- processamento concluído;
- HTTP `200` ou `201`;
- nota extraída em `t99019`.

Resultado validado:

- `t99001`: 30 registros;
- `t99008`: 30 registros;
- `t99019`: 30 registros;
- inválidos restantes pelo critério: 0.

Backups criados no banco:

```text
cleanup_backup_20260819_keep30_*
```

Exemplos:

- `cleanup_backup_20260819_keep30_t99001`
- `cleanup_backup_20260819_keep30_t99019`

## Bancos de dados

PostgreSQL externo:

- database: `dfe`;
- uso: assinantes, autenticação, auditoria, filas, extrações, monitores.

SQLite local:

- `var/db/program_catalog.sqlite`: catálogo de programas/testes e histórico auxiliar.
- `var/db/audit.sqlite`: banco auxiliar local quando aplicável.

## Workers

Comandos:

```bash
php bin/console app:api-request-worker
php bin/console app:api-extraction-worker
php bin/console app:webhook-delivery-worker
```

Funções:

- `app:api-request-worker`: processa requisições assíncronas da API;
- `app:api-extraction-worker`: extrai dados de NFe/NSU/DF-e;
- `app:webhook-delivery-worker`: entrega webhooks pendentes.

## APIs e legado

Módulos principais:

- `CEP`: integração mais tipada, com DTOs e service próprio;
- `NFe`: adaptador declarativo sobre `NFe/MT/ACBrNFeServicosMT.php`;
- `NFSe`: adaptador declarativo sobre `NFSe/MT/ACBrNFSeServicosMT.php`.

Diretriz arquitetural:

- novas operações públicas devem preferir DTOs dedicados;
- evitar expor detalhes genéricos do legado no contrato público;
- manter metadados API Platform/validator em XML onde esse padrão já foi adotado.

## Documentação atualizada

Manual geral:

```text
docs/MANUAL_OPERACIONAL_PROJETO.md
```

Arquivos de contexto:

- `docs/README_CONTEXTO.md`
- `docs/ARQUITETURA_ATUAL.md`
- `docs/MAPA_MODULOS_API.md`
- `docs/FLUXO_INTEGRACAO_LEGADO.md`
- `docs/CONTEXTO_ACUMULADO_PROJETO.md`
- `docs/CONTEXTO_SESSAO_ATUAL.md`
- `docs/GUIA_RETORNO_RAPIDO.md`

## Validações recentes executadas

Após as mudanças de usuários/permissões:

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
php -l src/Controller/UserAdminController.php
php -l src/Repository/Auth/AuthUserRepository.php
php -l src/EventSubscriber/AuthGateSubscriber.php
php -l src/Service/Auth/CurrentUserContext.php
```

## Estado do workspace no momento deste contexto

Há várias alterações locais acumuladas no workspace, incluindo mudanças recentes de autenticação, monitores, documentação e bancos SQLite auxiliares.

Antes de continuar qualquer nova alteração, rodar:

```bash
git status --short
```

Não reverter arquivos sem revisar, porque existem alterações do fluxo atual e possivelmente alterações anteriores do usuário.

## Pontos de atenção para próxima sessão

- Validar no navegador real a tela `/index.php/usuarios` como `super_admin` e como `company_admin`.
- Se mexer na tela de usuários, revisar `catalog-assets/admin/user-company-search.js`, pois ele precisa respeitar o atributo `form` na edição inline.
- Se mexer nos monitores, validar escopo por usuário comum com empresas vinculadas.
- Se mexer no banco, não apagar dados sem confirmação explícita e backup.
- Se mexer no menu/rotas, revisar `AuthGateSubscriber` e `templates/admin/base.html.twig` juntos.
- Não registrar tokens reais, senhas ou credenciais em documentação.
