# IUOAMC Broadcast Nexus — Failover Control Dashboard v1

## Phase 28 scope

Phase 28 adds a dedicated failover operations surface for the isolated staging environment.

### Operator surface
- Route: `/failover-control/`
- Redundancy group inventory
- Active policy summary
- Primary / secondary / peer membership
- Latest health snapshot for group members
- Manual evaluation of a redundancy group
- Decision history
- Recording authorization for a `recommend_failover` decision

### Safety contract
The dashboard is deliberately decision-only.

- `PRODUCTION_SWITCHING=false`
- Authorization does not execute a route change or stream switch.
- The failover backend returns `execution=not_implemented_in_isolated_phase` after authorization.
- No DNS, systemd, encoder, CDN, RTMP, SRT, YouTube, or production IPTV action is triggered by this UI.
- JWT is held in browser-tab memory only and is not persisted by the page.

### Data sources
The UI reads the unified NOC operations snapshot and uses the existing failover decision engine endpoints for evaluation, decision history and authorization recording.

This phase does not add production switching authority.
