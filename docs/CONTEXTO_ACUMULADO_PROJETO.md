# Contexto Acumulado do Projeto

Atualizado em `2026-06-08`.

Este arquivo consolida o estado pratico do projeto depois da serie de ajustes feitos na integracao `ACBr`, na exposicao via `API Platform` e na instrumentacao local de catalogos e testes.

## Objetivo atual do projeto

O repositorio funciona como uma fachada HTTP moderna sobre modulos legados do `ACBr`, principalmente:

- `NFe`
- `NFSe`
- `CEP`

O objetivo nao foi reescrever o motor fiscal. O objetivo foi:

- expor contratos HTTP previsiveis via `Symfony + API Platform`
- manter compatibilidade com os scripts legados do `ACBr`
- melhorar documentacao OpenAPI
- permitir testes por `Swagger/API Platform`, scripts shell e demos antigas
- registrar contexto e catalogar partes do sistema em `SQLite`

## Macrodecisoes ja tomadas

### 1. O legado continua sendo o executor real

Para `NFe` e `NFSe`, a maior parte da regra ainda e executada pelos scripts do legado, como:

- `NFe/MT/ACBrNFeServicosMT.php`
- `NFSe/MT/ACBrNFSeServicosMT.php`

A camada moderna:

- valida o contrato publico
- transforma payloads
- escolhe o metodo legado
- normaliza resposta e erro

### 2. O projeto adotou dois estilos de integracao

- `CEP`: integracao PHP mais direta, com DTOs e service dedicado
- `NFe/NFSe`: integracao via adaptador legado, com resources, providers/processors e metadados de operacao

### 3. O OpenAPI passou a ser parte importante da entrega

Foi feito trabalho relevante para que o `Try it out` do `API Platform`:

- carregue query params corretos para endpoints `GET`
- carregue payloads JSON corretos nos endpoints legados
- carregue XML bruto real nos endpoints que recebem XML
- permita edicao manual do payload no `Swagger`

### 4. O projeto passou a usar um catalogo local em SQLite

Hoje existem dois eixos de catalogacao:

- catalogo de programas/modulos
- catalogo de testes/cenarios executados

Banco principal:

- `var/db/program_catalog.sqlite`

## Estado por modulo

## NFe

Foi o modulo mais trabalhado.

### O que foi estabilizado

- `consulta-cadastro` com contrato proprio
- `consultar-com-chave` em `GET`
- `consultar-recibo` em `GET`
- `distribuicao-dfe/*` em `GET`
- endpoints XML que recebem corpo bruto real
- endpoints INI de envio recebendo corpo bruto
- suporte a um ou mais arquivos em envio assincrono
- `imprimir-pdf` aceitando XML bruto
- `inutilizacao/imprimir-pdf` aceitando XML bruto
- `inutilizacao/inutilizar` em `GET`

### Estado operacional de runtime

- a carga de bibliotecas saiu do modelo antigo de bind em `/usr/lib`
- o runtime de libs foi isolado em diretios dedicados
- houve diagnostico e correcao de que sobrescrever `/usr/lib` quebrava o container por `glibc`

### Observacao sobre email

O endpoint de envio de email foi ajustado diversas vezes, mas a rotina `NFE_EnviarEmail` da `ACBrLibNFE` continuou falhando com erro interno `-17` em cenarios testados. Ou seja:

- o contrato HTTP foi melhorado
- a normalizacao de XML foi melhorada
- mas o bloqueio restante ficou dentro da propria biblioteca ACBr

## NFSe

Foi o modulo com mais restricoes de provedor e ambiente.

### O que foi melhorado

- exemplos OpenAPI adicionados
- endpoints sem arquivo convertidos para `GET`
- endpoints de arquivo bruto convertidos para corpo direto
- normalizacao de `UTF-8` para payloads XML/INI
- preload de configuracao na demo
- exemplos especificos para `Sao Paulo`
- ajuste de certificado `PFX`
- ajuste de runtime SSL/assinatura

### Restricao relevante atual

Para `Sao Paulo / ISSSaoPaulo`, a biblioteca carregada no ambiente atual:

- identifica o provedor corretamente
- identifica servicos disponiveis
- mas nao informa URL de homologacao

Por isso, o projeto foi ajustado para tratar esse provedor como `producao-only` na demo/configuracao padrao desta instalacao.

Arquivo chave:

- `docs/OPERACAO_NFSE_SAO_PAULO.md`

## CEP

O modulo `CEP` foi menos turbulento.

O principal estado consolidado e:

- integracao mais direta via service
- exemplos e payloads documentados
- OpenAPI com exemplos mais claros

## Catalogos e testes

## Catalogo de programas

Foi criado um catalogo em SQLite para descrever o que existe no projeto.

Arquivos principais:

- `bin/sync_program_catalog.php`
- `docs/CATALOGO_PROGRAMAS_SQLITE.md`

## Catalogo de testes

Foi criada uma pagina para catalogar cenarios executados, com persistencia em SQLite e UI propria.

Pontos importantes:

- ha rota/pagina dedicada
- o sistema chegou a registrar execucoes reais do `API Platform`
- houve correcoes de permissao em `var/db`
- houve varias iteracoes para ajustar captura de cenarios reais

## Evolucao operacional recente

Depois do estado inicial de fachada HTTP e auditoria basica, o projeto passou a operar com mais camadas de orquestracao.

### 1. Execucao assincrona consolidada

O sistema hoje diferencia claramente:

- requisicao sincronica
- requisicao assincrona com `request_id`
- processamento posterior via worker

Isso deixou de ser apenas uma resposta `202` e passou a ter suporte operacional com:

- consulta por `request_id`
- auditoria detalhada
- configuracao de execucao por rota
- monitoramento de workers

### 2. Portal administrativo operacional

Foi criado e padronizado um conjunto de telas administrativas em `Twig` para operacao do ambiente:

- assinantes
- webhooks
- capacidade de workers
- configuracao de execucao
- consulta de requisicoes
- auditoria analitica
- catalogo de programas
- catalogo de testes

As telas mais novas passaram a usar:

- layout em largura total
- drawers laterais para formularios e detalhes
- remocao de breadcrumb duplicado
- paleta azul/branco consistente

### 3. Camada de webhooks

O projeto passou a ter uma camada completa de webhooks com:

- cadastro de endpoint
- vinculo com assinantes
- execucao separada por worker proprio
- criterios configuraveis de sucesso
- avaliacao opcional de payload
- reprocessamento manual
- retentativa com backoff exponencial
- assinatura HMAC com timestamp
- `Idempotency-Key`
- protecoes contra `SSRF`

### 4. Camada de extracao posterior

Foi introduzida uma segunda esteira apos a execucao principal da API, focada em extrair dados estruturados de `NFe` e `NSU`.

Novos componentes:

- campos de extracao em `t99001`
- `t99007` para dados extraidos de NFe
- `t99008` para dados extraidos de NSU
- worker dedicado `app:api-extraction-worker`

Essa camada existe para separar:

- sucesso da requisicao principal
- sucesso da persistencia estruturada posterior

### 5. Correcao critica do worker de extracao

Durante a evolucao da camada de extracao, foi identificado um erro importante:

- `ApiExtractionRepository` nao estava amarrado a `app.audit_connection`

Efeito pratico:

- o worker de extracao rodava, mas consultava a conexao errada
- as linhas em `t99001` permaneciam pendentes

Correcao aplicada:

- injecao explicita de `app.audit_connection` em `config/services.yaml`

### 6. Conectividade TLS com a SVRS

Foi corrigido no host o problema de conectividade SSL/TLS com a SVRS de homologacao para NFe.

Foram instaladas as ACs:

- `ICP-Brasil v10`
- `Autoridade Certificadora do SERPRO SSLv1`

Resultado:

- parou de ocorrer `Network subsystem is unusable`
- as consultas de NFe por chave voltaram a responder normalmente

### 7. Limitacoes atuais da extracao textual

No endpoint `GET /nfe/consultas/consultar-com-chave`, o retorno pode vir em formato textual `[Consulta]`, sem XML completo da nota.

Nesse caso, o parser atual consegue salvar principalmente:

- chave de acesso
- status
- motivo
- data/hora de autorizacao

Mas campos como:

- numero
- serie
- modelo
- emitente
- destinatario
- interessado

dependem de XML mais rico ou precisam ser derivados da chave de acesso em melhoria posterior.

## Testes fora do repositorio

Existe uma area externa ao git do projeto, usada para exemplos e transmissao:

- `/dados_containers/testes`

Hoje ela esta organizada principalmente como:

- `/dados_containers/testes/NFe`
- `/dados_containers/testes/NFSe`

Esses arquivos:

- sao usados nas demos e nos `curl`s
- nao entram automaticamente no git
- precisam ser tratados como artefatos operacionais, nao como fonte versionada do backend

## Demos legadas

As demos antigas continuam importantes para validacao real de comportamento da `ACBrLib`.

Em especial:

- `NFe/ACBrNFeDemo...`
- `NFSe/ACBrNFSeDemoMT.php`

Parte do trabalho recente consistiu em:

- carregar configuracoes corretamente nas demos
- normalizar leitura de arquivos locais
- impedir estados de configuracao que quebram o runtime

## Estado de configuracao mais sensivel

### Certificados

Para `NFSe Sao Paulo`, foi usado:

- `ArquivoPFX=/dados/tecnoflex_2026.pfx`

O certificado foi usado para extrair:

- `CNPJ`: `57039802000122`
- razao social: `TECNO FLEX IND E COM LTDA`
- e-mail do certificado: `fiscal@tflx.com.br`

### Dados cadastrais aplicados ao emissor NFSe

- `CNPJ`: `57039802000122`
- `IE`: `104044963116`
- `Inscricao Municipal/CCM`: `10768807`
- municipio: `3550308`
- UF: `SP`

## O que ainda depende do ambiente e nao so do codigo

- comportamento de `NFE_EnviarEmail`
- disponibilidade real de homologacao para `ISSSaoPaulo`
- compatibilidade exata de alguns metodos de impressao PDF da `ACBrLib`
- conteudo fiscal valido para transmissao real em alguns provedores

## Leitura recomendada agora

1. `docs/ARQUITETURA_ATUAL.md`
2. `docs/FLUXO_INTEGRACAO_LEGADO.md`
3. `docs/MAPA_MODULOS_API.md`
4. `docs/HISTORICO_IMPLEMENTACOES_ACBR.md`
5. `docs/OPERACAO_NFSE_SAO_PAULO.md`
