# Design: monitor de envios NFe para usuario final

Data: `2026-06-30`

## Objetivo

Criar uma tela administrativa de monitoramento de envios de `NFe` para usuario final, usando `Kendo UI`, com foco em:

- listar os ultimos `100` registros
- mostrar numero da nota, cliente, data, valor e situacao
- destacar envios em andamento e erros
- permitir detalhe rapido em modal
- permitir detalhe completo em pagina propria, equivalente a uma "tela de saida"

Essa primeira versao cobre apenas `NFe`.

## Escopo da primeira versao

Entram no escopo:

- nova tela Symfony/Twig no portal administrativo
- grade `Kendo Grid` com carregamento por endpoint JSON
- consulta combinando auditoria operacional e dados estruturados da nota
- detalhe rapido em modal `Kendo Window`
- pagina completa de detalhe
- recorte padrao dos ultimos `100` registros
- atualizacao apenas por recarregamento manual da pagina

Ficam fora do escopo nesta fase:

- suporte a `NFSe`
- auto refresh
- filtros avancados no servidor
- edicao ou reprocessamento pela tela
- dashboard em tempo real com push/websocket

## Motivacao arquitetural

O projeto atual ja usa `Symfony + Twig` para telas administrativas e `PostgreSQL` para auditoria operacional. Tambem possui dados estruturados de `NFe` gravados nas tabelas `t99019+`.

A abordagem recomendada para esta tela e:

1. pagina HTML renderizada por `Twig`
2. `Kendo UI` para a grade e interacoes de detalhe rapido
3. endpoint JSON Symfony para alimentar a grade
4. camada de repositorio/servico para montar a consulta combinada

Essa estrategia respeita o padrao atual do repositorio e evita criar uma SPA isolada apenas para este fluxo.

## Fontes de dados

O monitor deve cruzar duas origens:

### 1. Auditoria operacional

Tabela principal:

- `t99001`

Responsabilidades nessa tela:

- identificar a requisicao de envio
- obter data/hora do envio
- obter status operacional
- identificar erros de execucao
- disponibilizar request/response e metadados de auditoria para o detalhe

### 2. Estrutura fiscal da nota

Tabelas principais:

- `t99019`: dados gerais da `NFe`
- `t99020`: emitente
- `t99021`: destinatario
- `t99032` e `t99033`: totais de impostos, quando necessario

Responsabilidades nessa tela:

- numero da nota
- cliente
- valor
- chave da nota
- dados estruturados para detalhe completo

## Regra de composicao dos registros

O monitor nao deve depender exclusivamente da extracao estruturada para exibir uma linha.

Regra:

- toda requisicao elegivel de envio de `NFe` encontrada em `t99001` deve aparecer na grade
- se houver nota estruturada vinculada, a linha e enriquecida com numero, cliente e valor
- se a nota ainda nao estiver estruturada, a linha continua visivel com foco operacional

Isso evita perder visibilidade de:

- envios em processamento
- envios com erro
- envios ainda sem extracao concluida

## Definicao da grade

Tecnologia:

- `Kendo Grid`

Ordenacao padrao:

- data/hora decrescente

Recorte inicial:

- ultimos `100` registros

Colunas da primeira versao:

- `numero_nota`
- `cliente`
- `data_envio`
- `valor_total`
- `situacao`
- `ocorrencia`
- `acoes`

### Significado das colunas

`numero_nota`

- numero da `NFe` quando existir nota estruturada
- fallback para vazio ou marcador visual quando ainda nao houver nota vinculada

`cliente`

- nome do destinatario preferencialmente
- fallback para documento do destinatario ou texto auxiliar quando o nome nao estiver disponivel

`data_envio`

- data/hora principal da requisicao de envio

`valor_total`

- valor total da nota quando disponivel

`situacao`

- estado consolidado, voltado ao usuario final

`ocorrencia`

- categoria visual curta para destacar `envio`, `erro` ou `processando`

`acoes`

- abrir detalhe rapido
- abrir detalhe completo

## Mapeamento de situacao

A coluna `situacao` deve traduzir o estado tecnico para algo legivel.

Estados previstos na primeira versao:

- `Enviado com sucesso`
- `Enviado com falha`
- `Em processamento`
- `Pendente`
- `Erro de extracao`
- `Sem nota vinculada`

O mapeamento exato sera implementado com base em campos de status de `t99001` e, quando aplicavel, nos campos recentes de extracao:

- `si_status_extracao`
- `dt_hr_ini_extracao`
- `dt_hr_fim_extracao`
- `t_erro_extracao`

## Interacao de detalhe rapido

O detalhe rapido sera aberto a partir da grade em um componente `Kendo Window`.

Conteudo minimo:

- numero da nota
- chave, se existir
- cliente
- valor
- status consolidado
- resumo operacional do envio
- mensagem de erro, se existir
- resumo curto da resposta
- link para abrir a tela completa

Objetivo:

- responder a maior parte das consultas operacionais sem navegar para outra pagina

## Tela completa de detalhe

A tela completa representa a "tela de saida" da nota.

Conteudo previsto:

- cabecalho com identificacao da nota
- situacao operacional da requisicao
- dados do emitente
- dados do destinatario
- totais principais
- chave da nota
- request e response relevantes da auditoria
- erros detalhados, quando houver
- referencias a `XML autorizado` e `DANFE`, quando estiverem disponiveis no fluxo

Essa tela deve funcionar mesmo quando a nota ainda nao estiver totalmente estruturada. Nesses casos, a pagina mostra primeiro o bloco operacional e informa claramente a ausencia parcial de dados fiscais enriquecidos.

## Endpoints e componentes

Primeira versao prevista:

### HTML

- rota da tela principal do monitor
- rota da pagina completa de detalhe

### JSON

- endpoint para listar os ultimos `100` registros do monitor
- endpoint para carregar o detalhe rapido sob demanda

## Estrutura prevista no projeto

Arquivos novos esperados:

- controller novo em `src/Controller/`
- template novo em `templates/admin/`
- assets proprios em `catalog-assets/monitor/`
- servico/repositorio novo para montar a consulta combinada

Assets de vendor:

- reutilizar `catalog-assets/kendo/`

## UX da primeira versao

Diretrizes:

- tela orientada a operacao
- leitura rapida da situacao
- status em destaque visual
- acoes de detalhe sempre visiveis
- manter compatibilidade com o layout atual do portal administrativo

Fluxo principal:

1. usuario abre o monitor
2. visualiza os ultimos `100` envios
3. identifica sucesso, processamento ou erro
4. abre detalhe rapido se a consulta for simples
5. abre pagina completa quando precisar inspecionar a saida da nota

## Regras de fallback

Quando nao houver dados suficientes para numero, cliente ou valor:

- a linha continua aparecendo
- a interface mostra que o registro ainda esta sem enriquecimento total
- o detalhe completo ainda pode ser aberto com foco no bloco operacional

## Riscos e cuidados

- vinculacao entre auditoria e nota estruturada pode nao estar completa para todos os fluxos historicos
- alguns envios podem existir apenas no nivel operacional e sem numero de nota ainda estruturado
- a definicao do join deve privilegiar robustez e nao perfeicao inicial
- a tela nao deve falhar se parte dos dados fiscais estiver ausente

## Testes e validacao

Validacoes minimas desta entrega futura:

- `php bin/console lint:container`
- `php bin/console lint:twig` nos templates novos
- validacao manual da tela no ambiente publicado na porta `8089`

Como a primeira versao e principalmente de leitura, a validacao funcional manual deve confirmar:

- carregamento da grade
- abertura do detalhe rapido
- abertura da tela completa
- exibicao coerente de sucesso, processamento e erro

## Decisoes aprovadas

- usar `Kendo UI`
- monitor inicial apenas para `NFe`
- origem combinada entre auditoria `t99001` e tabelas estruturadas `t99019+`
- detalhe rapido em modal e detalhe completo em pagina propria
- recorte inicial dos ultimos `100` registros
- sem auto refresh na primeira versao

## Proximo passo

Depois da aprovacao desta spec, o proximo passo e escrever um plano de implementacao detalhado antes de iniciar a codificacao.
