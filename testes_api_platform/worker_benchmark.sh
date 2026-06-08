#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/_common.sh"

TOTAL_REQUESTS="${TOTAL_REQUESTS:-1000}"
WORKER_COUNTS="${WORKER_COUNTS:-2 3 4 5 6 7 8 9 10}"
REQUEST_CONCURRENCY="${REQUEST_CONCURRENCY:-30}"
TEST_PATH="${TEST_PATH:-/nfe/ferramentas/openssl-info}"
ASYNC_CONFIG_KEY_PREFIX="${ASYNC_CONFIG_KEY_PREFIX:-benchmark_workers}"
PHP_BIN="${PHP_BIN:-php}"
LOG_DIR="${LOG_DIR:-${SCRIPT_DIR}/../var/benchmarks}"
LOG_FILE="${LOG_FILE:-${LOG_DIR}/worker_benchmark_$(date +%Y%m%d_%H%M%S).log}"
SUMMARY_FILE="${SUMMARY_FILE:-${LOG_FILE%.log}.tsv}"

mkdir -p "${LOG_DIR}"
exec > >(tee -a "${LOG_FILE}") 2>&1

if [[ -z "${API_TOKEN}" ]]; then
  echo "API_TOKEN nao definido e nao foi possivel carregar automaticamente da t00002." >&2
  exit 1
fi

if ! command -v psql >/dev/null 2>&1; then
  echo "psql nao encontrado. Este benchmark depende de acesso ao PostgreSQL." >&2
  exit 1
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT

pg_query() {
  PGPASSWORD="${AUDIT_DB_PASSWORD}" \
    psql -h "${AUDIT_DB_HOST}" -p "${AUDIT_DB_PORT}" -U "${AUDIT_DB_USER}" -d "${AUDIT_DB_NAME}" -Atqc "$1"
}

token_hash() {
  printf '%s' "${API_TOKEN}" | sha256sum | awk '{print $1}'
}

cleanup_async_config() {
  local config_key="$1"
  pg_query "DELETE FROM public.t99003 WHERE c_chave_configuracao = '${config_key}'"
}

cleanup_async_configs() {
  pg_query "DELETE FROM public.t99003 WHERE c_chave_configuracao LIKE '${ASYNC_CONFIG_KEY_PREFIX}_%'" >/dev/null
}

set_worker_capacity() {
  local workers="$1"
  pg_query "
    UPDATE public.t99005
    SET dt_fim_vigencia = now() - interval '1 second',
        dt_hr_atu = now()
    WHERE log_ativo = TRUE
      AND (dt_fim_vigencia IS NULL OR dt_fim_vigencia >= now());

    INSERT INTO public.t99005 (qtd_workers, dt_inicio_vigencia, dt_fim_vigencia, log_ativo, t_observacao, dt_hr_atu)
    VALUES (${workers}, now(), NULL, TRUE, 'benchmark ${TOTAL_REQUESTS} requisicoes em ${workers} workers - ${TEST_PATH}', now());
  " >/dev/null
}

queue_requests() {
  local output_file="$1"
  seq "${TOTAL_REQUESTS}" | xargs -I{} -P "${REQUEST_CONCURRENCY}" bash -lc '
    curl -sS -X GET "'"${BASE_URL}${TEST_PATH}"'" \
      -H "X-Api-Token: '"${API_TOKEN}"'" \
      -H "Accept: application/ld+json" |
    php -r '"'"'$d=json_decode(stream_get_contents(STDIN), true); $id=$d["request_id"] ?? ($d["resultado"]["request_id"] ?? ""); if (is_string($id) && $id !== "") { echo $id, PHP_EOL; }'"'"'
  ' > "${output_file}"
}

wait_for_requests() {
  local request_ids_csv="$1"
  local status_sql
  status_sql="SELECT COUNT(*)::text FROM public.t99001 WHERE u_c_request_id IN (${request_ids_csv}) AND si_status_processamento IN (1,2)"

  while true; do
    local pending
    pending="$(pg_query "${status_sql}")"
    if [[ "${pending}" == "0" ]]; then
      break
    fi
    sleep 1
  done
}

run_scenario() {
  local workers="$1"
  local config_key="${ASYNC_CONFIG_KEY_PREFIX}_${workers}"
  local request_ids_file="${tmp_dir}/request_ids_${workers}.txt"
  local request_ids_csv
  local enqueue_started_at enqueue_finished_at processing_started_at processing_finished_at
  local duration_requests duration_processing throughput avg_ms max_ms p95_ms failed_count

  print_title "Benchmark com ${workers} worker(s)"
  cleanup_async_config "${config_key}"
  set_worker_capacity "${workers}"
  pg_query "INSERT INTO public.t99003 (c_chave_configuracao, c_caminho, c_nome_operacao, c_modo_execucao, log_ativo, dt_hr_atu) VALUES ('${config_key}', '${TEST_PATH}', NULL, 'async', TRUE, now())"

  enqueue_started_at="$(date +%s)"
  queue_requests "${request_ids_file}"
  enqueue_finished_at="$(date +%s)"

  request_ids_csv="$(paste -sd, "${request_ids_file}" | sed "s/[^,]*/'&'/g")"
  if [[ -z "${request_ids_csv}" ]]; then
    echo "Nenhum request_id retornado. Abortando." >&2
    exit 1
  fi

  processing_started_at="$(date +%s)"
  local pids=()
  for _ in $(seq 1 "${workers}"); do
    ${PHP_BIN} bin/console app:api-request-worker --once --limit="${TOTAL_REQUESTS}" >/dev/null &
    pids+=($!)
  done

  wait_for_requests "${request_ids_csv}"

  for pid in "${pids[@]}"; do
    wait "${pid}" || true
  done
  processing_finished_at="$(date +%s)"

  duration_requests=$((enqueue_finished_at - enqueue_started_at))
  duration_processing=$((processing_finished_at - processing_started_at))
  throughput="$(awk "BEGIN { if (${duration_processing} <= 0) print 0; else printf \"%.2f\", ${TOTAL_REQUESTS}/${duration_processing} }")"

  avg_ms="$(pg_query "SELECT coalesce(round(avg(i_tempo_processamento_ms)),0)::text FROM public.t99001 WHERE u_c_request_id IN (${request_ids_csv})")"
  max_ms="$(pg_query "SELECT coalesce(max(i_tempo_processamento_ms),0)::text FROM public.t99001 WHERE u_c_request_id IN (${request_ids_csv})")"
  p95_ms="$(pg_query "SELECT coalesce(round(percentile_cont(0.95) WITHIN GROUP (ORDER BY i_tempo_processamento_ms)),0)::text FROM public.t99001 WHERE u_c_request_id IN (${request_ids_csv}) AND i_tempo_processamento_ms IS NOT NULL")"
  failed_count="$(pg_query "SELECT count(*)::text FROM public.t99001 WHERE u_c_request_id IN (${request_ids_csv}) AND si_status_processamento = 4")"

  printf 'workers=%s queued_seconds=%s processing_seconds=%s throughput_req_s=%s avg_ms=%s max_ms=%s p95_ms=%s failed=%s\n' \
    "${workers}" "${duration_requests}" "${duration_processing}" "${throughput}" "${avg_ms}" "${max_ms}" "${p95_ms}" "${failed_count}"
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "${workers}" "${duration_requests}" "${duration_processing}" "${throughput}" "${avg_ms}" "${p95_ms}" "${max_ms}" "${failed_count}" >> "${SUMMARY_FILE}"

  cleanup_async_config "${config_key}"
}

cleanup_async_configs
printf 'Benchmark iniciado em %s\n' "$(date -Is)"
printf 'base_url=%s\nendpoint=%s\ntotal_requests=%s\nrequest_concurrency=%s\nworker_counts=%s\nlog_file=%s\nsummary_file=%s\n' \
  "${BASE_URL}" "${TEST_PATH}" "${TOTAL_REQUESTS}" "${REQUEST_CONCURRENCY}" "${WORKER_COUNTS}" "${LOG_FILE}" "${SUMMARY_FILE}"
printf 'workers\tqueued_seconds\tprocessing_seconds\tthroughput_req_s\tavg_ms\tp95_ms\tmax_ms\tfailed\n' > "${SUMMARY_FILE}"

for workers in ${WORKER_COUNTS}; do
  run_scenario "${workers}"
done

print_title "Resumo TSV"
cat "${SUMMARY_FILE}"
printf '\nBenchmark finalizado em %s\n' "$(date -Is)"
printf 'Log salvo em: %s\nResumo salvo em: %s\n' "${LOG_FILE}" "${SUMMARY_FILE}"
