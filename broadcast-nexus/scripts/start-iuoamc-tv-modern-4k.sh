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
"${compose[@]}" up -d --build modern-media-gateway modern-4k-source

for i in $(seq 1 60); do
  if curl -fsS http://127.0.0.1:58888/iuoamc-tv-4k/index.m3u8 >/tmp/iuoamc-tv-4k.m3u8 2>/dev/null; then
    if grep -q '^#EXTM3U' /tmp/iuoamc-tv-4k.m3u8; then
      echo "OK: 4K LL-HLS manifest is available"
      break
    fi
  fi
  if [[ "$i" -eq 60 ]]; then
    echo "ERROR: 4K LL-HLS did not become ready" >&2
    "${compose[@]}" logs --no-color --tail=60 modern-media-gateway modern-4k-source >&2 || true
    exit 1
  fi
  sleep 2
done

echo
echo "===== STREAM INFO ====="
head -30 /tmp/iuoamc-tv-4k.m3u8

echo
echo "===== CONTAINERS ====="
"${compose[@]}" ps

echo
echo "Modern 4K preview is active on loopback only."
echo "LL-HLS: http://127.0.0.1:58888/iuoamc-tv-4k/index.m3u8"
echo "WebRTC page: http://127.0.0.1:58889/iuoamc-tv-4k"
echo "API: http://127.0.0.1:59997/v3/paths/list"
echo "Production/public outputs remain disabled."
