# Control Room / NOC Dashboard v1

## Purpose
Provide an isolated operations interface for observing Broadcast Nexus state without touching production infrastructure.

## Components
- `control-room` API: local port `58115`
- `noc-dashboard`: local port `58116`
- Monitoring / Alerts / Failover / NOC services remain separate.

## Real-time telemetry
The control room accepts normalized telemetry from isolated services and presents the latest metric per source.

Examples:
- encoder FPS / bitrate / latency
- packet loss / jitter
- HLS freshness
- black frame / frozen frame / silence flags
- failover recommendation state
- open NOC incident count

## Chaos testing
Chaos scenarios are synthetic-only in this phase.

Supported initial scenarios:
- frozen frame
- black frame
- silence
- bitrate drop
- packet loss
- stale HLS
- primary unavailable

A simulation records injected conditions and expected/observed decisions. It does not execute `systemctl`, modify routes, stop processes, change stream destinations, or touch production.

## UI
The dashboard refreshes local telemetry every five seconds and displays:
- open incidents
- pending failover decisions
- critical metric count
- latest telemetry table
- synthetic chaos scenario catalog

Authentication uses a local development bearer token stored in browser local storage. This must be replaced by the production identity flow before staging.

## Safety boundary
The dashboard and control-room API are bound to `127.0.0.1` only. They have no production stream key, SSH capability, production host reference, or automatic switching authority.
