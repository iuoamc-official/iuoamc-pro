#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[[ -f .env ]] || { echo "ERROR: .env not found" >&2; exit 1; }
set -a
# shellcheck disable=SC1091
source .env
set +a

for flag in PRODUCTION_SWITCHING PRODUCTION_OUTPUTS_ENABLED PUBLIC_PUBLISHING_ENABLED; do
  value="${!flag:-false}"
  [[ "${value,,}" == "false" ]] || {
    echo "ERROR: $flag must remain false for isolated 4K preview (current: $value)" >&2
    exit 1
  }
done

echo "===== IUOAMC TV MODERN 4K PREVIEW ====="
echo "Resolution: 3840x2160"
echo "Public publishing: disabled"
echo "Gateway bindings: direct loopback only"
echo "CPU cores: $(nproc)"
echo "Memory: $(free -h | awk '/^Mem:/ {print $2}')"

for c in \
  iuoamc-modern4k-hls-edge \
  iuoamc-modern4k-webrtc-edge \
  iuoamc-modern4k-api-edge \
  iuoamc-modern4k-metrics-edge; do
  docker rm -f "$c" >/dev/null 2>&1 || true
done

compose=(docker compose -f compose.modern-4k.yaml)
"${compose[@]}" config >/dev/null

"${compose[@]}" down >/dev/null 2>&1 || true
docker network rm iuoamc_broadcast_nexus_modern4k >/dev/null 2>&1 || true

"${compose[@]}" up -d --build --force-recreate modern-media-gateway modern-4k-source modern-webrtc-player

GATEWAY_ID="$("${compose[@]}" ps -q modern-media-gateway)"
PLAYER_ID="$("${compose[@]}" ps -q modern-webrtc-player)"
[[ -n "$GATEWAY_ID" ]] || { echo "ERROR: modern media gateway container not found" >&2; exit 1; }
[[ -n "$PLAYER_ID" ]] || { echo "ERROR: WebRTC player container not found" >&2; exit 1; }

required_bindings=(
  "8888/tcp 127.0.0.1:58888"
  "8889/tcp 127.0.0.1:58889"
  "8189/tcp 127.0.0.1:58189"
  "9997/tcp 127.0.0.1:59997"
  "9998/tcp 127.0.0.1:59998"
)
for entry in "${required_bindings[@]}"; do
  container_port="${entry%% *}"
  host_binding="${entry#* }"
  if ! docker port "$GATEWAY_ID" "$container_port" | grep -q "$host_binding"; then
    echo "ERROR: missing direct loopback binding $container_port -> $host_binding" >&2
    docker inspect "$GATEWAY_ID" --format '{{json .NetworkSettings.Ports}}' >&2 || true
    exit 1
  fi
done

if ! docker port "$PLAYER_ID" "8080/tcp" | grep -q '127.0.0.1:58900'; then
  echo "ERROR: custom WebRTC player is not bound to 127.0.0.1:58900" >&2
  exit 1
fi

HLS_URL="http://127.0.0.1:58888/iuoamc-tv-4k/index.m3u8"
for i in $(seq 1 60); do
  rm -f /tmp/iuoamc-tv-4k.m3u8
  if curl -fsSL --max-redirs 8 \
      -H 'Accept: application/vnd.apple.mpegurl,application/x-mpegURL,*/*' \
      "$HLS_URL" \
      -o /tmp/iuoamc-tv-4k.m3u8 2>/dev/null; then
    if grep -q '^#EXTM3U' /tmp/iuoamc-tv-4k.m3u8 2>/dev/null; then
      echo "OK: 4K LL-HLS manifest is available"
      break
    fi
  fi

  if [[ "$i" -eq 60 ]]; then
    echo "ERROR: 4K LL-HLS did not become ready" >&2
    "${compose[@]}" logs --no-color --tail=120 modern-media-gateway modern-4k-source >&2 || true
    exit 1
  fi
  sleep 2
done

for i in $(seq 1 30); do
  if curl -fsS http://127.0.0.1:58900/ >/dev/null 2>&1; then
    echo "OK: custom WebRTC player is available"
    break
  fi
  if [[ "$i" -eq 30 ]]; then
    echo "ERROR: custom WebRTC player did not become ready" >&2
    "${compose[@]}" logs --no-color --tail=80 modern-webrtc-player >&2 || true
    exit 1
  fi
  sleep 1
done

echo
echo "===== STREAM INFO ====="
head -30 /tmp/iuoamc-tv-4k.m3u8

echo
echo "===== DIRECT LOOPBACK ENDPOINTS ====="
docker port "$GATEWAY_ID" || true
docker port "$PLAYER_ID" || true

echo
echo "===== CONTAINERS ====="
"${compose[@]}" ps modern-media-gateway modern-4k-source modern-webrtc-player

echo
echo "Modern 4K preview is active on direct loopback bindings."
echo "LL-HLS: $HLS_URL"
echo "MediaMTX WebRTC: http://127.0.0.1:58889/iuoamc-tv-4k/"
echo "IUOAMC WebRTC Player: http://127.0.0.1:58900/"
echo "API: http://127.0.0.1:59997/v3/paths/list"
echo "Production/public outputs remain disabled."
