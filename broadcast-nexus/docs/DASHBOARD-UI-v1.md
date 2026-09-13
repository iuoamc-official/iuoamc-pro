# Broadcast Nexus Dashboard UI v1

## Purpose
Provide a visible, unified staging dashboard for IUOAMC Broadcast Nexus without modifying the current production Laravel platform or current broadcast path.

## Scope
The dashboard is a standalone static UI served by its own Nginx container and exposed only through the isolated staging gateway.

Visible navigation modules:
- Overview
- Live Studio
- Channels
- Scheduler
- Media Library
- Playout
- Encoder
- Distribution
- NOC
- Failover
- Supervisor
- System Health

## Runtime health probes
The Overview polls only staging-relative health endpoints:
- `/health`
- `/supervisor/health`
- `/noc/health`
- `/control-room/health`

The UI does not store credentials or production stream keys.

## Safety constraints
- `PRODUCTION_SWITCHING=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`
- `PUBLIC_PUBLISHING_ENABLED=false`
- No production DNS changes.
- No production server changes.
- No production RTMP/SRT/YouTube destination activation.

## Staging route
When the staging stack is deployed, the dashboard is available at the staging gateway root. The gateway remains bound to `127.0.0.1:58200` until a separately approved external ingress is configured.

## Phase 17 status
The visual shell and health surface are implemented. Module-specific CRUD/control screens will be bound incrementally to their existing service APIs after staging deployment and validation.
