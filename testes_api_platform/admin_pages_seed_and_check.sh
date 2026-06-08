#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
. "${SCRIPT_DIR}/_common.sh"

SEED_LABEL="${SEED_LABEL:-admin_pages_seed}"
ADMIN_BASE_URL="${ADMIN_BASE_URL:-${BASE_URL}}"
WEBHOOK_TEST_URL="${WEBHOOK_TEST_URL:-http://127.0.0.1:9/webhook-teste-admin}"
PSQL_BIN="${PSQL_BIN:-psql}"

if ! command -v "${PSQL_BIN}" >/dev/null 2>&1; then
  echo "psql nao encontrado." >&2
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "curl nao encontrado." >&2
  exit 1
fi

pg_query() {
  local sql="$1"
  PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    "${PSQL_BIN}" -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -Atqc "${sql}"
}

pg_exec_file() {
  local file="$1"
  PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    "${PSQL_BIN}" -v ON_ERROR_STOP=1 -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -f "${file}"
}

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

token_hash() {
  printf '%s' "${API_TOKEN}" | sha256sum | awk '{print $1}'
}

uuid() {
  if [[ -r /proc/sys/kernel/random/uuid ]]; then
    cat /proc/sys/kernel/random/uuid
  else
    php -r 'echo Symfony\Component\Uid\Uuid::v4()->toRfc4122(), PHP_EOL;' 2>/dev/null || date +%s%N
  fi
}

http_status() {
  local path="$1"
  curl -sS -o /tmp/admin_page_check_body.html -w '%{http_code}' "${ADMIN_BASE_URL}${path}"
}

assert_http_page() {
  local path="$1"
  local expected_text="$2"
  local code

  code="$(http_status "${path}")"
  if [[ "${code}" != "200" ]]; then
    echo "FALHA: ${path} retornou HTTP ${code}" >&2
    exit 1
  fi

  if grep -q 'alert alert-warning' /tmp/admin_page_check_body.html; then
    echo "FALHA: ${path} renderizou alerta operacional." >&2
    grep -n 'alert alert-warning' /tmp/admin_page_check_body.html >&2 || true
    exit 1
  fi

  if ! grep -q "${expected_text}" /tmp/admin_page_check_body.html; then
    echo "FALHA: ${path} nao contem texto esperado: ${expected_text}" >&2
    exit 1
  fi

  echo "OK: ${path}"
}

print_title "Aplicando schema operacional"
pg_exec_file "${PROJECT_DIR}/sql/dfe_schema.sql" >/dev/null

if [[ -z "${API_TOKEN}" ]]; then
  echo "API_TOKEN nao definido e nao foi possivel carregar automaticamente da t00002." >&2
  exit 1
fi

assinante_count="$(pg_query "SELECT COUNT(*)::text FROM public.t00002 WHERE c_identificador = '$(sql_escape "${ASSINANTE_IDENTIFICADOR}")'")"
if [[ "${assinante_count}" == "0" ]]; then
  echo "FALHA: assinante ${ASSINANTE_IDENTIFICADOR} nao encontrado em t00002." >&2
  exit 1
fi

TOKEN_HASH="$(token_hash)"
ASSINANTE_JSON="{\"c_identificador\":\"$(sql_escape "${ASSINANTE_IDENTIFICADOR}")\",\"c_nome\":\"Assinante teste ${SEED_LABEL}\"}"
NOW_SQL="now()"

print_title "Limpando dados anteriores do seed"
pg_query "DELETE FROM public.t00003 WHERE c_nome = 'Webhook Teste Admin';"
pg_query "DELETE FROM public.t99003 WHERE c_chave_configuracao = '${SEED_LABEL}_openssl_async';"
pg_query "DELETE FROM public.t99005 WHERE t_observacao LIKE '${SEED_LABEL}:%';"
pg_query "DELETE FROM public.t99001 WHERE c_rota LIKE 'seed.admin.%';"

print_title "Alimentando configuracao de workers"
pg_query "
INSERT INTO public.t99005 (qtd_workers, dt_inicio_vigencia, dt_fim_vigencia, log_ativo, t_observacao, dt_hr_atu)
VALUES (3, ${NOW_SQL} - interval '1 hour', ${NOW_SQL} + interval '7 days', TRUE, '${SEED_LABEL}: capacidade vigente para teste das telas', ${NOW_SQL})
"

print_title "Alimentando regra de execucao"
pg_query "
INSERT INTO public.t99003 (c_chave_configuracao, c_caminho, c_nome_operacao, c_modo_execucao, log_ativo, dt_hr_atu)
VALUES ('${SEED_LABEL}_openssl_async', '/nfe/ferramentas/openssl-info', NULL, 'async', TRUE, ${NOW_SQL});
"

print_title "Alimentando webhooks e vinculos"
WEBHOOK_ID="$(
pg_query "
WITH upsert AS (
  INSERT INTO public.t00003 (c_nome, c_url, c_metodo_http, t_headers_json, t_secret, si_timeout_segundos, log_ativo, dt_hr_atu)
  VALUES ('Webhook Teste Admin', '$(sql_escape "${WEBHOOK_TEST_URL}")', 'POST', '{\"X-Teste\":\"${SEED_LABEL}\"}', 'secret_${SEED_LABEL}', 3, TRUE, ${NOW_SQL})
  RETURNING id_t00003
)
SELECT id_t00003 FROM upsert
UNION ALL
SELECT id_t00003 FROM public.t00003 WHERE c_nome = 'Webhook Teste Admin'
LIMIT 1;
"
)"

pg_query "
DELETE FROM public.t00004 WHERE c_assinante_identificador = '$(sql_escape "${ASSINANTE_IDENTIFICADOR}")' AND t00003_id = ${WEBHOOK_ID};
INSERT INTO public.t00004 (c_assinante_identificador, t00003_id, c_programa, c_evento, c_caminho, c_modo_execucao, log_ativo, dt_hr_atu)
VALUES
  ('$(sql_escape "${ASSINANTE_IDENTIFICADOR}")', ${WEBHOOK_ID}, 'nfe', 'request.completed', '/nfe/ferramentas/openssl-info', 'async', TRUE, ${NOW_SQL}),
  ('$(sql_escape "${ASSINANTE_IDENTIFICADOR}")', ${WEBHOOK_ID}, 'nfe', 'request.failed', '/nfe/ferramentas/openssl-info', 'async', TRUE, ${NOW_SQL});
"

BINDING_COMPLETED_ID="$(pg_query "SELECT id_t00004::text FROM public.t00004 WHERE c_assinante_identificador = '$(sql_escape "${ASSINANTE_IDENTIFICADOR}")' AND t00003_id = ${WEBHOOK_ID} AND c_evento = 'request.completed' ORDER BY id_t00004 DESC LIMIT 1")"
BINDING_FAILED_ID="$(pg_query "SELECT id_t00004::text FROM public.t00004 WHERE c_assinante_identificador = '$(sql_escape "${ASSINANTE_IDENTIFICADOR}")' AND t00003_id = ${WEBHOOK_ID} AND c_evento = 'request.failed' ORDER BY id_t00004 DESC LIMIT 1")"

print_title "Alimentando fila, tentativas, eventos e entregas"
REQUEST_COMPLETED="$(uuid)"
REQUEST_FAILED="$(uuid)"
REQUEST_QUEUED="$(uuid)"
REQUEST_PROCESSING="$(uuid)"

pg_query "
INSERT INTO public.t99001 (
  u_c_request_id, c_metodo, c_caminho, c_cod_programa, c_nome_programa, c_rota, c_nome_operacao, c_modo_execucao,
  c_token_hash, t_query_string, t_corpo_requisicao, t_headers_requisicao, t_corpo_resposta, t_headers_resposta,
  t_assinante_json, si_status_processamento, si_status_http, i_tempo_processamento_ms,
  dt_hr_recebimento, dt_hr_ini_processamento, dt_hr_fim_processamento, dt_hr_atu
)
VALUES
  ('${REQUEST_COMPLETED}', 'GET', '/nfe/ferramentas/openssl-info', 'nfe', 'NFe', 'seed.admin.completed', 'seed.completed', 'async', '${TOKEN_HASH}', NULL, NULL, '{\"x-api-token\":\"[mascarado]\"}', '{\"ok\":true,\"seed\":\"${SEED_LABEL}\"}', 'HTTP/1.1 200 OK', '${ASSINANTE_JSON}', 3, 200, 120, ${NOW_SQL} - interval '20 minutes', ${NOW_SQL} - interval '19 minutes', ${NOW_SQL} - interval '18 minutes', ${NOW_SQL} - interval '18 minutes'),
  ('${REQUEST_FAILED}', 'GET', '/nfe/ferramentas/openssl-info', 'nfe', 'NFe', 'seed.admin.failed', 'seed.failed', 'async', '${TOKEN_HASH}', NULL, NULL, '{\"x-api-token\":\"[mascarado]\"}', '{\"erro\":\"falha simulada\"}', 'HTTP/1.1 500 Error', '${ASSINANTE_JSON}', 4, 500, 340, ${NOW_SQL} - interval '15 minutes', ${NOW_SQL} - interval '14 minutes', ${NOW_SQL} - interval '13 minutes', ${NOW_SQL} - interval '13 minutes'),
  ('${REQUEST_QUEUED}', 'GET', '/nfe/ferramentas/openssl-info', 'nfe', 'NFe', 'seed.admin.queued', 'seed.queued', 'async', '${TOKEN_HASH}', NULL, NULL, '{\"x-api-token\":\"[mascarado]\"}', NULL, NULL, '${ASSINANTE_JSON}', 1, NULL, NULL, ${NOW_SQL} - interval '10 minutes', NULL, NULL, ${NOW_SQL} - interval '10 minutes'),
  ('${REQUEST_PROCESSING}', 'GET', '/nfe/ferramentas/openssl-info', 'nfe', 'NFe', 'seed.admin.processing', 'seed.processing', 'async', '${TOKEN_HASH}', NULL, NULL, '{\"x-api-token\":\"[mascarado]\"}', NULL, NULL, '${ASSINANTE_JSON}', 2, NULL, NULL, ${NOW_SQL} - interval '8 minutes', ${NOW_SQL} - interval '7 minutes', NULL, ${NOW_SQL} - interval '7 minutes');
"

REQ_COMPLETED_ID="$(pg_query "SELECT id_t99001::text FROM public.t99001 WHERE u_c_request_id = '${REQUEST_COMPLETED}'")"
REQ_FAILED_ID="$(pg_query "SELECT id_t99001::text FROM public.t99001 WHERE u_c_request_id = '${REQUEST_FAILED}'")"
REQ_PROCESSING_ID="$(pg_query "SELECT id_t99001::text FROM public.t99001 WHERE u_c_request_id = '${REQUEST_PROCESSING}'")"

pg_query "
INSERT INTO public.t99002 (t99001_id, si_num_tentativa, si_status_processamento, c_cod_programa, c_nome_programa, si_status_http, t_corpo_resposta, t_erro, dt_hr_ini_processamento, dt_hr_fim_processamento, dt_hr_atu)
VALUES
  (${REQ_COMPLETED_ID}, 1, 3, 'nfe', 'NFe', 200, '{\"ok\":true}', NULL, ${NOW_SQL} - interval '19 minutes', ${NOW_SQL} - interval '18 minutes', ${NOW_SQL} - interval '18 minutes'),
  (${REQ_FAILED_ID}, 1, 4, 'nfe', 'NFe', 500, NULL, 'Falha simulada para teste de tela', ${NOW_SQL} - interval '14 minutes', ${NOW_SQL} - interval '13 minutes', ${NOW_SQL} - interval '13 minutes'),
  (${REQ_PROCESSING_ID}, 1, 2, 'nfe', 'NFe', NULL, NULL, NULL, ${NOW_SQL} - interval '7 minutes', NULL, ${NOW_SQL} - interval '7 minutes');

INSERT INTO public.t99004 (t99001_id, c_evento, t_detalhe, dt_hr_evento)
VALUES
  (${REQ_COMPLETED_ID}, 'seed.created', '${SEED_LABEL}: requisicao concluida de exemplo', ${NOW_SQL} - interval '18 minutes'),
  (${REQ_FAILED_ID}, 'seed.created', '${SEED_LABEL}: requisicao falha de exemplo', ${NOW_SQL} - interval '13 minutes'),
  (${REQ_PROCESSING_ID}, 'worker.started', '${SEED_LABEL}: processamento simulado', ${NOW_SQL} - interval '7 minutes');
"

pg_query "
INSERT INTO public.t99006 (t00004_id, t99001_id, c_status_entrega, si_num_tentativa, si_status_http, t_payload_json, t_headers_resposta, t_corpo_resposta, t_erro, dt_hr_proxima_tentativa, dt_hr_ini_processamento, dt_hr_fim_processamento, dt_hr_atu)
VALUES
  (${BINDING_COMPLETED_ID}, ${REQ_COMPLETED_ID}, 'success', 1, 200, '{\"request_id\":\"${REQUEST_COMPLETED}\",\"seed\":\"${SEED_LABEL}\"}', 'HTTP/1.1 200 OK', 'ok', NULL, NULL, ${NOW_SQL} - interval '17 minutes', ${NOW_SQL} - interval '17 minutes', ${NOW_SQL} - interval '17 minutes'),
  (${BINDING_FAILED_ID}, ${REQ_FAILED_ID}, 'failed_final', 3, 500, '{\"request_id\":\"${REQUEST_FAILED}\",\"seed\":\"${SEED_LABEL}\"}', 'HTTP/1.1 500 Error', 'erro', 'Falha simulada para teste de reenfileirar', NULL, ${NOW_SQL} - interval '12 minutes', ${NOW_SQL} - interval '12 minutes', ${NOW_SQL} - interval '12 minutes')
ON CONFLICT (t00004_id, t99001_id) DO UPDATE
SET c_status_entrega = EXCLUDED.c_status_entrega,
    si_num_tentativa = EXCLUDED.si_num_tentativa,
    si_status_http = EXCLUDED.si_status_http,
    t_erro = EXCLUDED.t_erro,
    dt_hr_atu = EXCLUDED.dt_hr_atu;
"

print_title "Validando paginas administrativas"
assert_http_page "/configuracao-execucao" "Configuração de Execução"
assert_http_page "/capacidade-workers" "Capacidade de Workers"
assert_http_page "/assinantes" "Assinantes"
assert_http_page "/webhooks" "Webhooks"
assert_http_page "/monitor-workers" "Monitor de Workers"
assert_http_page "/consulta-requisicoes?requisicao=${REQUEST_COMPLETED}" "${REQUEST_COMPLETED}"

print_title "Resumo dos dados criados"
echo "assinante: ${ASSINANTE_IDENTIFICADOR}"
echo "webhook_id: ${WEBHOOK_ID}"
echo "request_completed: ${REQUEST_COMPLETED}"
echo "request_failed: ${REQUEST_FAILED}"
echo "request_queued: ${REQUEST_QUEUED}"
echo "request_processing: ${REQUEST_PROCESSING}"
echo
echo "OK: seed e validacao das telas administrativas concluidos."
