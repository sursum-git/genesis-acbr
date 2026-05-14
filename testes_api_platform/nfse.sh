#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/_common.sh"

run_get \
  "NFSe - OpenSSL info" \
  "/nfse/ferramentas/openssl-info"

run_get \
  "NFSe - Obter certificados" \
  "/nfse/ferramentas/obter-certificados"

run_get \
  "NFSe - Padrao nacional - consultar DPS por chave (placeholder)" \
  "/nfse/padrao-nacional/consultar-dps-por-chave?AChaveDPS=SUBSTITUIR_CHAVE_DPS"

run_get \
  "NFSe - Padrao nacional - consultar NFSe por chave (placeholder)" \
  "/nfse/padrao-nacional/consultar-nfse-por-chave?AChaveNFSe=SUBSTITUIR_CHAVE_NFSE"

run_get \
  "NFSe - Demais provedores - consultar situacao (placeholder)" \
  "/nfse/demais-provedores/consultas/consultar-situacao?AProtocolo=SUBSTITUIR_PROTOCOLO&ANumLote=1"

run_get \
  "NFSe - Demais provedores - consultar por periodo (placeholder)" \
  "/nfse/demais-provedores/consultas/consultar-nfse-por-periodo?ADataInicial=01/04/2026&ADataFinal=30/04/2026&APagina=1&ATipoPeriodo=1"

run_post_text \
  "NFSe - Padrao nacional - enviar evento com arquivo bruto (placeholder)" \
  "/nfse/padrao-nacional/enviar-evento" \
  $'[Evento]\nTipoEvento=110111\nChave=SUBSTITUIR_CHAVE_EVENTO'

run_post_text \
  "NFSe - Demais provedores - consultar NFSe generico com arquivo bruto (placeholder)" \
  "/nfse/demais-provedores/consultas/consultar-nfse-generico" \
  $'[ConsultaNFSe]\nNumero=12345'

run_post_text \
  "NFSe - Demais provedores - enviar lote RPS assincrono com arquivo bruto (placeholder)" \
  "/nfse/demais-provedores/envio/enviar-lote-rps-assincrono?ALote=1" \
  $'[IdentificacaoNFSe]\nTipoXML=RPS\n\n[IdentificacaoRps]\nNumero=1\nSerie=RPS\nDataEmissao=14/05/2026\nCompetencia=14/05/2026\nTipo=1\nTipoTributacaoRps=T\nNaturezaOperacao=1\nStatus=1\n\n[Prestador]\nTipoPessoa=1\nCNPJ=57039802000122\nInscricaoMunicipal=10768807\nInscricaoEstadual=104044963116\nRazaoSocial=TECNO FLEX IND E COM LTDA\nOptanteSN=1\nOptanteMEISimei=2\nIncentivadorCultural=1\n\n[Tomador]\nTipoPessoa=1\nCNPJCPF=12345678000195\nRazaoSocial=TOMADOR EXEMPLO LTDA\nEmail=fiscal@tflx.com.br\nLogradouro=RUA DE TESTE\nNumero=100\nBairro=CENTRO\nCodigoMunicipio=3550308\nxMunicipio=SAO PAULO\nUF=SP\nCEP=01001000\n\n[Servico]\nItemListaServico=0107\nCodigoTributacaoMunicipio=0107\nCodigoServico=0107\nCodigoCnae=6201500\nDiscriminacao=Servico de exemplo NFSe Sao Paulo - lote assincrono.\nCodigoMunicipio=3550308\nMunicipioPrestacaoServico=3550308\nExigibilidadeISS=1\nResponsavelRetencao=1\nLocalPrestacao=1\n\n[Valores]\nValorServicos=150.00\nValorDeducoes=0.00\nValorPis=0.00\nValorCofins=0.00\nValorInss=0.00\nValorIr=0.00\nValorCsll=0.00\nBaseCalculo=150.00\nAliquota=2.00\nValorIss=3.00\nISSRetido=2\nValorLiquidoNfse=150.00\nValorTotalNotaFiscal=150.00'
