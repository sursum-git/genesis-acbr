# Mapa Atual dos Modulos da API

## Panorama

A API atual exposta pelo API Platform possui 66 operacoes mapeadas em `src/ApiResource/`.

Os modulos efetivamente organizados no codigo sao:

- `CEP`
- `NFe`
- `NFSe`

## Navegacao e Documentacao

Paginas de entrada:

- `/` -> hub principal
- `/apis` -> atalhos para documentacao
- `/demos` -> atalhos para demos legadas

Atalhos de documentacao por filtro:

- `/docs/todos`
- `/docs/cep`
- `/docs/nfe`
- `/docs/nfse`

Destino final da documentacao:

- `/index.php/docs`

## Modulo CEP

Recursos principais em `src/ApiResource/AcbrCep/`.

Operacoes atuais:

- `GET /acbr-cep/configuracoes`
- `POST /acbr-cep/configuracoes`
- `POST /acbr-cep/consulta-cep`
- `POST /acbr-cep/consulta-logradouro`

Caracteristicas:

- usa DTOs dedicados em `src/Dto/AcbrCep/`
- usa service dedicado em `App\Service\AcbrCep\AcbrCepMtService`
- usa processors/providers especificos
- nao depende do adaptador generico de legado

## Modulo NFe

Recursos em `src/ApiResource/Nfe/`.

Grupos identificados:

- `consultas`
- `distribuicao-dfe`
- `envio`
- `eventos`
- `ferramentas`
- `inutilizacao`

Padrao tecnico:

- `GET` para consultas simples/ferramentas
- `POST` para operacoes com payload
- execucao delegada ao script `NFe/MT/ACBrNFeServicosMT.php`

Exemplos de operacoes:

- `GET /nfe/consultas/status-servico`
- `GET /nfe/consultas/consultar-com-chave`
- `POST /nfe/consultas/consultar-com-chave-xml`
- `GET /nfe/consultas/consultar-recibo`
- `GET /nfe/consultas/consulta-cadastro`
- `GET /nfe/distribuicao-dfe/por-chave`
- `GET /nfe/distribuicao-dfe/por-nsu`
- `GET /nfe/distribuicao-dfe/por-ult-nsu`
- `POST /nfe/envio/enviar-sincrono-xml`
- `POST /nfe/envio/enviar-assincrono-ini`
- `POST /nfe/envio/enviar-email`
- `POST /nfe/eventos/cancelar`
- `POST /nfe/inutilizacao/inutilizar`
- `GET /nfe/ferramentas/obter-certificados`

Observacao atual:

- `consulta-cadastro` foi isolada em recurso proprio com metadados XML do API Platform e validacao XML do Symfony Validator

## Modulo NFSe

Recursos em `src/ApiResource/Nfse/`.

Grupos identificados:

- `padrao-nacional`
- `demais-provedores/consultas`
- `demais-provedores/cancelamento`
- `demais-provedores/envio`
- `demais-provedores/servicos-prestados`
- `demais-provedores/servicos-tomados`
- `ferramentas`

Padrao tecnico:

- `GET` para operacoes de leitura/ferramentas
- `POST` para operacoes de emissao, consulta e cancelamento
- execucao delegada ao script `NFSe/MT/ACBrNFSeServicosMT.php`

Exemplos de operacoes:

- `POST /nfse/padrao-nacional/enviar-evento`
- `POST /nfse/padrao-nacional/consultar-dps-por-chave`
- `POST /nfse/demais-provedores/consultas/consultar-nfse-por-periodo`
- `POST /nfse/demais-provedores/envio/emitir-nota`
- `POST /nfse/demais-provedores/envio/substituir-nfse`
- `POST /nfse/demais-provedores/cancelamento/cancelar`
- `POST /nfse/demais-provedores/servicos-prestados/por-periodo`
- `POST /nfse/demais-provedores/servicos-tomados/por-numero`
- `GET /nfse/ferramentas/openssl-info`

## Componentes de Suporte

### Providers e Processors

- `src/State/AcbrCep/`: implementacao especifica do CEP
- `src/State/Legacy/`: adaptadores genericos para NFe e NFSe

### Contrato comum do legado

As operacoes NFe/NFSe estendem `AbstractAcbrLegacyOperationResource`, que padroniza:

- `payload`
- `resultado`
- `mensagem`

### Tratamento OpenAPI

`App\OpenApi\LegacyOptionalRequestBodyOpenApiFactory` ajusta a documentacao para que request bodies de caminhos legados nao sejam marcados como obrigatorios por default.

## Leitura Rapida da Arquitetura por Modulo

- `CEP`: integracao mais tipada e mais proxima de um servico de dominio.
- `NFe`: adaptacao declarativa sobre script legado unico.
- `NFSe`: adaptacao declarativa sobre script legado unico.

Essa diferenca e importante para qualquer evolucao futura, porque o CEP ja esta mais preparado para refatoracoes orientadas a servico do que NFe/NFSe.
