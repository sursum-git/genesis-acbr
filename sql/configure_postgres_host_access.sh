#!/usr/bin/env bash
set -euo pipefail

PG_CONF="${PG_CONF:-/etc/postgresql/14/main/postgresql.conf}"
PG_HBA="${PG_HBA:-/etc/postgresql/14/main/pg_hba.conf}"
MARKER_BEGIN="# codex-lib-acbr-php-begin"
MARKER_END="# codex-lib-acbr-php-end"

cp -n "$PG_CONF" "${PG_CONF}.codex.bak"
cp -n "$PG_HBA" "${PG_HBA}.codex.bak"

python3 - "$PG_CONF" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
text = path.read_text()
pattern = re.compile(r"(?m)^#?\s*listen_addresses\s*=.*$")
replacement = "listen_addresses = '*'"
if pattern.search(text):
    text = pattern.sub(replacement, text, count=1)
else:
    text += "\n" + replacement + "\n"
path.write_text(text)
PY

python3 - "$PG_HBA" "$MARKER_BEGIN" "$MARKER_END" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
marker_begin = sys.argv[2]
marker_end = sys.argv[3]
block = f"""{marker_begin}
host    dfe             postgres        172.16.0.0/12          scram-sha-256
host    dfe             postgres        157.173.96.0/20        scram-sha-256
host    genesis_compras postgres        127.0.0.1/32           scram-sha-256
host    dfe             postgres        127.0.0.1/32           scram-sha-256
{marker_end}
"""

text = path.read_text()
if marker_begin in text and marker_end in text:
    start = text.index(marker_begin)
    end = text.index(marker_end) + len(marker_end)
    text = text[:start] + block + text[end:]
else:
    if not text.endswith("\n"):
        text += "\n"
    text += "\n" + block

path.write_text(text)
PY

pg_ctlcluster 14 main restart

echo "PostgreSQL reconfigurado para acesso externo do container."
