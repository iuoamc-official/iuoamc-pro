# IUOAMC Broadcast Nexus

Mission-Critical Broadcast & Media Operations Platform.

## Isolation policy
This directory is intentionally isolated from the existing IUOAMC Pro application.

- No changes to existing Laravel routes, controllers, migrations, jobs, or views.
- No production stream keys or credentials.
- No production RTMP/SRT outputs.
- No DNS, Nginx, Apache, systemd, firewall, or server changes.
- No access to the existing production database.
- Development ports bind to `127.0.0.1` only.

## Architecture
- Control Plane
- Media Plane
- Broadcast Plane
- Monitoring Plane
- Storage Plane

## Phase 1 foundation
- Broadcast Orchestrator API
- Media Library API
- PostgreSQL
- Redis
- NATS JetStream
- MinIO
- RBAC/Audit primitives

The current website and current broadcast remain untouched until a future explicit cutover phase.
