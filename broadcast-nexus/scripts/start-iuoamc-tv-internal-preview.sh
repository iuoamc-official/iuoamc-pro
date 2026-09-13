#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMPORT_DIR="${LEGACY_TV_IMPORT_DIR:-/opt/iuoamc-broadcast/import/legacy-tv}"
cd "$ROOT"

[[ -f .env ]] || { echo "ERROR: $ROOT/.env not found" >&2; exit 1; }
set -a
# shellcheck disable=SC1091
source .env
set +a

for flag in PRODUCTION_SWITCHING PRODUCTION_OUTPUTS_ENABLED PUBLIC_PUBLISHING_ENABLED; do
  value="${!flag:-false}"
  [[ "${value,,}" == "false" ]] || {
    echo "ERROR: $flag must remain false for internal preview (current: $value)" >&2
    exit 1
  }
done

required=(
  transitions/mca-tv-official-ident-3s.mp4
  programs/mca-tv-episode-02-mise-en-place.mp4
  transitions/mca-tv-official-up-next-3s.mp4
  programs/mca-tv-episode-03-knife-skills.mp4
)
for rel in "${required[@]}"; do
  [[ -r "$IMPORT_DIR/$rel" ]] || { echo "ERROR: missing preview asset $IMPORT_DIR/$rel" >&2; exit 1; }
done

# compose.shadow.yaml extends the failover service declared in compose.noc.yaml,
# while compose.noc.yaml extends core services declared in compose.yaml.
# Always load the complete dependency chain before applying the preview overlay.
preview_compose=(
  docker compose
  -f compose.yaml
  -f compose.noc.yaml
  -f compose.shadow.yaml
  -f compose.preview.yaml
)

echo "===== START INTERNAL SHADOW PREVIEW ====="
echo "Loopback HLS only: 127.0.0.1:58118"

# Validate the merged project first so a compose dependency error fails before build/start.
"${preview_compose[@]}" config >/dev/null

"${preview_compose[@]}" up -d --build \
  shadow-encoder-a shadow-encoder-b shadow-source shadow-hls

check_url() {
  local name="$1" url="$2"
  local i
  for i in $(seq 1 30); do
    if curl -fsS "$url" >/tmp/iuoamc-preview-${name}.m3u8 2>/dev/null && \
       grep -q '^#EXTM3U' /tmp/iuoamc-preview-${name}.m3u8; then
      echo "OK: encoder ${name} HLS is available"
      return 0
    fi
    sleep 2
  done
  echo "ERROR: encoder ${name} HLS did not become ready" >&2
  return 1
}

check_url a http://127.0.0.1:58118/a/index.m3u8
check_url b http://127.0.0.1:58118/b/index.m3u8

echo
echo "===== INTERNAL PREVIEW STATUS ====="
"${preview_compose[@]}" ps shadow-encoder-a shadow-encoder-b shadow-source shadow-hls

echo
echo "===== SOURCE LOG TAIL ====="
"${preview_compose[@]}" logs --no-color --tail=20 shadow-source

echo
echo "===== HLS A MANIFEST ====="
cat /tmp/iuoamc-preview-a.m3u8

echo
echo "IUOAMC TV internal preview is running on loopback only."
echo "A: http://127.0.0.1:58118/a/index.m3u8"
echo "B: http://127.0.0.1:58118/b/index.m3u8"
echo "Production switching/public publishing/output flags remain disabled."
