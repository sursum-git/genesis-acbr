# Uso do executavel ACBr API CLI

Este cliente serve para softwares desktop chamarem a API central do ACBr sem
rodar Symfony, ACBr, certificado ou worker na maquina local.

O funcionamento e o mesmo no Windows e no Linux:

1. o sistema monta um arquivo `chamada.ini`;
2. a operacao pode apontar para um arquivo separado de parametros;
3. o executavel le os arquivos;
4. chama a API central usando `X-Api-Token`;
5. grava ou imprime um JSON de resposta.

## 1. Arquivos necessarios

### Windows

Estrutura minima:

```text
C:\ACBrAPI\
  acbr-api-cli.exe
  chamada.ini
  parametros\
    consulta-cadastro-cpf.ini
  saida\
```

### Linux

Estrutura minima:

```text
~/acbr-api-cli/
  dist/
    acbr-api-cli
    libacbr_api_cli.so
    libacbr_api_cli.h
  chamada.ini
  parametros/
    consulta-cadastro-cpf.ini
  saida/
```

Para usar apenas como executavel no Linux, o arquivo principal e:

```text
dist/acbr-api-cli
```

A `.so` e usada quando outro programa Linux quer carregar a biblioteca
diretamente.

## 2. Como executar

### Windows

No `cmd`, dentro da pasta onde esta o executavel:

```bat
acbr-api-cli.exe --arq-config chamada.ini
```

Com caminho completo:

```bat
C:\ACBrAPI\acbr-api-cli.exe --arq-config C:\ACBrAPI\chamada.ini
```

### Linux

Na primeira vez, libere execucao:

```bash
chmod +x dist/acbr-api-cli
```

Execute:

```bash
./dist/acbr-api-cli --arq-config chamada.ini
```

Com caminho completo:

```bash
/home/parreiras/acbr-api-cli/dist/acbr-api-cli --arq-config /home/parreiras/acbr-api-cli/chamada.ini
```

## 3. Arquivo chamada.ini

O `chamada.ini` informa a API, o token, a operacao e os arquivos usados.

Exemplo para consulta cadastro:

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

No Windows tambem pode usar barra invertida:

```ini
[Arquivos]
Entrada=parametros\consulta-cadastro-cpf.ini
Saida=saida\consulta-cadastro-cpf.json
```

Campos:

- `BaseURL`: endereco da API central.
- `Token`: token do assinante.
- `TimeoutSeconds`: tempo maximo da chamada.
- `Modulo`: modulo chamado, por exemplo `nfe`.
- `Operacao`: operacao do modulo.
- `Entrada`: arquivo de parametros ou arquivo bruto XML/INI.
- `Saida`: arquivo onde o JSON de resposta sera gravado.

Se `Saida` ficar vazio, o JSON aparece direto no terminal.

## 4. Arquivo de parametros

Para operacoes de consulta, `Entrada` aponta para outro `.ini` com os
parametros.

Exemplo `parametros/consulta-cadastro-cpf.ini`:

```ini
[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj
```

Esse arquivo tambem pode ser direto, sem secao:

```ini
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj
```

## 5. Operacoes com XML ou INI bruto

Quando a operacao envia XML ou INI para a API, `Entrada` aponta para o arquivo
bruto que sera enviado no corpo da requisicao.

Exemplo `chamada.ini` para envio XML:

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=SEU_TOKEN_DO_ASSINANTE
TimeoutSeconds=120

[Requisicao]
Modulo=nfe
Operacao=enviar-assincrono-xml

[Parametros]
ALote=1

[Arquivos]
Entrada=xml/lote.xml
Saida=saida/envio-assincrono-xml.json
```

Nesse caso:

- `[Parametros]` fica no proprio `chamada.ini`;
- `Entrada` e o XML/INI enviado;
- `Saida` recebe o JSON de retorno.

## 6. Retorno

Sucesso:

```json
{
  "ok": true,
  "status_code": 200,
  "data": {}
}
```

Erro:

```json
{
  "ok": false,
  "status_code": 401,
  "error": "token invalido",
  "data": {}
}
```

Chamada aceita para processamento assincrono:

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

## 7. Codigos de saida

- `0`: chamada aceita ou concluida.
- `1`: erro no uso ou configuracao.
- `2`: erro retornado pela API.
- `3`: erro de rede ou timeout.
- `4`: erro lendo ou gravando arquivo.

## 8. Operacoes disponiveis

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

No arquivo:

```ini
[Requisicao]
Modulo=nfe
Operacao=status-servico
```

## 9. Executar varios cenarios

O cliente tambem roda varias chamadas em sequencia usando `--suite-config`.

Windows:

```bat
acbr-api-cli.exe --suite-config windows-app\suites\nfe-cenarios.ini
```

Linux:

```bash
./dist/acbr-api-cli --suite-config windows-app/suites/nfe-cenarios.ini
```

Exemplo de suite:

```ini
[API]
BaseURL=http://157.173.110.195:8089/index.php
Token=SEU_TOKEN_DO_ASSINANTE
TimeoutSeconds=60

[Suite]
OutputDir=saida

[Cenarios]
status_servico=chamadas/status-servico.ini
consulta_cadastro_cpf=chamadas/consulta-cadastro-cpf.ini
```

Cada cenario aponta para seu proprio arquivo de chamada.

Exemplo `chamadas/consulta-cadastro-cpf.ini`:

```ini
[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Arquivos]
Entrada=../parametros/consulta-cadastro-cpf.ini
```

O resultado final da suite fica em:

```text
saida/resumo.json
```

E cada cenario gera seu proprio JSON dentro da pasta `saida`.

## 10. Biblioteca Linux .so

Para programas Linux que querem chamar a biblioteca diretamente, use:

```text
dist/libacbr_api_cli.so
dist/libacbr_api_cli.h
```

Interface C:

```c
char* AcbrApiRunConfig(char* configPath);
void AcbrApiFree(char* ptr);
```

Exemplo de chamada:

```c
char *retorno = AcbrApiRunConfig("/home/parreiras/acbr-api-cli/chamada.ini");
printf("%s\n", retorno);
AcbrApiFree(retorno);
```

Compilar o exemplo incluído:

```bash
gcc -o dist/acbr-api-cli-so-example linux-example/call_config.c -Ldist -lacbr_api_cli -Wl,-rpath,'$ORIGIN'
```

Executar:

```bash
./dist/acbr-api-cli-so-example chamada.ini
```

## 11. Checklist rapido

Antes de executar, confirme:

- o executavel existe;
- no Linux, o executavel tem permissao com `chmod +x`;
- `chamada.ini` aponta para o token correto;
- `Entrada` aponta para um arquivo que existe;
- a pasta de `Saida` existe ou pode ser criada;
- a maquina tem acesso ao endereco da `BaseURL`.
