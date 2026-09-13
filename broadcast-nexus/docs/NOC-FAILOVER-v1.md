# NOC Monitoring & Failover v1

## Purpose
Provide a mission-critical monitoring and decision layer without touching the current production broadcast.

## Components

### Monitoring Service
- Monitoring targets
- Health samples
- Video/audio presence
- Frozen-frame detection flags
- Black-frame detection flags
- Silence detection flags
- FPS / bitrate / latency
- Packet loss / jitter
- Timestamp drift
- HLS freshness

### Alert Engine
- Rule-based alert evaluation
- Severity: info / warning / critical
- Open / acknowledge / resolve lifecycle
- Fingerprint-based de-duplication
- NATS event emission

### Failover Decision Engine
- Redundancy groups
- Primary / secondary / peer members
- Active-Standby and Active-Active models
- Consecutive bad-sample threshold
- Recovery-good-sample threshold
- Cooldown windows
- Recommendation evidence
- Manual authorization record

Important: Phase v1 records failover authorization but does NOT execute a production switch.

### NOC API
- Global target state summary
- Open alert summary
- Incident lifecycle
- Failover recommendation count

## Safety Model
- No production stream credentials
- No RTMP/SRT output switching
- No DNS/reverse-proxy changes
- No systemd/firewall changes
- No interaction with current IUOAMC TV production encoder
- Production switching explicitly reports `false`

## Default Alert Rules
- Frozen frame
- Black frame
- Audio silence
- Low frame rate
- Stale HLS
- Timestamp drift

## Failover Decision Sequence
1. Monitoring samples target health.
2. Alert engine evaluates anomalies.
3. Failover engine checks consecutive state evidence.
4. Healthy candidate is selected by priority.
5. `recommend_failover` is recorded.
6. Authorized operator may record approval.
7. No production switch is executed in this isolated phase.

## Future Phase
A later controlled integration layer may execute an authorized switch only after:
- Shadow testing
- Dual-output validation
- Rollback validation
- Explicit production approval
