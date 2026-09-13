# Encoder Control Dashboard v1

## Phase
Broadcast Nexus Phase 24.

## Purpose
Provide an authenticated staging control surface for encoder nodes, house profiles and encoder job lifecycle without spawning a production encoder process or enabling any external broadcast output.

## Dashboard route
- `/encoder-control/`

## API route
- `/encoder-api/`

## Capabilities
- Register or heartbeat encoder nodes.
- View node state, zone and capabilities.
- Change node state between standby, ready, busy, degraded and offline.
- List fixed house profiles for staging job intent.
- Create encoder job intent for a channel and optional playout run.
- Attach an encoder node to a queued/assigned job.
- Inspect jobs by channel.
- Move a job through the isolated state machine: queued → assigned → running → stopping → stopped, with failure paths.
- Read a safety endpoint before operations.

## Safety model
`running` is a control-plane database state only in this phase.

The extension explicitly reports:
- `production_execution=false`
- `ffmpeg_spawn_enabled=false`
- `external_destinations_enabled=false`

The staging Compose overlay keeps:
- `PRODUCTION_OUTPUTS_ENABLED=false`

If that flag is true, the Phase 24 job state endpoint refuses state-changing execution with HTTP 503.

No stream keys, RTMP/SRT URLs, YouTube endpoints, production HLS origins or CDN configuration are introduced by this phase.

## Authentication
The UI accepts an operator JWT and keeps it only in page memory. It is not stored in localStorage or sessionStorage.

## Production isolation
This phase does not modify the current Laravel application, production host, production systemd services, production encoder, DNS, firewall, or current IUOAMC TV broadcast path.
