# Security & Isolation Guardrails

1. Never store stream keys, passwords, tokens, SSH keys, or production `.env` files in Git.
2. Never point the Broadcast Nexus development stack at the existing production database.
3. Development services bind to `127.0.0.1` only until an explicit staging deployment is approved.
4. No changes to production DNS, Nginx, Apache, systemd, firewall, certificates, or server users from this branch.
5. No production RTMP/SRT/WebRTC destination is permitted during the foundation phases.
6. No edits to existing Laravel application files outside `broadcast-nexus/` from this branch.
7. Use synthetic/local media only until shadow testing is approved.
8. Every future production migration must include rollback and a pre-cutover checklist.
9. The current website and broadcast remain authoritative production until explicit cutover approval.
10. Any future production secret must be injected by a secrets manager or runtime environment, never committed.
