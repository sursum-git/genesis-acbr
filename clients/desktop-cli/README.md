# ACBr API CLI Desktop

Cliente Windows em linha de comando para softwares desktop consumirem a API
central do projeto ACBr.

Uso principal:

```bat
acbr-api-cli.exe --arq-config C:\IntegracaoACBr\chamada.ini
```

O executavel nao roda Symfony, ACBr, certificados ou workers localmente. Ele
apenas le um arquivo `.ini`, chama a API central usando `X-Api-Token` e devolve
um JSON de resposta.

## Build

No Linux:

```bash
GOCACHE=/tmp/acbr-api-cli-gocache GOOS=windows GOARCH=amd64 go build -o dist/acbr-api-cli.exe
```

Build local para testes:

```bash
GOCACHE=/tmp/acbr-api-cli-gocache go build -o dist/acbr-api-cli
```

## Formato do INI

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=tok_xxx
TimeoutSeconds=60

[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj

[Arquivos]
Entrada=
Saida=C:\IntegracaoACBr\resposta.json
```

Se `Saida` estiver vazio, o JSON e impresso no `stdout`.

Flags tecnicas podem sobrescrever o arquivo:

```bat
acbr-api-cli.exe --arq-config chamada.ini --base-url http://host/index.php --token tok_xxx --timeout 30
```

## Exemplos

### Consulta cadastro

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=tok_xxx

[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj

[Arquivos]
Saida=C:\IntegracaoACBr\consulta-cadastro-resposta.json
```

### Envio assincrono XML

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=tok_xxx
TimeoutSeconds=120

[Requisicao]
Modulo=nfe
Operacao=enviar-assincrono-xml

[Parametros]
ALote=1

[Arquivos]
Entrada=C:\Notas\lote.xml
Saida=C:\Notas\envio-resposta.json
```

Quando a API retornar HTTP `202`, o JSON tera `request_id`. Consulte depois
com o exemplo abaixo.

### Consultar request_id

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=tok_xxx

[Requisicao]
Modulo=request
Operacao=get

[Parametros]
RequestId=req-123

[Arquivos]
Saida=C:\Notas\request-resposta.json
```

## Operacoes v1

- `nfe/status-servico`
- `nfe/consulta-cadastro`
- `nfe/consultar-chave`
- `nfe/distribuicao-chave`
- `nfe/distribuicao-nsu`
- `nfe/distribuicao-ult-nsu`
- `nfe/enviar-sincrono-xml`
- `nfe/enviar-assincrono-xml`
- `nfe/enviar-sincrono-ini`
- `nfe/enviar-assincrono-ini`
- `nfe/validar-regras`
- `nfe/imprimir-pdf`
- `nfe/inutilizar`
- `request/get`

## Saida JSON

Sucesso sincrono:

```json
{
  "ok": true,
  "status_code": 200,
  "data": {}
}
```

Aceite assincrono:

```json
{
  "ok": true,
  "status_code": 202,
  "request_id": "req-123",
  "data": {
    "request_id": "req-123"
  }
}
```

Erro:

```json
{
  "ok": false,
  "status_code": 401,
  "error": "token invalido",
  "data": {
    "mensagem": "token invalido"
  }
}
```

## Exit codes

- `0`: chamada aceita ou concluida.
- `1`: erro de configuracao ou uso.
- `2`: erro HTTP/API.
- `3`: erro de rede ou timeout.
- `4`: erro de leitura ou gravacao de arquivo.
