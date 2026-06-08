# Roteiro de teste das telas, workers, webhooks e `request_id`

Este roteiro descreve o fluxo completo para testar as telas novas e validar uma chamada de API desde o cadastro do assinante ate a consulta do retorno por `request_id`.

Base usada nos exemplos:

```text
http://157.173.110.195:8089/index.php
```

Se estiver testando localmente, troque a base por:

```text
http://127.0.0.1:8089/index.php
```

## 1. Ordem recomendada de teste

Siga esta ordem para reduzir erro de configuracao:

1. Criar ou revisar um assinante em `/assinantes`.
2. Copiar o token gerado automaticamente para esse assinante.
3. Criar uma faixa de capacidade em `/capacidade-workers`.
4. Criar uma regra de execucao global ou por endpoint em `/configuracao-execucao`.
5. Criar um endpoint de webhook em `/webhooks`.
6. Criar um vinculo entre assinante, webhook, API/evento e modo `sync` ou `async`.
7. Chamar um endpoint na documentacao da API Platform usando o token do assinante.
8. Se a chamada retornar `202`, copiar o `request_id`.
9. Rodar o worker de requisicoes para processar a fila.
10. Consultar `/requests/{requestId}` com o mesmo token.
11. Rodar o worker de webhooks, se houver entrega pendente.
12. Validar historico em `/monitor-workers` e `/consulta-requisicoes`.

## 2. Telas envolvidas

As telas principais ficam nestes caminhos:

```text
/assinantes
/capacidade-workers
/configuracao-execucao
/webhooks
/monitor-workers
/consulta-requisicoes
/docs/nfe
/docs/nfse
/docs/cep
```

Links completos:

```text
http://157.173.110.195:8089/index.php/assinantes
http://157.173.110.195:8089/index.php/capacidade-workers
http://157.173.110.195:8089/index.php/configuracao-execucao
http://157.173.110.195:8089/index.php/webhooks
http://157.173.110.195:8089/index.php/monitor-workers
http://157.173.110.195:8089/index.php/consulta-requisicoes
http://157.173.110.195:8089/index.php/docs/nfe
http://157.173.110.195:8089/index.php/docs/nfse
http://157.173.110.195:8089/index.php/docs/cep
```

## 3. Criar assinante

Acesse:

```text
/assinantes
```

No formulario "Novo assinante", preencha os campos do assinante. O token nao aparece no formulario porque ele e gerado automaticamente pelo backend no momento do cadastro.

Campos importantes:

- `c_identificador`: identificador tecnico do assinante. Exemplo: `teste_001`.
- `c_nome`: nome visivel. Exemplo: `Assinante Teste 001`.
- `log_ativo`: deixe ativo.

Depois de salvar, o assinante aparece na tabela da esquerda. A coluna `Token` mostra o inicio do token mascarado.

Para chamar APIs, voce precisa do token completo. Se a tela nao estiver exibindo o token completo, consulte diretamente na base ou use os scripts de teste que carregam o token automaticamente pela `t00002`.

Consulta direta do token, se necessario:

```bash
PGPASSWORD='SENHA_DO_BANCO' psql \
  -h 157.173.110.195 \
  -p 5432 \
  -U postgres \
  -d dfe \
  -Atqc "SELECT c_token FROM public.t00002 WHERE c_identificador = 'teste_001' LIMIT 1"
```

Alternativa sem copiar token manualmente: informe o identificador do assinante e deixe os scripts buscarem o token:

```bash
ASSINANTE_IDENTIFICADOR='teste_001' bash testes_api_platform/async_multi_endpoint_flow.sh
```

Exemplo de uso do token em chamada HTTP:

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/nfe/ferramentas/openssl-info' \
  -H 'X-Api-Token: SEU_TOKEN_COMPLETO'
```

Use sempre `X-Api-Token`. Neste ambiente Apache, esse header e mais confiavel que `Authorization: Bearer`.

## 4. Configurar capacidade de workers

Acesse:

```text
/capacidade-workers
```

Essa tela alimenta a `t99005`. Ela e uma referencia administrativa para indicar quantos workers devem estar ativos em determinado periodo. A tela nao inicia processos automaticamente; ela registra a capacidade planejada para operacao e testes.

Para um primeiro teste, cadastre:

- Quantidade de workers: `1` ou `2`.
- Inicio da vigencia: data/hora atual.
- Fim da vigencia: deixe vazio para valer por tempo indeterminado.
- Observacao: exemplo `Teste inicial de fluxo async`.
- Faixa ativa: marcado.

Depois valide se o badge "Atual" mostra a quantidade configurada.

## 5. Configurar modo sync ou async por API

Acesse:

```text
/configuracao-execucao
```

Essa tela alimenta a `t99003`. Ela define se uma rota deve executar em modo `sync` ou `async`.

Para forcar um endpoint em async, cadastre uma regra assim:

```text
Chave de configuracao: teste_nfe_openssl_async
Caminho: /nfe/ferramentas/openssl-info
Operacao: deixe vazio
Modo: async
Regra ativa: marcado
```

Para voltar o mesmo endpoint para sincrono, altere a regra para:

```text
Modo: sync
```

Regras de precedencia usadas pelo sistema:

1. Se a chamada esta sendo feita pelo worker interno, sempre roda `sync` para evitar reenfileiramento infinito.
2. Se existir vinculo ativo em `/webhooks` para o assinante/API, o modo do vinculo tem prioridade.
3. Se nao houver vinculo aplicavel, vale a regra da `t99003`.
4. Se nao houver regra, vale o modo definido na operacao.
5. Se nada estiver definido, o padrao e `sync`.

## 6. Criar webhook

Acesse:

```text
/webhooks
```

No formulario "Novo webhook", cadastre um endpoint que vai receber a notificacao.

Campos sugeridos para teste:

```text
Nome: Webhook Teste
URL: https://webhook.site/SEU_ID_DE_TESTE
Metodo HTTP: POST
Headers extras em JSON: {"X-Teste":"api-platform"}
Secret: opcional, pode usar o botao Gerar secret
Timeout: 10
Webhook ativo: marcado
```

Se voce nao tiver uma URL publica para receber webhook, use a tela para cadastrar, mas a entrega pode falhar. Mesmo falhando, isso ainda serve para testar a fila e o historico em `t99006`.

## 7. Criar vinculo entre assinante, webhook e API

Na mesma tela:

```text
/webhooks
```

Use o formulario "Novo vinculo".

Exemplo para testar NFe OpenSSL:

```text
Assinante: selecione o assinante criado
Webhook: selecione o webhook criado
Programa: nfe
Evento: request.completed
Caminho opcional: /nfe/ferramentas/openssl-info
Modo de execucao no vinculo: async
Vinculo ativo: marcado
```

O campo "Modo de execucao no vinculo" e a forma mais direta de trocar entre `sync` e `async` para aquele assinante/API. Como o vinculo tem prioridade sobre a `t99003`, ele e ideal para testes por cliente sem afetar outros assinantes.

Eventos disponiveis:

- `request.completed`: agenda webhook quando a requisicao termina com sucesso.
- `request.failed`: agenda webhook quando a requisicao termina com falha.

Para testar falha, crie outro vinculo com evento `request.failed` ou altere o existente temporariamente.

## 8. Chamar endpoints pela API Platform

Acesse uma documentacao:

```text
/docs/nfe
/docs/nfse
/docs/cep
```

A rota de consulta por request tambem aparece nas tres paginas:

```text
GET /requests/{requestId}
```

Para executar uma chamada:

1. Abra o endpoint desejado.
2. Clique em "Try it out".
3. Informe `X-Api-Token` com o token completo do assinante.
4. Preencha os parametros/payload.
5. Execute.

Se o endpoint estiver em `sync`, a API retorna o resultado direto e tambem grava auditoria.

Se o endpoint estiver em `async`, a API deve retornar HTTP `202` com um `request_id`.

Exemplo de resposta esperada no modo async:

```json
{
  "mensagem": "Requisicao aceita para processamento assincrono.",
  "request_id": "9f0d3656-33bb-4ef5-9a36-9bb174d4fd93"
}
```

Guarde esse `request_id`. Ele e a chave para consultar o retorno depois.

## 9. Teste rapido por curl

Exemplo com NFe OpenSSL, usando o token do assinante:

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/nfe/ferramentas/openssl-info' \
  -H 'X-Api-Token: SEU_TOKEN_COMPLETO' \
  -H 'Accept: application/ld+json'
```

Exemplo de consulta do resultado:

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/requests/REQUEST_ID_AQUI' \
  -H 'X-Api-Token: SEU_TOKEN_COMPLETO' \
  -H 'Accept: application/json'
```

Importante: o token usado em `/requests/{requestId}` precisa ser o mesmo token do assinante que criou a requisicao. Se usar outro token, o sistema retorna `404` ou `401`.

## 10. Processar fila de requisicoes async

Quando uma chamada retorna `202`, ela foi gravada na `t99001` e ficou pendente para worker.

Para processar um ciclo manual:

```bash
php bin/console app:api-request-worker --once --limit=10
```

Para deixar rodando continuamente:

```bash
php bin/console app:api-request-worker --limit=10 --sleep=2
```

Para simular mais de um worker, abra mais de um terminal e rode o mesmo comando continuo, ou execute processos separados:

```bash
php bin/console app:api-request-worker --limit=10 --sleep=2
```

```bash
php bin/console app:api-request-worker --limit=10 --sleep=2
```

Depois do worker processar:

- `t99001` recebe status final, HTTP e corpo de resposta.
- `t99002` recebe a tentativa do worker.
- `t99004` recebe eventos operacionais.
- Se houver vinculo de webhook aplicavel, `t99006` recebe entrega pendente.

## 11. Consultar retorno por request_id

Depois de rodar o worker, consulte:

```text
GET /requests/{requestId}
```

Pela API Platform:

1. Abra `/docs/nfe`, `/docs/nfse` ou `/docs/cep`.
2. Procure `GET /requests/{requestId}`.
3. Clique em "Try it out".
4. Informe o `requestId`.
5. Informe `X-Api-Token` com o token do mesmo assinante.
6. Execute.

Por curl:

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/requests/9f0d3656-33bb-4ef5-9a36-9bb174d4fd93' \
  -H 'X-Api-Token: SEU_TOKEN_COMPLETO' \
  -H 'Accept: application/json'
```

Campos principais da resposta:

- `u_c_request_id`: request_id consultado.
- `c_caminho`: endpoint original chamado.
- `c_nome_operacao`: operacao API Platform.
- `c_modo_execucao`: `sync` ou `async`.
- `si_status_processamento`: status operacional.
- `si_status_http`: HTTP retornado pelo endpoint processado.
- `t_corpo_resposta`: corpo retornado pelo endpoint processado.
- `t_erro`: erro capturado, se houver.
- `i_tempo_processamento_ms`: tempo de processamento.

Status de processamento:

- `0`: recebida.
- `1`: enfileirada.
- `2`: processando.
- `3`: concluida.
- `4`: falha.
- `5`: nao autorizada.

## 12. Processar entregas de webhook

Se a requisicao finalizada combinar com algum vinculo ativo, o sistema cria entrega pendente na `t99006`.

Para enviar as entregas pendentes:

```bash
php bin/console app:webhook-delivery-worker --once --limit=20
```

Para deixar rodando continuamente:

```bash
php bin/console app:webhook-delivery-worker --limit=20 --sleep=2
```

Depois valide em:

```text
/webhooks
/monitor-workers
/consulta-requisicoes
```

Na tela `/webhooks`, veja a secao "Entregas recentes em `t99006`".

Na tela `/monitor-workers`, veja requisicoes recentes, filas e entregas de webhook.

Na tela `/consulta-requisicoes`, pesquise pelo `request_id` para ver request, response, eventos e rastreabilidade.

## 13. Fluxo completo recomendado para primeiro teste

Use este fluxo para validar tudo de ponta a ponta:

1. Em `/assinantes`, crie `teste_001`.
2. Copie o token completo do assinante.
3. Em `/capacidade-workers`, cadastre `2` workers ativos a partir de agora.
4. Em `/webhooks`, cadastre um webhook com URL de teste.
5. Em `/webhooks`, crie vinculo:

```text
Assinante: teste_001
Webhook: Webhook Teste
Programa: nfe
Evento: request.completed
Caminho opcional: /nfe/ferramentas/openssl-info
Modo de execucao no vinculo: async
Ativo: sim
```

6. Em `/docs/nfe`, execute:

```text
GET /nfe/ferramentas/openssl-info
```

7. Informe header:

```text
X-Api-Token: SEU_TOKEN_COMPLETO
```

8. Confirme que retornou `202` e copie o `request_id`.
9. Rode:

```bash
php bin/console app:api-request-worker --once --limit=10
```

10. Em `/docs/nfe`, execute:

```text
GET /requests/{requestId}
```

11. Informe o mesmo token e o `request_id`.
12. Confirme que `si_status_processamento` saiu de `1/2` e virou `3` ou `4`.
13. Rode:

```bash
php bin/console app:webhook-delivery-worker --once --limit=20
```

14. Valide `/webhooks`, `/monitor-workers` e `/consulta-requisicoes`.

## 14. Como testar varios endpoints

Existe um script pronto para forcar varios endpoints em async, disparar chamadas, processar worker e consultar cada retorno por `request_id`:

```bash
API_TOKEN='SEU_TOKEN_COMPLETO' bash testes_api_platform/async_multi_endpoint_flow.sh
```

Se quiser usar a base publica:

```bash
BASE_URL='http://157.173.110.195:8089/index.php' API_TOKEN='SEU_TOKEN_COMPLETO' bash testes_api_platform/async_multi_endpoint_flow.sh
```

Esse script:

- cria regras temporarias em `t99003`;
- chama varios endpoints NFe;
- captura os `request_id`;
- roda `app:api-request-worker`;
- consulta `/requests/{requestId}`;
- imprime o status final de cada chamada.

Para validar as telas administrativas com dados semiprontos:

```bash
bash testes_api_platform/admin_pages_seed_and_check.sh
```

Esse script alimenta tabelas operacionais e valida as telas:

- `/configuracao-execucao`
- `/capacidade-workers`
- `/assinantes`
- `/webhooks`
- `/monitor-workers`
- `/requests/{requestId}`

## 15. Como saber se o teste funcionou

O fluxo esta correto quando:

- O assinante esta ativo em `/assinantes`.
- A chamada de API com `X-Api-Token` nao retorna `401`.
- Em modo `async`, a primeira resposta retorna HTTP `202` e `request_id`.
- Depois do `app:api-request-worker`, `/requests/{requestId}` retorna HTTP `200`.
- O campo `si_status_processamento` fica `3` para concluido ou `4` para falha processada.
- Se houver webhook vinculado, aparece registro em `t99006` na tela `/webhooks`.
- Depois do `app:webhook-delivery-worker`, a entrega muda para sucesso ou falha final/tentativa.
- Em `/consulta-requisicoes`, o `request_id` mostra request, response e eventos.

## 16. Problemas comuns

Se `/requests/{requestId}` retornar `401`:

- O header `X-Api-Token` nao foi enviado ou esta incorreto.

Se `/requests/{requestId}` retornar `404`:

- O `request_id` nao existe.
- O token usado nao pertence ao assinante que criou a requisicao.

Se a chamada nao retornar `202`:

- O endpoint esta em `sync`.
- Verifique o vinculo em `/webhooks`.
- Verifique a regra em `/configuracao-execucao`.
- Lembre que o vinculo do webhook tem prioridade sobre a `t99003`.

Se o webhook nao aparece em `t99006`:

- Verifique se o vinculo esta ativo.
- Verifique se o evento escolhido combina com o resultado: `request.completed` para sucesso ou `request.failed` para falha.
- Verifique se o programa/caminho do vinculo combina com o endpoint chamado.
- Rode o worker de requisicoes antes, porque o webhook so e agendado quando a requisicao finaliza.

Se a entrega do webhook falhar:

- Confirme se a URL do webhook e acessivel pelo servidor.
- Confirme metodo HTTP, headers e timeout.
- Veja o erro na tela `/webhooks` ou em `/monitor-workers`.

## 17. Referencia rapida de comandos

Processar requisicoes async uma vez:

```bash
php bin/console app:api-request-worker --once --limit=10
```

Processar webhooks uma vez:

```bash
php bin/console app:webhook-delivery-worker --once --limit=20
```

Rodar fluxo automatico de multiplos endpoints:

```bash
API_TOKEN='SEU_TOKEN_COMPLETO' bash testes_api_platform/async_multi_endpoint_flow.sh
```

Rodar seed e validacao das telas:

```bash
bash testes_api_platform/admin_pages_seed_and_check.sh
```

Consultar um request manualmente:

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/requests/REQUEST_ID_AQUI' \
  -H 'X-Api-Token: SEU_TOKEN_COMPLETO' \
  -H 'Accept: application/json'
```
