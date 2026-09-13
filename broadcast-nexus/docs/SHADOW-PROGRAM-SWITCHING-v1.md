# Shadow Program Switching v1

## Purpose
Provide one stable Program Output URL inside the isolated shadow lab while automatically selecting healthy Encoder A or Encoder B.

## Program Output
- Manifest: `http://127.0.0.1:58119/program/index.m3u8`
- State API: `http://127.0.0.1:58119/v1/state`

## Switching policy
- Primary source: A
- Standby source: B
- Poll interval: 2 seconds
- Switch A -> B when A is unhealthy and B is healthy
- Fail back B -> A after 5 consecutive healthy A checks
- If B fails while A is healthy, return immediately to A

## HLS continuity model
The switcher rewrites segment references in the active playlist so every segment request remains pinned to the source that produced that playlist. This prevents a mid-playlist source change from redirecting a segment name to the wrong encoder.

## Safety
- `SHADOW_MODE=true` is required or the service refuses to start.
- The service knows only the internal shadow HLS hosts.
- No YouTube, RTMP, SRT, public CDN, DNS, Nginx production configuration, or stream key is present.
- Program Output binds to `127.0.0.1:58119` only.
- Response headers explicitly expose `X-IUOAMC-Production-Connected: false`.

## End-to-end test
Run `scripts/shadow-program-switch-test.sh`.

The test validates:
1. Initial active source is A.
2. Program manifest is valid HLS.
3. Stopping only `shadow-encoder-a` causes automatic A -> B switching.
4. Program Output reports B.
5. Restoring A and satisfying recovery hysteresis causes B -> A failback.
6. Production remains disconnected.
