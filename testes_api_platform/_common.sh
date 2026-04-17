#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://157.173.110.195:8089/index.php}"
CNPJ="${CNPJ:-06013812000158}"
UF="${UF:-ES}"
IE="${IE:-06013812000158}"
CHAVE_NFE="${CHAVE_NFE:-32260406013812000158550030001955901308939122}"

print_title() {
  printf '\n== %s ==\n' "$1"
}

run_get() {
  local label="$1"
  local path="$2"
  print_title "$label"
  curl -sS -X GET "${BASE_URL}${path}" \
    -H 'Accept: application/ld+json'
  printf '\n'
}

run_post_json() {
  local label="$1"
  local path="$2"
  local payload="$3"
  print_title "$label"
  curl -sS -X POST "${BASE_URL}${path}" \
    -H 'Content-Type: application/ld+json' \
    -H 'Accept: application/ld+json' \
    -d "$payload"
  printf '\n'
}

run_post_xml() {
  local label="$1"
  local path="$2"
  local xml_file="$3"
  print_title "$label"
  curl -sS -X POST "${BASE_URL}${path}" \
    -H 'Content-Type: application/xml' \
    -H 'Accept: application/ld+json' \
    --data-binary "@${xml_file}"
  printf '\n'
}
