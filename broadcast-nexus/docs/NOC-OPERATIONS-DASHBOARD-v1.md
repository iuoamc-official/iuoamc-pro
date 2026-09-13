# Phase 27 — NOC Operations Dashboard

## Scope
Phase 27 upgrades the isolated Broadcast Nexus NOC from a summary surface into an authenticated staging operations console. It remains strictly decision-only for failover and does not execute any production switch.

## Operator surface
- UI: `/noc-control/`
- NOC aggregation API: `/noc-api/`
- Monitoring API: `/monitoring-api/`
- Alerts API: `/alerts-api/`
- Failover API: `/failover-api/`

JWT is kept only in browser-tab memory by the static operator UI.

## Operations snapshot
The NOC extension aggregates:
- monitoring targets and latest health samples
- video/audio presence
- frozen frame / black frame / silence
- FPS / bitrate / loudness / latency
- packet loss / jitter / timestamp drift / HLS age
- active and acknowledged alerts
- incident lifecycle
- redundancy groups, members and policies
- recent failover decisions

## Monitoring workflow
Operators can create isolated targets and ingest staging health samples. Alert evaluation remains handled by the existing alert engine and uses the configured alert rules.

## Incident workflow
Incidents support:
- open
- investigating
- mitigated
- resolved

Every operator state transition is recorded in the audit log.

## Redundancy workflow
Operators can create:
- active/standby or active/active redundancy groups
- primary / secondary / peer members
- failover policies with bad-sample, recovery, cooldown and manual-authorization thresholds

The recommended staging policy requires manual authorization.

## Failover safety
Failover evaluation may return a recommendation and operators may record authorization. Authorization does **not** perform a stream switch.

Hard safety characteristics:
- `production_switching=false`
- `execution_mode=decision-only`
- no DNS changes
- no systemd changes
- no production encoder process changes
- no RTMP/SRT/YouTube/IPTV destination switching
- authorization response remains `not_implemented_in_isolated_phase`

## Production isolation
No current Laravel application file is modified by this phase. All changes remain in the isolated `broadcast-nexus/` tree and the draft PR remains unmerged.
