# Broadcast Nexus Playout Control Dashboard v1

## Phase 23 purpose
Provide an authenticated staging control surface for deterministic playout operations without enabling production media output.

## UI
Route: `/playout-control/`

Capabilities:
- select channel and rundown
- compile deterministic rundown queue
- inspect queue items and SHA-256 queue hash
- list channel playout runs
- move runs through legal control-plane states
- inspect fallback resolution behavior

## State machine
Allowed transitions:
- compiled -> ready / cancelled
- ready -> running / cancelled
- running -> paused / completed / failed / cancelled
- paused -> running / completed / failed / cancelled

Terminal states do not transition further in this phase.

## Safety
`running` means only the playout control-plane run state. Phase 23 does not activate FFmpeg output, encoder jobs, distribution destinations, YouTube, IPTV, RTMP or SRT.

All staging ingress remains behind the existing local staging gateway and `X-Production-Switching: false` guard.

## API additions
- `GET /v1/channels/{channel_id}/runs`
- `POST /v1/runs/{run_id}/transition`
- `GET /v1/runs/{run_id}/events`
- `GET /v1/channels/{channel_id}/fallback-policies`
- `GET /v1/control-safety`

The existing deterministic compiler, queue hash and fallback resolver remain unchanged.
