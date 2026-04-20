# Referencia de Payloads

## Regra geral

### Endpoints GET

- Nao usam `payload`.
- Os valores vao na query string.

Exemplo:

```http
GET /index.php/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj
```

### Endpoints POST legados de NFe e NFSe

- O corpo deve ser um objeto JSON.
- O objeto principal deve ter a chave `payload`.
- `payload` deve ser um objeto JSON, nunca array.
- As chaves dentro de `payload` sao repassadas para o metodo legado ACBr.

Formato:

```json
{
  "payload": {
    "Campo1": "valor",
    "Campo2": "valor"
  }
}
```

### Endpoints POST de CEP

- Nao usam a chave `payload`.
- Os campos vao direto na raiz do JSON.

Formato:

```json
{
  "campo1": "valor",
  "campo2": "valor"
}
```

## NFe

### `GET /nfe/consultas/status-servico`

- Sem payload.

### `GET /nfe/consultas/consulta-cadastro`

Parametros de query:

- `AcUF`: UF com 2 letras, ex. `ES`
- `AnDocumento`: CPF/CNPJ ou IE
- `TipoDocumento`: `cpf_cnpj` ou `inscricao_estadual`

Exemplos:

```text
AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj
AcUF=ES&AnDocumento=06013812000158&TipoDocumento=inscricao_estadual
```

### `GET /nfe/consultas/consultar-com-chave`

Parametros de query:

- `eChaveOuNFe`: chave da NFe com 44 digitos

Exemplo:

```text
eChaveOuNFe=32260406013812000158550030001955901308939122
```

Observacao:

- Este endpoint recebe automaticamente `AExtrairEventos=1` pela configuracao do resource.

### `POST /nfe/consultas/consultar-com-chave-xml`

- O corpo deve conter o XML completo da NF-e ou do `nfeProc`.
- O sistema extrai a chave de acesso do `chNFe` ou do `infNFe/@Id`.
- Header recomendado: `Content-Type: application/xml`
- No API Platform, o exemplo desse endpoint deve refletir um `nfeProc` completo.

Exemplo:

```xml
<?xml version="1.0"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe>
    <infNFe Id="NFe32260406013812000158550030001955901308939122" versao="4.00" />
  </NFe>
  <protNFe versao="4.00">
    <infProt>
      <chNFe>32260406013812000158550030001955901308939122</chNFe>
    </infProt>
  </protNFe>
</nfeProc>
```

### `GET /nfe/consultas/consultar-recibo`

Parametros de query:

- `ARecibo`: numero do recibo

Exemplo:

```text
ARecibo=SUBSTITUIR_RECIBO
```

### `GET /nfe/distribuicao-dfe/por-chave`

Usa query string, sem `payload`.

Parâmetros:

- `AcUFAutor`: UF do ator autor
- `AeCNPJCPF`: CNPJ ou CPF do ator
- `AechNFe`: chave da NFe

Exemplo:

```text
/nfe/distribuicao-dfe/por-chave?AcUFAutor=ES&AeCNPJCPF=06013812000158&AechNFe=32260406013812000158550030001955901308939122
```

### `GET /nfe/distribuicao-dfe/por-nsu`

Usa query string, sem `payload`.

Parâmetros:

- `AcUFAutor`
- `AeCNPJCPF`
- `AeNSU`

Exemplo:

```text
/nfe/distribuicao-dfe/por-nsu?AcUFAutor=ES&AeCNPJCPF=06013812000158&AeNSU=000000000000001
```

### `GET /nfe/distribuicao-dfe/por-ult-nsu`

Usa query string, sem `payload`.

Parâmetros:

- `AcUFAutor`
- `AeCNPJCPF`
- `AeultNSU`

Exemplo:

```text
/nfe/distribuicao-dfe/por-ult-nsu?AcUFAutor=ES&AeCNPJCPF=06013812000158&AeultNSU=000000000000000
```

### `GET /nfe/ferramentas/openssl-info`

- Sem payload.

### `GET /nfe/ferramentas/obter-certificados`

- Sem payload.

### Outros POST de NFe

Os demais endpoints de NFe tambem seguem o padrao:

```json
{
  "payload": {
    "CampoDoMetodoLegado": "valor"
  }
}
```

Arquivos de exemplo:

- [nfe.http](/dados_containers/www/testes_api_platform/nfe.http)
- [nfe.sh](/dados_containers/www/testes_api_platform/nfe.sh)

## NFSe

### `GET /nfse/ferramentas/openssl-info`

- Sem payload.

### `GET /nfse/ferramentas/obter-certificados`

- Sem payload.

### `POST /nfse/padrao-nacional/consultar-dps-por-chave`

Campos em `payload`:

- `AChaveDFe`

Payload:

```json
{
  "payload": {
    "AChaveDFe": "SUBSTITUIR_CHAVE_DPS"
  }
}
```

### `POST /nfse/padrao-nacional/consultar-nfse-por-chave`

Campos em `payload`:

- `AChaveDFe`

Payload:

```json
{
  "payload": {
    "AChaveDFe": "SUBSTITUIR_CHAVE_NFSE"
  }
}
```

### `POST /nfse/demais-provedores/consultas/consultar-situacao`

Campos comuns em `payload`:

- `APrestadorCNPJ`
- `AProtocolo`

Payload:

```json
{
  "payload": {
    "APrestadorCNPJ": "06013812000158",
    "AProtocolo": "SUBSTITUIR_PROTOCOLO"
  }
}
```

### `POST /nfse/demais-provedores/consultas/consultar-nfse-por-periodo`

Campos comuns em `payload`:

- `APrestadorCNPJ`
- `ADataInicial`
- `ADataFinal`

Payload:

```json
{
  "payload": {
    "APrestadorCNPJ": "06013812000158",
    "ADataInicial": "2026-04-01",
    "ADataFinal": "2026-04-30"
  }
}
```

### Outros POST de NFSe

Os demais endpoints de NFSe tambem seguem o mesmo padrao:

```json
{
  "payload": {
    "CampoDoMetodoLegado": "valor"
  }
}
```

Como os provedores variam, os nomes dos campos mudam conforme o metodo legado do ACBr.
Para exemplos prontos:

- [nfse.http](/dados_containers/www/testes_api_platform/nfse.http)
- [nfse.sh](/dados_containers/www/testes_api_platform/nfse.sh)

## CEP

### `POST /acbr-cep/consulta-cep`

Campos na raiz do JSON:

- `cep`
- `webservice`

Payload:

```json
{
  "cep": "29103091",
  "webservice": "0"
}
```

### `POST /acbr-cep/consulta-logradouro`

Campos na raiz do JSON:

- `cidade`
- `tipo`
- `logradouro`
- `uf`
- `bairro`
- `webservice`

Payload:

```json
{
  "cidade": "Vila Velha",
  "tipo": "ROD",
  "logradouro": "Darly Santos",
  "uf": "ES",
  "bairro": "Aracas",
  "webservice": "0"
}
```

Arquivos de exemplo:

- [cep.http](/dados_containers/www/testes_api_platform/cep.http)
- [cep.sh](/dados_containers/www/testes_api_platform/cep.sh)
