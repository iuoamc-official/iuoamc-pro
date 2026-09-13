# Phase 33 — Staging SFU / TURN Integration Layer

## Purpose
Prepare the Broadcast Nexus WebRTC stack for a future dedicated staging VPS without enabling public WebRTC ingress or production media routing.

## What is added
- RTC Gateway provider readiness API.
- Explicit `SFU_PROVIDER`, `SFU_ENABLED`, `TURN_ENABLED`, and `PUBLIC_WEBRTC_INGRESS_ENABLED` controls.
- Placeholder-only SFU/TURN credential variables in `.env.example`.
- Optional `compose.sfu-staging.yaml` overlay with an SFU lab and TURN lab.
- Loopback-only host bindings for all Phase 33 lab ports.
- Contract tests proving the default state remains disabled and isolated.

## Provider readiness
`GET /rtc-gateway-api/v1/provider-config` returns only readiness metadata. It never returns SFU keys, API secrets, TURN shared secrets, or production credentials.

`GET /rtc-gateway-api/v1/readiness` verifies the staging safety envelope, including public WebRTC ingress and production output flags.

## Default state
The committed example configuration keeps:

- `SFU_PROVIDER=loopback`
- `SFU_ENABLED=false`
- `TURN_ENABLED=false`
- `PUBLIC_WEBRTC_INGRESS_ENABLED=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`

Therefore Phase 32 local loopback remains the effective WebRTC transport until a future explicit staging activation.

## Optional lab overlay
The `sfu-lab` profile in `compose.sfu-staging.yaml` defines isolated SFU and TURN containers. All published host ports are bound to `127.0.0.1`; this overlay does not create public ingress.

No real credentials are committed. `CHANGE_ME_*` values are placeholders and readiness rejects placeholder credentials when a provider is enabled.

## Future staging VPS activation
Activation on the new staging VPS must be a separate reviewed step. It will require generated secrets, TLS, DNS decisions, firewall rules, public UDP/TCP planning, and explicit approval. None of those actions are performed by Phase 33.

## Production safety
Phase 33 does not modify the current IUOAMC production server, current broadcast encoder, production DNS, systemd, firewall, YouTube, RTMP, SRT, IPTV production, or CDN configuration.
