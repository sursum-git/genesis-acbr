#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://157.173.110.195:8089/index.php}"
CNPJ="${CNPJ:-06013812000158}"
UF="${UF:-ES}"
IE="${IE:-06013812000158}"
CHAVE_NFE="${CHAVE_NFE:-32260406013812000158550030001955901308939122}"
AUDIT_DB_HOST="${AUDIT_DB_HOST:-157.173.110.195}"
AUDIT_DB_PORT="${AUDIT_DB_PORT:-5432}"
AUDIT_DB_NAME="${AUDIT_DB_NAME:-dfe}"
AUDIT_DB_USER="${AUDIT_DB_USER:-postgres}"
AUDIT_DB_PASSWORD="${AUDIT_DB_PASSWORD:-81215122}"
ASSINANTE_IDENTIFICADOR="${ASSINANTE_IDENTIFICADOR:-002}"
API_TOKEN="${API_TOKEN:-}"

if [[ -z "${API_TOKEN}" ]] && command -v psql >/dev/null 2>&1; then
  API_TOKEN="$(
    PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    psql -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -Atqc \
      "SELECT c_token FROM public.t00002 WHERE c_identificador = '${ASSINANTE_IDENTIFICADOR}' LIMIT 1" 2>/dev/null || true
  )"
fi

print_title() {
  printf '\n== %s ==\n' "$1"
}

run_get() {
  local label="$1"
  local path="$2"
  print_title "$label"
  local curl_args=(curl -sS -X GET "${BASE_URL}${path}" -H 'Accept: application/ld+json')
  if [[ -n "${API_TOKEN}" ]]; then
    curl_args+=(-H "X-Api-Token: ${API_TOKEN}")
  fi
  "${curl_args[@]}"
  printf '\n'
}

run_post_json() {
  local label="$1"
  local path="$2"
  local payload="$3"
  print_title "$label"
  local curl_args=(curl -sS -X POST "${BASE_URL}${path}" -H 'Content-Type: application/ld+json' -H 'Accept: application/ld+json' -d "$payload")
  if [[ -n "${API_TOKEN}" ]]; then
    curl_args+=(-H "X-Api-Token: ${API_TOKEN}")
  fi
  "${curl_args[@]}"
  printf '\n'
}

run_post_xml() {
  local label="$1"
  local path="$2"
  local xml_file="$3"
  print_title "$label"
  local curl_args=(curl -sS -X POST "${BASE_URL}${path}" -H 'Content-Type: application/xml' -H 'Accept: application/ld+json' --data-binary "@${xml_file}")
  if [[ -n "${API_TOKEN}" ]]; then
    curl_args+=(-H "X-Api-Token: ${API_TOKEN}")
  fi
  "${curl_args[@]}"
  printf '\n'
}

run_post_text() {
  local label="$1"
  local path="$2"
  local payload="$3"
  print_title "$label"
  local curl_args=(curl -sS -X POST "${BASE_URL}${path}" -H 'Content-Type: text/plain' -H 'Accept: application/ld+json' --data-binary "$payload")
  if [[ -n "${API_TOKEN}" ]]; then
    curl_args+=(-H "X-Api-Token: ${API_TOKEN}")
  fi
  "${curl_args[@]}"
  printf '\n'
}
