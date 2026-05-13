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
  $'[RPS]\nNumero=1\nSerie=A1\nTipo=1'
