#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

FILES=(-f compose.yaml -f compose.noc.yaml -f compose.supervisor.yaml -f compose.staging.yaml)

echo "IUOAMC Broadcast Nexus staging preflight"

if [ ! -f .env ]; then
  echo "FAIL: .env missing"
  exit 2
fi
if grep -q 'CHANGE_ME_' .env; then
  echo "FAIL: placeholder secrets remain in .env"
  exit 3
fi

bash scripts/security-preflight.sh

docker compose "${FILES[@]}" config --quiet

COMBINED="$(docker compose "${FILES[@]}" config)"
if printf '%s' "$COMBINED" | grep -Eqi 'a\.rtmp\.youtube\.com|rtmps?://|platform-iuoamc\.uk'; then
  echo "FAIL: production destination/reference found in staging config"
  exit 4
fi

if ! printf '%s' "$COMBINED" | grep -q 'PRODUCTION_SWITCHING: "false"'; then
  echo "FAIL: staging production switching guard missing"
  exit 5
fi

if ! printf '%s' "$COMBINED" | grep -q 'PRODUCTION_OUTPUTS_ENABLED: "false"'; then
  echo "FAIL: staging production outputs guard missing"
  exit 6
fi

echo "PASS: staging preflight"
