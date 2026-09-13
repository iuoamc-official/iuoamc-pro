#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

DURATION_SECONDS="${SOAK_DURATION_SECONDS:-3600}"
INTERVAL_SECONDS="${SOAK_INTERVAL_SECONDS:-30}"
FAILURE_EVERY="${SOAK_FAILURE_EVERY:-0}"
FILES=(-f compose.yaml -f compose.noc.yaml -f compose.shadow.yaml -f compose.supervisor.yaml)

if [ ! -f .env ]; then
  echo "Missing .env"
  exit 2
fi
if grep -q 'CHANGE_ME_' .env; then
  echo "Refusing to run while CHANGE_ME values remain in .env"
  exit 3
fi

START="$(date +%s)"
END=$((START + DURATION_SECONDS))
ITER=0
FAILURES=0
SWITCHES_START=0

cleanup() {
  docker compose "${FILES[@]}" start shadow-encoder-a shadow-encoder-b >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "Starting isolated Broadcast Nexus soak test"
echo "Duration: ${DURATION_SECONDS}s | interval: ${INTERVAL_SECONDS}s"
echo "Production connectivity: disabled"

docker compose "${FILES[@]}" up -d --build

for _ in $(seq 1 60); do
  if curl -fsS http://127.0.0.1:58119/v1/state >/tmp/iuoamc-shadow-state.json 2>/dev/null; then
    SWITCHES_START="$(python3 - <<'PY'
import json
with open('/tmp/iuoamc-shadow-state.json') as f:
    d=json.load(f)
print(int(d.get('switch_count',0)))
PY
)"
    break
  fi
  sleep 2
done

while [ "$(date +%s)" -lt "$END" ]; do
  ITER=$((ITER + 1))

  VALIDATION="$(curl -fsS http://127.0.0.1:58117/v1/validate 2>/dev/null || true)"
  PROGRAM="$(curl -fsS http://127.0.0.1:58119/v1/state 2>/dev/null || true)"
  SUPERVISOR="$(curl -fsS http://127.0.0.1:58120/health 2>/dev/null || true)"

  if ! printf '%s' "$VALIDATION" | python3 -c 'import json,sys; d=json.load(sys.stdin); raise SystemExit(0 if d.get("overall") in {"healthy","degraded"} and d.get("production_outputs",[])==[] else 1)' 2>/dev/null; then
    echo "[$(date -Is)] validation failure"
    FAILURES=$((FAILURES + 1))
  fi

  if ! printf '%s' "$PROGRAM" | python3 -c 'import json,sys; d=json.load(sys.stdin); raise SystemExit(0 if d.get("active_source") in {"a","b"} and d.get("production_connected") is False else 1)' 2>/dev/null; then
    echo "[$(date -Is)] program-output failure"
    FAILURES=$((FAILURES + 1))
  fi

  if ! printf '%s' "$SUPERVISOR" | python3 -c 'import json,sys; d=json.load(sys.stdin); raise SystemExit(0 if d.get("production_switching") is False else 1)' 2>/dev/null; then
    echo "[$(date -Is)] supervisor isolation failure"
    FAILURES=$((FAILURES + 1))
  fi

  if [ "$FAILURE_EVERY" -gt 0 ] && [ $((ITER % FAILURE_EVERY)) -eq 0 ]; then
    echo "[$(date -Is)] injecting isolated A encoder restart"
    docker compose "${FILES[@]}" stop shadow-encoder-a >/dev/null
    sleep 18
    docker compose "${FILES[@]}" start shadow-encoder-a >/dev/null
  fi

  echo "[$(date -Is)] iteration=$ITER failures=$FAILURES"
  sleep "$INTERVAL_SECONDS"
done

STATE="$(curl -fsS http://127.0.0.1:58119/v1/state)"
SWITCHES_END="$(printf '%s' "$STATE" | python3 -c 'import json,sys; print(int(json.load(sys.stdin).get("switch_count",0)))')"

cat <<EOF
SOAK_RESULT duration_seconds=$DURATION_SECONDS iterations=$ITER failures=$FAILURES switches=$((SWITCHES_END-SWITCHES_START)) production_connected=false
EOF

[ "$FAILURES" -eq 0 ]
