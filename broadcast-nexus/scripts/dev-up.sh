#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env from .env.example."
  echo "Replace every CHANGE_ME value, then run again."
  exit 2
fi

if grep -q 'CHANGE_ME_' .env; then
  echo "Refusing to start while CHANGE_ME values remain in .env"
  exit 3
fi

if grep -R -E 'platform-iuoamc\.uk|rtmps?://|srt://' compose.yaml services packages 2>/dev/null; then
  echo "Safety check found a production-domain or stream-output reference. Refusing to start."
  exit 4
fi

docker compose up -d --build
docker compose ps
