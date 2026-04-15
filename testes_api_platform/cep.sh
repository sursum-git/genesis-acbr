#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/_common.sh"

run_post_json \
  "CEP - Consulta por CEP" \
  "/acbr-cep/consulta-cep" \
  '{"cep":"29103091","webservice":"0"}'

run_post_json \
  "CEP - Consulta por logradouro" \
  "/acbr-cep/consulta-logradouro" \
  '{"cidade":"Vila Velha","tipo":"ROD","logradouro":"Darly Santos","uf":"ES","bairro":"Aracas","webservice":"0"}'
