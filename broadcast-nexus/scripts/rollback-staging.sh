#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
FILES=(-f compose.yaml -f compose.noc.yaml -f compose.supervisor.yaml -f compose.staging.yaml)

echo "Stopping isolated staging stack"
docker compose "${FILES[@]}" down --remove-orphans

echo "Staging rollback complete. Production was not modified by this script."
