# Historico de Implementacoes ACBr

Atualizado em `2026-05-15`.

Este arquivo resume, por grupos, o que ja foi implementado no projeto. Ele nao substitui `git log`; ele organiza o historico em linguagem operacional.

## Fase 1: estrutura da camada moderna

Commits de base relevantes:

- `d223205` refactor de metadata para XML e uso de `DBAL`
- `7bbd79c` separacao de mappings da API Platform e validator
- `924e85f`, `bce6273`, `0e50cec`, `c410310` consolidacao de `consulta-cadastro`

Resultado:

- o projeto passou a expor recursos com metadata XML
- `consulta-cadastro` deixou de ser somente um espelho cru do legado
- a base de `Doctrine DBAL` passou a ser usada para catalogo local

## Fase 2: runtime de bibliotecas ACBr

Commits relevantes:

- `4f00f1c`
- `dece8d1`
- `efd19ab`
- `5f5d87e`

Resultado:

- houve diagnostico do problema com bind em `/usr/lib`
- o projeto abandonou a estrategia que quebrava o container
- o runtime de libs passou a ser tratado em diretios isolados

## Fase 3: exemplos e testes da API Platform

Commits relevantes:

- `c23ba6a`
- `6ecca69`
- `84f6fa4`

Resultado:

- foram criadas pastas e scripts de teste
- foram documentados formatos de payload de `NFe`, `NFSe` e `CEP`
- a equipe passou a ter exemplos shell e HTTP para validacao

## Fase 4: catalogo SQLite e UI de catalogos

Commits relevantes:

- `be0175e`
- `7c35178`
- `5bbeda3`
- `6d88c66`
- `43d4637`
- `52174ff`
- `d3e8dba`

Resultado:

- catalogo de testes e catalogo de programas foram estruturados
- houve correcoes de rota Apache
- houve correcoes de permissao em `SQLite`
- a pagina de testes passou a exibir mais contexto e historico

## Fase 5: OpenAPI forte para NFe e CEP

Commits relevantes:

- `595ae5c`
- `6d07c64`
- `faf20cf`
- `97d76c1`
- `9dba0b2`

Resultado:

- o `API Platform` passou a carregar exemplos mais corretos
- `CEP`, `NFeConsultas`, `NFeDistribuicaoDFe`, `NFeEnvio`, `NFeEventos` e `NFeInutilizacao` tiveram melhorias de schema

## Fase 6: Swagger XML e payload editavel

Commits relevantes:

- `160f3bb`
- `9359050`
- `4f38c44`
- `ad8b463`
- `a6db50a`
- `06a5bcc`
- `3e6afaa`
- `a07a9e6`
- `813cf1d`
- `7db1f18`
- `455cf8d`
- `18c3f77`
- `9cd2c55`

Resultado:

- houve varias iteracoes para o `Swagger UI` parar de mostrar `<notagname>` e XML escapado
- o projeto passou a servir exemplos XML reais e editaveis nos endpoints certos
- foi necessario agir tanto no OpenAPI quanto no bootstrap JS do Swagger

## Fase 7: consolidacao de NFe como API utilizavel

Commits relevantes:

- `9a1aba5`
- `5fc794c`
- `b2b5da1`
- `d41ee3d`
- `99aa626`
- `5c921fe`
- `b8ceb54`
- `61d5ddc`
- `571bd00`
- `83e4710`
- `b4dd13a`
- `c970ffe`

Resultado:

- consultas importantes migradas para `GET`
- distribuicao DFe migrada para `GET`
- envio `XML` e `INI` passou a aceitar corpo bruto
- envio assincrono passou a aceitar um ou mais documentos
- validacao de regras de negocio passou a aceitar XML bruto
- impressao/salvamento de PDF passou a aceitar XML bruto nos endpoints corretos
- inutilizacao passou a usar `GET` onde fazia sentido

## Fase 8: email na NFe

Commits relevantes:

- `d7e4d0e`
- `77148aa`
- `341cd1d`
- `0529fe8`

Resultado:

- o contrato do endpoint de email foi testado e refeito algumas vezes
- a configuracao inline chegou a ser suportada
- depois foi revertida para simplificar o contrato
- a conclusao operacional foi que o bloqueio restante estava dentro de `NFE_EnviarEmail` da `ACBrLib`

## Fase 9: primeiros ciclos fortes de NFSe

Commits relevantes:

- `bd0d959`
- `336105c`
- `75fb2de`
- `36d7d13`

Resultado:

- payloads e exemplos de `NFSe` foram levados para o OpenAPI
- endpoints sem arquivo migraram para `GET`
- endpoints baseados em arquivo bruto passaram a aceitar corpo direto
- `imprimir-pdf` e `salvar-pdf` ganharam exemplos melhores

## Fase 10: NFSe Sao Paulo

Commits relevantes:

- `a8fce68`
- `3cd783a`
- `30c1048`
- `b3936be`
- `e48e2a7`
- `f237dba`
- `5a1067f`
- `698c441`
- `e4bdda2`
- `25b684b`
- `e358484`
- `ab3e408`

Resultado:

- provedor `ISSSaoPaulo` configurado por padrao
- bibliotecas SSL/assinatura corrigidas
- leitura de XML/INI endurecida para evitar problemas de `UTF-8`
- demos passaram a carregar configuracao real do `INI`
- inscricao municipal da empresa foi aplicada
- exemplos de `NFSe` para Sao Paulo foram refeitos
- o formato de `INI` aceito pela ACBr foi consolidado
- descobriu-se que esta build nao informa URL de homologacao para `ISSSaoPaulo`
- o projeto foi ajustado para operar esse provedor em `Produção` por padrao

## Estado do historico

Hoje o projeto ja passou do estagio de “ligar a API”.

O que existe agora e:

- uma camada moderna funcional
- catalogacao local
- OpenAPI significativamente mais usavel
- `NFe` bastante amadurecida
- `NFSe` ainda dependente de restricoes concretas de provedor/biblioteca

