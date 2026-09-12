# Shadow NOC Integration v1

## Purpose
Connect the isolated A/B HLS shadow broadcast to the NOC and Failover Decision Engine without any production switching capability.

## Flow
Synthetic source -> Shadow Encoder A/B -> HLS A/B -> Shadow Validator -> Shadow NOC Bridge -> health_samples -> Failover Decision Engine.

## Health behavior
The validator tracks playlist progress. A playlist that stops advancing for more than 12 seconds is marked stale. The bridge writes the A/B health state into the same monitoring tables used by the NOC.

## Redundancy policy
The bridge bootstraps a lab-only redundancy group named `Shadow HLS A/B`:
- A = primary
- B = secondary
- 3 consecutive bad samples required
- 2 consecutive healthy standby samples required
- 20 second decision cooldown
- no production switching

## Internal lab endpoint
The Failover service exposes a shadow-only evaluation endpoint only when `SHADOW_MODE=true`. It requires `X-Shadow-Lab-Key` and uses constant-time key comparison. The secret is supplied from `.env`; it is never committed.

## Automated E2E test
Run:

```bash
bash scripts/shadow-e2e-test.sh
```

The test:
1. Starts the isolated shadow/NOC stack.
2. Confirms both A and B are healthy.
3. Stops only `shadow-encoder-a`.
4. Waits for HLS stale detection.
5. Confirms a failover recommendation is persisted.
6. Restarts A and confirms A/B recover.
7. Confirms `production_switching=false`.

## Safety boundary
The test never stops or modifies any production service, systemd unit, DNS record, external stream destination, or current IUOAMC broadcast process.
