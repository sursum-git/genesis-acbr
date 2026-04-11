# Contexto da Arquitetura

Arquivos de contexto adicionados para descrever a arquitetura atualmente existente no projeto:

- `docs/ARQUITETURA_ATUAL.md`: visao geral da estrutura e do modelo arquitetural
- `docs/MAPA_MODULOS_API.md`: mapa dos modulos e da exposicao atual da API
- `docs/FLUXO_INTEGRACAO_LEGADO.md`: como a camada Symfony/API Platform integra com o legado ACBr
- `docs/CATALOGO_PROGRAMAS_SQLITE.md`: regras do catalogo local de programas em SQLite

Leitura recomendada:

1. `ARQUITETURA_ATUAL.md`
2. `MAPA_MODULOS_API.md`
3. `FLUXO_INTEGRACAO_LEGADO.md`

## Regra Operacional do Projeto

Sempre que novos arquivos forem criados no projeto, eles devem ser adicionados ao Git e commitados no mesmo fluxo de trabalho, sem deixar arquivos novos apenas localmente.

Sempre que houver mudanças no backend, devem ser executados testes compatíveis com a alteração.

Sempre que houver mudanças no frontend, devem ser executados testes end-to-end cobrindo o fluxo impactado.

## Regra Operacional do Catalogo de Programas

O projeto deve manter o banco SQLite `var/db/program_catalog.sqlite` como catalogo local dos programas/modulos do repositorio.

Esse catalogo deve seguir estas regras:

- todo programa precisa ter descricao resumida e explicacao detalhada atualizadas
- todo programa precisa ter tambem o caminho fisico principal do arquivo de referencia
- sempre que um programa for alterado no codigo, sua explicacao no catalogo deve ser revisada e atualizada
- toda alteracao relevante de programa deve gerar historico na tabela `program_history`
- programas encerrados nao devem ser apagados fisicamente da tabela principal; devem receber `ended_at` e status de encerrado
- encerramentos tambem devem ser registrados no historico
- a referencia operacional desse catalogo fica documentada em `docs/CATALOGO_PROGRAMAS_SQLITE.md`
- o acesso da aplicacao ao catalogo deve usar Doctrine DBAL e repositorios, evitando `PDO` direto em controllers

## Diretriz Arquitetural Atual

Os modulos `NFe` e `NFSe` devem usar DTOs proprios de entrada e saida na camada Symfony/API Platform.

A integracao com o legado continua existindo, mas o contrato publico da API nao deve mais depender diretamente do recurso generico legado como modelo de entrada/saida.

Quando uma operacao exigir validacao de negocio ou de contrato mais forte, o padrao preferencial e criar DTO especifico por operacao com metadados externos de configuracao e provider/processor dedicado.

No estado atual, `NFe consulta-cadastro` ja segue esse padrao e usa o contrato publico baseado em `AcUF`, `AnDocumento` e `TipoDocumento`, convertendo internamente para os parametros do legado.

Para `consulta-cadastro`, os metadados da operacao no API Platform devem ficar em XML em `config/api_platform/resources/`, e as regras de validacao do DTO devem ficar em XML em `config/validator/`, evitando atributos PHP nessa operacao.

## Topologia Local

Esta pasta `/dados_containers/www` e um bind mount usado por um container Docker.

O servidor web da aplicacao roda dentro do container e a exposicao HTTP acontece pela porta `8089`.

Ao validar paginas e fluxos web localmente, a verificacao deve considerar o acesso pela porta `8089`, e nao assumir um servidor ativo diretamente no host em `127.0.0.1:80`.

Para testes E2E web deste projeto, considerar como endpoint padrao o servidor `http://157.173.110.195:8089`.
