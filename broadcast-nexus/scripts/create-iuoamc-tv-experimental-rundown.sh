#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: $ROOT/.env not found" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

for flag in PRODUCTION_SWITCHING PRODUCTION_OUTPUTS_ENABLED PUBLIC_PUBLISHING_ENABLED; do
  value="${!flag:-false}"
  if [[ "${value,,}" != "false" ]]; then
    echo "ERROR: $flag must remain false for experimental rundown creation (current: $value)" >&2
    exit 1
  fi
done

compose=(docker compose -f compose.yaml -f compose.noc.yaml -f compose.supervisor.yaml -f compose.staging.yaml)
postgres_cid="$(${compose[@]} ps -q postgres)"
[[ -n "$postgres_cid" ]] || { echo "ERROR: postgres container is not running" >&2; exit 1; }

# Ensure all required imported assets exist and are ready before touching the playlist.
required_keys=(
  AST-LEGACY-TV-IDENT-001
  AST-LEGACY-TV-PROG-002
  AST-LEGACY-TV-UPNEXT-001
  AST-LEGACY-TV-PROG-003
)

for key in "${required_keys[@]}"; do
  state="$(${compose[@]} exec -T postgres psql -At -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT status FROM media_assets WHERE asset_key='${key}' LIMIT 1;")"
  if [[ "$state" != "ready" ]]; then
    echo "ERROR: required media asset $key is not ready (status: ${state:-missing})" >&2
    exit 1
  fi
done

playlist_name="IUOAMC TV Experimental Loop 001"
rundown_name="IUOAMC TV Experimental Broadcast 001"

# Create or reuse the experimental playlist.
playlist_id="$(${compose[@]} exec -T postgres psql -At -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<SQL
WITH ch AS (
  SELECT id FROM channels WHERE slug='iuoamc-tv' LIMIT 1
), existing AS (
  SELECT p.id FROM playlists p JOIN ch ON ch.id=p.channel_id
  WHERE p.name='${playlist_name}' LIMIT 1
), inserted AS (
  INSERT INTO playlists(channel_id,name,status,loop_enabled)
  SELECT ch.id,'${playlist_name}','draft',true FROM ch
  WHERE NOT EXISTS (SELECT 1 FROM existing)
  RETURNING id
)
SELECT id FROM inserted
UNION ALL
SELECT id FROM existing
LIMIT 1;
SQL
)"

[[ -n "$playlist_id" ]] || { echo "ERROR: channel iuoamc-tv not found or playlist could not be created" >&2; exit 1; }

# Rebuild only this isolated experimental playlist so re-running the script is deterministic.
${compose[@]} exec -T postgres psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<SQL
BEGIN;
DELETE FROM playlist_items WHERE playlist_id='${playlist_id}'::uuid;

INSERT INTO playlist_items(playlist_id,position,item_type,media_asset_id,title_override,metadata)
SELECT '${playlist_id}'::uuid,1,'ident',id,'IUOAMC TV Official Ident',
       jsonb_build_object('experimental',true,'source','legacy-import','production_outputs_enabled',false)
FROM media_assets WHERE asset_key='AST-LEGACY-TV-IDENT-001';

INSERT INTO playlist_items(playlist_id,position,item_type,media_asset_id,title_override,metadata)
SELECT '${playlist_id}'::uuid,2,'asset',id,'Mise en Place',
       jsonb_build_object('experimental',true,'role','PROGRAM','production_outputs_enabled',false)
FROM media_assets WHERE asset_key='AST-LEGACY-TV-PROG-002';

INSERT INTO playlist_items(playlist_id,position,item_type,media_asset_id,title_override,metadata)
SELECT '${playlist_id}'::uuid,3,'break',id,'IUOAMC TV Up Next',
       jsonb_build_object('experimental',true,'role','UP_NEXT','production_outputs_enabled',false)
FROM media_assets WHERE asset_key='AST-LEGACY-TV-UPNEXT-001';

INSERT INTO playlist_items(playlist_id,position,item_type,media_asset_id,title_override,metadata)
SELECT '${playlist_id}'::uuid,4,'asset',id,'Knife Skills',
       jsonb_build_object('experimental',true,'role','PROGRAM','production_outputs_enabled',false)
FROM media_assets WHERE asset_key='AST-LEGACY-TV-PROG-003';

INSERT INTO playlist_items(playlist_id,position,item_type,media_asset_id,title_override,metadata)
SELECT '${playlist_id}'::uuid,5,'ident',id,'IUOAMC TV Official Ident',
       jsonb_build_object('experimental',true,'source','legacy-import','production_outputs_enabled',false)
FROM media_assets WHERE asset_key='AST-LEGACY-TV-IDENT-001';

UPDATE playlists
SET status='draft', loop_enabled=true, updated_at=now()
WHERE id='${playlist_id}'::uuid;
COMMIT;
SQL

# Create or reuse a draft rundown for today. If another version exists for today,
# allocate the next free version. Re-running this script reuses the named rundown.
rundown_id="$(${compose[@]} exec -T postgres psql -At -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<SQL
WITH ch AS (
  SELECT id FROM channels WHERE slug='iuoamc-tv' LIMIT 1
), existing AS (
  SELECT r.id FROM rundowns r JOIN ch ON ch.id=r.channel_id
  WHERE r.name='${rundown_name}' LIMIT 1
), next_version AS (
  SELECT COALESCE(MAX(r.version),0)+1 AS v
  FROM rundowns r JOIN ch ON ch.id=r.channel_id
  WHERE r.scheduled_date=CURRENT_DATE
), inserted AS (
  INSERT INTO rundowns(channel_id,name,scheduled_date,state,version)
  SELECT ch.id,'${rundown_name}',CURRENT_DATE,'draft',next_version.v
  FROM ch,next_version
  WHERE NOT EXISTS (SELECT 1 FROM existing)
  RETURNING id
)
SELECT id FROM inserted
UNION ALL
SELECT id FROM existing
LIMIT 1;
SQL
)"

[[ -n "$rundown_id" ]] || { echo "ERROR: rundown could not be created" >&2; exit 1; }

# Audit only if this exact setup has not already been recorded.
${compose[@]} exec -T postgres psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<SQL
INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
SELECT
  'experimental-rundown-setup','scheduler.experimental.rundown.create','rundown','${rundown_id}','scheduler',
  jsonb_build_object(
    'channel_slug','iuoamc-tv',
    'playlist_id','${playlist_id}',
    'playlist_name','${playlist_name}',
    'experimental',true,
    'production_switching',false,
    'production_outputs_enabled',false,
    'public_publishing_enabled',false
  )
WHERE NOT EXISTS (
  SELECT 1 FROM audit_events
  WHERE action='scheduler.experimental.rundown.create'
    AND resource_id='${rundown_id}'
);
SQL

echo "===== EXPERIMENTAL PLAYLIST ====="
${compose[@]} exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c "
SELECT p.name,p.status,p.loop_enabled,pi.position,pi.item_type,
       COALESCE(pi.title_override,m.title) AS item_title,m.asset_key,m.status AS asset_status
FROM playlists p
JOIN playlist_items pi ON pi.playlist_id=p.id
LEFT JOIN media_assets m ON m.id=pi.media_asset_id
WHERE p.id='${playlist_id}'::uuid
ORDER BY pi.position;"

echo "===== EXPERIMENTAL RUNDOWN ====="
${compose[@]} exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c "
SELECT c.slug,c.name AS channel,r.name AS rundown,r.scheduled_date,r.state,r.version
FROM rundowns r JOIN channels c ON c.id=r.channel_id
WHERE r.id='${rundown_id}'::uuid;"

echo "Experimental playlist and draft rundown created safely."
echo "No schedule slot was activated and all production/public outputs remain disabled."
