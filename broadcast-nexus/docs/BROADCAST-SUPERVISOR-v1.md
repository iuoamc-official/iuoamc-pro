# Broadcast Supervisor v1

The Broadcast Supervisor is the unified runtime state machine for one IUOAMC channel.

## Purpose
It coordinates runtime intent and visibility across:
- Playout
- Live Studio
- Encoder
- Distribution
- Program Output
- Monitoring / NOC
- Recovery / Failover

## Runtime states
- stopped
- starting
- running
- degraded
- recovering
- stopping
- failed

## Source modes
- playout
- studio
- emergency
- shadow

## Safety in this phase
The Supervisor records state, service health, transitions, and coordination intent only.
It does not call systemd, Docker, RTMP endpoints, YouTube, DNS, or production routing.
`production_switching` remains false.

## Design properties
- explicit legal state transitions
- monotonic generation counter
- auditable transition history
- per-service runtime registry
- channel runtime snapshot
- event publication over NATS
- recovery state visibility

## Next maturity steps
1. Shadow soak testing
2. Security hardening and secret-management policy
3. Backup / restore validation
4. DR topology validation
5. CI/CD gates and signed build artifacts
6. Staging deployment
7. Controlled cutover runbook with rollback
