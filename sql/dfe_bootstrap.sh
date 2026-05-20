#!/usr/bin/env bash
set -euo pipefail

PGHOST="${PGHOST:-localhost}"
PGPORT="${PGPORT:-5432}"
PGUSER="${PGUSER:-postgres}"
PGPASSWORD="${PGPASSWORD:-81215122}"
SOURCE_DB="${SOURCE_DB:-genesis_compras}"
TARGET_DB="${TARGET_DB:-dfe}"
SQL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

export PGPASSWORD

if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -Atqc "SELECT 1 FROM pg_database WHERE datname = '${TARGET_DB}'" | grep -q 1; then
  createdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" "$TARGET_DB"
fi

if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$TARGET_DB" -Atqc "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 't00002'" | grep -q 1; then
  pg_dump -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$SOURCE_DB" -t public.t00002 --schema-only --section=pre-data --no-owner --no-privileges | \
    psql -v ON_ERROR_STOP=1 -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$TARGET_DB"
fi

psql -v ON_ERROR_STOP=1 -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$TARGET_DB" -f "$SQL_DIR/dfe_schema.sql"

SOURCE_COLUMNS="$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$SOURCE_DB" -Atqc \
  "SELECT string_agg(quote_ident(column_name), ', ' ORDER BY ordinal_position)
   FROM information_schema.columns
   WHERE table_schema = 'public' AND table_name = 't00002'")"

if [[ -z "$SOURCE_COLUMNS" ]]; then
  echo "Nao foi possivel descobrir as colunas da public.t00002 em ${SOURCE_DB}." >&2
  exit 1
fi

psql -v ON_ERROR_STOP=1 -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$TARGET_DB" -c "TRUNCATE TABLE public.t00002"

psql -v ON_ERROR_STOP=1 -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$SOURCE_DB" -c "\copy (SELECT ${SOURCE_COLUMNS} FROM public.t00002) TO STDOUT WITH CSV" | \
  psql -v ON_ERROR_STOP=1 -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$TARGET_DB" -c "\copy public.t00002 (${SOURCE_COLUMNS}) FROM STDIN WITH CSV"

echo "Database ${TARGET_DB} provisionada com t00002 + c_token e tabelas t99001..t99004."
