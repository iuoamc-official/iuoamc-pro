# Channel Operations Dashboard v1

## Purpose
Phase 20 turns the Channels area into a real staging control surface for IUOAMC Broadcast Nexus while preserving the production isolation boundary.

## Operator workflow
- Enter an operator JWT for the current browser tab only.
- List and create channels through the Broadcast Orchestrator API.
- Create broadcast-session records for a selected channel.
- Initialize or change the channel Supervisor source mode: playout, studio, emergency, or shadow.
- Inspect runtime state, source mode, generation, service health, and recent transitions.
- Request legal state-machine transitions through the Broadcast Supervisor API.

## Runtime states
The dashboard exposes the Supervisor states: stopped, starting, running, degraded, recovering, stopping, and failed. The Supervisor service remains authoritative and rejects illegal transitions.

## Safety boundary
Phase 20 does not activate a production transport or destination. The page controls metadata and the isolated Supervisor state machine only.

Required staging constraints remain:
- `PRODUCTION_SWITCHING=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`
- `PUBLIC_PUBLISHING_ENABLED=false`
- no production stream keys in GitHub or the UI
- no production DNS/server changes

## Routes
- UI: `/channels-control/`
- Orchestrator API: `/orchestrator-api/`
- Supervisor API: `/supervisor/`

## Dashboard navigation
The Dashboard UI container injects a single `navigation.js` asset into HTML responses. It routes Live Studio and Channels buttons to their operational pages without modifying the current production Laravel application.

## Authentication
The operator JWT is held only in an in-memory JavaScript variable for the active page. The Phase 20 contracts reject browser-storage use in executable scripts.

## Production status
Production switching is not implemented or enabled by this dashboard.
