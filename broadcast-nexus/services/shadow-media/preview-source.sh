#!/usr/bin/env bash
set -euo pipefail

MEDIA_ROOT="${PREVIEW_MEDIA_ROOT:-/preview-media}"
FPS="${SHADOW_FPS:-25}"
SIZE="${SHADOW_SIZE:-1280x720}"
VIDEO_RATE="${SHADOW_VIDEO_RATE:-2500k}"
AUDIO_RATE="${SHADOW_AUDIO_RATE:-128k}"
TICKER_FILE="${SHADOW_TICKER_FILE:-/opt/shadow/ticker.txt}"
TICKER_SPEED="${SHADOW_TICKER_SPEED:-140}"
TICKER_HEIGHT="${SHADOW_TICKER_HEIGHT:-72}"
TICKER_FONT_SIZE="${SHADOW_TICKER_FONT_SIZE:-30}"
WIDTH="${SIZE%x*}"
HEIGHT="${SIZE#*x}"

[[ "$WIDTH" =~ ^[0-9]+$ && "$HEIGHT" =~ ^[0-9]+$ ]] || {
  echo "ERROR: SHADOW_SIZE must be WIDTHxHEIGHT (current: $SIZE)" >&2
  exit 1
}
[[ -r "$TICKER_FILE" ]] || {
  echo "ERROR: ticker file missing: $TICKER_FILE" >&2
  exit 1
}

PLAYLIST=(
  "transitions/mca-tv-official-ident-3s.mp4"
  "programs/mca-tv-episode-02-mise-en-place.mp4"
  "transitions/mca-tv-official-up-next-3s.mp4"
  "programs/mca-tv-episode-03-knife-skills.mp4"
  "transitions/mca-tv-official-ident-3s.mp4"
)

for rel in "${PLAYLIST[@]}"; do
  [[ -r "${MEDIA_ROOT}/${rel}" ]] || {
    echo "ERROR: preview asset missing: ${MEDIA_ROOT}/${rel}" >&2
    exit 1
  }
done

echo "IUOAMC TV internal preview source starting (staging/shadow only)."
echo "Sequence: IDENT -> Mise en Place -> UP NEXT -> Knife Skills -> IDENT"
echo "Ticker: enabled (${TICKER_FILE})"

# drawbox uses iw/ih for input dimensions. Using w/h here can fail during
# filter initialization on FFmpeg builds where those aliases are unavailable.
video_filter="scale=${WIDTH}:${HEIGHT}:force_original_aspect_ratio=decrease,pad=${WIDTH}:${HEIGHT}:(ow-iw)/2:(oh-ih)/2,fps=${FPS},drawbox=x=0:y=ih-${TICKER_HEIGHT}:w=iw:h=${TICKER_HEIGHT}:color=black@0.72:t=fill,drawtext=font='Noto Sans Arabic':textfile=${TICKER_FILE}:reload=1:fontcolor=white:fontsize=${TICKER_FONT_SIZE}:y=h-${TICKER_HEIGHT}+20:x=w-mod(t*${TICKER_SPEED}\,w+text_w):fix_bounds=1"

play_one() {
  local rel="$1" file="${MEDIA_ROOT}/${rel}"
  local has_audio=0
  if ffprobe -v error -select_streams a:0 -show_entries stream=index -of csv=p=0 "$file" 2>/dev/null | grep -q .; then
    has_audio=1
  fi

  echo "PREVIEW PLAY: ${rel}"

  if [[ "$has_audio" -eq 1 ]]; then
    ffmpeg -hide_banner -loglevel warning -re -i "$file" \
      -map 0:v:0 -map 0:a:0 \
      -vf "$video_filter" \
      -c:v libx264 -preset veryfast -pix_fmt yuv420p \
      -b:v "$VIDEO_RATE" -maxrate 3000k -bufsize 6000k \
      -g 50 -keyint_min 50 -sc_threshold 0 \
      -c:a aac -b:a "$AUDIO_RATE" -ar 48000 -ac 2 \
      -f tee \
      "[f=mpegts]udp://shadow-encoder-a:5000?pkt_size=1316|[f=mpegts]udp://shadow-encoder-b:5000?pkt_size=1316"
  else
    ffmpeg -hide_banner -loglevel warning -re -i "$file" \
      -f lavfi -i "anullsrc=channel_layout=stereo:sample_rate=48000" \
      -map 0:v:0 -map 1:a:0 \
      -vf "$video_filter" \
      -c:v libx264 -preset veryfast -pix_fmt yuv420p \
      -b:v "$VIDEO_RATE" -maxrate 3000k -bufsize 6000k \
      -g 50 -keyint_min 50 -sc_threshold 0 \
      -c:a aac -b:a "$AUDIO_RATE" -ar 48000 -ac 2 \
      -shortest \
      -f tee \
      "[f=mpegts]udp://shadow-encoder-a:5000?pkt_size=1316|[f=mpegts]udp://shadow-encoder-b:5000?pkt_size=1316"
  fi
}

while true; do
  for rel in "${PLAYLIST[@]}"; do
    play_one "$rel"
  done
done
