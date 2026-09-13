# WebRTC Media Layer v1

## Scope
This phase models the real-time media layer without connecting to any production broadcast output.

## Components

### Media Router Adapter
Responsibilities:
- Provision logical media-router rooms
- Track participant media sessions
- Register audio/video/screen tracks
- Maintain room media state
- Emit active-speaker events
- Track screen-sharing sessions

The adapter deliberately does not hard-code one SFU implementation. It is designed so a later provider adapter can target LiveKit, mediasoup, Janus, or another SFU without changing the control-plane contract.

### Participant Join Flow
1. Live Studio creates/authorizes a participant.
2. Media Router creates a participant session.
3. Browser or future SFU adapter negotiates media externally to this control API.
4. Published tracks are registered by kind/source.
5. Track lifecycle and connection state are emitted over NATS.

### Track Types
- audio / microphone
- video / camera
- screen_video / screen_share
- screen_audio / system_audio

### Active Speaker
Audio-level signals are recorded as time-series events and emitted as `studio.active_speaker.changed` events. Later layout automation can subscribe without coupling the media router to graphics or switching logic.

### Screen Share
Screen sharing is modeled independently from camera tracks so it can be routed to Preview or Program, recorded as ISO, or composed into layouts.

## ISO Recording Worker
Recording jobs are explicit state machines:

queued -> starting -> recording -> finalizing -> completed

Terminal alternatives:
- failed
- cancelled

Recording kinds:
- participant_av
- participant_audio
- screen
- program
- preview
- audio_mix

The current worker is control-plane only. It does not invoke FFmpeg, GStreamer, or an SFU egress API yet. This prevents accidental capture or broadcast during isolated development.

## Storage
Completed recordings reference object-storage bucket/object metadata and SHA-256 checksums. No recording media is committed to Git.

## Local ports
- Media Router Adapter: `127.0.0.1:58105`
- ISO Recorder: `127.0.0.1:58106`

## Events
Examples:
- studio.router.ready
- studio.participant.connected
- studio.track.published
- studio.active_speaker.changed
- studio.screen_share.started
- studio.recording.queued
- studio.recording.started
- studio.recording.completed
- studio.recording.failed

## Production isolation
This phase contains no:
- TURN credentials
- SFU production endpoint
- production WebSocket ingress
- RTMP/SRT output
- YouTube integration
- production Nginx routing
- systemd service installation
- modification to the existing IUOAMC site
