# Broadcast Nexus Dashboard Integration v1

## Phase 18
The dashboard now consumes a unified staging integration API instead of depending only on separate browser-side health probes.

## Service
`dashboard-api` runs internally on port `8121` and is exposed only through the staging gateway at `/dashboard-api/`.

It aggregates health for:
- Orchestrator
- Media Library
- Scheduler
- Playout
- Live Studio
- Media Router
- ISO Recorder
- RTC Gateway
- Encoder
- Distribution
- IPTV
- Monitoring
- Alerts
- Failover
- NOC
- Control Room
- Broadcast Supervisor

## Endpoints
- `GET /health` — integration service health and safety envelope.
- `GET /v1/safety` — staging safety flags.
- `GET /v1/summary` — unified service health snapshot with latency and counts.

## Safety envelope
The staging compose file explicitly supplies:
- `PRODUCTION_SWITCHING=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`
- `PUBLIC_PUBLISHING_ENABLED=false`
- `SHADOW_MODE=true`

The dashboard API treats staging as safe only when production switching, production outputs, and public publishing are all disabled.

## UI binding
The Phase 18 dashboard polls `/dashboard-api/v1/summary` every 15 seconds and renders service availability, latency, Supervisor/NOC/Control Room state, and the safety envelope.

## Isolation
This phase does not alter the existing Laravel application, production DNS, production streaming services, systemd units, Nginx on the production server, or any production RTMP/SRT/YouTube destination.
