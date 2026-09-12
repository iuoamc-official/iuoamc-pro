#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env. Replace every CHANGE_ME value before starting the shadow lab."
  exit 2
fi
if grep -q 'CHANGE_ME_' .env; then
  echo "Refusing to start while CHANGE_ME values remain in .env"
  exit 3
fi

echo "Starting IUOAMC isolated shadow broadcast + NOC lab."
echo "No production output destinations are configured."

docker compose -f compose.yaml -f compose.noc.yaml -f compose.shadow.yaml up -d --build

echo
echo "Shadow HLS A:  http://127.0.0.1:58118/a/index.m3u8"
echo "Shadow HLS B:  http://127.0.0.1:58118/b/index.m3u8"
echo "Program Output:http://127.0.0.1:58119/program/index.m3u8"
echo "Switcher State:http://127.0.0.1:58119/v1/state"
echo "Validator:     http://127.0.0.1:58117/v1/validate"
echo "Failover API:  http://127.0.0.1:58113/health"
echo "NOC UI:        http://127.0.0.1:58116/"
