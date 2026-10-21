# Contexto da Sessão Atual

Atualizado em `2026-10-21`.

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

- Repositório: `https://github.com/sursum-git/genesis-acbr.git`.
- Checkout local no Windows: `D:\opencode\genesis-acbr`.
- Host: `157.173.110.195`, porta `22`, usuário `root`.
- Hostname confirmado: `vmi2146066.contaboserver.net`.
- Autenticação por chave pública `ed25519`; a chave privada permanece somente no Windows em `%USERPROFILE%\.ssh\genesis-acbr`.
- A chave é protegida por frase secreta. Usar o `ssh-agent` do Windows para autenticar sem solicitar a frase a cada conexão.
- Em `2026-10-20`, o serviço `ssh-agent` foi iniciado e configurado com inicialização automática; a chave foi adicionada pelo usuário com `ssh-add`.
- Acesso confirmado em `2026-10-20` com `BatchMode=yes`, sem solicitação de senha: retorno `SSH_OK`, usuário `root` e hostname acima.
- Não registrar, copiar ou versionar a chave privada, frases secretas, senhas, tokens ou certificados.

Comando de acesso no PowerShell:

```powershell
ssh -i "$env:USERPROFILE\.ssh\genesis-acbr" root@157.173.110.195
```

Teste de acesso sem interação:

```powershell
ssh -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=15 -i "$env:USERPROFILE\.ssh\genesis-acbr" root@157.173.110.195 'echo SSH_OK; whoami; hostname'
```

Se a chave ainda não estiver carregada no agente, executar no PowerShell do usuário e informar a frase secreta somente no prompt local:

```powershell
ssh-add "$env:USERPROFILE\.ssh\genesis-acbr"
```

Se o serviço estiver parado ou desabilitado, executar em PowerShell como administrador, nesta ordem:

```powershell
Set-Service ssh-agent -StartupType Automatic
Start-Service ssh-agent
```

Entrada Symfony:

```text
/index.php
```

## Bibliotecas ACBr: diretriz permanente e homologacao

- Usar sempre a variante Linux `CONSOLE-MT` x64, sem dependencia de interface grafica. Nao selecionar `Linux/MT` ou instalar GTK/X11 para contornar a escolha do binario errado.
- Homologacao: release cadastrada `1.5.0.489`, artefato `Linux/CONSOLE-MT/libacbrnfe64.so` do pacote `ACBrLibNFe-Linux-1.5.0.489.7z`.
- Caminho no container: `/opt/acbr_libs/releases/nfe-1.5.0.489-console-mt/x64/libacbrnfe64.so`.
- SHA-256: `9d23f60f411cb33b99d271d2c4e890165cc3d37fa20432602c4e3014ffb63be1`.
- Copia persistente no host: `/dados_containers/acbr_libs/console-releases/nfe-1.5.0.489-console-mt/x64/libacbrnfe64.so`. Ao recriar o container, restaurar esse arquivo no caminho cadastrado antes de atender requisicoes; o diretorio `/opt/acbr_libs/releases` ainda nao possui volume proprio.
- Producao continua na `1.5.0.444`; nao promover a nova release sem homologacao fiscal.
- A variante grafica anterior foi preservada e sua substituicao consta no historico `t99038_acbr_library_release_change`.
- A Console exporta as 53 funcoes esperadas pelo header PHP. A versao real `1.5.0.489` foi confirmada com `NFE_Versao` em instancia inicializada em memoria, sem servicos fiscais.
- Correcao de 2026-10-21: no INI nativo `[NFe] Ambiente=0` e producao e `Ambiente=1` e homologacao. No XML, `tpAmb=1` e producao e `tpAmb=2` e homologacao. O resolvedor e a validacao do ambiente efetivo seguem essa distincao.
- Inicializacao deve carregar diretamente o INI solicitado. Retorno `-3` indica propriedade invalida e deve bloquear a operacao. Nao usar fallback `[Memory]` + `ConfigLer`: nessa modalidade o metodo pode retornar sucesso sem importar certificado, schemas ou ambiente.
- O perfil `homologacao-1.5.0.489` foi corrigido para `Ambiente=1`. Os arquivos de homologacao ficam isolados em `/dados/acbr-homologacao/nfe` no container (host: `/dados_containers/lib_acbr_php/acbr-homologacao/nfe`).
- O perfil `default` nao foi alterado neste teste: embora cadastrado na release de producao, seu INI estava com `Ambiente=1` (homologacao nativa). A selecao agora bloqueia essa divergencia de perfil/release; revisar esse perfil antes de qualquer emissao de producao.
- Schemas de homologacao: `Schemas/NFe-1.5.0.489-console`, snapshot oficial ACBr r48770 com manifesto de checksums. O perfil de homologacao deve apontar para esse diretorio; `Schemas/NFe` de producao permanece separado.
- Validacao inicial em 2026-10-20: 25 testes de backend passaram e a consulta de versao retornou `1.5.0.489`; naquele momento ainda nao havia teste fiscal real. O resultado da emissao de homologacao de 2026-10-21 esta registrado abaixo.
- A normalizacao de CNPJ e chave NF-e deve preservar letras, usar maiusculas e validar o formato antes de construir caminhos ou eventos.

## Teste real de homologacao em 2026-10-21

- Emissao solicitada pelo usuario para MEDTEXTIL, NF-e numero `202342`, serie `3`, valor `1739.80`.
- Copia do XML de producao adaptada para `tpAmb=2`, destinatario com nome de homologacao e nova assinatura; valores e numeracao preservados.
- Release efetiva: `1.5.0.489 CONSOLE-MT`, perfil `homologacao-1.5.0.489`.
- Resultado: `cStat=100`, Autorizado o uso da NF-e, protocolo `332260000216313`, recebido em `2026-10-21T11:56:31-03:00`, `tpAmb=2` no protocolo.
- Chave: `32261006013812000158550030002023421786005810`.
- XML no container: `/dados/acbr-homologacao/nfe/06013812000158/NFe/202610/NFe/32261006013812000158550030002023421786005810-nfe.xml`.
- Nao registrar o token do assinante nem dados privados do certificado neste contexto.

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


## Seleção de ambiente NFe pelo header (2026-10-21)

Use `X-ACBr-Ambiente: 1` para produção e `X-ACBr-Ambiente: 2` para homologação.
O servidor consulta a release ativa e vigente para NFe nesse ambiente e usa o perfil INI, caminho e SHA-256 do cadastro. Não escolhe a maior versão encontrada no disco nem promove automaticamente uma release de homologação.
Valores diferentes de 1/2, perfil explícito conflitante e INI com ambiente divergente são rejeitados com HTTP 400 antes da chamada fiscal. Sem esse header, mantém-se a seleção anterior por perfil.
O header é preservado pela auditoria e pelo worker assíncrono; a release é resolvida na execução, conforme a vigência do cadastro naquele instante.

Exemplo de headers para envio de XML em homologação:

```http
X-Api-Token: <seu-token>
X-ACBr-Ambiente: 2
Content-Type: application/xml
```

O XML deve ser preparado para o ambiente correspondente (`tpAmb=1` produção; `tpAmb=2` homologação). O header não reescreve o XML.
A numeração pública não deve ser copiada para o INI nativo: nele, produção é `Ambiente=0` e homologação é `Ambiente=1`.

Cliente Go (CLI e interface C compartilhada), incluindo suites:

```ini
[API]
Ambiente=2
```

A opção é enviada como `X-ACBr-Ambiente`, não como parâmetro da URL. Suites fornecem o valor apenas quando a chamada não possui um valor próprio. Clientes antigos continuam usando o comportamento anterior; recompile os binários para reconhecer a nova opção.

### Validação operacional do header

Em 2026-10-21, header 2 carregou efetivamente NFE_Versao=1.5.0.489 no servidor. Valores 0 e 3 foram bloqueados. O cadastro de produção continua apontando a release 1.5.0.444 e perfil default com Ambiente nativo=1; por isso header 1 é bloqueado por divergência, antes de chamar o legado. A criação de perfil dedicado de produção, Ambiente=0, com vínculo cadastral preservando o histórico está pendente de autorização explícita. Nenhum perfil ou cadastro de produção foi alterado neste fluxo. Os 27 testes selecionados do backend passaram; não houve nova emissão fiscal.

### Teste de emissão com numeração menor e header (2026-10-21)

A pedido do usuário, nova cópia do XML original 202342, série 3, foi preparada como nota 202341, mantendo itens e valor R$ 1.739,80. Chave recalculada: 32261006013812000158550030002023411786005813. XML tpAmb=2, destinatário com nome padrão de homologação, assinatura anterior removida para nova assinatura pelo servidor.
Envio único via POST /index.php/nfe/envio/enviar-sincrono-xml?ALote=202341, usando X-ACBr-Ambiente: 2, sem informar perfil na URL. Pré-validação confirmou release vigente e runtime Console-MT 1.5.0.489.
SEFAZ autorizou: cStat=100, protocolo 332260000216325, recebimento 2026-10-21T12:14:23-03:00; protocolo e XML confirmam tpAmb=2. Artefato autorizado no container: /dados/acbr-homologacao/nfe/06013812000158/NFe/202610/NFe/32261006013812000158550030002023411786005813-nfe.xml.
A resposta HTTP da API foi 400 com mensagem Empty reply from server, apesar da autorização fiscal. Docker/Apache registrou child pid 2099 exit signal Segmentation fault (11) às 15:14:24 UTC, após a autorização. A etapa nativa exata da falha ainda não foi determinada. Não reenviar a nota: ela já está autorizada. A pendência é investigar a falha de retorno/finalização, não repetir a emissão.
Evidências privadas no host /dados_containers/lib_acbr_php/teste-header-homologacao-20261021-202341; cópias locais ignoradas em var/tmp/homologacao-medtextil-20261021-202341. Produção não foi alterada.
