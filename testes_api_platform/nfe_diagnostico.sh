#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/_common.sh"

if [[ -z "${API_TOKEN}" ]]; then
  echo "API_TOKEN nao definido e nao foi possivel carregar automaticamente da t00002." >&2
  exit 1
fi

if ! command -v psql >/dev/null 2>&1; then
  echo "psql nao encontrado. Este diagnostico depende de acesso ao PostgreSQL para ler a t99001." >&2
  exit 1
fi

tmp_body="$(mktemp)"
trap 'rm -f "${tmp_body}"' EXIT

pg_query() {
  PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    psql -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -Atqc "$1"
}

token_hash() {
  printf '%s' "${API_TOKEN}" | sha256sum | awk '{print $1}'
}

json_message() {
  php -r '$d=json_decode(file_get_contents($argv[1]), true); $m=$d["mensagem"] ?? ($d["resultado"]["mensagem"] ?? null); if (is_array($m)) { echo json_encode($m, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } else { echo trim((string) $m); }' "$1"
}

run_case() {
  local titulo="$1"
  local path="$2"
  local before_id
  local http_code
  local row
  local request_id
  local status_proc
  local versao
  local revisao
  local data_ult

  print_title "${titulo}"
  before_id="$(pg_query "SELECT coalesce(max(id_t99001), 0)::text FROM public.t99001 WHERE c_token_hash = '$(token_hash)'")"
  http_code="$(
    curl -sS -o "${tmp_body}" -w '%{http_code}' -X GET "${BASE_URL}${path}" \
      -H "X-Api-Token: ${API_TOKEN}" \
      -H 'Accept: application/ld+json'
  )"
  row="$(pg_query "SELECT u_c_request_id || '|' || coalesce(si_status_processamento::text,'') || '|' || coalesce(c_versao_programa,'') || '|' || coalesce(c_revisao_programa,'') || '|' || coalesce(to_char(dt_hr_ult_atu_programa, 'YYYY-MM-DD\"T\"HH24:MI:SS'), '') FROM public.t99001 WHERE c_token_hash = '$(token_hash)' AND id_t99001 > ${before_id} ORDER BY id_t99001 ASC LIMIT 1")"
  request_id="${row%%|*}"
  row="${row#*|}"
  status_proc="${row%%|*}"
  row="${row#*|}"
  versao="${row%%|*}"
  row="${row#*|}"
  revisao="${row%%|*}"
  data_ult="${row#*|}"

  echo "HTTP: ${http_code}"
  echo "request_id: ${request_id}"
  echo "status_processamento: ${status_proc}"
  echo "versao_programa: ${versao}"
  echo "revisao_programa: ${revisao}"
  echo "data_ult_atu_programa: ${data_ult}"
  echo "mensagem: $(json_message "${tmp_body}")"
}

run_case "Diagnostico NFe - OpenSSL info" "/nfe/ferramentas/openssl-info"
run_case "Diagnostico NFe - Obter certificados" "/nfe/ferramentas/obter-certificados"
run_case "Diagnostico NFe - Status do servico" "/nfe/consultas/status-servico"
run_case "Diagnostico NFe - Consulta cadastro CNPJ" "/nfe/consultas/consulta-cadastro?AcUF=${UF}&AnDocumento=${CNPJ}&TipoDocumento=cpf_cnpj"
run_case "Diagnostico NFe - Consultar com chave" "/nfe/consultas/consultar-com-chave?eChaveOuNFe=${CHAVE_NFE}"
