#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
FILES=(-f compose.yaml -f compose.noc.yaml -f compose.supervisor.yaml -f compose.staging.yaml)

bash scripts/staging-preflight.sh

echo "Starting isolated staging stack only"
docker compose "${FILES[@]}" up -d --build

echo "Waiting for local staging gateway"
for _ in $(seq 1 40); do
  if curl -fsS http://127.0.0.1:58200/health >/dev/null 2>&1; then
    echo "Staging gateway healthy"
    exit 0
  fi
  sleep 2
done

echo "FAIL: staging gateway did not become healthy"
exit 10
