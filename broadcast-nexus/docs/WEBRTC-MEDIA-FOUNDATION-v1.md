# WebRTC Media Session Foundation v1

## Scope
Phase 32 moves Live Studio beyond control-state toggles by adding real browser media capture and a real RTCPeerConnection transport path inside an isolated staging lab.

## Implemented
- `navigator.mediaDevices.getUserMedia()` for camera and microphone capture.
- `navigator.mediaDevices.getDisplayMedia()` for screen/system-audio capture where the browser permits it.
- Real `RTCPeerConnection` offer/answer negotiation.
- Local in-browser loopback receiver using two peer connections.
- Media Router participant session creation.
- Media track registration for camera, microphone, screen video and system audio.
- RTC Gateway safety/config endpoint.
- Unified operator-session protection through the Phase 31 Session Broker.

## Isolation Model
This phase deliberately does **not** connect to an external SFU or TURN service.

The RTC Gateway reports:
- provider: `local-loopback`
- `ice_servers: []`
- `sfu_endpoint: null`
- `turn_endpoint: null`
- `external_connectivity: false`
- `production_webrtc: false`

The browser's WebRTC transport remains inside the page. It is intended to validate capture permissions, tracks, negotiation, playback, and control-plane integration before a staging SFU is introduced.

## Safety
- No production TURN credentials.
- No production SFU endpoint.
- No public WebRTC ingress.
- No RTMP/SRT/YouTube/IPTV output.
- No change to the current production broadcaster.
- Production output flags remain disabled.

## Next step
After deployment to the dedicated staging VPS, introduce a staging-only SFU/TURN provider behind the existing adapter boundary, then run multi-browser connectivity, NAT traversal, quality telemetry, reconnection, and load tests before any production design is considered.
