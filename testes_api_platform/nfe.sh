#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/_common.sh"
NFE_XML_FILE="${NFE_XML_FILE:-${SCRIPT_DIR}/fixtures/nfe_consulta_exemplo.xml}"

run_get \
  "NFe - Status do servico" \
  "/nfe/consultas/status-servico"

run_get \
  "NFe - Consulta cadastro por CNPJ" \
  "/nfe/consultas/consulta-cadastro?AcUF=${UF}&AnDocumento=${CNPJ}&TipoDocumento=cpf_cnpj"

run_get \
  "NFe - Consulta cadastro por inscricao estadual" \
  "/nfe/consultas/consulta-cadastro?AcUF=${UF}&AnDocumento=${IE}&TipoDocumento=inscricao_estadual"

run_get \
  "NFe - Consultar com chave" \
  "/nfe/consultas/consultar-com-chave?eChaveOuNFe=${CHAVE_NFE}"

run_post_xml \
  "NFe - Consultar com XML" \
  "/nfe/consultas/consultar-com-chave-xml" \
  "${NFE_XML_FILE}"

run_get \
  "NFe - Distribuicao DFe por chave" \
  "/nfe/distribuicao-dfe/por-chave?AcUFAutor=${UF}&AeCNPJCPF=${CNPJ}&AechNFe=${CHAVE_NFE}"

run_get \
  "NFe - Distribuicao DFe por ult NSU" \
  "/nfe/distribuicao-dfe/por-ult-nsu?AcUFAutor=${UF}&AeCNPJCPF=${CNPJ}&AeultNSU=000000000000000"

run_get \
  "NFe - Distribuicao DFe por NSU" \
  "/nfe/distribuicao-dfe/por-nsu?AcUFAutor=${UF}&AeCNPJCPF=${CNPJ}&AeNSU=000000000000001"

run_get \
  "NFe - Consultar recibo (placeholder)" \
  "/nfe/consultas/consultar-recibo?ARecibo=SUBSTITUIR_RECIBO"

run_get \
  "NFe - OpenSSL info" \
  "/nfe/ferramentas/openssl-info"

run_get \
  "NFe - Obter certificados" \
  "/nfe/ferramentas/obter-certificados"
