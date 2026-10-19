# Painel NFSe - Design

Data: 2026-09-08

## Contexto

O projeto ja expoe APIs NFSe em `Symfony + API Platform` sobre o legado `NFSe/MT/ACBrNFSeServicosMT.php`, mas ainda nao possui uma tela operacional equivalente aos monitores NFe.

As APIs atuais cobrem os principais blocos:

- emissao por RPS/lote para demais provedores;
- cancelamento de NFSe;
- substituicao de NFSe;
- consultas de servicos prestados/tomados;
- operacoes do Padrao Nacional com DPS/eventos.

O estado operacional documentado para Sao Paulo/SP continua relevante: a instalacao atual reconhece `ISSSaoPaulo`, mas trata a configuracao padrao como producao-only porque a biblioteca nao informa URL de homologacao neste ambiente.

## Objetivo

Criar uma primeira tela administrativa chamada `NFSe`, acessivel em `/index.php/monitor-nfse`, para concentrar operacoes NFSe de uso frequente sem substituir a documentacao OpenAPI.

A tela deve servir como console operacional inicial para:

- emitir RPS/NFSe por INI ou XML;
- cancelar nota;
- substituir nota;
- consultar dados basicos no Padrao Nacional;
- acessar rapidamente a documentacao NFSe existente.

## Fora de Escopo

Esta primeira entrega nao cria um monitor completo de notas NFSe com persistencia propria, grade historica extraida e detalhes fiscais como o monitor NFe.

Tambem nao tenta normalizar todas as regras municipais em um unico formulario fiscal definitivo. NFSe varia por provedor/municipio, entao o painel deve guiar os campos comuns e preservar editores de INI/XML bruto quando a operacao depender do provedor.

## Contrato Atual das APIs

### Demais provedores

Emissao:

- `POST /nfse/demais-provedores/envio/emitir-nota`
- `POST /nfse/demais-provedores/envio/enviar-um-rps`
- `POST /nfse/demais-provedores/envio/enviar-lote-rps-sincrono`
- `POST /nfse/demais-provedores/envio/enviar-lote-rps-assincrono`

Essas operacoes recebem INI/XML bruto no corpo e usam `NFSE_Emitir`.

Cancelamento:

- `POST /nfse/demais-provedores/cancelamento/cancelar`

A operacao recebe arquivo/conteudo bruto de cancelamento no corpo e usa `NFSE_Cancelar`.

Substituicao:

- `POST /nfse/demais-provedores/envio/substituir-nfse`

Hoje esta operacao usa payload generico. O legado exige:

- `AeArquivoXmlOuIni`: novo RPS/NFSe em INI ou XML;
- `ANumeroNFSe`: numero da NFSe substituida;
- `ASerieNFSe`: serie da NFSe substituida;
- `ACodigoCancelamento`: codigo de cancelamento;
- `AMotivoCancelamento`: motivo do cancelamento;
- `ANumeroLote`: numero do lote;
- `ACodigoVerificacao`: codigo de verificacao da NFSe substituida.

O painel deve preencher esses campos de forma guiada e enviar o payload no formato esperado pelo endpoint atual. Uma melhoria recomendada e documentar melhor esse endpoint no OpenAPI, com exemplo completo.

### Padrao Nacional

Operacoes iniciais no painel:

- `POST /nfse/padrao-nacional/enviar-evento`;
- `GET /nfse/padrao-nacional/consultar-dps-por-chave`;
- `GET /nfse/padrao-nacional/consultar-nfse-por-chave`;
- `GET /nfse/padrao-nacional/obter-danfse`.

O painel deve deixar claro que Padrao Nacional usa DPS/eventos, enquanto demais provedores geralmente operam com RPS/lote.

## Interface

Criar um painel administrativo denso e direto, seguindo AdminLTE/Twig ja usado no portal.

Estrutura:

- cabecalho compacto com titulo `NFSe`;
- faixa de status com ambiente/configuracao e aviso operacional de Sao Paulo quando aplicavel;
- abas para `RPS`, `Cancelar`, `Substituir`, `Padrao Nacional` e `Retorno`;
- botoes de acao com icones Bootstrap Icons;
- editores `textarea` para INI/XML bruto;
- campos curtos para chaves, numero, serie, lote, codigo de cancelamento e codigo de verificacao;
- area de retorno com HTTP status, mensagem e JSON bruto formatado.

Nao criar landing page. A primeira tela deve ser o console utilizavel.

## Backend

Adicionar `NfsePanelController` com:

- `GET /monitor-nfse`: renderiza a tela;
- endpoints auxiliares internos do painel para encaminhar operacoes para as APIs existentes quando isso simplificar token, headers e tratamento de erro.

Os endpoints auxiliares devem seguir o padrao do `NfeOutputMonitorController::forwardInternalApi`, usando o token resolvido pelo usuario atual quando necessario.

Rotas iniciais sugeridas:

- `POST /monitor-nfse/acoes/emitir-rps`;
- `POST /monitor-nfse/acoes/cancelar`;
- `POST /monitor-nfse/acoes/substituir`;
- `POST /monitor-nfse/acoes/padrao-nacional/enviar-evento`;
- `GET /monitor-nfse/acoes/padrao-nacional/consultar-dps`;
- `GET /monitor-nfse/acoes/padrao-nacional/consultar-nfse`.

Essas rotas sao uma camada de conveniencia para o painel; as APIs publicas em `/nfse/...` continuam sendo a fonte principal de contrato externo.

## Autorizacao e Menu

Adicionar link `NFSe` no grupo `Monitores` do menu lateral.

Permissao:

- `super_admin`: ve o painel;
- `company_admin`: ve o painel;
- `common`: ve o painel.

A autorizacao deve seguir o comportamento dos monitores NFe. O painel nao deve expor telas internas de operacao ou demos legadas para usuarios nao autorizados.

## Frontend

Adicionar asset dedicado `catalog-assets/monitor/nfse-panel.js`.

Responsabilidades:

- alternar abas;
- validar campos minimos no navegador;
- montar payloads JSON ou corpos brutos conforme a operacao;
- enviar `fetch` para as rotas auxiliares do painel;
- mostrar resultado formatado;
- impedir duplo submit enquanto a requisicao estiver em andamento.

O CSS deve reaproveitar estilos existentes do portal/monitor sempre que possivel. Se for necessario CSS novo, criar apenas regras especificas para o painel NFSe.

## Tratamento de Erros

Erros esperados:

- corpo INI/XML vazio;
- tentativa de substituir sem dados da nota substituida;
- cancelamento sem motivo/codigo;
- retorno ACBr com erro de provedor, certificado, schema ou URL;
- falha HTTP da API interna.

O painel deve mostrar a mensagem retornada pela API/ACBr e preservar o JSON bruto para diagnostico.

## Testes

Implementar com TDD.

Testes iniciais:

- menu de usuario comum contem `/index.php/monitor-nfse`;
- menu de administrador de empresa contem `/index.php/monitor-nfse`;
- rota `GET /monitor-nfse` renderiza o console com abas e URLs de acao;
- asset JS do painel aparece versionado no template;
- endpoint auxiliar de cancelamento rejeita payload vazio;
- endpoint auxiliar de substituicao rejeita campos obrigatorios ausentes.

Depois da implementacao, executar os testes PHP focados e uma verificacao sintatica dos arquivos alterados.

## Riscos

NFSe depende fortemente do municipio/provedor. O painel inicial deve evitar prometer fluxo fiscal universal.

Substituicao e cancelamento podem funcionar de forma diferente conforme provedor. Por isso o painel deve combinar campos guiados com payload bruto editavel.

O worktree atual possui alteracoes preexistentes. A implementacao deve tocar apenas arquivos necessarios e nao reverter alteracoes de terceiros.
