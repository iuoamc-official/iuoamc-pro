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

cleanup() {
  docker compose "${FILES[@]}" start shadow-encoder-a >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "[1/7] Starting isolated shadow active/standby lab"
docker compose "${FILES[@]}" up -d --build shadow-encoder-a shadow-encoder-b shadow-source shadow-hls shadow-validator shadow-program-switcher

wait_active() {
  expected="$1"
  for _ in $(seq 1 45); do
    state="$(curl -fsS http://127.0.0.1:58119/v1/state 2>/dev/null || true)"
    if printf '%s' "$state" | python3 - "$expected" <<'PY' 2>/dev/null
import json,sys
expected=sys.argv[1]
d=json.load(sys.stdin)
raise SystemExit(0 if d.get("active")==expected else 1)
PY
    then
      return 0
    fi
    sleep 2
  done
  return 1
}

wait_manifest() {
  for _ in $(seq 1 40); do
    headers="$(mktemp)"
    body="$(mktemp)"
    if curl -fsS -D "$headers" http://127.0.0.1:58119/program/index.m3u8 -o "$body" 2>/dev/null \
      && grep -q '^#EXTM3U' "$body" \
      && grep -qi '^X-IUOAMC-Production-Connected: false' "$headers"; then
      rm -f "$headers" "$body"
      return 0
    fi
    rm -f "$headers" "$body"
    sleep 2
  done
  return 1
}

echo "[2/7] Waiting for primary A and Program Output"
wait_active a
wait_manifest

echo "[3/7] Injecting isolated primary failure: shadow-encoder-a only"
docker compose "${FILES[@]}" stop shadow-encoder-a

echo "[4/7] Waiting for automatic switch to B"
wait_active b
ACTIVE_HEADER="$(curl -fsSI http://127.0.0.1:58119/program/index.m3u8 | tr -d '\r' | awk -F': ' 'tolower($1)=="x-iuoamc-shadow-active"{print $2}')"
[ "$ACTIVE_HEADER" = "b" ] || { echo "FAIL: Program Output did not report B"; exit 20; }

echo "[5/7] Restoring primary A"
docker compose "${FILES[@]}" start shadow-encoder-a

echo "[6/7] Waiting for healthy failback to A"
wait_active a
ACTIVE_HEADER="$(curl -fsSI http://127.0.0.1:58119/program/index.m3u8 | tr -d '\r' | awk -F': ' 'tolower($1)=="x-iuoamc-shadow-active"{print $2}')"
[ "$ACTIVE_HEADER" = "a" ] || { echo "FAIL: Program Output did not fail back to A"; exit 21; }

echo "[7/7] Verifying production isolation"
STATE="$(curl -fsS http://127.0.0.1:58119/v1/state)"
printf '%s' "$STATE" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("production_connected") is False; assert d.get("switch_count",0) >= 2'

echo "PASS: isolated Program Output switched A -> B -> A without production access"
