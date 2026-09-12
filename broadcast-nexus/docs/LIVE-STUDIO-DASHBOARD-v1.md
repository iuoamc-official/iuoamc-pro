# Live Studio Control Dashboard v1

## Purpose
Phase 19 provides a real staging operator surface for the existing IUOAMC Live Studio control plane.

## Operator functions
- Authenticate with a staging JWT held in browser-tab memory only.
- List and create studio rooms.
- Inspect room state, Preview and Program buses.
- Add participants with studio roles.
- Toggle participant microphone, camera and screen-share control flags.
- Create scenes and select the Preview scene.
- TAKE Preview to Program.
- Move rooms through waiting, ready, live, ended and archived states according to the server transition rules.

## Routing
- UI: `/studio-control/`
- API proxy: `/live-studio-api/`
- The main Dashboard Live Studio navigation is redirected to the Studio Control surface in staging.

## Security
The browser does not persist the operator JWT in localStorage or sessionStorage. Authorization is forwarded only to the isolated Live Studio API.

Phase 19 does not activate a production media router or any YouTube, RTMP, SRT or IPTV production destination. Studio `live` is a control-plane room state only.

## Production isolation
The staging gateway continues to advertise `X-Production-Switching: false`. Distribution and encoder production outputs remain disabled by the staging compose guardrails.
