# Catalogo de Programas em SQLite

## Objetivo

Este projeto passa a manter um catalogo local em SQLite para registrar os programas/modulos existentes, suas explicacoes detalhadas e o historico de alteracoes.

Arquivo do banco:

- `var/db/program_catalog.sqlite`

Script de manutencao:

- `bin/sync_program_catalog.php`

## Estrutura

### Tabela `programs`

Mantem o estado atual de cada programa.

Campos principais:

- `code`: identificador tecnico estavel do programa
- `name`: nome de exibicao
- `path`: caminho principal no repositorio
- `physical_path`: caminho fisico principal do arquivo de referencia do programa
- `category`: classificacao do programa
- `status`: `active`, `inactive`, `deprecated` ou `ended`
- `description`: descricao resumida
- `detailed_explanation`: explicacao detalhada do programa
- `started_at`: data de inicio/logica de vigencia
- `ended_at`: data de encerramento, quando aplicavel
- `created_at` e `updated_at`: auditoria tecnica

### Tabela `program_history`

Mantem o historico de eventos relevantes do programa.

Eventos previstos:

- `created`
- `updated`
- `ended`

Cada registro guarda snapshots da descricao, explicacao detalhada, status e datas no momento do evento.

## Regras Operacionais

### Sempre que um programa for atualizado

Devem ser atualizados em `programs`:

- a descricao resumida, se mudou
- a explicacao detalhada, refletindo o estado atual do codigo
- qualquer metadado estrutural impactado

Essas alteracoes devem gerar historico em `program_history`.

### Sempre que um programa for encerrado ou excluido do projeto

Nao deve haver `DELETE` fisico na tabela `programs`.

Em vez disso:

1. atualizar `status` para `ended`
2. preencher `ended_at`
3. registrar o evento correspondente em `program_history`

O banco possui trigger para bloquear delecao fisica de registros da tabela principal.

### Sempre que novos programas forem adicionados ao projeto

Eles devem ser registrados no catalogo SQLite com:

- nome
- caminho principal
- caminho fisico principal do arquivo de referencia
- descricao resumida
- explicacao detalhada
- evento inicial no historico

## Processo Atual Recomendado

1. Atualizar `bin/sync_program_catalog.php` com os programas e explicacoes correntes.
2. Executar `php bin/sync_program_catalog.php`.
3. Se houve criacao de novos arquivos no projeto, seguir a regra do repositorio e commitar.
4. Se houve mudanca de programas, garantir que a explicacao detalhada tambem foi revisada.
