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
- Endpoints `POST` de CEP: campos diretos no JSON, sem wrapper `payload`.
- Header recomendado para `POST`: `Content-Type: application/ld+json`

Arquivos desta pasta:

- [nfe.http](/dados_containers/www/testes_api_platform/nfe.http)
- [cep.http](/dados_containers/www/testes_api_platform/cep.http)
- [nfse.http](/dados_containers/www/testes_api_platform/nfse.http)
- [nfe.sh](/dados_containers/www/testes_api_platform/nfe.sh)
- [cep.sh](/dados_containers/www/testes_api_platform/cep.sh)
- [nfse.sh](/dados_containers/www/testes_api_platform/nfse.sh)
- [_common.sh](/dados_containers/www/testes_api_platform/_common.sh)
- [payloads.md](/dados_containers/www/testes_api_platform/payloads.md)

Scripts shell:

- `bash testes_api_platform/nfe.sh`
- `bash testes_api_platform/cep.sh`
- `bash testes_api_platform/nfse.sh`

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
