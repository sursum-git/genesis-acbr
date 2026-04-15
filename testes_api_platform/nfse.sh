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

run_post_json \
  "NFSe - Padrao nacional - consultar DPS por chave (placeholder)" \
  "/nfse/padrao-nacional/consultar-dps-por-chave" \
  '{"payload":{"AChaveDFe":"SUBSTITUIR_CHAVE_DPS"}}'

run_post_json \
  "NFSe - Padrao nacional - consultar NFSe por chave (placeholder)" \
  "/nfse/padrao-nacional/consultar-nfse-por-chave" \
  '{"payload":{"AChaveDFe":"SUBSTITUIR_CHAVE_NFSE"}}'

run_post_json \
  "NFSe - Demais provedores - consultar situacao (placeholder)" \
  "/nfse/demais-provedores/consultas/consultar-situacao" \
  "{\"payload\":{\"APrestadorCNPJ\":\"${CNPJ}\",\"AProtocolo\":\"SUBSTITUIR_PROTOCOLO\"}}"

run_post_json \
  "NFSe - Demais provedores - consultar por periodo (placeholder)" \
  "/nfse/demais-provedores/consultas/consultar-nfse-por-periodo" \
  "{\"payload\":{\"APrestadorCNPJ\":\"${CNPJ}\",\"ADataInicial\":\"2026-04-01\",\"ADataFinal\":\"2026-04-30\"}}"
