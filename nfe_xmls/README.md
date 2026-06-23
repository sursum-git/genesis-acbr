# Estrutura dos XMLs de NFe

Esta pasta foi separada em tres grupos:

- `originais_vazios/`
  Contem os arquivos originais sem o sufixo ` (1)`. Eles estavam vazios.

- `producao_autorizadas/`
  Contem os XMLs originais com conteudo, mantidos sem alteracoes.

- `homologacao_convertidos/`
  Contem copias geradas a partir dos XMLs de producao com ajustes basicos para homologacao:
  - `tpAmb` alterado para `2`
  - `emit/xNome` ajustado para `NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL`
  - `dest/xNome` ajustado para `NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL`
  - remocao de `Signature`
  - remocao de `protNFe`
  - remocao do envelope `nfeProc`, deixando apenas a `NFe`

## Observacao importante

Os XMLs em `homologacao_convertidos/` estao preparados para testes de homologacao no conteudo do documento, mas o servidor atual ainda esta com o legado NFe configurado em producao no arquivo:

- `NFe/MT/ACBrNFe.INI`

Valor atual:

- `Ambiente=0`

Na interface do legado isso corresponde a:

- `0 = Producao`
- `1 = Homologacao`

Enquanto esse valor nao for alterado no runtime/INI, chamadas como validacao de regras de negocio podem retornar rejeicao `252` por divergencia de ambiente.
