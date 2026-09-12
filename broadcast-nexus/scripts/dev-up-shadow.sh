#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo "Starting IUOAMC isolated shadow broadcast only."
echo "No production output destinations are configured."

docker compose -f compose.shadow.yaml up -d --build

echo
echo "Shadow HLS A: http://127.0.0.1:58118/a/index.m3u8"
echo "Shadow HLS B: http://127.0.0.1:58118/b/index.m3u8"
echo "Validator:    http://127.0.0.1:58117/v1/validate"
