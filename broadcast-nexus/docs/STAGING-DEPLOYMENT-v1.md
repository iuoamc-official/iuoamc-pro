# IUOAMC Broadcast Nexus — Staging Deployment v1

## Purpose
Provide a production-isolated staging deployment package for the Broadcast Nexus before any cutover work.

## Hard guards
- `PRODUCTION_SWITCHING=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`
- `PUBLIC_PUBLISHING_ENABLED=false`
- No production RTMP/RTMPS destination is present.
- No production DNS, Nginx, systemd, firewall, or stream key changes are performed by these scripts.

## Compose files
Use:

```bash
docker compose \
  -f compose.yaml \
  -f compose.noc.yaml \
  -f compose.supervisor.yaml \
  -f compose.staging.yaml \
  config
```

The staging gateway is bound to `127.0.0.1:58200` by default. Public TLS exposure, if later approved, must be added outside this baseline only after the smoke/soak gates pass.

## Deployment sequence
1. Prepare a separate server or VM.
2. Clone the repository and checkout `broadcast-nexus-foundation` or an approved staging tag.
3. Create `.env` from `.env.example` and replace every placeholder with staging-only secrets.
4. Run `scripts/staging-preflight.sh`.
5. Run `scripts/backup-lab.sh` if an existing staging data set is present.
6. Run `scripts/deploy-staging.sh`.
7. Run `scripts/staging-smoke-test.sh`.
8. Run the shadow and program switch tests.
9. Run `scripts/soak-test.sh` for the approved duration.
10. Review NOC incidents, failover history, Supervisor runtime snapshots, backup manifests, and CI results.

## Rollback
Run `scripts/rollback-staging.sh`. The script stops only the staging compose project and does not modify production.

## Promotion gate
Staging must not be promoted to production until all of these are true:
- CI contracts pass.
- Security preflight passes.
- Backup/restore validation passes.
- Shadow A/B switch test passes.
- Program output remains healthy during the soak period.
- No unresolved critical NOC incident remains.
- A human approves the cutover runbook and rollback point.

## Explicit non-goals
This package does not configure production YouTube, production IPTV, production DNS, production stream keys, or the current production server.
