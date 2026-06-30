# Contexto da Sessao Atual

Atualizado em `2026-06-30`.

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
6. Extracao posterior de dados de `NFe`, `NSU` e normalizacao estrutural da nota

Estado atual consolidado:

- a extracao de `NFe` e `NSU` esta funcional no ambiente real
- os endpoints de envio e consulta de NFe ja alimentam as tabelas novas da nota
- houve reorganizacao dos XMLs de amostra em `nfe_xmls/`
- os XMLs convertidos para homologacao foram limpos para uso como XML de envio, sem autorizacao acoplada

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
- `t99019`: dados gerais da NFe
- `t99020`: emitente versionado
- `t99021`: destinatario versionado
- `t99022`: transporte versionado
- `t99023`: pivô `t99019 x t99020`
- `t99024`: pivô `t99019 x t99021`
- `t99025`: pivô `t99019 x t99022`
- `t99026`: produtos e servicos
- `t99027`: impostos por modalidade
- `t99028`: cobranca
- `t99029`: pagamentos
- `t99030`: duplicatas
- `t99031`: modalidade de imposto ligada ao item
- `t99032`: total de impostos da NF
- `t99033`: modalidades dos totais de impostos da NF

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
- `/nfe/envio/enviar-sincrono-xml`
- `/nfe/envio/enviar-assincrono-xml`
- `/nfe/envio/enviar-sincrono-ini`
- `/nfe/envio/enviar-assincrono-ini`

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
- os endpoints de envio de NFe tambem entram na fila de extracao
- o worker agora preserva o corpo completo de requisicao/resposta nas rotas extraiveis, sem truncar XML
- o XML de envio com raiz `<NFe>` tambem e aceito na extracao como documento `procNFe`
- o campo `t99007.documento_consulta` foi ampliado para suportar chave de 44 digitos

Limitacoes atuais:

- no retorno textual de `consultar-com-chave`, campos como `numero`, `serie`, `modelo`, `emitente_documento`, `destinatario_documento` e `interessado_documento` nao vem completos no payload
- hoje eles so serao preenchidos totalmente quando houver XML mais rico ou item de distribuicao
- melhoria futura possivel: derivar `numero`, `serie`, `modelo` e `emitente_documento` da chave de acesso
- se a SEFAZ devolver apenas rejeicao textual na distribuicao, a extracao conclui sem erro mas nao cria estrutura rica de nota

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

## XMLs de amostra em `nfe_xmls`

Estrutura atual:

- `nfe_xmls/originais_vazios/`
- `nfe_xmls/producao_autorizadas/`
- `nfe_xmls/homologacao_convertidos/`
- `nfe_xmls/homologacao_pronto_50030197218.xml`
- `nfe_xmls/retorno_homologacao_50030197218_formatado.json`

Regra atual dos grupos:

- `originais_vazios`: arquivos antigos sem sufixo ` (1)` que estavam vazios
- `producao_autorizadas`: XMLs originais com autorizacao completa
- `homologacao_convertidos`: copias preparadas para teste de envio em homologacao

Nos arquivos de `homologacao_convertidos` foram aplicadas estas mudancas:

- `tpAmb = 2`
- `emit/xNome = NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL`
- `dest/xNome = NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL`
- remocao de `Signature`
- remocao de `protNFe`
- remocao do envelope `nfeProc`, deixando apenas `NFe`

Importante:

- isso prepara o XML para envio/validacao em homologacao
- isso nao muda sozinho o ambiente runtime do legado ACBr

### Amostras recentes validadas

Arquivos recentes criados para reutilizacao:

- `nfe_xmls/homologacao_pronto_50030197218.xml`: XML pronto para envio em homologacao, sem `Signature` e sem `protNFe`
- `nfe_xmls/retorno_homologacao_50030197218_formatado.json`: retorno real formatado do endpoint sincronico com autorizacao

Historico importante:

- a primeira amostra `homologacao_pronto_50030197217.xml` foi removida porque gerou rejeicao `904`
- a causa foi `tPag=90` com `vPag` informado
- a amostra substituta `50030197218` autorizou com `CStat=150`

Resultado real confirmado em `2026-06-30`:

- endpoint: `/nfe/envio/enviar-sincrono-xml`
- autorizacao principal: `CStat=150`
- campos retornados com sucesso: `c_stat_receita`, `xml_autorizado`, `danfe_base64`
- campo ainda ausente no payload testado: `caminho_danfe`

Conclusao operacional:

- o enriquecimento do envio sincronico esta funcionando para XML autorizado e DANFE em base64
- ainda existe um ajuste pendente para garantir `caminho_danfe` no retorno quando o PDF vier inline pelo ACBr

### Enriquecimento do envio de NFe

Commits relevantes mais recentes:

- `0a6c42c`: enriquecimento de resposta autorizada de NFe
- `0760f39`: fix da conexao do repositorio de parametros para o banco de auditoria
- `1e70f61`: configuracao da pasta dedicada `NFe/arqs/danfes`
- `396af63`: preserva `PathPDF` separado de `PathNFe`
- `e48138e`: trata retorno inline do ACBr com PDF em base64 no fluxo sincronico
- `245070b`: adiciona amostra inicial de XML homologacao
- `0ccf33d`: substitui amostra invalida por XML homologacao valido
- `abe5a7f`: adiciona retorno formatado real de homologacao

Comportamento atual do enriquecimento:

- usa codigos parametrizados em `t99034`
- hoje o sucesso funcional esperado e `HTTP 200/201` com `CStat principal = 150`
- ao autorizar, tenta localizar o XML processado e gerar a DANFE
- se o ACBr devolver caminho fisico do PDF, o sistema usa esse caminho
- se o ACBr devolver o PDF inline em base64, o sistema popula `danfe_base64`
- nesse segundo caso, o retorno real testado mostrou que ainda pode faltar `caminho_danfe`

## Ambiente runtime da NFe

O legado NFe ainda depende do arquivo:

- `NFe/MT/ACBrNFe.INI`

Valor relevante:

- `Ambiente=0`

Correspondencia:

- `0 = producao`
- `1 = homologacao`

Conclusao operacional:

- os XMLs de `homologacao_convertidos` estao no formato certo para homologacao
- mas, se o runtime continuar com `Ambiente=0`, o servidor pode responder rejeicao `252` por divergencia de ambiente
- houve alteracao local em `NFe/MT/ACBrNFe.INI` que nao foi incorporada automaticamente aos commits recentes de XML

## Validacoes reais recentes

Testes reais ja confirmados no ambiente:

- `/nfe/envio/enviar-sincrono-xml` gerando `procNFe` e alimentando `t99019` a `t99033`
- `/nfe/consultas/consultar-com-chave-xml` salvando o XML completo e gerando `procNFe`
- `/nfe/distribuicao-dfe/por-chave` concluindo extracao sem falhar, inclusive em retorno textual sem `docZip`

Exemplos de requests auditadas que servem de referencia:

- `11709`: envio XML com extracao concluida
- `11712`: distribuicao por chave com extracao concluida
- `11713`: consulta por XML com extracao concluida

## Arquivos principais para retomar rapidamente

- `docs/README_CONTEXTO.md`
- `docs/ARQUITETURA_ATUAL.md`
- `docs/CONTEXTO_ACUMULADO_PROJETO.md`
- `docs/GUIA_RETORNO_RAPIDO.md`
- `docs/roteiro-teste-telas-workers-webhooks.md`

## Commits recentes relevantes

- `3029d49`: compatibilidade de extracao e pivôs da NFe
- `5789843`: ajuste do handling de payload para NFe e NSU
- `fe237bd`: organizacao dos XMLs por producao/homologacao/vazios
- `f284333`: remocao de assinatura e autorizacao dos XMLs de homologacao

## Pontos de atencao

1. O worker de extracao precisa permanecer rodando no host para consumir `si_status_extracao = pendente`.
2. Nem todo retorno de NFe traz XML completo; em alguns casos a extracao sera parcial.
3. Os XMLs de homologacao ja foram preparados, mas o runtime da NFe pode ainda estar apontando para producao.
4. Existe sujeira local fora destes docs, especialmente `NFe/MT/ACBrNFe.INI`, `var/db/program_catalog.sqlite` e um item local chamado `-`; nao sobrescrever sem analisar.
3. Se aparecer fila parada em extracao, verificar primeiro se o worker esta ativo e se usa a conexao `app.audit_connection`.
4. Se houver testes com webhook local, a protecao `SSRF` vai bloquear destinos privados como `127.0.0.1`.
