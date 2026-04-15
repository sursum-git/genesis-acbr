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
  'http://157.173.110.195:8089/index.php/nfe/consultas/consultar-com-chave' \
  -H 'Content-Type: application/ld+json' \
  -d '{"payload":{"eChaveOuNFe":"32260406013812000158550030001955901308939122"}}'
```

```bash
curl -sS -X POST \
  'http://157.173.110.195:8089/index.php/nfe/distribuicao-dfe/por-chave' \
  -H 'Content-Type: application/ld+json' \
  -d '{"payload":{"AcUFAutor":"ES","AeCNPJCPF":"06013812000158","AechNFe":"32260406013812000158550030001955901308939122"}}'
```
