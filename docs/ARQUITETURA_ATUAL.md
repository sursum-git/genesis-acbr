# Arquitetura Atual do Projeto

## Visao Geral

O projeto atual e uma aplicacao PHP 8.2+ baseada em Symfony 7 e API Platform 4.2, publicada a partir da raiz web do repositorio.

Ha dois blocos principais convivendo no mesmo deploy:

1. Uma camada HTTP moderna em `src/`, exposta via Symfony/API Platform.
2. Um conjunto de modulos legados ACBr em pastas como `NFe/`, `NFSe/` e `ConsultaCEP/`.

Na pratica, a arquitetura e uma camada de adaptacao. O Symfony organiza rotas, documentacao OpenAPI, serializacao e tratamento de erros. As regras de negocio especializadas continuam majoritariamente nos scripts legados ACBr.

## Stack Atual

- PHP `>= 8.2`
- Symfony `^7.0`
- API Platform `^4.2`
- Twig para paginas simples de navegacao
- cURL para chamadas internas do adaptador legado
- Extensao PHP `ffi` habilitada

Arquivo de bootstrap principal:

- `index.php`

Arquivo de composicao da aplicacao:

- `composer.json`

## Organizacao de Pastas

### Camada Symfony/API

- `src/ApiResource/`: contratos da API expostos pelo API Platform
- `src/State/`: providers e processors
- `src/Service/`: servicos de integracao
- `src/Dto/`: DTOs usados no modulo CEP
- `src/Controller/`: paginas HTML simples para navegação
- `src/EventSubscriber/`: tratamento padronizado de excecoes
- `src/OpenApi/`: customizacao da documentacao OpenAPI
- `config/`: rotas, servicos e pacotes Symfony
- `templates/`: views Twig da home e navegacao

### Camada Legada ACBr

- `ConsultaCEP/`: implementacao legada do modulo CEP
- `NFe/`: scripts e configuracoes da NFe
- `NFSe/`: scripts e configuracoes da NFSe
- `Boleto/`, `ConsultaCNPJ/`, `GTIN/` e outras pastas: demos legadas ainda acessiveis

## Modelo Arquitetural em Uso

### 1. Entrada HTTP

As requisicoes entram por `index.php`, que inicializa o `App\Kernel`.

### 2. Resolucao de rota

O projeto combina:

- rotas por atributo em `src/Controller/`
- rotas geradas automaticamente pelo API Platform para `src/ApiResource/`

### 3. Execucao por estilo de modulo

Existem dois estilos de execucao hoje:

#### CEP

O modulo CEP usa integracao PHP direta.

Fluxo:

`ApiResource -> State Processor/Provider -> App\Service\AcbrCep\AcbrCepMtService -> ACBrCEPApiMT`

Esse caminho usa DTOs dedicados e mapeamento mais estruturado entre request, resposta e excecao.

#### NFe e NFSe

Os modulos NFe e NFSe usam um adaptador de legado com DTOs explicitos de entrada e saida.

Fluxo:

`ApiResource -> DTO de entrada/saida -> State Processor/Provider -> App\Service\Legacy\AcbrLegacyScriptExecutor -> script PHP legado`

Cada operacao declara em `extraProperties`:

- `acbr_script`
- `acbr_method`
- `acbr_payload` opcional
- `acbr_query_params` opcional

Ou seja, o recurso API define metadados e a execucao e roteada dinamicamente para o script legado correspondente, mas a superficie publica da API agora passa por DTOs dedicados de `NFe` e `NFSe`.

No modulo `NFe`, a operacao `consulta-cadastro` ja foi promovida para um desenho mais completo, com DTO especifico, atributos de validacao, provider proprio e traducao de `TipoDocumento` para o contrato legado.

## Caracteristicas Arquiteturais Relevantes

### Camada moderna sem reescrita completa do dominio

O projeto nao reimplementa toda a logica fiscal no Symfony. Em vez disso, encapsula o legado existente atras de uma API documentada.

### Documentacao centralizada

O API Platform funciona como catalogo central dos endpoints, com filtros por tag para:

- `cep`
- `nfe`
- `nfse`

### Mesma base para UI simples e API

O mesmo deploy serve:

- paginas HTML simples em `/`, `/apis` e `/demos`
- documentacao OpenAPI em `/index.php/docs`
- demos legadas em rotas diretas de arquivos PHP

### Tratamento de erro por modulo

O CEP possui excecao e subscriber proprios.
As operacoes legadas genericas usam `AcbrLegacyApiException` com resposta JSON padronizada.

### Persistencia auxiliar com Doctrine DBAL

O catalogo local de programas em SQLite deixou de ser consultado com `PDO` direto em controller.

Agora a aplicacao usa `Doctrine DBAL` com uma camada de repositorio dedicada para acesso ao banco auxiliar do projeto.

## Dependencias Estruturais Importantes

- O modulo CEP depende fisicamente de `ConsultaCEP/MT/ACBrCEPApiMT.php`.
- NFe e NFSe dependem de scripts como `NFe/MT/ACBrNFeServicosMT.php` e `NFSe/MT/ACBrNFSeServicosMT.php`.
- A execucao do adaptador legado usa a URL atual da requisicao para fazer chamada HTTP interna ao proprio sistema.

Isso significa que a arquitetura atual assume:

- scripts legados acessiveis no mesmo host
- estrutura de pastas preservada
- servidor capaz de responder a chamadas HTTP locais para os endpoints/arquivos legados

## Estado Atual da Migracao

O estado atual nao e de substituicao total do legado.

O projeto esta em um modo de transicao controlada:

- API moderna para consumo externo
- scripts legados ainda responsaveis por parte central da execucao
- demos antigas mantidas acessiveis enquanto a conversao continua

Esse desenho favorece evolucao incremental, mas mantem acoplamento relevante com a estrutura historica do ACBr.
