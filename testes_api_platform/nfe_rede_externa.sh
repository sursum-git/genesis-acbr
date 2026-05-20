#!/usr/bin/env bash
set -euo pipefail

probe() {
  local titulo="$1"
  local url="$2"
  local out

  echo
  echo "== ${titulo} =="
  out="$(
    curl -sS -o /dev/null \
      -w 'http=%{http_code} remote_ip=%{remote_ip} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} total=%{time_total} err=%{errormsg}\n' \
      "${url}" || true
  )"
  echo "${out}"
}

probe "SVRS homologacao - status servico NFe" "https://nfe-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx"
probe "SVRS homologacao - consulta cadastro" "https://cad-homologacao.svrs.rs.gov.br/ws/cadconsultacadastro/cadconsultacadastro4.asmx"
