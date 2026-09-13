#!/usr/bin/env bash
set -euo pipefail

MEDIA_ROOT="${PREVIEW_MEDIA_ROOT:-/preview-media}"
WIDTH="${MODERN_WIDTH:-3840}"
HEIGHT="${MODERN_HEIGHT:-2160}"
FPS="${MODERN_FPS:-25}"
VIDEO_RATE="${MODERN_VIDEO_RATE:-16000k}"
AUDIO_RATE="${MODERN_AUDIO_RATE:-192k}"
GOP="${MODERN_GOP:-50}"
TICKER_FILE="${MODERN_TICKER_FILE:-/opt/shadow/ticker.txt}"
TARGET="rtsp://modern-media-gateway:8554/iuoamc-tv-4k"

PLAYLIST=(
  "transitions/mca-tv-official-ident-3s.mp4"
  "programs/mca-tv-episode-02-mise-en-place.mp4"
  "transitions/mca-tv-official-up-next-3s.mp4"
  "programs/mca-tv-episode-03-knife-skills.mp4"
  "transitions/mca-tv-official-ident-3s.mp4"
)

for rel in "${PLAYLIST[@]}"; do
  [[ -r "${MEDIA_ROOT}/${rel}" ]] || { echo "ERROR: missing ${MEDIA_ROOT}/${rel}" >&2; exit 1; }
done
[[ -r "$TICKER_FILE" ]] || { echo "ERROR: missing ticker file $TICKER_FILE" >&2; exit 1; }

# drawbox uses input-frame dimensions (iw/ih), while drawtext exposes output-frame
# dimensions as w/h. Keep the expressions separate so FFmpeg can parse both filters.
vf="scale=${WIDTH}:${HEIGHT}:force_original_aspect_ratio=decrease,pad=${WIDTH}:${HEIGHT}:(ow-iw)/2:(oh-ih)/2,fps=${FPS},drawbox=x=0:y=ih-120:w=iw:h=120:color=black@0.72:t=fill,drawtext=font='Noto Sans Arabic':textfile=${TICKER_FILE}:reload=1:fontcolor=white:fontsize=48:y=h-88:x=w-mod(t*220\,w+text_w):fix_bounds=1"

echo "IUOAMC TV modern 4K publisher starting."
echo "Master: ${WIDTH}x${HEIGHT} ${FPS}fps H.264/Opus"
echo "Output gateway: internal only"
echo "Ticker: enabled"

publish_one() {
  local rel="$1" file="${MEDIA_ROOT}/${rel}" has_audio=0
  ffprobe -v error -select_streams a:0 -show_entries stream=index -of csv=p=0 "$file" 2>/dev/null | grep -q . && has_audio=1 || true
  echo "4K PLAY: ${rel}"

  if [[ "$has_audio" -eq 1 ]]; then
    ffmpeg -hide_banner -loglevel warning -re -i "$file" \
      -map 0:v:0 -map 0:a:0 \
      -vf "$vf" \
      -c:v libx264 -preset ultrafast -tune zerolatency -pix_fmt yuv420p \
      -b:v "$VIDEO_RATE" -maxrate "$VIDEO_RATE" -bufsize 32000k \
      -g "$GOP" -keyint_min "$GOP" -sc_threshold 0 \
      -c:a libopus -b:a "$AUDIO_RATE" -ar 48000 -ac 2 \
      -fflags +genpts -avoid_negative_ts make_zero \
      -f rtsp -rtsp_transport tcp "$TARGET"
  else
    ffmpeg -hide_banner -loglevel warning -re -i "$file" \
      -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000 \
      -map 0:v:0 -map 1:a:0 \
      -vf "$vf" \
      -c:v libx264 -preset ultrafast -tune zerolatency -pix_fmt yuv420p \
      -b:v "$VIDEO_RATE" -maxrate "$VIDEO_RATE" -bufsize 32000k \
      -g "$GOP" -keyint_min "$GOP" -sc_threshold 0 \
      -c:a libopus -b:a "$AUDIO_RATE" -ar 48000 -ac 2 -shortest \
      -fflags +genpts -avoid_negative_ts make_zero \
      -f rtsp -rtsp_transport tcp "$TARGET"
  fi
}

while true; do
  for rel in "${PLAYLIST[@]}"; do
    publish_one "$rel"
    sleep 0.2
  done
done
