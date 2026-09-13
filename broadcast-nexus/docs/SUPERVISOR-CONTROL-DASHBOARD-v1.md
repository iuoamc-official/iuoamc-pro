# Supervisor Control Dashboard v1

## Phase
Phase 29 — isolated staging control plane.

## UI and API
- UI: `/supervisor-control/`
- Supervisor API proxy: `/supervisor/`
- Fleet endpoint: `GET /v1/runtimes`
- Safety endpoint: `GET /v1/safety`
- Existing runtime snapshot and transition endpoints remain unchanged.

## Operator capabilities
- View all channels and whether runtime is initialized.
- View desired/actual state, source mode, generation, health and recovery state.
- View per-channel service health and recent transitions.
- Initialize runtime or change source mode.
- Request only legal state-machine transitions enforced by the existing Supervisor API.

## Safety envelope
- `PRODUCTION_SWITCHING=false`.
- Supervisor transitions remain control-plane records only.
- No encoder, distribution, DNS, CDN, RTMP, SRT, YouTube or IPTV production switch is executed.
- JWT is held in page memory only by this dashboard.
- Staging gateway continues to emit `X-Production-Switching: false`.

## Production isolation
This phase changes only the isolated `broadcast-nexus/` tree and does not modify the current IUOAMC production Laravel application, production server configuration, DNS, systemd, firewall, or active broadcast outputs.
