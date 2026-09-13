# Phase 25 — Distribution Control Dashboard

## Scope
Phase 25 adds a staging-only Distribution operations surface on top of the existing Distribution Fabric control plane.

## Dashboard
- `/distribution-control/`
- Channel selection
- Destination inventory
- Destination creation in disabled state
- Safe enable/disable controls
- Encoder-job selection
- Output-session creation
- Output-session lifecycle controls

## Staging Safety
`PRODUCTION_OUTPUTS_ENABLED=false` remains mandatory.

External destination kinds are hard-locked in the dashboard extension:
- YouTube
- IPTV HLS
- SRT
- RTMP

Only the following staging-safe intent kinds may be enabled:
- `internal_hls`
- `recording`

Even when a staging-safe destination is marked enabled, Phase 25 does not open a network connection, publish media, spawn FFmpeg, expose stream keys, or connect to a CDN/provider. Session states are control-plane records only.

## Session Lifecycle
`created -> connecting -> connected -> degraded/disconnecting -> disconnected`

Failure is modeled independently, with controlled retry from `failed -> connecting`.

## Security
- JWT is supplied by the operator and kept only in page memory.
- No provider credentials or stream keys are stored in the dashboard source.
- All state changes remain permission protected and audited.
- Staging gateway passes Authorization headers to the Distribution API.

## Production Isolation
This phase does not modify the existing Laravel application, current production server, DNS, public ingress, or existing live broadcast chain.
