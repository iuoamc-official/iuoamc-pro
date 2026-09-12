#!/usr/bin/env bash
set -euo pipefail

FPS="${SHADOW_FPS:-25}"
SIZE="${SHADOW_SIZE:-1280x720}"
VIDEO_RATE="${SHADOW_VIDEO_RATE:-2500k}"
AUDIO_RATE="${SHADOW_AUDIO_RATE:-128k}"

exec ffmpeg -hide_banner -loglevel warning -re \
  -f lavfi -i "testsrc2=size=${SIZE}:rate=${FPS}" \
  -f lavfi -i "sine=frequency=1000:sample_rate=48000" \
  -map 0:v:0 -map 1:a:0 \
  -c:v libx264 -preset veryfast -pix_fmt yuv420p \
  -b:v "${VIDEO_RATE}" -maxrate 3000k -bufsize 6000k \
  -g 50 -keyint_min 50 -sc_threshold 0 \
  -c:a aac -b:a "${AUDIO_RATE}" -ar 48000 -ac 2 \
  -f tee \
  "[f=mpegts]udp://shadow-encoder-a:5000?pkt_size=1316|[f=mpegts]udp://shadow-encoder-b:5000?pkt_size=1316"
