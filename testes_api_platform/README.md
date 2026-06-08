# Testes API Platform

Base sugerida:

- `http://157.173.110.195:8089/index.php`

Dados de teste usados nos exemplos:

- `CNPJ`: `06013812000158`
- `UF`: `ES`
- `Inscricao Estadual`: `06013812000158`
- `Chave NFe`: `32260406013812000158550030001955901308939122`

Padroes de chamada:

- Endpoints `GET`: parametros em query string.
- Endpoints legados `POST` de NFe/NFSe: payload no formato `{"payload": {...}}`.
- Excecao em NFe consultas: `POST /nfe/consultas/consultar-com-chave-xml` recebe XML bruto no corpo.
- Esse endpoint deve usar o XML completo da NF-e, nao um XML resumido.
- `POST /nfe/envio/enviar-assincrono-xml` aceita 1 XML completo ou multiplos XMLs concatenados no mesmo corpo.
- Quando voce envia apenas 1 XML nesse endpoint, a API faz fallback automatico para envio sincrono para evitar a rejeicao `452`.
- Endpoints `POST` de CEP: campos diretos no JSON, sem wrapper `payload`.
- Header recomendado para `POST`: `Content-Type: application/ld+json`

Arquivos desta pasta:

- [nfe.http](/dados_containers/www/testes_api_platform/nfe.http)
- [cep.http](/dados_containers/www/testes_api_platform/cep.http)
- [nfse.http](/dados_containers/www/testes_api_platform/nfse.http)
- [nfe.sh](/dados_containers/www/testes_api_platform/nfe.sh)
- [nfe_diagnostico.sh](/dados_containers/www/testes_api_platform/nfe_diagnostico.sh)
- [nfe_rede_externa.sh](/dados_containers/www/testes_api_platform/nfe_rede_externa.sh)
- [cep.sh](/dados_containers/www/testes_api_platform/cep.sh)
- [nfse.sh](/dados_containers/www/testes_api_platform/nfse.sh)
- [auditoria_requisicoes.sh](/dados_containers/www/testes_api_platform/auditoria_requisicoes.sh)
- [admin_pages_seed_and_check.sh](/dados_containers/www/testes_api_platform/admin_pages_seed_and_check.sh)
- [async_multi_endpoint_flow.sh](/dados_containers/www/testes_api_platform/async_multi_endpoint_flow.sh)
- [_common.sh](/dados_containers/www/testes_api_platform/_common.sh)
- [payloads.md](/dados_containers/www/testes_api_platform/payloads.md)

Scripts shell:

- `bash testes_api_platform/nfe.sh`
- `bash testes_api_platform/nfe_diagnostico.sh`
- `bash testes_api_platform/nfe_rede_externa.sh`
- `bash testes_api_platform/cep.sh`
- `bash testes_api_platform/nfse.sh`
- `bash testes_api_platform/auditoria_requisicoes.sh`
- `bash testes_api_platform/admin_pages_seed_and_check.sh`
- `bash testes_api_platform/async_multi_endpoint_flow.sh`

Teste repetivel de auditoria/gravação no PostgreSQL:

- faz uma chamada síncrona autenticada e valida a gravação na `t99001`
- força um endpoint seguro para modo `async` via `t99003`
- valida retorno `202`
- roda `php bin/console app:api-request-worker --once --limit=1`
- confirma a gravação final em `t99001`, a tentativa em `t99002` e a leitura por `/requests/{requestId}`

Teste repetivel das telas administrativas:

- aplica `sql/dfe_schema.sql`
- alimenta `t99005`, `t99003`, `t00003`, `t00004`, `t99001`, `t99002`, `t99004` e `t99006`
- valida por HTTP as telas `/configuracao-execucao`, `/capacidade-workers`, `/assinantes`, `/webhooks`, `/monitor-workers` e uma consulta por `request_id`
- usa a marca `admin_pages_seed` para limpar e recriar somente os dados gerados pelo proprio teste

Teste de multiplos endpoints async:

- força um conjunto de endpoints para `async` via `t99003`
- dispara varias chamadas e captura o `request_id` retornado no `202`
- executa `app:api-request-worker`
- consulta cada resultado em `/requests/{requestId}` usando o mesmo `X-Api-Token`
- valida que o retorno final ficou disponivel por `request_id`

Variáveis úteis para repetir sem editar o arquivo:

```bash
API_TOKEN='SEU_TOKEN' bash testes_api_platform/auditoria_requisicoes.sh
```

```bash
ASSINANTE_IDENTIFICADOR='002' bash testes_api_platform/auditoria_requisicoes.sh
```

```bash
BASE_URL='http://127.0.0.1/index.php' AUDIT_DB_HOST='157.173.110.195' bash testes_api_platform/auditoria_requisicoes.sh
```

Os scripts agora tentam carregar automaticamente o token do assinante pela `t00002` usando:

- `ASSINANTE_IDENTIFICADOR` para localizar o assinante
- `AUDIT_DB_HOST`, `AUDIT_DB_PORT`, `AUDIT_DB_NAME`, `AUDIT_DB_USER`, `AUDIT_DB_PASSWORD`

Nos scripts shell, o token e enviado por `X-Api-Token`, porque esse ambiente Apache nao preserva `Authorization` de forma confiavel.

Diagnostico de NFe:

- `nfe_diagnostico.sh` roda um conjunto menor de chamadas e imprime tambem a versao do programa salva na `t99001`
- `nfe_rede_externa.sh` testa conectividade direta do servidor para os endpoints externos da SVRS usados por `status-servico` e `consulta-cadastro`

Voce pode sobrescrever variaveis sem editar os arquivos:

```bash
BASE_URL='http://127.0.0.1/index.php' bash testes_api_platform/nfe.sh
```

```bash
CNPJ='06013812000158' UF='ES' CHAVE_NFE='32260406013812000158550030001955901308939122' bash testes_api_platform/nfe.sh
```

Exemplos rapidos:

```bash
curl -sS -X GET \
  'http://157.173.110.195:8089/index.php/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj'
```

```bash
curl -sS -X POST \
  'http://157.173.110.195:8089/index.php/nfe/consultas/consultar-com-chave?eChaveOuNFe=32260406013812000158550030001955901308939122'
```

```bash
curl -sS -X POST \
  'http://157.173.110.195:8089/index.php/nfe/consultas/consultar-com-chave-xml' \
  -H 'Content-Type: application/xml' \
  --data-binary @testes_api_platform/fixtures/nfe_consulta_exemplo.xml
```

```bash
curl -sS -X POST \
  'http://157.173.110.195:8089/index.php/nfe/envio/enviar-assincrono-xml?ALote=1' \
  -H 'Content-Type: application/xml' \
  --data-binary @testes_api_platform/fixtures/nfe_consulta_exemplo.xml
```

```bash
curl -sS -X POST \
  'http://157.173.110.195:8089/index.php/nfe/envio/enviar-assincrono-xml?ALote=1' \
  -H 'Content-Type: application/xml' \
  --data-binary @testes_api_platform/fixtures/nfe_envio_assincrono_exemplo.xml
```

```bash
curl -sS -X GET \
  'http://157.173.110.195:8089/index.php/nfe/consultas/consultar-recibo?ARecibo=SUBSTITUIR_RECIBO'
```

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/nfe/distribuicao-dfe/por-chave?AcUFAutor=ES&AeCNPJCPF=06013812000158&AechNFe=32260406013812000158550030001955901308939122' \
  -H 'Accept: application/ld+json'
```

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/nfe/distribuicao-dfe/por-nsu?AcUFAutor=ES&AeCNPJCPF=06013812000158&AeNSU=000000000000001' \
  -H 'Accept: application/ld+json'
```

```bash
curl -sS \
  'http://157.173.110.195:8089/index.php/nfe/distribuicao-dfe/por-ult-nsu?AcUFAutor=ES&AeCNPJCPF=06013812000158&AeultNSU=000000000000000' \
  -H 'Accept: application/ld+json'
```
