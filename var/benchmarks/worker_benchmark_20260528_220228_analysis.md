# Benchmark de workers - 2026-05-28

Endpoint testado:

```text
/nfe/ferramentas/openssl-info
```

Carga por rodada:

```text
1000 requisicoes
```

Concorrencia de entrada:

```text
30 chamadas HTTP simultaneas
```

## Resultado

| Workers | Enfileiramento (s) | Processamento (s) | Vazao (req/s) | Falhas |
| ---: | ---: | ---: | ---: | ---: |
| 2 | 230 | 227 | 4.41 | 0 |
| 3 | 223 | 178 | 5.62 | 0 |
| 4 | 232 | 155 | 6.45 | 0 |
| 5 | 222 | 145 | 6.90 | 0 |
| 6 | 239 | 141 | 7.09 | 0 |
| 7 | 225 | 145 | 6.90 | 0 |
| 8 | 234 | 143 | 6.99 | 0 |
| 9 | 222 | 136 | 7.35 | 0 |
| 10 | 225 | 135 | 7.41 | 0 |

## Leitura

Melhor tempo absoluto:

```text
10 workers: 135 segundos para processar 1000 requisicoes.
```

Melhor custo-beneficio:

```text
5 workers.
```

Motivo:

```text
De 2 ate 5 workers existe ganho claro de tempo.
De 5 para 6 workers o processamento cai apenas de 145s para 141s, ganho de 4s.
De 6 para 10 workers o processamento cai de 141s para 135s, ganho de apenas 6s usando 4 workers a mais.
```

Conclusao operacional:

```text
Use 5 workers como ponto de custo-beneficio.
Use 6 workers se quiser uma pequena folga operacional com custo ainda aceitavel.
Use 10 workers apenas se a prioridade for menor tempo absoluto e o custo de processos extras nao importar.
```

Observacao:

```text
As colunas avg_ms/p95_ms/max_ms do TSV ficaram zeradas porque o worker atual finaliza as requisicoes async sem gravar i_tempo_processamento_ms. Para este benchmark, a comparacao confiavel e o tempo de parede medido em processing_seconds e throughput_req_s.
```

Arquivos gerados:

```text
/dados_containers/www/var/benchmarks/worker_benchmark_20260528_220228.log
/dados_containers/www/var/benchmarks/worker_benchmark_20260528_220228.tsv
/dados_containers/www/var/benchmarks/worker_benchmark_20260528_220228_analysis.md
```
