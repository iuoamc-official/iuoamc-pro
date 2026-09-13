# Cutover & Rollback Runbook v1

## Current status
Planning only. This runbook does not execute production changes.

## Preconditions
- CI green.
- 24–72h staging soak passed.
- Backup/restore drill passed.
- Shadow failover passed.
- Security review passed.
- Production secrets loaded only at cutover time from an approved secret store.
- Existing production remains healthy and available for rollback.

## Cutover sequence
1. Freeze nonessential configuration changes.
2. Capture final production configuration inventory.
3. Confirm old encoder and old platform are healthy.
4. Start new production stack without public routing.
5. Validate internal HLS and NOC health.
6. Validate one non-public test destination.
7. Move IPTV/web distribution first.
8. Observe for a defined stability window.
9. Move external live destination only after approval.
10. Keep old system intact but passive during rollback window.

## Immediate rollback triggers
- program output unavailable beyond threshold;
- repeated freeze/black/silence alarms;
- encoder instability;
- timestamp/DTS instability;
- NOC cannot determine active program path;
- authentication or control-plane failure;
- data integrity concern.

## Rollback sequence
1. Stop only new external distribution outputs.
2. Restore routing to the existing production system.
3. Confirm old Program output health.
4. Confirm YouTube/IPTV/web health.
5. Preserve new-system logs and state for analysis.
6. Do not destroy the failed environment until incident review is complete.

## Guardrail
No automated script in this repository is authorized to perform this production cutover or rollback without a separately approved production procedure.
