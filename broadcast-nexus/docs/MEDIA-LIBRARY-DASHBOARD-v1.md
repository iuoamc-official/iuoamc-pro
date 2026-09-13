# IUOAMC Broadcast Nexus — Media Library Dashboard v1

## Phase 22 scope
Phase 22 turns the Media Library dashboard from a placeholder into an authenticated staging control surface.

Implemented capabilities:
- list registered media assets;
- register video, audio, image, subtitle and document assets;
- inspect one asset and its storage metadata;
- edit title, duration and JSON metadata;
- request the existing presigned PUT upload URL;
- verify a presigned/external upload with object-store HEAD validation;
- generate a short-lived presigned GET download URL;
- upload through an isolated staging relay when the private MinIO endpoint is not browser-reachable;
- display asset key, media type, status and byte size.

## Staging routes
- UI: `/media-control/`
- API: `/media-library-api/`

The Media Library service remains internal to the Docker network. The gateway forwards authenticated staging requests only.

## Upload model
The original presigned upload flow remains the primary object-storage API:
1. register asset;
2. request `/v1/assets/{asset_id}/upload-url`;
3. PUT the object using the signed URL;
4. call `/v1/assets/{asset_id}/upload-complete`;
5. the service performs `HEAD` against object storage before setting the asset to `uploaded`.

For the isolated staging browser, `/v1/assets/{asset_id}/upload-relay` is also available. It streams the request to a temporary file, enforces a configurable staging size limit, uploads the object through the private S3 endpoint, removes the temporary file and marks the asset uploaded.

Default relay limit: 2 GiB (`MEDIA_RELAY_MAX_BYTES`).

## Security and isolation
- JWT exists only in page memory for the current browser tab.
- No browser persistence is used for the operator token.
- MinIO credentials are never sent to the browser.
- Asset buckets remain private.
- Download URLs expire after 15 minutes.
- Upload URLs expire after 15 minutes.
- No production CDN/public publishing is enabled.
- No YouTube, RTMP, SRT or production IPTV destination is connected.
- `X-Production-Switching: false` remains enforced by the staging gateway.

## Audit
Metadata updates, relay uploads and upload completion events are recorded in `audit_events`. Successful uploads publish the internal `media.asset.uploaded` NATS event.

## Production boundary
Phase 22 does not make object storage public. Any future production object-store ingress, CDN, lifecycle policy, replication policy or public delivery hostname requires a separate explicit production phase.
