# Contexto da Sessao Atual

Atualizado em `2026-06-08`.

## Objetivo deste arquivo

Permitir que uma nova sessao retome o projeto sem depender do historico do chat.

Este arquivo deve responder rapidamente:

- qual e o estado atual do sistema
- quais fluxos operacionais estao ativos
- quais tabelas e workers existem
- quais pontos recentes exigem continuidade ou cuidado

## Resumo executivo

O projeto e uma fachada moderna em `Symfony 7 + API Platform 4.2` sobre modulos legados do `ACBr`.

Hoje o sistema esta organizado em seis blocos principais:

1. APIs publicas para `NFe`, `NFSe`, `CEP` e outros modulos legados
2. Auditoria operacional em `PostgreSQL`
3. Catalogos auxiliares em `SQLite`
4. Portal administrativo em `Twig/AdminLTE`
5. Processamento assincrono por workers separados
6. Extracao posterior de dados de `NFe` e `NSU`

## Bancos de dados em uso

### PostgreSQL externo

Banco operacional e de auditoria:

- host: `157.173.110.195`
- database: `dfe`

Conexao Symfony:

- `config/services/audit_connection.xml`
- service id: `app.audit_connection`

Tabelas mais importantes:

- `t00002`: assinantes e tokens
- `t00003`: webhooks
- `t00004`: vinculos assinante x webhook
- `t99001`: requisicoes auditadas
- `t99002`: tentativas/processamento principal
- `t99003`: configuracao de execucao
- `t99004`: eventos operacionais
- `t99005`: capacidade de workers
- `t99006`: fila/tentativas de entrega de webhook
- `t99007`: execucao de consulta DF-e e envelope `retDistDFeInt`
- `t99008`: documentos `docZip` retornados, com schema exato e XML bruto
- `t99009` a `t99018`: normalizacao de `resNFe`, `procNFe`, `resEvento`, `procEventoNFe` e `procInutNFe`

Campos recentes adicionados em `t99001`:

- `si_status_extracao`
- `dt_hr_ini_extracao`
- `dt_hr_fim_extracao`
- `t_erro_extracao`

### SQLite local

Banco local versionado:

- `var/db/program_catalog.sqlite`

Funcoes:

- catalogo de programas
- catalogo de testes
- historico auxiliar local

## Workers atuais

Os workers sao separados e nao compartilham a mesma responsabilidade:

### 1. Worker da API

Comando:

- `php bin/console app:api-request-worker`

Funcao:

- consome requisicoes assincronas
- executa a chamada interna da API
- grava status e resposta em `t99001`

### 2. Worker de webhook

Comando:

- `php bin/console app:webhook-delivery-worker`

Funcao:

- entrega webhooks para endpoints externos
- usa retentativa, avaliacao de payload e criterios de sucesso

### 3. Worker de extracao

Comando:

- `php bin/console app:api-extraction-worker`

Funcao:

- processa apenas requisicoes concluidas e elegiveis para extracao
- le `t99001`
- salva a execucao em `t99007`, os documentos brutos em `t99008` e a normalizacao nas tabelas `t99009` a `t99018`

Observacao critica:

- esse worker ja foi corrigido para usar `app.audit_connection`
- antes disso ele estava ligado na conexao errada e nao encontrava itens pendentes

## Fluxo operacional atual da API

### Requisicao sincronica

1. a API autentica o assinante
2. grava a entrada em `t99001`
3. executa a operacao
4. salva status, resposta e eventos
5. se a rota for elegivel para extracao, marca `si_status_extracao = pendente`
6. o worker de extracao processa depois
7. se houver webhook vinculado, a entrega entra no fluxo proprio

### Requisicao assincrona

1. a API autentica o assinante
2. grava a entrada em `t99001`
3. retorna `request_id`
4. o worker da API executa depois
5. ao concluir, a requisicao pode seguir para extracao
6. webhooks seguem em fila separada

## Extracao de NFe e NSU

### Rotas elegiveis

- `/nfe/consultas/consultar-com-chave`
- `/nfe/consultas/consultar-com-chave-xml`
- `/nfe/distribuicao-dfe/por-chave`
- `/nfe/distribuicao-dfe/por-nsu`
- `/nfe/distribuicao-dfe/por-ult-nsu`

### Rotas fora da extracao

- inutilizacao
- qualquer rota fora da whitelist acima

### Status de extracao

- `0`: nao se aplica
- `1`: pendente
- `2`: processando
- `3`: concluido
- `4`: falha

### Estado funcional atual

- a extracao de distribuicao DF-e persiste primeiro o envelope da consulta e cada `docZip`
- o nome exato de `docZip/@schema` passou a ser preservado no banco
- a normalizacao atual cobre `resNFe`, `procNFe`, `resEvento`, `procEventoNFe` e `procInutNFe`
- a extracao de resposta textual `[Consulta]` continua existindo como fallback de consulta NFe fora da distribuicao

Limitacao atual:

- no retorno textual de `consultar-com-chave`, campos como `numero`, `serie`, `modelo`, `emitente_documento`, `destinatario_documento` e `interessado_documento` nao vem completos no payload
- hoje eles so serao preenchidos totalmente quando houver XML mais rico ou item de distribuicao
- melhoria futura possivel: derivar `numero`, `serie`, `modelo` e `emitente_documento` da chave de acesso

## Webhooks

Estado atual do modulo:

- suporte a variaveis para `querystring`, `headers` e `path params`
- configuracao de sucesso por codigos HTTP e por combinacao de `HTTP + payload`
- operadores de payload:
  - `equals`
  - `contains`
  - `in`
- configuracao de tentativas e intervalo
- `secret` mascarado
- geracao automatica de token/secret
- regeneracao de secret
- `Idempotency-Key`
- `X-Webhook-Timestamp`
- assinatura com `timestamp.payload`
- backoff exponencial com jitter
- bloqueio de URLs locais/privadas para reduzir risco de `SSRF`
- reprocessamento manual de falhas

## Assinantes

Estado atual:

- token nao e mais digitado manualmente no cadastro
- token e gerado automaticamente ao criar o assinante
- existe botao para copiar token por registro
- correcoes aplicadas para checkboxes booleanos desmarcados salvarem como `false`

## Portal administrativo

Padrao visual consolidado:

- remover breadcrumb duplicado
- remover fundos amarelo/bege antigos
- usar layout consistente com as telas novas
- formularios principais em drawer lateral, nao em coluna fixa

Telas recentes padronizadas:

- assinantes
- webhooks
- capacidade de workers
- configuracao de execucao
- consulta de requisicoes

## Consulta de requisicoes

Estado atual:

- drawer lateral mais largo
- sem breadcrumb
- mostra detalhes da requisicao, resposta e extracao
- mostra informacao de worker e PID quando aplicavel
- deve continuar sendo a tela operacional principal para suporte

## Conectividade externa NFe

Foi corrigida a validacao TLS com a SVRS de homologacao no host.

Certificados instalados no trust store do host:

- `ICP-Brasil v10`
- `Autoridade Certificadora do SERPRO SSLv1`

Resultado pratico:

- deixou de ocorrer `Network subsystem is unusable`
- `consultar-com-chave` voltou a responder normalmente contra a SVRS

## Arquivos principais para retomar rapidamente

- `docs/README_CONTEXTO.md`
- `docs/ARQUITETURA_ATUAL.md`
- `docs/CONTEXTO_ACUMULADO_PROJETO.md`
- `docs/GUIA_RETORNO_RAPIDO.md`
- `docs/roteiro-teste-telas-workers-webhooks.md`

## Pontos de atencao

1. O worker de extracao precisa permanecer rodando no host para consumir `si_status_extracao = pendente`.
2. Nem todo retorno de NFe traz XML completo; em alguns casos a extracao sera parcial.
3. Se aparecer fila parada em extracao, verificar primeiro se o worker esta ativo e se usa a conexao `app.audit_connection`.
4. Se houver testes com webhook local, a protecao `SSRF` vai bloquear destinos privados como `127.0.0.1`.
