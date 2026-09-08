# Contexto Desktop CLI e Biblioteca Linux

Atualizado em `2026-09-08`.

## Resumo

Foi criado um cliente desktop para consumir a API central do projeto ACBr sem
rodar Symfony, ACBr, certificados ou workers na maquina do cliente.

O cliente fica em:

```text
clients/desktop-cli
```

Ele usa o mesmo contrato de arquivos no Windows e no Linux:

- um arquivo principal `chamada.ini`;
- opcionalmente um arquivo de parametros apontado por `[Arquivos].Entrada`;
- resposta JSON em `[Arquivos].Saida` ou no `stdout`;
- autenticacao via header `X-Api-Token`;
- formato aceito pela API Platform via `Accept: application/ld+json`.

## Artefatos

### Windows

Executavel:

```text
clients/desktop-cli/dist/acbr-api-cli.exe
```

App de testes por lote:

```text
clients/desktop-cli/windows-app/rodar-cenarios.bat
clients/desktop-cli/windows-app/suites/nfe-cenarios.ini
clients/desktop-cli/windows-app/chamadas/*.ini
clients/desktop-cli/windows-app/parametros/*.ini
```

### Linux

Executavel Linux:

```text
clients/desktop-cli/dist/acbr-api-cli
```

Biblioteca compartilhada:

```text
clients/desktop-cli/dist/libacbr_api_cli.so
clients/desktop-cli/dist/libacbr_api_cli.h
```

Exemplo C:

```text
clients/desktop-cli/linux-example/call_config.c
```

Os binarios em `dist/` sao artefatos gerados e ficam ignorados pelo Git.

## Como executar

### Windows

```bat
acbr-api-cli.exe --arq-config chamada.ini
```

Para varios cenarios:

```bat
acbr-api-cli.exe --suite-config windows-app\suites\nfe-cenarios.ini
```

### Linux

```bash
chmod +x dist/acbr-api-cli
./dist/acbr-api-cli --arq-config chamada.ini
```

Para varios cenarios:

```bash
./dist/acbr-api-cli --suite-config windows-app/suites/nfe-cenarios.ini
```

## Contrato do chamada.ini

Exemplo de consulta:

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=SEU_TOKEN_DO_ASSINANTE
TimeoutSeconds=60

[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Arquivos]
Entrada=parametros/consulta-cadastro-cpf.ini
Saida=saida/consulta-cadastro-cpf.json
```

Para operacoes de consulta, `Entrada` aponta para um arquivo de parametros.

Exemplo:

```ini
[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj
```

Para operacoes com corpo bruto, como envio XML/INI, `Entrada` aponta para o
arquivo XML/INI que sera enviado no body.

## Operacoes implementadas

```text
nfe/status-servico
nfe/consulta-cadastro
nfe/consultar-chave
nfe/distribuicao-chave
nfe/distribuicao-nsu
nfe/distribuicao-ult-nsu
nfe/enviar-sincrono-xml
nfe/enviar-assincrono-xml
nfe/enviar-sincrono-ini
nfe/enviar-assincrono-ini
nfe/validar-regras
nfe/imprimir-pdf
nfe/inutilizar
request/get
```

## Retorno

O executavel retorna JSON com:

```json
{
  "ok": true,
  "status_code": 200,
  "data": {}
}
```

Em erro:

```json
{
  "ok": false,
  "status_code": 401,
  "error": "mensagem da API",
  "data": {}
}
```

Codigos de saida:

- `0`: chamada aceita ou concluida;
- `1`: erro de uso/configuracao;
- `2`: erro HTTP/API;
- `3`: erro de rede/timeout;
- `4`: erro lendo ou gravando arquivo.

## Biblioteca Linux .so

Interface C exportada:

```c
char* AcbrApiRunConfig(char* configPath);
void AcbrApiFree(char* ptr);
```

`AcbrApiRunConfig` recebe o caminho do mesmo `chamada.ini` usado pelo
executavel e retorna JSON com:

```json
{
  "exit_code": 0,
  "stdout": "{...}",
  "stderr": ""
}
```

Quem chamar `AcbrApiRunConfig` deve liberar a string retornada com
`AcbrApiFree`.

## Documentacao criada

Guia principal:

```text
clients/desktop-cli/USO_EXECUTAVEL.md
```

README do cliente:

```text
clients/desktop-cli/README.md
```

## Commits relevantes

```text
0e1ad22 feat: add desktop API CLI client
0065d32 docs: add desktop cli sample config
c84e0a3 fix: request supported api platform format
c5cf31a feat: add desktop cli scenario runner
1d3f942 feat: add linux shared library build
3938f87 docs: document desktop cli executable usage
27b9cc4 docs: update desktop cli sample paths
```

Esses commits ja foram enviados para `origin/main`.

## Validacoes realizadas

Foram executados durante a implementacao:

```bash
GOCACHE=/tmp/acbr-api-cli-gocache go test -count=1 ./...
GOCACHE=/tmp/acbr-api-cli-gocache GOOS=windows GOARCH=amd64 go build -o dist/acbr-api-cli.exe
GOCACHE=/tmp/acbr-api-cli-gocache go build -buildmode=c-shared -o dist/libacbr_api_cli.so
gcc -o dist/acbr-api-cli-so-example linux-example/call_config.c -Ldist -lacbr_api_cli -Wl,-rpath,'$ORIGIN'
```

Tambem houve validacao manual pelo usuario:

- Windows: suite por `.bat` executou todos os cenarios com `ok=true`;
- Linux/WSL Ubuntu: `./dist/acbr-api-cli --arq-config ../chamada.ini`
  executou `consulta-cadastro` e retornou HTTP `200` com `ok=true`.

## Cuidados para proximas sessoes

- Nao versionar token real em `chamada.ini`, suites ou documentacao.
- A pasta `clients/desktop-cli/windows-test-local/` e ignorada pelo Git e pode
  conter arquivos locais com token real.
- Se alterar o cliente, rodar os testes Go e gerar novamente os artefatos em
  `dist/`.
- Se alterar o catalogo de programas, revisar `bin/sync_program_catalog.php` e
  decidir conscientemente se deve sincronizar `var/db/program_catalog.sqlite`.
- O workspace ainda possuia varias alteracoes locais fora do escopo do cliente;
  nao reverter sem revisar.
