# IUOAMC Broadcast Nexus — Shadow Soak Test v1

## Objective
Run the isolated synthetic broadcast continuously before any staging or production connection is allowed.

## Default run
`scripts/soak-test.sh` defaults to 1 hour.

Recommended gates:
- Development gate: 1 hour
- Pre-staging gate: 24 hours
- Release-candidate gate: 72 hours

## Environment variables
- `SOAK_DURATION_SECONDS` — total run duration
- `SOAK_INTERVAL_SECONDS` — validation interval
- `SOAK_FAILURE_EVERY` — inject an isolated restart of shadow encoder A every N validation cycles; `0` disables injection

## What is checked
- A/B shadow HLS remains available or safely degraded
- Program output always has one valid active source
- Program output remains disconnected from production
- Supervisor reports `production_switching=false`
- Optional repeated A-side failure/recovery does not break the unified Program Output

## Pass criteria
- Script exits 0
- `failures=0`
- `production_connected=false`
- Program Output remains available through planned isolated A-side failures

## Important
This soak framework does not connect to YouTube, IPTV production, DNS, the current broadcast process, or the current public site.
