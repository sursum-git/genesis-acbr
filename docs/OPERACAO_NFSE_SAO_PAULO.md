# Operacao NFSe Sao Paulo

Atualizado em `2026-05-15`.

Este arquivo registra o estado operacional especifico da integracao `NFSe` para `Sao Paulo/SP` no ambiente atual.

## Empresa de teste atualmente configurada

- Razao social: `TECNO FLEX IND E COM LTDA`
- `CNPJ`: `57039802000122`
- `IE`: `104044963116`
- `Inscricao Municipal/CCM`: `10768807`
- Municipio IBGE: `3550308`
- `UF`: `SP`
- `PFX`: `/dados/tecnoflex_2026.pfx`

## Configuracao base consolidada

Arquivo:

- [NFSe/MT/ACBrNFSe.INI](/dados_containers/www/NFSe/MT/ACBrNFSe.INI)

Campos importantes:

- `CodigoMunicipio=3550308`
- `LayoutNFSe=0`
- `Emitente.CNPJ=57039802000122`
- `Emitente.InscMun=10768807`
- `Emitente.RazSocial=TECNO FLEX IND E COM LTDA`
- `SSLCryptLib=1`
- `SSLHttpLib=3`
- `SSLXmlSignLib=4`
- `ArquivoPFX=/dados/tecnoflex_2026.pfx`
- `PathSchemas=/var/www/html/Schemas/NFSe/`
- `PathSalvar=/var/www/html/NFSe/arqs/`

## O que ja foi confirmado na biblioteca

Consulta direta da `ACBrLibNFSe` mostrou:

- provedor ativo: `ISSSaoPaulo`
- layout: `Proprio`
- autenticacao: `RequerCertificado`
- servicos disponiveis:
  - `EnviarLoteAssincrono`
  - `EnviarLoteSincrono`
  - `EnviarUnitario`
  - `ConsultarSituacao`
  - `ConsultarLote`
  - `ConsultarRps`
  - `ConsultarNfse`
  - `ConsultarServicoPrestado`
  - `ConsultarServicoTomado`
  - `CancelarNfse`
  - `TestarEnvio`

## Restricao mais importante do ambiente atual

Nesta instalacao da `ACBrLibNFSe`, o provedor `ISSSaoPaulo`:

- e reconhecido corretamente
- mas nao informa URL de homologacao

Consequencia pratica:

- a demo e a configuracao padrao do projeto foram ajustadas para usar `Produção`
- insistir em `Homologação` tende a retornar erro de URL nao informada

## Decisao adotada no projeto

Para `ISSSaoPaulo` nesta instalacao:

- usar `Produção` por padrao
- mostrar aviso explicito na demo
- nao deixar a demo permanecer em `Homologação` quando o provedor atual for Sao Paulo

Arquivo ajustado:

- [NFSe/ACBrNFSeBase.php](/dados_containers/www/NFSe/ACBrNFSeBase.php)

## Formato de arquivo aceito

### XML

O XML generico ABRASF usado antes nao era suficiente para Sao Paulo.

Problemas encontrados ao longo do processo:

- `ID Inválido. Impossível Salvar XML`
- `Input is not proper UTF-8`
- `Arquivo inválido`

Foi necessario:

- normalizar codificacao
- ajustar leitura de upload da demo
- abandonar XML generico como base de transmissao

### INI

Para Sao Paulo, o caminho pratico ficou sendo `INI`.

O formato que a ACBr passou a aceitar estruturalmente usa secoes como:

- `[IdentificacaoNFSe]`
- `[IdentificacaoRps]`
- `[Prestador]`
- `[Tomador]`
- `[Servico]`
- `[Valores]`

Os exemplos externos foram refeitos em:

- `/dados_containers/testes/NFSe/ini`

## Exemplos externos

Diretorio operacional:

- `/dados_containers/testes/NFSe`

Arquivos importantes:

- `/dados_containers/testes/NFSe/ini/teste01_sucesso_enviar_um_rps.ini`
- `/dados_containers/testes/NFSe/ini/teste02_sucesso_enviar_lote_rps_sincrono.ini`
- `/dados_containers/testes/NFSe/ini/teste03_sucesso_enviar_lote_rps_assincrono.ini`

Observacao:

- esses arquivos ficam fora do git do backend
- eles sao exemplos operacionais, nao artefatos versionados do repositorio

## Estado atual do envio

O problema de `ID Inválido` foi superado para o `INI` refeito.

O estado mais recente confirmado foi:

- o arquivo passou a ser aceito pela ACBr
- o proximo bloqueio virou a falta de URL de homologacao quando o ambiente estava em homologacao

Por isso o projeto foi normalizado para `Produção`.

## Relacao com a demo

Pagina:

- `http://157.173.110.195:8089/NFSe/ACBrNFSeDemoMT.php`

Comportamento esperado agora:

- deve carregar configuracao preenchida a partir do `INI`
- deve abrir em `Produção`
- deve exibir aviso de que `ISSSaoPaulo` nao possui homologacao nesta instalacao

## O que revisar antes de um novo teste

1. Confirmar que a demo abriu em `Produção`.
2. Confirmar que o `PFX` continua apontando para `/dados/tecnoflex_2026.pfx`.
3. Confirmar `CNPJ`, `CCM` e `CodigoMunicipio`.
4. Usar um dos `INI` refeitos da pasta `/dados_containers/testes/NFSe/ini`.
5. Nao salvar configuracao manual com libs SSL zeradas.

