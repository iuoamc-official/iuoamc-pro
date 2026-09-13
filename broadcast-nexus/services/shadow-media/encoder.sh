#!/usr/bin/env bash
set -euo pipefail

NAME="${SHADOW_ENCODER_NAME:-a}"
OUT="/shadow-hls/${NAME}"
mkdir -p "${OUT}"
rm -f "${OUT}"/*.m3u8 "${OUT}"/*.ts || true

exec ffmpeg -hide_banner -loglevel warning \
  -use_wallclock_as_timestamps 1 \
  -fflags +genpts+discardcorrupt \
  -i "udp://0.0.0.0:5000?fifo_size=5000000&overrun_nonfatal=1&buffer_size=4194304" \
  -map 0:v:0 -map 0:a:0 \
  -vf "setpts=N/(25*TB)" \
  -af "aresample=async=1:first_pts=0" \
  -c:v libx264 -preset veryfast -pix_fmt yuv420p \
  -b:v 2500k -maxrate 3000k -bufsize 6000k \
  -g 50 -keyint_min 50 -sc_threshold 0 \
  -c:a aac -b:a 128k -ar 48000 -ac 2 \
  -avoid_negative_ts make_zero \
  -f hls -hls_time 2 -hls_list_size 6 \
  -hls_flags delete_segments+append_list+independent_segments+program_date_time \
  -hls_segment_filename "${OUT}/segment_%06d.ts" \
  "${OUT}/index.m3u8"
