#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

DUMP="${1:-}"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
  echo "Usage: $0 path/to/postgres.dump"
  exit 2
fi
if [ ! -f .env ]; then
  echo "Missing .env"
  exit 3
fi
set -a
. ./.env
set +a

RESTORE_DB="iuoamc_broadcast_restore_test"
FILES=(-f compose.yaml)

echo "Creating isolated restore-test database: $RESTORE_DB"
docker compose "${FILES[@]}" exec -T postgres psql -U "$POSTGRES_USER" -d postgres -v ON_ERROR_STOP=1 <<SQL
DROP DATABASE IF EXISTS $RESTORE_DB;
CREATE DATABASE $RESTORE_DB;
SQL

cat "$DUMP" | docker compose "${FILES[@]}" exec -T postgres \
  pg_restore -U "$POSTGRES_USER" -d "$RESTORE_DB" --clean --if-exists --no-owner --no-privileges

COUNT="$(docker compose "${FILES[@]}" exec -T postgres psql -U "$POSTGRES_USER" -d "$RESTORE_DB" -Atc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")"
if [ "${COUNT:-0}" -lt 10 ]; then
  echo "FAIL: restore schema incomplete"
  exit 10
fi

echo "PASS: isolated restore validation succeeded with $COUNT public tables"
