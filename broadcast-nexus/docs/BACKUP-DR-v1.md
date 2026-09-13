# Backup & Disaster Recovery v1

## Scope
This document covers the isolated Broadcast Nexus only. It does not alter the current production website or stream.

## Recovery objectives
- PostgreSQL metadata: RPO <= 15 minutes target, RTO <= 60 minutes target.
- Object media metadata/artifacts: versioned object storage with replicated copies.
- NATS JetStream: periodic snapshot/restore procedure.
- Configuration: Git as source of truth; secrets remain outside Git.

## Backup classes
1. PostgreSQL logical dump plus periodic base backup.
2. MinIO/S3 object replication or versioned backup bucket.
3. NATS JetStream stream snapshots.
4. Audit/export bundle for channel state, schedules, failover policies, and runtime configuration.

## Restore validation
A backup is not considered valid until restored into an isolated restore environment and the following pass:
- schema opens successfully;
- channel/runtime records can be read;
- media object references resolve;
- NATS streams are readable;
- supervisor starts in non-production mode;
- no production destination is present.

## DR topology
Future staging/production design supports a secondary node/provider with:
- replicated PostgreSQL backups;
- replicated object storage;
- clean deployment from GitHub artifacts;
- separate secrets store;
- DNS/traffic cutover performed only through an explicit runbook.

## Safety
No automated production failover is enabled by this document or the accompanying scripts.
