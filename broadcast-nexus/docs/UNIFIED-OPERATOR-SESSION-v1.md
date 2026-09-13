# Unified Operator Session v1 — Phase 31

## Goal
Replace repeated manual JWT entry across every Broadcast Nexus control page with one short-lived operator session for the isolated staging gateway.

## Flow
1. Operator opens `/auth-control/`.
2. A short-lived operator JWT is submitted once to `/auth-api/v1/session/exchange`.
3. The Operator Session Broker validates issuer, audience, signature and expiry using the existing Nexus JWT contract.
4. The JWT is stored server-side in Redis under a cryptographically random session ID with a bounded TTL.
5. The browser receives only an `HttpOnly`, `SameSite=Strict` session cookie.
6. Every protected API route uses Nginx `auth_request` against the internal session validator.
7. The gateway injects the validated bearer token into the internal service request. Browser pages do not need to retain the real JWT.
8. `/auth-api/v1/session/logout` deletes the Redis session and clears the cookie.

## Browser Storage
No `localStorage` and no `sessionStorage` are used for authentication. The sign-in field is cleared immediately after exchange. Existing control pages are bootstrapped with a non-secret placeholder so their legacy client-side token guard continues to work while Nginx replaces the Authorization header with the server-side session token.

## Security Properties
- Session IDs are generated with `secrets.token_urlsafe(32)`.
- Session data is stored in private Redis DB 1.
- Session TTL defaults to 30 minutes and can never exceed the source JWT expiry.
- Validation re-checks the stored JWT on every authenticated request.
- The validation endpoint is exposed only through an Nginx `internal` location.
- There is no generic public `/auth-api/` proxy; only exchange, status and logout are browser-routable.
- Cookie is `HttpOnly` and `SameSite=Strict`.
- `SESSION_COOKIE_SECURE=false` is used only while the staging gateway is loopback HTTP. It must be `true` when staging is placed behind HTTPS.

## Safety Envelope
This phase does not enable production media execution. Staging remains configured with:
- `PRODUCTION_SWITCHING=false`
- `PRODUCTION_OUTPUTS_ENABLED=false`
- `PUBLIC_PUBLISHING_ENABLED=false`

No production stream key, CDN origin, DNS record, encoder process or distribution destination is introduced by this phase.
