# IUOAMC Broadcast Nexus Roadmap

## Phase 0 — Isolated Foundation
- Dedicated branch and directory
- Architecture blueprint
- Isolation guardrails
- Local-only PostgreSQL / Redis / NATS / MinIO

## Phase 1 — Control Plane Foundation
- Broadcast Orchestrator API
- Auth/RBAC primitives
- Audit log
- Media Library API
- Service health registry

## Phase 2 — Scheduling
- Channels
- Programs / Episodes
- Playlists
- Rundowns
- Schedule slots
- EPG
- Conflict detection

## Phase 3 — Playout
- Deterministic playout engine
- Fallback content
- Preview output
- Graphics hooks

## Phase 4 — Live Studio
- WebRTC rooms
- Host / guests
- Preview / Program
- Screen share
- Lower thirds / ticker
- Recording

## Phase 5 — Distribution
- Encoder workers
- HLS / IPTV
- YouTube
- Internal stream
- Per-destination isolation

## Phase 6 — NOC & Failover
- Telemetry
- Frozen frame / black frame
- Silence/loudness
- Bitrate/FPS/timestamp health
- Automated failover

## Phase 7 — HA / DR
- Active/Active nodes
- Redundant playout/encoder
- Disaster recovery environment
- Shadow run
- Controlled migration from current production
