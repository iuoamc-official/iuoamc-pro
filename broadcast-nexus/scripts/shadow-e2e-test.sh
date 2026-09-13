#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

FILES=(-f compose.yaml -f compose.noc.yaml -f compose.shadow.yaml)

if [ ! -f .env ]; then
  echo "Missing .env"
  exit 2
fi
if grep -q 'CHANGE_ME_' .env; then
  echo "Refusing to run while CHANGE_ME values remain in .env"
  exit 3
fi

set -a
. ./.env
set +a

cleanup() {
  docker compose "${FILES[@]}" start shadow-encoder-a >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "[1/6] Starting isolated shadow + NOC stack"
docker compose "${FILES[@]}" up -d --build postgres nats failover monitoring shadow-encoder-a shadow-encoder-b shadow-source shadow-hls shadow-validator shadow-noc-bridge

wait_for_both() {
  for _ in $(seq 1 40); do
    body="$(curl -fsS http://127.0.0.1:58117/v1/validate 2>/dev/null || true)"
    if printf '%s' "$body" | python3 -c 'import json,sys; d=json.load(sys.stdin); raise SystemExit(0 if set(d.get("healthy_encoders",[]))=={"encoder_a","encoder_b"} else 1)' 2>/dev/null; then
      return 0
    fi
    sleep 2
  done
  return 1
}

echo "[2/6] Waiting for healthy A/B HLS"
wait_for_both

echo "[3/6] Injecting isolated failure: stopping shadow-encoder-a only"
docker compose "${FILES[@]}" stop shadow-encoder-a

echo "[4/6] Waiting for stale detection and failover recommendation"
FOUND=0
for _ in $(seq 1 30); do
  COUNT="$(docker compose "${FILES[@]}" exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "SELECT count(*) FROM failover_decisions d JOIN redundancy_groups g ON g.id=d.group_id WHERE g.name='Shadow HLS A/B' AND d.decision='recommend_failover';" 2>/dev/null || echo 0)"
  if [ "${COUNT:-0}" -ge 1 ]; then
    FOUND=1
    break
  fi
  sleep 2
 done

if [ "$FOUND" -ne 1 ]; then
  echo "FAIL: no failover recommendation was recorded"
  exit 10
fi

echo "[5/6] Restoring shadow-encoder-a"
docker compose "${FILES[@]}" start shadow-encoder-a
sleep 5
wait_for_both

echo "[6/6] Verifying production isolation"
HEALTH="$(curl -fsS http://127.0.0.1:58113/health)"
printf '%s' "$HEALTH" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("production_switching") is False; assert d.get("shadow_mode") is True'

echo "PASS: isolated A/B shadow failover lab validated end-to-end"
