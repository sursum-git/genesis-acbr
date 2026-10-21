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

Guia completo de uso:

```text
USO_EXECUTAVEL.md
```

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
Ambiente=2

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


## Seleção de ambiente NFe pelo header (2026-10-21)

Use `X-ACBr-Ambiente: 1` para produção e `X-ACBr-Ambiente: 2` para homologação.
O servidor consulta a release ativa e vigente para NFe nesse ambiente e usa o perfil INI, caminho e SHA-256 do cadastro. Não escolhe a maior versão encontrada no disco nem promove automaticamente uma release de homologação.
Valores diferentes de 1/2, perfil explícito conflitante e INI com ambiente divergente são rejeitados com HTTP 400 antes da chamada fiscal. Sem esse header, mantém-se a seleção anterior por perfil.
O header é preservado pela auditoria e pelo worker assíncrono; a release é resolvida na execução, conforme a vigência do cadastro naquele instante.

Exemplo de headers para envio de XML em homologação:

```http
X-Api-Token: <seu-token>
X-ACBr-Ambiente: 2
Content-Type: application/xml
```

O XML deve ser preparado para o ambiente correspondente (`tpAmb=1` produção; `tpAmb=2` homologação). O header não reescreve o XML.
A numeração pública não deve ser copiada para o INI nativo: nele, produção é `Ambiente=0` e homologação é `Ambiente=1`.

Cliente Go (CLI e interface C compartilhada), incluindo suites:

```ini
[API]
Ambiente=2
```

A opção é enviada como `X-ACBr-Ambiente`, não como parâmetro da URL. Suites fornecem o valor apenas quando a chamada não possui um valor próprio. Clientes antigos continuam usando o comportamento anterior; recompile os binários para reconhecer a nova opção.

### Exemplos e binários atualizados para ambiente no header

Os exemplos `chamada.ini`, `windows-app/suites/nfe-cenarios.ini` e `linux-example/chamada.ini` selecionam homologação (`Ambiente=2`). Para produção, use `Ambiente=1` após a configuração correspondente no servidor estar disponível. O valor pertence à seção `[API]`, não à seção `[Parametros]`.

Tanto o executável quanto `AcbrApiRunConfig` na biblioteca Linux enviam `X-ACBr-Ambiente`. A interface C e os códigos de saída permanecem compatíveis. As chamadas de uma suite herdam seu ambiente e podem definir um valor próprio em `[API]` para sobrescrevê-lo. Sem a propriedade, o cliente mantém a seleção legada do servidor; não assume produção.

O cliente envia o XML como recebido: o `tpAmb` do XML deve corresponder ao ambiente escolhido. A seleção da versão vigente da ACBrLib ocorre no servidor, sem informar caminho da biblioteca ou perfil INI no cliente.

Use os binários recompilados em `dist/acbr-api-cli.exe`, `dist/acbr-api-cli`, `dist/libacbr_api_cli.so` e o header C `dist/libacbr_api_cli.h`. O exemplo C compilado fica em `dist/acbr-api-cli-so-example`. O exemplo de configuração Linux está em `linux-example/chamada.ini`; substitua o token antes de chamar a API real.

Uma falha HTTP de envio não prova que a SEFAZ rejeitou a nota. O cliente informa o erro e não repete automaticamente a emissão; consulte a situação da chave antes de reenviar.
