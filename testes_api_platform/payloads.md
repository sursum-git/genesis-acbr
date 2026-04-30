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

### `POST /nfe/envio/enviar-sincrono-ini`

- O corpo deve conter o conteudo completo de um arquivo INI da NF-e.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`
- `ALote` pode ser enviado por query string e, se omitido, assume `1`.

Exemplo:

```ini
[NFE]
Versao=4.00

[Identificacao]
cUF=32
cNF=30893912
natOp=VENDA DE MERCADORIA ADQUIRIDA
mod=55
serie=3
nNF=195590
dhEmi=02/04/2026 12:39:51
dhSaiEnt=06/04/2026 12:40:02
tpNF=1
idDest=2
cMunFG=3205200
tpImp=1
tpEmis=1
cDV=2
tpAmb=2
finNFe=1
indFinal=0
indPres=9
procEmi=0
verProc=5.0
```

### `POST /nfe/envio/enviar-assincrono-ini`

- O corpo deve conter 1 INI completo ou varios INIs concatenados.
- Cada arquivo deve comecar com a secao `[NFE]`.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`
- `ALote` pode ser enviado por query string e, se omitido, assume `1`.
- Se vier apenas 1 INI, o endpoint faz fallback automatico para envio sincrono.

Exemplo com 2 INIs:

```ini
[NFE]
Versao=4.00

[Identificacao]
cUF=32
cNF=30893912
natOp=VENDA DE MERCADORIA ADQUIRIDA
mod=55
serie=3
nNF=195590
dhEmi=02/04/2026 12:39:51
dhSaiEnt=06/04/2026 12:40:02
tpNF=1
idDest=2
cMunFG=3205200
tpImp=1
tpEmis=1
cDV=2
tpAmb=2
finNFe=1
indFinal=0
indPres=9
procEmi=0
verProc=5.0

[NFE]
Versao=4.00

[Identificacao]
cUF=32
cNF=30893913
natOp=VENDA DE MERCADORIA ADQUIRIDA
mod=55
serie=3
nNF=195591
dhEmi=02/04/2026 12:39:52
dhSaiEnt=06/04/2026 12:40:03
tpNF=1
idDest=2
cMunFG=3205200
tpImp=1
tpEmis=1
cDV=3
tpAmb=2
finNFe=1
indFinal=0
indPres=9
procEmi=0
verProc=5.0
```

### `POST /nfe/envio/validar-regras-negocio`

- O corpo deve conter o XML completo da NF-e.
- Nao usa `payload`.
- Header recomendado: `Content-Type: application/xml`
- O endpoint apenas valida o XML informado.

Exemplo:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe xmlns="http://www.portalfiscal.inf.br/nfe">
    <infNFe Id="NFe32260406013812000158550030001955901308939122" versao="4.00">
      <ide>
        <cUF>32</cUF>
        <tpAmb>2</tpAmb>
      </ide>
    </infNFe>
  </NFe>
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

### `POST /nfe/envio/enviar-email`

Campos em `payload`:

- `AeArquivoXmlNFe`: caminho interno do container ou XML bruto completo
- `AePara`
- `AeChaveNFe`
- `AEnviaPDF`: `0` ou `1`
- `AeAssunto`
- `AeCC`
- `AeAnexos`
- `AeMensagem`

Payload:

```json
{
  "payload": {
    "AeArquivoXmlNFe": "/var/www/html/NFe/arqs/06013812000158/NFe/202604/NFe/32260406013812000158550030001955901308939122-nfe.xml",
    "AePara": "destinatario@exemplo.com",
    "AeChaveNFe": "32260406013812000158550030001955901308939122",
    "AEnviaPDF": 0,
    "AeAssunto": "Envio de NF-e",
    "AeCC": "",
    "AeAnexos": "",
    "AeMensagem": "Segue a NF-e em anexo."
  }
}
```

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
