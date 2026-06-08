# Benchmark de Workers

Script operacional:

- `bash testes_api_platform/worker_benchmark.sh`

Objetivo:

- forcar um endpoint seguro para `async`
- disparar `1000` requisicoes autenticadas
- comparar o processamento com `2`, `3`, `4` e `5` workers

Variaveis mais uteis:

- `TOTAL_REQUESTS=1000`
- `WORKER_COUNTS="2 3 4 5"`
- `REQUEST_CONCURRENCY=30`
- `TEST_PATH=/nfe/ferramentas/openssl-info`
- `API_TOKEN=...`
- `ASSINANTE_IDENTIFICADOR=002`

Exemplo:

```bash
WORKER_COUNTS="2 3 4 5" TOTAL_REQUESTS=1000 bash testes_api_platform/worker_benchmark.sh
```

Saida esperada por linha:

```text
workers=2 queued_seconds=... processing_seconds=... throughput_req_s=... avg_ms=... max_ms=... p95_ms=... failed=...
```

Leitura recomendada:

- `queued_seconds`: tempo para aceitar e enfileirar as requisicoes
- `processing_seconds`: tempo para drenar a fila com aquela quantidade de workers
- `throughput_req_s`: vazao media da drenagem
- `avg_ms`, `max_ms`, `p95_ms`: latencias gravadas na `t99001`
- `failed`: total de falhas na amostra

Tabela para registrar o resultado real do ambiente:

| workers | queued_seconds | processing_seconds | throughput_req_s | avg_ms | p95_ms | max_ms | failed | observacao |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| 2 |  |  |  |  |  |  |  |  |
| 3 |  |  |  |  |  |  |  |  |
| 4 |  |  |  |  |  |  |  |  |
| 5 |  |  |  |  |  |  |  |  |

Interpretacao final:

- escolher o menor numero de workers que entregue vazao estavel sem crescimento relevante de falha ou de latencia `p95`
- se `throughput` quase nao crescer de um passo para outro, o degrau seguinte tende a ter pior custo-beneficio
