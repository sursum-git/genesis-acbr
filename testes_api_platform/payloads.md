# Referencia de Payloads

## Regra geral

## NFSe Sao Paulo

- Para a cidade de Sao Paulo/SP, o provedor do ACBr deve estar configurado como `ISSSaoPaulo`.
- Essa selecao nao vai dentro do XML ou do INI de envio; ela fica na configuracao do componente.
- Configuracao esperada no ACBr:
  - `CodigoMunicipio=3550308`
  - `LayoutNFSe=0`
  - `PathSchemas=/var/www/html/Schemas/NFSe/`
- Em Sao Paulo, o XML bruto precisa seguir o layout proprio do provedor `ISSSaoPaulo`, que e diferente do XML ABRASF generico.
- Para testes iniciais de transmissao, prefira enviar INI bruto para o ACBr quando ainda nao houver XML assinado no layout especifico do provedor.

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

### `POST /nfe/envio/imprimir-pdf`

- O corpo deve conter o XML completo da NF-e.
- Nao usa `payload`.
- Header recomendado: `Content-Type: application/xml`
- A ACBr salva o PDF no diretório já configurado anteriormente.

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

### `GET /nfe/envio/gerar-chave`

Usa query string, sem `payload`.

Parâmetros:

- `ACodigoUF`: codigo da UF emitente
- `ACodigoNumerico`: codigo numerico da chave
- `AModelo`: modelo do documento fiscal
- `ASerie`: serie da NF-e
- `ANumero`: numero da NF-e
- `ATpEmi`: tipo de emissao
- `AEmissao`: data de emissao no formato `YYYY-MM-DD`
- `ACNPJCPF`: CNPJ ou CPF do emitente

Exemplo:

```text
/nfe/envio/gerar-chave?ACodigoUF=32&ACodigoNumerico=13089391&AModelo=55&ASerie=3&ANumero=195590&ATpEmi=1&AEmissao=2026-04-15&ACNPJCPF=06013812000158
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

### `POST /nfe/inutilizacao/imprimir-pdf`

- O corpo deve conter o XML completo da inutilizacao.
- Nao usa `payload`.
- Header recomendado: `Content-Type: application/xml`

Exemplo:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<procInutNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <inutNFe versao="4.00">
    <infInut Id="ID3526060601381200015855003000196318196318">
      <tpAmb>2</tpAmb>
      <xServ>INUTILIZAR</xServ>
      <cUF>32</cUF>
      <ano>26</ano>
      <CNPJ>06013812000158</CNPJ>
      <mod>55</mod>
      <serie>3</serie>
      <nNFIni>196318</nNFIni>
      <nNFFin>196318</nNFFin>
      <xJust>Inutilizacao de numeracao nao utilizada</xJust>
    </infInut>
  </inutNFe>
</procInutNFe>
```

### `GET /nfe/inutilizacao/inutilizar`

Usa query string, sem `payload`.

Parâmetros:

- `ACNPJ`
- `AJustificativa`
- `AAno`
- `AModelo`
- `ASerie`
- `ANumeroInicial`
- `ANumeroFinal`

Exemplo:

```text
/nfe/inutilizacao/inutilizar?ACNPJ=06013812000158&AJustificativa=Inutilizacao%20de%20numeracao%20nao%20utilizada&AAno=26&AModelo=55&ASerie=3&ANumeroInicial=195591&ANumeroFinal=195600
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

### `GET /nfse/padrao-nacional/consultar-dps-por-chave`

Parametros de query:

- `AChaveDPS`

Exemplo:

```text
AChaveDPS=SUBSTITUIR_CHAVE_DPS
```

### `GET /nfse/padrao-nacional/consultar-nfse-por-chave`

Parametros de query:

- `AChaveNFSe`

Exemplo:

```text
AChaveNFSe=SUBSTITUIR_CHAVE_NFSE
```

### `GET /nfse/demais-provedores/consultas/consultar-situacao`

Parametros de query:

- `AProtocolo`
- `ANumLote`

Exemplo:

```text
AProtocolo=SUBSTITUIR_PROTOCOLO&ANumLote=1
```

### `GET /nfse/demais-provedores/consultas/consultar-nfse-por-periodo`

Parametros de query:

- `ADataInicial`
- `ADataFinal`
- `APagina`
- `ATipoPeriodo`

Exemplo:

```text
ADataInicial=01/04/2026&ADataFinal=30/04/2026&APagina=1&ATipoPeriodo=1
```

### `GET /nfse/demais-provedores/consultas/consultar-nfse-por-faixa`

Parametros de query:

- `ANumeroInicial`
- `ANumeroFinal`
- `APagina`

Exemplo:

```text
ANumeroInicial=1000&ANumeroFinal=1010&APagina=1
```

### `POST /nfse/padrao-nacional/enviar-evento`

- O corpo deve conter o conteudo completo do arquivo de evento.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`

Exemplo:

```ini
[Evento]
TipoEvento=110111
Chave=SUBSTITUIR_CHAVE_EVENTO
```

### `POST /nfse/demais-provedores/cancelamento/cancelar`

- O corpo deve conter o conteudo completo do arquivo de cancelamento.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`

Exemplo:

```ini
[CancelamentoNFSe]
Numero=12345
CodigoCancelamento=1
```

### `POST /nfse/demais-provedores/consultas/consultar-nfse-generico`

- O corpo deve conter o conteudo completo do arquivo INI de consulta.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`

Exemplo:

```ini
[ConsultaNFSe]
Numero=12345
```

### `POST /nfse/demais-provedores/consultas/consultar-link-nfse`

- O corpo deve conter o conteudo completo do arquivo INI da consulta de link.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`

Exemplo:

```ini
[ConsultaLinkNFSe]
Numero=12345
```

### `POST /nfse/demais-provedores/envio/emitir-nota`

- O corpo deve conter o conteudo completo do XML ou INI da NFSe.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`
- `ALote` deve ir na query string.

Exemplo:

```text
ALote=1
```

### `POST /nfse/demais-provedores/envio/enviar-lote-rps-assincrono`

- O corpo deve conter o conteudo completo do XML ou INI do lote.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`
- `ALote` deve ir na query string.

Exemplo:

```text
ALote=1
```

### `POST /nfse/demais-provedores/envio/enviar-lote-rps-sincrono`

- O corpo deve conter o conteudo completo do XML ou INI do lote.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`
- `ALote` deve ir na query string.

### `POST /nfse/demais-provedores/envio/enviar-um-rps`

- O corpo deve conter o conteudo completo do XML ou INI do RPS.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`
- `ALote` deve ir na query string.

### `GET /nfse/demais-provedores/envio/link-nfse`

Parametros de query:

- `ANumeroNFSe`
- `ACodigoVerificacao`
- `AChaveAcesso`
- `AValorServico`

Exemplo:

```text
ANumeroNFSe=12345&ACodigoVerificacao=ABC12345&AValorServico=150.00
```

### `GET /nfse/demais-provedores/envio/gerar-token`

- Sem payload.

### `POST /nfse/demais-provedores/envio/salvar-pdf`

- O corpo deve conter o conteudo completo do XML ou INI da NFSe.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`

### `POST /nfse/demais-provedores/envio/imprimir-pdf`

- O corpo deve conter o conteudo completo do XML ou INI da NFSe.
- Nao usa `payload`.
- Header recomendado: `Content-Type: text/plain`

### `GET /nfse/demais-provedores/servicos-prestados/por-periodo`

Parametros de query:

- `ADataInicial`
- `ADataFinal`
- `APagina`
- `ATipoPeriodo`

Exemplo:

```text
ADataInicial=01/04/2026&ADataFinal=30/04/2026&APagina=1&ATipoPeriodo=1
```

### `GET /nfse/demais-provedores/servicos-tomados/por-numero`

Parametros de query:

- `ANumero`
- `APagina`
- `ADataInicial`
- `ADataFinal`
- `ATipoPeriodo`

Exemplo:

```text
ANumero=12345&APagina=1&ADataInicial=01/04/2026&ADataFinal=30/04/2026&ATipoPeriodo=1
```

### Outros POST de NFSe

Os endpoints que enviam XML/INI ou estruturas mais ricas continuam usando `payload`:

```json
{
  "payload": {
    "CampoDoMetodoLegado": "valor"
  }
}
```

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
