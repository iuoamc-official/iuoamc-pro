# Shadow Broadcast Environment v1

## Purpose
Provide an end-to-end media validation path without touching any production channel, stream key, DNS route, reverse proxy, systemd service, or existing Laravel runtime.

## Flow
Synthetic source (test pattern + 1 kHz tone)
→ MPEG-TS over internal Docker network
→ Encoder A and Encoder B
→ independent HLS outputs
→ local Nginx HLS server
→ validation API

## Isolation
- Docker network is marked `internal: true`.
- HLS and validator are exposed only on `127.0.0.1`.
- There are no RTMP/RTMPS/SRT production destinations.
- There are no YouTube/Facebook credentials.
- There are no production stream keys.
- No service manipulates host systemd, firewall, DNS, or production processes.

## Local endpoints
- HLS A: `http://127.0.0.1:58118/a/index.m3u8`
- HLS B: `http://127.0.0.1:58118/b/index.m3u8`
- Validation API: `http://127.0.0.1:58117/v1/validate`

## Validation criteria
Each HLS path must:
- return HTTP 200
- contain `#EXTM3U`
- expose at least two `.ts` media segments
- remain independently healthy

## Redundancy objective
Encoder A and Encoder B receive the same synthetic source independently and create separate HLS manifests. A single encoder failure must not invalidate the other manifest.

## Current limitation
This is a shadow-chain foundation. It intentionally does not switch production traffic, publish to an external CDN, or connect to real broadcast destinations.
