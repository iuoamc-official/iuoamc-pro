# IUOAMC Live Studio Core v1

## Purpose
Provide the control plane for professional browser-based interviews, panels, training rooms, event feeds and internal live sessions without coupling studio state to the current production broadcast.

## Safety boundary
This phase does not include a production WebRTC router, RTMP/SRT output, FFmpeg process, stream key, DNS change or connection to the existing IUOAMC live channel.

## Core model

### Studio Room
A room is a managed production session with:
- channel/session association
- workflow state
- maximum participant capacity
- 1920x1080 / 25fps program defaults
- metadata and audit trail

States:
`draft -> waiting -> ready -> live -> ended -> archived`

### Participants
Roles:
- director
- host
- cohost
- guest
- producer
- observer

Participant state is separate from room state so a guest can wait, connect, move to preview/program and disconnect without mutating the production workflow.

### Guest invitations
Guest links use random one-time material. Only SHA-256 hashes are stored in the database. Plain invite tokens are returned once to the authorized caller.

### Preview / Program buses
The studio has two explicit buses:
- PVW / Preview
- PGM / Program

The `TAKE` operation copies the current preview scene to program and increments a monotonic revision. This makes switching auditable and safe for future active/active renderers.

### Scenes
Initial layouts:
- single
- split2
- split3
- split4
- grid
- picture-in-picture
- presentation
- custom

Scenes contain ordered layers rather than hard-coded page layouts.

### Layer types
- participant
- screen share
- media
- image
- color
- lower third
- ticker
- logo/bug
- clock
- HTML renderer

### Graphics
Graphics templates are versioned and separated from runtime instances. This supports lower thirds, tickers, logos, clocks, breaking-news banners and custom graphics without mixing template definition with on-air state.

### Recording model
Recording requests support:
- Program output
- Preview output
- ISO participant recording
- ISO screen recording
- Audio mix

This phase models recording lifecycle only. Media workers will be implemented later.

## Event subjects
Initial studio events include:
- `studio.room.created`
- `studio.room.waiting`
- `studio.room.ready`
- `studio.room.live`
- `studio.room.ended`
- `studio.participant.created`
- `studio.scene.created`
- `studio.bus.preview.changed`
- `studio.bus.program.changed`
- `studio.program.take`
- `studio.recording.requested`

## Future WebRTC layer
The media router will be attached behind a provider-neutral adapter. The control plane must not depend directly on one vendor. Candidates can include LiveKit, mediasoup or another standards-based SFU.

The future adapter contract will expose:
- room provisioning
- participant grants
- publish/subscribe permissions
- track inventory
- active speaker telemetry
- egress/recording hooks
- health state

## High-availability direction
The studio state is database/event-driven so renderers and media-router nodes can be replaced without losing the editorial state. Preview/program revisions are designed for idempotent consumers and future leader/failover logic.
