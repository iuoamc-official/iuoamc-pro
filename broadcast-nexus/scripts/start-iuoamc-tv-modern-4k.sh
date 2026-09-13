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
echo "Gateway bindings: loopback only"
echo "CPU cores: $(nproc)"
echo "Memory: $(free -h | awk '/^Mem:/ {print $2}')"

compose=(docker compose -f compose.modern-4k.yaml)
"${compose[@]}" config >/dev/null
"${compose[@]}" up -d --build --force-recreate modern-media-gateway modern-4k-source

GATEWAY_ID="$("${compose[@]}" ps -q modern-media-gateway)"
[[ -n "$GATEWAY_ID" ]] || { echo "ERROR: modern media gateway container not found" >&2; exit 1; }

MODERN_NETWORK="$(docker inspect "$GATEWAY_ID" --format '{{range $name, $cfg := .NetworkSettings.Networks}}{{$name}}{{"\n"}}{{end}}' | grep 'modern4k' | head -1)"
[[ -n "$MODERN_NETWORK" ]] || { echo "ERROR: modern4k Docker network not found" >&2; exit 1; }

echo "Modern network: $MODERN_NETWORK"

docker pull nginx:1.27-alpine >/dev/null
docker pull alpine/socat:latest >/dev/null

start_hls_edge() {
  local name="iuoamc-modern4k-hls-edge"
  docker rm -f "$name" >/dev/null 2>&1 || true

  # Create first on the normal bridge so Docker reliably installs the loopback
  # PortBinding, attach it to modern4k so nginx can resolve MediaMTX, then start.
  docker create \
    --name "$name" \
    --restart unless-stopped \
    -p "127.0.0.1:58888:18088/tcp" \
    -v "$ROOT/modern-media/hls-edge.conf:/etc/nginx/conf.d/default.conf:ro" \
    nginx:1.27-alpine \
    >/dev/null

  docker network connect "$MODERN_NETWORK" "$name"
  docker start "$name" >/dev/null

  if ! docker port "$name" "18088/tcp" | grep -q '127.0.0.1:58888'; then
    echo "ERROR: HLS edge did not publish 127.0.0.1:58888" >&2
    docker inspect "$name" --format '{{json .NetworkSettings.Ports}}' >&2 || true
    exit 1
  fi
}

start_tcp_edge() {
  local name="$1" host_port="$2" listen_port="$3" target_port="$4"
  docker rm -f "$name" >/dev/null 2>&1 || true
  docker run -d \
    --name "$name" \
    --restart unless-stopped \
    -p "127.0.0.1:${host_port}:${listen_port}/tcp" \
    alpine/socat:latest \
    -d -d "TCP-LISTEN:${listen_port},fork,reuseaddr" "TCP:modern-media-gateway:${target_port}" \
    >/dev/null
  docker network connect "$MODERN_NETWORK" "$name"
  if ! docker port "$name" "${listen_port}/tcp" | grep -q "127.0.0.1:${host_port}"; then
    echo "ERROR: loopback edge ${name} did not publish 127.0.0.1:${host_port}" >&2
    exit 1
  fi
}

start_hls_edge
start_tcp_edge iuoamc-modern4k-webrtc-edge 58889 18089 8889
start_tcp_edge iuoamc-modern4k-api-edge 59997 19997 9997
start_tcp_edge iuoamc-modern4k-metrics-edge 59998 19998 9998

for i in $(seq 1 60); do
  code="$(curl -sS -o /tmp/iuoamc-tv-4k.m3u8 -w '%{http_code}' http://127.0.0.1:58888/iuoamc-tv-4k/index.m3u8 2>/dev/null || true)"
  if [[ "$code" == "200" ]] && grep -q '^#EXTM3U' /tmp/iuoamc-tv-4k.m3u8 2>/dev/null; then
    echo "OK: 4K LL-HLS manifest is available"
    break
  fi
  if [[ "$i" -eq 60 ]]; then
    echo "ERROR: 4K LL-HLS did not become ready (last HTTP status: ${code:-none})" >&2
    "${compose[@]}" logs --no-color --tail=100 modern-media-gateway modern-4k-source >&2 || true
    docker port iuoamc-modern4k-hls-edge >&2 || true
    docker logs --tail=80 iuoamc-modern4k-hls-edge >&2 || true
    exit 1
  fi
  sleep 2
done

echo
echo "===== STREAM INFO ====="
head -30 /tmp/iuoamc-tv-4k.m3u8

echo
echo "===== LOOPBACK ENDPOINTS ====="
docker port iuoamc-modern4k-hls-edge || true
docker port iuoamc-modern4k-webrtc-edge || true
docker port iuoamc-modern4k-api-edge || true

echo
echo "===== CONTAINERS ====="
"${compose[@]}" ps modern-media-gateway modern-4k-source

echo
echo "Modern 4K preview is active on loopback only."
echo "LL-HLS: http://127.0.0.1:58888/iuoamc-tv-4k/index.m3u8"
echo "WebRTC page: http://127.0.0.1:58889/iuoamc-tv-4k"
echo "API: http://127.0.0.1:59997/v3/paths/list"
echo "Production/public outputs remain disabled."
