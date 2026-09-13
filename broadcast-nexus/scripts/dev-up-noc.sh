#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env from .env.example. Replace every CHANGE_ME value, then run again."
  exit 2
fi

if grep -q 'CHANGE_ME_' .env; then
  echo "Refusing to start while CHANGE_ME values remain in .env"
  exit 3
fi

docker compose -f compose.yaml -f compose.noc.yaml up -d --build
docker compose -f compose.yaml -f compose.noc.yaml ps
