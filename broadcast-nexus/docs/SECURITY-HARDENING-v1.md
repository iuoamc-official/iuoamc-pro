# IUOAMC Broadcast Nexus — Security Hardening v1

## Status
Pre-staging security baseline. This document does not enable production access.

## Security boundaries

### 1. Secrets
- No stream keys, database passwords, JWT secrets, SSH keys, TLS private keys, or provider tokens in Git.
- `.env` remains untracked; only `.env.example` is allowed.
- Production secrets must later come from a dedicated secrets manager or host credential store.
- Secrets must be independently scoped per service and environment.

### 2. Network
- Development and shadow ports bind to `127.0.0.1` only.
- Shadow media network remains Docker-internal.
- Database, Redis, NATS, MinIO, NOC, supervisor, and control APIs are not publicly exposed in the current phase.
- Production ingress/egress rules will be deny-by-default.

### 3. Identity and authorization
- API authorization uses explicit permissions.
- Production staging requires centralized identity, MFA for administrative users, short-lived sessions, and role separation.
- High-impact actions require audit events and explicit operator authorization.

### 4. Broadcast safety
- Production switching remains disabled.
- Shadow failover may switch only isolated synthetic HLS sources.
- No production RTMP/RTMPS/SRT destination is permitted during shadow testing.
- No `systemctl`, production process kill, DNS change, or reverse-proxy mutation is permitted from shadow services.

### 5. Audit
- Security-sensitive control actions must create audit records.
- Staging will add append-only audit export and retention.
- Production will require externalized audit retention so an application administrator cannot silently rewrite history.

### 6. Supply chain
- CI performs repository isolation checks, contract tests, syntax checks, and Docker Compose validation.
- Production release phase will add image vulnerability scanning, SBOM generation, image signing, immutable tags, and verified deployment provenance.

### 7. Data protection
- Media and recordings are private by default.
- Object-storage buckets must deny anonymous access.
- Recording retention and deletion rules will be explicit per content class.

## Mandatory pre-staging gates
1. All contract tests pass.
2. No production destination appears in executable shadow code.
3. No committed secret-like private key material.
4. Docker Compose validates without starting services.
5. Soak framework completes the selected duration with zero production connectivity.
6. Backup/restore test is completed before public staging.

## Not enabled yet
- Public ingress
- Production output destinations
- Production secrets
- Automatic production failover
- DNS cutover
- Production deployment
