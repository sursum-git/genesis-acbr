#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
. "${SCRIPT_DIR}/_common.sh"

PHP_BIN="${PHP_BIN:-php}"
ASYNC_CONFIG_PREFIX="${ASYNC_CONFIG_PREFIX:-teste_multi_async}"
RUN_WORKER="${RUN_WORKER:-1}"
WORKER_LIMIT="${WORKER_LIMIT:-20}"
WAIT_CYCLES="${WAIT_CYCLES:-20}"
WAIT_SLEEP="${WAIT_SLEEP:-1}"

if [[ -z "${API_TOKEN}" ]]; then
  echo "API_TOKEN nao definido e nao foi possivel carregar automaticamente da t00002." >&2
  exit 1
fi

if ! command -v psql >/dev/null 2>&1; then
  echo "psql nao encontrado. Este teste depende de acesso ao PostgreSQL." >&2
  exit 1
fi

request_ids_file="$(mktemp)"
status_body_file="$(mktemp)"
response_body_file="$(mktemp)"
trap 'rm -f "${request_ids_file}" "${status_body_file}" "${response_body_file}"; cleanup_async_configs' EXIT

pg_query() {
  local sql="$1"
  PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    psql -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -Atqc "${sql}"
}

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

extract_request_id() {
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

json_field() {
  local file="$1"
  local field="$2"
  php -r '
    $d = json_decode(file_get_contents($argv[1]), true);
    $field = $argv[2];
    $value = is_array($d) && array_key_exists($field, $d) ? $d[$field] : "";
    if (is_array($value)) {
      echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
      echo (string) $value;
    }
  ' "$file" "$field"
}

cleanup_async_configs() {
  pg_query "DELETE FROM public.t99003 WHERE c_chave_configuracao LIKE '$(sql_escape "${ASYNC_CONFIG_PREFIX}")_%'" >/dev/null 2>&1 || true
}

force_async_path() {
  local key="$1"
  local path="$2"
  pg_query "
    DELETE FROM public.t99003 WHERE c_chave_configuracao = '$(sql_escape "${key}")';
    INSERT INTO public.t99003 (c_chave_configuracao, c_caminho, c_nome_operacao, c_modo_execucao, log_ativo, dt_hr_atu)
    VALUES ('$(sql_escape "${key}")', '$(sql_escape "${path}")', NULL, 'async', TRUE, now());
  " >/dev/null
}

call_get_async() {
  local label="$1"
  local path="$2"
  local config_key="$3"
  local http_code request_id

  force_async_path "${config_key}" "${path%%\?*}"
  http_code="$(
    curl -sS -o "${response_body_file}" -w '%{http_code}' -X GET "${BASE_URL}${path}" \
      -H "X-Api-Token: ${API_TOKEN}" \
      -H 'Accept: application/ld+json'
  )"

  if [[ "${http_code}" != "202" ]]; then
    echo "FALHA: ${label} deveria retornar 202, retornou ${http_code}." >&2
    cat "${response_body_file}" >&2
    exit 1
  fi

  request_id="$(extract_request_id "${response_body_file}")"
  printf '%s|%s|%s\n' "${label}" "${path}" "${request_id}" >> "${request_ids_file}"
  echo "ENFILEIRADO: ${label} request_id=${request_id}"
}

wait_for_final_status() {
  local request_id="$1"
  local cycle=1
  local status=""

  while [[ "${cycle}" -le "${WAIT_CYCLES}" ]]; do
    if [[ "${RUN_WORKER}" == "1" ]]; then
      "${PHP_BIN}" "${PROJECT_DIR}/bin/console" app:api-request-worker --once --limit="${WORKER_LIMIT}" >/dev/null
    fi

    status="$(pg_query "SELECT coalesce(si_status_processamento::text, '') FROM public.t99001 WHERE u_c_request_id = '$(sql_escape "${request_id}")' LIMIT 1")"
    if [[ "${status}" != "1" && "${status}" != "2" && -n "${status}" ]]; then
      printf '%s' "${status}"
      return 0
    fi

    sleep "${WAIT_SLEEP}"
    cycle=$((cycle + 1))
  done

  printf '%s' "${status}"
  return 1
}

consult_status_endpoint() {
  local label="$1"
  local request_id="$2"
  local http_code status_proc status_http

  http_code="$(
    curl -sS -o "${status_body_file}" -w '%{http_code}' -X GET "${BASE_URL}/requests/${request_id}" \
      -H "X-Api-Token: ${API_TOKEN}" \
      -H 'Accept: application/json'
  )"

  if [[ "${http_code}" != "200" ]]; then
    echo "FALHA: /requests/${request_id} para ${label} retornou ${http_code}." >&2
    cat "${status_body_file}" >&2
    exit 1
  fi

  status_proc="$(json_field "${status_body_file}" "si_status_processamento")"
  status_http="$(json_field "${status_body_file}" "si_status_http")"
  echo "STATUS: ${label} request_id=${request_id} processamento=${status_proc} http=${status_http}"
}

cleanup_async_configs

print_title "Disparando varios endpoints em async"
call_get_async "NFe OpenSSL info" "/nfe/ferramentas/openssl-info" "${ASYNC_CONFIG_PREFIX}_nfe_openssl"
call_get_async "NFe obter certificados" "/nfe/ferramentas/obter-certificados" "${ASYNC_CONFIG_PREFIX}_nfe_certificados"
call_get_async "NFe status servico" "/nfe/consultas/status-servico" "${ASYNC_CONFIG_PREFIX}_nfe_status"
call_get_async "NFe consulta cadastro" "/nfe/consultas/consulta-cadastro?AcUF=${UF}&AnDocumento=${CNPJ}&TipoDocumento=cpf_cnpj" "${ASYNC_CONFIG_PREFIX}_nfe_consulta_cadastro"

print_title "Processando fila e consultando retorno por request_id"
while IFS='|' read -r label path request_id; do
  final_status="$(wait_for_final_status "${request_id}" || true)"
  if [[ "${final_status}" == "1" || "${final_status}" == "2" || -z "${final_status}" ]]; then
    echo "FALHA: ${label} nao finalizou. status_atual=${final_status}" >&2
    exit 1
  fi

  consult_status_endpoint "${label}" "${request_id}"
done < "${request_ids_file}"

print_title "Resumo"
cat "${request_ids_file}"
echo
echo "OK: fluxo async com multiplos endpoints e consulta por request_id validado."
