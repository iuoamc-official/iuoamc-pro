# IPTV / HLS Control Dashboard v1

Phase 26 adds an isolated staging control surface for HLS manifest records.

## Capabilities
- authenticated channel selection
- manifest upsert for variant, target duration and playlist window
- manifest state control: idle, ready, active, stale, error
- M3U preview generation
- channel preview snapshot with staging-path validation
- audit events and NATS state events

## Safety
- `PUBLIC_PUBLISHING_ENABLED=false` is required for staging operation
- manifest paths must remain under `/staging-hls/`
- absolute URLs and parent-directory traversal are rejected by the Phase 26 state controller
- no public HLS origin is enabled
- no CDN publishing is enabled
- no production HLS is enabled
- ACTIVE is a control-plane state only and does not start a media process
- no stream keys or production destination credentials are stored in the UI

## Staging routes
- UI: `/iptv-control/`
- API: `/iptv-api/`

The current production broadcast platform is not modified or contacted by this phase.
