# Deterministic Playout v1

## Purpose
Create a playout control layer that compiles schedule data into an immutable, hashable queue before any future media engine executes it.

## Design principles
- Deterministic compilation
- Explicit queue hashes
- No hidden timing decisions at runtime
- Fallback policy resolution is separate from media execution
- EPG is generated from the same scheduling source of truth
- No production encoder or stream output exists in this phase

## Flow
Scheduler -> Rundown -> Compiler -> Playout Run -> Queue Items

Each compiled run stores:
- source revision
- channel
- rundown
- deterministic queue hash
- ordered queue items
- planned timestamps
- planned durations
- fallback policy reference

## Fallback model
Triggers:
- missing_asset
- source_timeout
- decode_error
- live_unavailable
- manual

Actions:
- play_asset
- play_slate
- play_filler
- hold_last
- skip

Fallback rules are channel-scoped and ordered by priority.

## EPG generation
EPG records are derived from schedule slots. Generation does not publish them automatically; approval/publication remains a separate future control-plane action.

## Safety boundary
This service does not execute FFmpeg, GStreamer, SRT, RTMP, HLS, or WebRTC. It only compiles and exposes playout intent. Media execution will be introduced behind a separate engine boundary in a later phase.
