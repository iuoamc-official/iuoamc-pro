# Staging Design v1

## Principle
Staging must be physically and logically separate from the current production website and broadcast stack.

## Proposed endpoints
- control-staging.iuoamc.pro
- studio-staging.iuoamc.pro
- noc-staging.iuoamc.pro
- stream-staging.iuoamc.pro
- api-staging.iuoamc.pro

## Rules
- No production stream keys.
- No production database credentials.
- No production DNS target changes.
- No shared writable storage with the current site.
- All staging outputs use synthetic or explicitly designated test destinations.
- Production credentials are not copied into staging.

## Deployment order
1. Provision isolated staging host/network.
2. Install container runtime and reverse proxy.
3. Deploy PostgreSQL, Redis, NATS, object storage.
4. Deploy control services.
5. Deploy media services.
6. Deploy NOC/Supervisor.
7. Run database migrations and seed test channel.
8. Run shadow HLS A/B.
9. Run 24–72 hour soak test.
10. Perform backup/restore drill.
11. Perform failure injection and recovery drills.

## Promotion gate
Staging is considered ready only when CI is green, restore drill passes, soak thresholds pass, and no production destination appears in configuration.
