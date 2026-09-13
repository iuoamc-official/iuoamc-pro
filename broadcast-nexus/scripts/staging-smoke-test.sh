#!/usr/bin/env bash
set -euo pipefail

BASE="${STAGING_BASE_URL:-http://127.0.0.1:58200}"

echo "Smoke testing $BASE"

HEALTH="$(curl -fsS "$BASE/health")"
printf '%s' "$HEALTH" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d["status"]=="ok"; assert d["production_switching"] is False'

HEADERS="$(curl -fsSI "$BASE/health")"
printf '%s' "$HEADERS" | grep -qi '^X-IUOAMC-Environment: staging'
printf '%s' "$HEADERS" | grep -qi '^X-Production-Switching: false'

echo "PASS: staging smoke test"
