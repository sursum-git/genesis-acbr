# Fluxo Atual de Integracao com o Legado ACBr

## Objetivo

Este documento descreve como a camada Symfony/API Platform conversa hoje com o legado ACBr existente no projeto.

## Dois Padroes de Integracao

### 1. Integracao direta via classe PHP

Usada no modulo `CEP`.

Fluxo:

1. O endpoint API Platform recebe a requisicao.
2. Um processor ou provider do modulo CEP transforma os dados de entrada.
3. `App\Service\AcbrCep\AcbrCepMtService` chama `ACBrCEPApiMT`.
4. O retorno legado e convertido para DTO/resource de resposta.
5. Excecoes sao convertidas em JSON por `AcbrCepExceptionSubscriber`.

Vantagens do padrao atual:

- contrato mais explicito
- DTOs separados para entrada e saida
- menor dependencia de metadados dinamicos

### 2. Integracao indireta via HTTP interno

Usada nos modulos `NFe` e `NFSe`.

Fluxo:

1. O endpoint API Platform recebe a requisicao.
2. O `ApiResource` informa, via `extraProperties`, qual script e qual metodo do legado devem ser chamados.
3. `AcbrLegacyOperationProcessor` ou `AcbrLegacyOperationProvider` coleta payload fixo, payload da requisicao e query params.
4. `AcbrLegacyScriptExecutor` monta uma chamada cURL para o proprio host atual.
5. O script legado recebe um `POST` com `metodo=<acao>` e os demais campos.
6. A resposta JSON ou texto e normalizada para o formato padrao da API.
7. Erros sao convertidos em JSON por `AcbrLegacyApiExceptionSubscriber`.

## Papel das `extraProperties`

Nos recursos NFe/NFSe, o comportamento nao fica em uma classe de dominio por endpoint. Ele e descrito por metadados como:

- `acbr_script`: caminho do script legado
- `acbr_method`: metodo que o script deve executar
- `acbr_payload`: payload fixo adicional
- `acbr_query_params`: parametros aceitos via query string em GETs

Exemplo conceitual:

```php
extraProperties: [
    'acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php',
    'acbr_method' => 'Enviar',
    'acbr_payload' => ['tipoArquivo' => 'xml']
]
```

Esse desenho permite expor muitos endpoints com pouco codigo novo, mas aumenta a dependencia de convencoes e da estrutura do legado.

## Contrato de Resposta do Adaptador Generico

`AbstractAcbrLegacyOperationResource` padroniza a saida com:

- `payload`: dados recebidos na chamada
- `resultado`: retorno bruto/normalizado do legado
- `mensagem`: mensagem principal, quando disponivel

Se o legado devolver:

- JSON valido: a API retorna a estrutura decodificada
- texto puro: a API encapsula em `mensagem` e `raw`
- erro HTTP >= 400: a API tenta extrair `mensagem`, senao devolve o corpo bruto

## Dependencias Operacionais

O adaptador atual depende de alguns pressupostos fortes:

- a requisicao HTTP atual precisa existir no `RequestStack`
- o host atual precisa ser acessivel via cURL pelo proprio servidor
- os arquivos legados precisam continuar nos caminhos esperados
- scripts legados precisam aceitar o protocolo atual baseado em `POST`

Se qualquer um desses pontos mudar, a camada de adaptacao quebra mesmo que o Symfony continue funcional.

## Implicacoes Arquiteturais

### Beneficios

- permite migracao incremental
- reduz retrabalho imediato sobre regras fiscais ja existentes
- concentra a exposicao de API e OpenAPI em uma camada moderna

### Limitacoes

- forte acoplamento com caminhos fisicos de scripts
- dependencia de chamada HTTP interna em vez de invocacao direta
- menor tipagem em NFe/NFSe
- maior dificuldade para testes isolados de dominio

## Leitura Estrategica

Hoje o sistema nao e apenas um backend Symfony nem apenas um legado ACBr.

Ele funciona como uma fachada moderna sobre um nucleo legado ainda ativo. Essa e a decisao arquitetural central do estado atual do projeto.
