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

Para executar varios cenarios de uma vez:

```bat
acbr-api-cli.exe --suite-config windows-app\suites\nfe-cenarios.ini
```

## Build

No Linux:

```bash
GOCACHE=/tmp/acbr-api-cli-gocache GOOS=windows GOARCH=amd64 go build -o dist/acbr-api-cli.exe
```

Build local para testes:

```bash
GOCACHE=/tmp/acbr-api-cli-gocache go build -o dist/acbr-api-cli
```

Biblioteca compartilhada Linux:

```bash
GOCACHE=/tmp/acbr-api-cli-gocache go build -buildmode=c-shared -o dist/libacbr_api_cli.so
```

Esse comando gera:

```text
dist/libacbr_api_cli.so
dist/libacbr_api_cli.h
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

[Arquivos]
Entrada=parametros\consulta-cadastro-cpf.ini
Saida=C:\IntegracaoACBr\resposta.json
```

Para operacoes de consulta, `Entrada` aponta para um arquivo de parametros.
Para operacoes com corpo bruto, `Entrada` aponta para o arquivo XML ou INI que
sera enviado no body.

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

[Arquivos]
Entrada=parametros\consulta-cadastro-cpf.ini
Saida=C:\IntegracaoACBr\consulta-cadastro-resposta.json
```

Arquivo `parametros\consulta-cadastro-cpf.ini`:

```ini
[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj
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

## App Windows de cenarios

A pasta `windows-app` contem um app simples para teste em Windows:

```text
windows-app/
  rodar-cenarios.bat
  suites/nfe-cenarios.ini
  chamadas/*.ini
  parametros/*.ini
```

Fluxo:

1. Gere ou copie `dist\acbr-api-cli.exe`.
2. Edite `windows-app\suites\nfe-cenarios.ini` e informe o token.
3. Execute `windows-app\rodar-cenarios.bat`.
4. Consulte as respostas em `windows-app\saida`.

Tambem existe uma pasta local ignorada pelo Git, `windows-test-local`, para
testes com token real sem versionar segredo.

## Biblioteca Linux .so

A biblioteca Linux expõe uma interface C simples:

```c
char* AcbrApiRunConfig(char* configPath);
void AcbrApiFree(char* ptr);
```

`AcbrApiRunConfig` recebe o caminho do mesmo arquivo `.ini` usado pelo CLI e
retorna um JSON:

```json
{
  "exit_code": 0,
  "stdout": "{...}",
  "stderr": ""
}
```

Compile o exemplo C:

```bash
gcc -o dist/acbr-api-cli-so-example linux-example/call_config.c -Ldist -lacbr_api_cli -Wl,-rpath,'$ORIGIN'
```

Execute:

```bash
dist/acbr-api-cli-so-example chamada.ini
```

Quem chama a biblioteca deve liberar a string retornada usando
`AcbrApiFree`.

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
