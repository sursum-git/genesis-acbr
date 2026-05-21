# Changelog Operacional

Atualizado em `2026-05-21`.

Este arquivo registra o historico do projeto em ordem cronologica, com foco operacional.

Ele responde a perguntas como:

- o que mudou em cada fase pratica do projeto
- quando determinado comportamento foi introduzido
- quais problemas de ambiente ou runtime ja foram diagnosticados
- quais restricoes do `ACBr` continuam abertas

## 2026-04-11

### Estrutura inicial e catalogo local

Foram criados os primeiros artefatos de organizacao do projeto:

- regras de commit para arquivos novos
- regras de testes backend e E2E
- catalogo local de programas
- pagina web do catalogo de programas
- documentacao do bind mount Docker
- referencia do endpoint E2E padrao em `8089`

Impacto:

- o projeto passou a se organizar em torno de um catalogo local em `SQLite`
- a operacao no ambiente com bind mount deixou de depender de memoria informal

### Primeira base moderna para a API

Entraram:

- `Doctrine DBAL`
- DTOs tipados
- inicio da tipagem de `NFe consulta-cadastro`
- metadata XML para API/validator

Impacto:

- o projeto deixou de depender apenas de contratos dinamicos do legado
- `consulta-cadastro` passou a ter uma camada publica mais forte

## 2026-04-13

### Consolidacao da arquitetura Symfony + API Platform

O projeto consolidou:

- metadata XML em `config/api_platform/resources`
- uso de `DBAL` no lugar de `PDO` em partes novas
- separacao de mappings da API Platform e validator

Impacto:

- a camada moderna ficou mais organizada e consistente com o resto do projeto

## 2026-04-14

### Diagnostico do runtime da NFe

Foi confirmado o diagnostico do runtime da demo da `NFe`.

Impacto:

- o projeto passou a tratar de forma mais direta a carga de bibliotecas do `ACBr`

## 2026-04-15

### Runtime de bibliotecas ACBr

Foi tentada uma adaptacao de runtime Linux para as libs do `ACBr`, depois revertida, e por fim consolidada uma abordagem de diretio compartilhado de bibliotecas.

Resultado pratico:

- abandonou-se a estrategia de bind em `/usr/lib`
- passou-se a usar um diretorio isolado para as libs do `ACBr`

### Material de teste e payloads

Foram criados:

- exemplos HTTP para API Platform
- scripts shell executaveis
- documentacao dos formatos de payload de `NFe`, `NFSe` e `CEP`

Impacto:

- o time passou a ter uma base reproducivel de testes manuais

### OpenAPI inicial de NFe e CEP

O projeto ganhou:

- exemplos de `requestBody`
- schemas mais explicitos
- melhor suporte ao `Try it out`

## 2026-04-16

### Catalogo de testes

Foi criada a primeira versao do catalogo de testes com:

- `SQLite`
- UI propria
- pagina dedicada
- historico de execucoes

Depois vieram correcoes de:

- rota Apache
- captura de cenarios reais
- visibilidade da resposta
- permissoes de escrita no banco

Impacto:

- o projeto passou a registrar melhor o uso e os cenarios exercitados

## 2026-04-17

### Suporte forte a XML no OpenAPI

Essa foi uma fase de muitas iteracoes para corrigir o comportamento do `Swagger UI` com XML.

Problemas enfrentados:

- `<?xml ...?><notagname>...`
- XML escapado com `&lt;...&gt;`
- campos nao editaveis no `Try it out`
- placeholder de root indefinido

Foram ajustados:

- `OpenAPI Factory`
- exemplos XML
- bootstrap JS do Swagger
- heuristicas do frontend para manter o campo editavel

Impacto:

- os endpoints XML de `NFe` passaram a carregar exemplos reais e editaveis

### `NFe` consultas e contratos

Tambem nesse periodo:

- `consultar-com-chave` foi movido para `GET`
- foi criado o endpoint XML equivalente
- `consultar-recibo` foi movido para `GET`

## 2026-04-20

### Distribuicao DFe em `GET`

Foram convertidos para `GET`:

- `por-chave`
- `por-nsu`
- `por-ult-nsu`

Tambem foi gerado um snapshot `NSU` em arquivo.

Impacto:

- os endpoints ficaram mais naturais para consulta
- a documentacao e os testes acompanharam essa mudanca

## 2026-04-27

### Envio de NFe com multiplos arquivos

Os endpoints de envio passaram a aceitar:

- multiplos XMLs
- multiplos INIs
- lote assincrono mais flexivel

Tambem houve mudanca de ambiente da `NFe` para homologacao.

Impacto:

- os testes de envio puderam ser feitos com lotes reais de mais de um documento

## 2026-04-29

### Ajustes finos de exemplos e UX do Swagger

Foram corrigidos:

- fixture XML de homologacao
- editabilidade dos campos XML no Swagger
- normalizacao de XML para o endpoint de envio de email

Tambem entrou uma tentativa de configuracao de email mais rica, que depois seria revertida.

## 2026-04-30

### Simplificacao e consolidacao da NFe

Entraram:

- envio `INI` por corpo bruto
- validacao de regras de negocio por XML bruto
- simplificacao do endpoint de envio de email

Impacto:

- a `NFe` ficou mais consistente entre XML e INI

## 2026-05-04

### `gerar-chave`, PDF e inutilizacao

Houve uma ida e volta importante:

- `gerar-chave` foi convertido para `GET`
- por engano recebeu fluxo de PDF
- depois o fluxo correto foi movido para `imprimir-pdf`

Tambem foi ajustado:

- `imprimir-pdf` de inutilizacao por XML bruto
- `inutilizacao` como `GET`

Impacto:

- os endpoints ficaram coerentes com seu objetivo real

## 2026-05-08

### `NFSe` no OpenAPI

Foi o primeiro reforco grande da `NFSe` na documentacao da API:

- exemplos de payload foram criados
- os grupos de endpoints passaram a aparecer melhor no `Swagger`

## 2026-05-11

### `NFSe` sem arquivo migrada para `GET`

Foi feita a separacao:

- endpoints de consulta simples em `GET`
- endpoints de arquivo mantendo `POST`

Impacto:

- a superficie HTTP da `NFSe` ficou mais previsivel

## 2026-05-13

### `NFSe` com corpo bruto real

Os endpoints que recebem apenas arquivo ou lote passaram a aceitar:

- XML/INI diretamente no body

Tambem houve:

- melhoria dos exemplos de `NFSe`
- configuracao inicial de `ISSSaoPaulo`
- correcao das libs SSL da `NFSe`
- normalizacao de `UTF-8`
- endurecimento da leitura de XML
- preload de configuracao da demo

Esse dia concentrou muito do diagnostico de:

- `CalcHash`
- problemas de codificacao
- falhas de leitura da demo

## 2026-05-20 e 2026-05-21

### Portal administrativo em `AdminLTE 4`

O portal Symfony/Twig passou por uma troca visual relevante:

- criacao de um shell compartilhado em `templates/admin/base.html.twig`
- adicao de tema azul/branco em `catalog-assets/adminlte-portal.css`
- migracao do hub e das paginas simples para o novo layout
- migracao dos catalogos e da auditoria para o mesmo shell lateral

Impacto:

- o portal deixou de usar apenas layouts locais e passou a ter uma base visual unica
- a navegacao lateral virou a entrada principal das paginas administrativas
- o topo foi simplificado, removendo titulo e subtitulo redundantes

### Separacao da auditoria em duas telas

A auditoria deixou de concentrar tudo numa unica pagina.

Entraram:

- `/auditoria-requisicoes` como tela operacional
- `/auditoria-requisicoes/visao-analitica` como tela analitica

Tambem sairam:

- bloco de “modos da auditoria”
- toggle de modo compacto/expandido

Impacto:

- filtros, lista e detalhe ficaram mais focados na tela operacional
- indicadores, comparativos e graficos passaram para uma tela propria
- a navegacao entre as duas visoes foi movida para o menu lateral

### Limpeza de redundancias visuais

Foram removidos textos repetidos que poluiam a leitura:

- subtitulo da barra superior
- titulo da barra superior
- bloco introdutorio extra no hub
- descricao duplicada nas paginas simples

Impacto:

- a primeira dobra ficou mais objetiva
- o cabecalho passou a competir menos com o conteudo principal

## 2026-05-14

### Empresa de teste `TECNO FLEX`

Foram aplicados:

- `CNPJ`
- `IE`
- `Inscricao Municipal`
- exemplos refeitos para `Sao Paulo`
- ajuste dos exemplos `INI` aceitos pela `ACBr`

Resultado importante:

- o erro de `ID Inválido` foi superado para o `INI` refeito
- o bloqueio restante passou a ser a ausencia de URL de homologacao para `ISSSaoPaulo`

### Sao Paulo tratado como producao-only

Foi confirmado na propria biblioteca que:

- o provedor `ISSSaoPaulo` esta ativo
- os servicos existem
- mas a homologacao nao e informada nessa build

O projeto foi entao ajustado para:

- abrir a demo em `Produção`
- avisar o usuario na UI
- evitar que a tela permaneça em `Homologação` nesse caso

## 2026-05-15

### Consolidacao de contexto

Foram criados arquivos de contexto acumulado para o projeto:

- `CONTEXTO_ACUMULADO_PROJETO.md`
- `HISTORICO_IMPLEMENTACOES_ACBR.md`
- `OPERACAO_NFSE_SAO_PAULO.md`
- este `CHANGELOG_OPERACIONAL.md`

Impacto:

- o conhecimento de projeto deixou de ficar espalhado apenas em conversa e `git log`
- a operacao futura passa a ter um ponto de consulta mais direto

## Estado atual resumido

### NFe

- bastante amadurecida
- contratos e payloads mais claros
- boa cobertura de XML/INI bruto
- `Swagger` utilizavel para a maior parte dos cenarios

### NFSe

- bem melhor documentada e integrada que no inicio
- ainda sensivel a variacoes de provedor
- `ISSSaoPaulo` depende das limitacoes reais da biblioteca carregada

### Catalogos

- catalogo de programas funcional
- catalogo de testes estruturado
