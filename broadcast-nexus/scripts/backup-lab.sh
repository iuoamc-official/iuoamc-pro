#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

FILES=(-f compose.yaml -f compose.noc.yaml -f compose.shadow.yaml -f compose.supervisor.yaml)
OUT="${1:-./backup-lab}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DEST="$OUT/$STAMP"
mkdir -p "$DEST"

if [ ! -f .env ]; then
  echo "Missing .env"
  exit 2
fi
set -a
. ./.env
set +a

printf '%s\n' "Creating isolated Broadcast Nexus backup bundle in $DEST"

docker compose "${FILES[@]}" exec -T postgres \
  pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc > "$DEST/postgres.dump"

docker compose "${FILES[@]}" exec -T postgres \
  psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc \
  "SELECT json_build_object('channels',(SELECT count(*) FROM channels),'audit_events',(SELECT count(*) FROM audit_events),'health_samples',(SELECT count(*) FROM health_samples));" \
  > "$DEST/manifest.json"

sha256sum "$DEST/postgres.dump" "$DEST/manifest.json" > "$DEST/SHA256SUMS"

cat > "$DEST/README.txt" <<EOF
IUOAMC Broadcast Nexus isolated backup bundle
Created: $STAMP
Production website/stream touched: no
Contains secrets: no
Restore target: isolated environment only
EOF

printf '%s\n' "PASS: backup bundle created"
