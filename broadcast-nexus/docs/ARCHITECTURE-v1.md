# IUOAMC Broadcast Nexus — Architecture Blueprint v1.0

## Design goals
- Zero direct dependency on the current production application.
- No single point of failure in the final production design.
- Active/Active capable.
- Multi-channel and multi-studio ready.
- IPTV + YouTube + Web + Internal Live.
- Disaster Recovery ready.
- Target architecture: 99.99% availability.

## Planes

### Control Plane
- Admin dashboard
- Authentication / RBAC
- Broadcast Orchestrator
- Scheduler
- Audit log

### Media Plane
- Media Library
- Metadata
- QC
- Proxy generation
- Object storage

### Broadcast Plane
- Playout
- Live Studio
- Graphics
- Encoder
- Distribution

### Monitoring Plane
- NOC Dashboard
- Telemetry
- Alerts
- Frozen/black frame detection
- Silence/loudness monitoring
- Bitrate/FPS/timestamp checks
- Failover decisions

### Storage Plane
- PostgreSQL
- Redis
- Object storage
- Recording archive
- Compliance recording

## Event backbone
NATS JetStream.

Initial event families:
- media.asset.*
- schedule.*
- playout.*
- encoder.*
- stream.output.*
- failover.*
- recording.*

## Current branch policy
This branch is architecture-only and isolated. It must not modify existing production Laravel files, routes, jobs, migrations, views, or deployment configuration.
