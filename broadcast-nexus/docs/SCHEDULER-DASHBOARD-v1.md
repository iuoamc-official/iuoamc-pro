# Broadcast Nexus Scheduler Dashboard v1

## Purpose
Phase 21 turns the Scheduler placeholder into an authenticated staging operations surface.

## Capabilities
- Select channels from the Orchestrator API.
- Create and list programs.
- Create and list episodes under a program.
- Create and list playlists.
- Create and list rundowns.
- Create schedule slots with explicit conflict pre-checks.
- View the schedule timeline.
- View EPG events.
- Set the staging EPG `published` flag.

## Authentication
The operator JWT is held only in JavaScript memory for the active tab. The UI does not write it to browser persistent storage.

## Safety
- Staging only.
- `PRODUCTION_SWITCHING=false` remains enforced by the staging gateway and stack.
- EPG publish only updates the isolated staging database flag.
- No public IPTV publishing is triggered.
- No YouTube, RTMP, SRT, or production distribution output is activated.
- No production stream keys are stored in the dashboard.

## Routes
- UI: `/scheduler-control/`
- Scheduler API: `/scheduler-api/`
- Channel inventory: `/orchestrator-api/`

## Service extension
`app.runtime:app` loads `app.phase21`, adding read/list and episode/rundown/EPG operations without replacing the Phase 2 scheduler core.

## Conflict model
The dashboard calls the scheduler conflict endpoint before creating a slot. Non-emergency overlapping slots are rejected by both the UI workflow and the existing scheduler API.
