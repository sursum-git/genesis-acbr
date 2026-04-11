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

## Regra Operacional do Catalogo de Programas

O projeto deve manter o banco SQLite `var/db/program_catalog.sqlite` como catalogo local dos programas/modulos do repositorio.

Esse catalogo deve seguir estas regras:

- todo programa precisa ter descricao resumida e explicacao detalhada atualizadas
- sempre que um programa for alterado no codigo, sua explicacao no catalogo deve ser revisada e atualizada
- toda alteracao relevante de programa deve gerar historico na tabela `program_history`
- programas encerrados nao devem ser apagados fisicamente da tabela principal; devem receber `ended_at` e status de encerrado
- encerramentos tambem devem ser registrados no historico
- a referencia operacional desse catalogo fica documentada em `docs/CATALOGO_PROGRAMAS_SQLITE.md`
