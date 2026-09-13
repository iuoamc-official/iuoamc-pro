# System Health Dashboard v1

## Scope
Phase 30 adds a staging-only unified health view for the IUOAMC Broadcast Nexus.

## UI
- `/system-health/`

## API
- `GET /dashboard-api/v1/system-health`

## Coverage
The dashboard aggregates:
- all Broadcast Nexus application service `/health` endpoints
- PostgreSQL TCP and database query health
- Redis TCP health
- NATS client port and monitoring endpoint health
- MinIO liveness health
- encoder node state counts
- HLS manifest state counts
- channel supervisor runtime state counts
- open/acknowledged alert counts
- service latency and up/degraded/down state

## Safety
The page is observability-only. It has no controls that can switch sources or activate outputs.

The staging envelope remains mandatory:
- `PRODUCTION_SWITCHING=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`
- `PUBLIC_PUBLISHING_ENABLED=false`

No DNS, host Nginx, systemd, firewall, CDN, RTMP, SRT, YouTube or production IPTV setting is modified by this phase.

## Refresh
The browser refreshes the unified snapshot every 10 seconds. This is a staging operational view and not a production SLA monitor yet.
