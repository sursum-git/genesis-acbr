#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/_common.sh"

TEST_PATH_SYNC="${TEST_PATH_SYNC:-/nfe/ferramentas/openssl-info}"
TEST_PATH_ASYNC="${TEST_PATH_ASYNC:-/nfe/ferramentas/openssl-info}"
ASYNC_CONFIG_KEY="${ASYNC_CONFIG_KEY:-teste_auditoria_openssl_info}"
PHP_BIN="${PHP_BIN:-php}"

if [[ -z "${API_TOKEN}" ]]; then
  echo "API_TOKEN nao definido e nao foi possivel carregar automaticamente da t00002." >&2
  exit 1
fi

if ! command -v psql >/dev/null 2>&1; then
  echo "psql nao encontrado. Este teste depende de acesso ao PostgreSQL para verificar a t99001." >&2
  exit 1
fi

request_tmp="$(mktemp)"
response_tmp="$(mktemp)"

pg_query() {
  local sql="$1"
  PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    psql -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -Atqc "${sql}"
}

token_hash() {
  printf '%s' "${API_TOKEN}" | sha256sum | awk '{print $1}'
}

json_get() {
  local file="$1"
  local expr="$2"
  php -r '$d=json_decode(file_get_contents($argv[1]), true); $parts=explode(".", $argv[2]); $v=$d; foreach($parts as $p){ if(!is_array($v) || !array_key_exists($p,$v)){ exit(2);} $v=$v[$p]; } if(is_array($v)){ echo json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } else { echo (string)$v; }' "$file" "$expr"
}

extract_async_request_id() {
  local file="$1"
  php -r '
    $d = json_decode(file_get_contents($argv[1]), true);
    $candidates = [
      $d["request_id"] ?? null,
      $d["resultado"]["request_id"] ?? null,
      $d["resultado"]["member"][0] ?? null,
      $d["member"][0] ?? null,
    ];
    foreach ($candidates as $candidate) {
      if (is_string($candidate) && preg_match("/^[0-9a-f-]{36}$/i", $candidate) === 1) {
        echo $candidate;
        exit(0);
      }
    }
    exit(2);
  ' "$file"
}

assert_equals() {
  local expected="$1"
  local actual="$2"
  local message="$3"
  if [[ "${expected}" != "${actual}" ]]; then
    echo "FALHA: ${message}. Esperado='${expected}' Atual='${actual}'" >&2
    exit 1
  fi
}

assert_not_empty() {
  local value="$1"
  local message="$2"
  if [[ -z "${value}" ]]; then
    echo "FALHA: ${message}" >&2
    exit 1
  fi
}

cleanup_async_config() {
  pg_query "DELETE FROM public.t99003 WHERE c_chave_configuracao = '${ASYNC_CONFIG_KEY}'"
}

wait_for_async_processing() {
  local request_id="$1"
  local max_cycles="${2:-10}"
  local cycle=1
  local current_status=""

  while [[ "${cycle}" -le "${max_cycles}" ]]; do
    ${PHP_BIN} bin/console app:api-request-worker --once --limit=1 >/dev/null
    current_status="$(pg_query "SELECT si_status_processamento::text FROM public.t99001 WHERE u_c_request_id = '${request_id}' LIMIT 1")"

    if [[ "${current_status}" != "1" ]]; then
      printf '%s' "${current_status}"
      return 0
    fi

    cycle=$((cycle + 1))
  done

  printf '%s' "${current_status}"
  return 1
}

cleanup_all() {
  cleanup_async_config
  rm -f "${request_tmp}" "${response_tmp}"
}

trap cleanup_all EXIT

cleanup_async_config

print_title "Teste 1 - Gravacao de requisicao sincrona na t99001"
start_sync="$(date -u '+%Y-%m-%d %H:%M:%S')"
sync_code="$(
  curl -sS -o "${response_tmp}" -w '%{http_code}' -X GET "${BASE_URL}${TEST_PATH_SYNC}" \
    -H "X-Api-Token: ${API_TOKEN}" \
    -H 'Accept: application/ld+json'
)"
echo "HTTP sync: ${sync_code}"
if [[ "${sync_code}" == "401" ]]; then
  echo "FALHA: token rejeitado no teste sincrono." >&2
  cat "${response_tmp}" >&2
  exit 1
fi

sync_row="$(pg_query "SELECT u_c_request_id || '|' || coalesce(si_status_http::text,'') || '|' || si_status_processamento::text FROM public.t99001 WHERE c_caminho = '${TEST_PATH_SYNC}' AND c_token_hash = '$(token_hash)' AND dt_hr_recebimento >= timestamp '${start_sync}' ORDER BY id_t99001 DESC LIMIT 1")"
assert_not_empty "${sync_row}" "nao encontrou registro na t99001 para a chamada sincrona"
sync_request_id="${sync_row%%|*}"
sync_rest="${sync_row#*|}"
sync_status_http="${sync_rest%%|*}"
sync_status_proc="${sync_rest##*|}"
assert_equals "${sync_code}" "${sync_status_http}" "status HTTP gravado na t99001 nao corresponde ao retorno da API"
echo "request_id sync: ${sync_request_id}"
echo "status_processamento sync: ${sync_status_proc}"

print_title "Teste 2 - Gravacao de requisicao assincrona e processamento por worker"
cleanup_async_config
pg_query "INSERT INTO public.t99003 (c_chave_configuracao, c_caminho, c_nome_operacao, c_modo_execucao, log_ativo, dt_hr_atu) VALUES ('${ASYNC_CONFIG_KEY}', '${TEST_PATH_ASYNC}', NULL, 'async', TRUE, now())"

async_code="$(
  curl -sS -o "${request_tmp}" -w '%{http_code}' -X GET "${BASE_URL}${TEST_PATH_ASYNC}" \
    -H "X-Api-Token: ${API_TOKEN}" \
    -H 'Accept: application/ld+json'
)"
echo "HTTP async: ${async_code}"
assert_equals "202" "${async_code}" "endpoint em modo async deveria retornar 202"

async_request_id="$(extract_async_request_id "${request_tmp}")"
assert_not_empty "${async_request_id}" "payload async nao retornou resultado.request_id"
echo "request_id async: ${async_request_id}"

queued_status="$(pg_query "SELECT si_status_processamento::text FROM public.t99001 WHERE u_c_request_id = '${async_request_id}' LIMIT 1")"
assert_equals "1" "${queued_status}" "requisicao async nao ficou enfileirada na t99001"

print_title "Executando worker uma vez"
final_status_after_worker="$(wait_for_async_processing "${async_request_id}" 10 || true)"

final_row="$(pg_query "SELECT si_status_http::text || '|' || si_status_processamento::text FROM public.t99001 WHERE u_c_request_id = '${async_request_id}' LIMIT 1")"
assert_not_empty "${final_row}" "nao encontrou a requisicao async apos o worker"
final_http="${final_row%%|*}"
final_status="${final_row##*|}"
attempt_count="$(pg_query "SELECT COUNT(*)::text FROM public.t99002 t2 INNER JOIN public.t99001 t1 ON t1.id_t99001 = t2.t99001_id WHERE t1.u_c_request_id = '${async_request_id}'")"
assert_not_empty "${attempt_count}" "nao encontrou tentativas na t99002"
if [[ "${attempt_count}" == "0" ]]; then
  echo "FALHA: worker nao gerou registro em t99002." >&2
  exit 1
fi
if [[ "${final_status_after_worker}" == "1" ]]; then
  echo "FALHA: requisicao async permaneceu enfileirada apos os ciclos do worker." >&2
  exit 1
fi

print_title "Consulta do endpoint /requests/{requestId}"
status_code="$(
  curl -sS -o "${response_tmp}" -w '%{http_code}' -X GET "${BASE_URL}/requests/${async_request_id}" \
    -H "X-Api-Token: ${API_TOKEN}" \
    -H 'Accept: application/json'
)"
assert_equals "200" "${status_code}" "endpoint de status nao retornou 200"

echo "status_http final: ${final_http}"
echo "status_processamento final: ${final_status}"
echo "tentativas registradas: ${attempt_count}"
echo
echo "OK: auditoria de requisicoes validada."
