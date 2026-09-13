#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMPORT_DIR="${LEGACY_TV_IMPORT_DIR:-/opt/iuoamc-broadcast/import/legacy-tv}"
cd "$ROOT"

[[ -f .env ]] || { echo "ERROR: $ROOT/.env not found" >&2; exit 1; }
set -a
# shellcheck disable=SC1091
source .env
set +a

for flag in PRODUCTION_SWITCHING PRODUCTION_OUTPUTS_ENABLED PUBLIC_PUBLISHING_ENABLED; do
  value="${!flag:-false}"
  [[ "${value,,}" == "false" ]] || { echo "ERROR: $flag must remain false (current: $value)" >&2; exit 1; }
done

compose=(docker compose -f compose.yaml -f compose.noc.yaml -f compose.supervisor.yaml -f compose.staging.yaml)

# Ensure the expected imported files exist locally. We use them only to measure duration.
required=(
  transitions/mca-tv-official-ident-3s.mp4
  programs/mca-tv-episode-02-mise-en-place.mp4
  transitions/mca-tv-official-up-next-3s.mp4
  programs/mca-tv-episode-03-knife-skills.mp4
)
for rel in "${required[@]}"; do
  [[ -f "$IMPORT_DIR/$rel" ]] || { echo "ERROR: missing $IMPORT_DIR/$rel" >&2; exit 1; }
done

# Obtain exact durations. Prefer host ffprobe; otherwise use a short-lived ffmpeg container.
probe_ms() {
  local file="$1" seconds
  if command -v ffprobe >/dev/null 2>&1; then
    seconds="$(ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 "$file")"
  else
    seconds="$(docker run --rm -v "$IMPORT_DIR:/media:ro" linuxserver/ffmpeg:latest \
      -v error -show_entries format=duration -of default=nw=1:nk=1 "/media/${file#$IMPORT_DIR/}" 2>/dev/null || true)"
  fi
  [[ -n "$seconds" ]] || { echo "ERROR: could not determine duration for $file" >&2; exit 1; }
  awk -v s="$seconds" 'BEGIN { printf "%d", (s*1000)+0.5 }'
}

ident_ms="$(probe_ms "$IMPORT_DIR/transitions/mca-tv-official-ident-3s.mp4")"
mise_ms="$(probe_ms "$IMPORT_DIR/programs/mca-tv-episode-02-mise-en-place.mp4")"
upnext_ms="$(probe_ms "$IMPORT_DIR/transitions/mca-tv-official-up-next-3s.mp4")"
knife_ms="$(probe_ms "$IMPORT_DIR/programs/mca-tv-episode-03-knife-skills.mp4")"

total_ms=$((ident_ms + mise_ms + upnext_ms + knife_ms + ident_ms))

echo "===== MEASURED DURATIONS ====="
printf 'IDENT: %d ms\nMise en Place: %d ms\nUP NEXT: %d ms\nKnife Skills: %d ms\nTOTAL LOOP: %d ms\n' \
  "$ident_ms" "$mise_ms" "$upnext_ms" "$knife_ms" "$total_ms"

# Persist exact durations and create a single staging schedule slot for the playlist.
# The slot is intentionally not public/published and the rundown remains draft.
"${compose[@]}" exec -T postgres psql -v ON_ERROR_STOP=1 \
  -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
  -v ident_ms="$ident_ms" -v mise_ms="$mise_ms" -v upnext_ms="$upnext_ms" -v knife_ms="$knife_ms" -v total_ms="$total_ms" <<'SQL'
BEGIN;

UPDATE media_assets SET duration_ms=:'ident_ms'::bigint, updated_at=now()
WHERE asset_key='AST-LEGACY-TV-IDENT-001';
UPDATE media_assets SET duration_ms=:'mise_ms'::bigint, updated_at=now()
WHERE asset_key='AST-LEGACY-TV-PROG-002';
UPDATE media_assets SET duration_ms=:'upnext_ms'::bigint, updated_at=now()
WHERE asset_key='AST-LEGACY-TV-UPNEXT-001';
UPDATE media_assets SET duration_ms=:'knife_ms'::bigint, updated_at=now()
WHERE asset_key='AST-LEGACY-TV-PROG-003';

DELETE FROM schedule_slots
WHERE metadata->>'experimental_key'='IUOAMC-TV-PREVIEW-001';

INSERT INTO schedule_slots(
  channel_id,rundown_id,playlist_id,slot_type,title,starts_at,ends_at,priority,hard_start,state,metadata
)
SELECT
  c.id,r.id,p.id,'playlist','IUOAMC TV Experimental Preview',
  now() + interval '5 minutes',
  now() + interval '5 minutes' + (:'total_ms'::bigint * interval '1 millisecond'),
  100,true,'planned',
  jsonb_build_object(
    'experimental_key','IUOAMC-TV-PREVIEW-001',
    'staging_only',true,
    'production_outputs_enabled',false,
    'public_publishing_enabled',false,
    'playlist_sequence',jsonb_build_array('IDENT','Mise en Place','UP NEXT','Knife Skills','IDENT')
  )
FROM channels c
JOIN rundowns r ON r.channel_id=c.id AND r.name='IUOAMC TV Experimental Broadcast 001' AND r.version=1
JOIN playlists p ON p.channel_id=c.id AND p.name='IUOAMC TV Experimental Loop 001'
WHERE c.slug='iuoamc-tv';

INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
SELECT 'experimental-preview','scheduler.preview.prepare','rundown',r.id::text,'scheduler',
       jsonb_build_object('staging_only',true,'production_outputs_enabled',false,'public_publishing_enabled',false)
FROM rundowns r JOIN channels c ON c.id=r.channel_id
WHERE c.slug='iuoamc-tv' AND r.name='IUOAMC TV Experimental Broadcast 001' AND r.version=1
  AND NOT EXISTS (
    SELECT 1 FROM audit_events a
    WHERE a.action='scheduler.preview.prepare' AND a.resource_id=r.id::text
  );

COMMIT;
SQL

# Compile through the service API using a short-lived, in-container token; nothing is exposed publicly.
rundown_id="$(${compose[@]} exec -T postgres psql -At -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c \
  "SELECT r.id FROM rundowns r JOIN channels c ON c.id=r.channel_id WHERE c.slug='iuoamc-tv' AND r.name='IUOAMC TV Experimental Broadcast 001' AND r.version=1 LIMIT 1;")"
[[ -n "$rundown_id" ]] || { echo "ERROR: experimental rundown not found" >&2; exit 1; }

jwt_token="$(${compose[@]} exec -T playout python - <<'PY'
import os,time,jwt
now=int(time.time())
print(jwt.encode({
 'sub':'staging-preview-compiler',
 'permissions':['broadcast.read','broadcast.write'],
 'iat':now,'exp':now+300,
 'iss':os.environ['JWT_ISSUER'],'aud':os.environ['JWT_AUDIENCE']
},os.environ['JWT_SECRET'],algorithm='HS256'))
PY
)"

compile_json="$(${compose[@]} exec -T playout python - "$rundown_id" "$jwt_token" <<'PY'
import json,sys,urllib.request
rid,token=sys.argv[1],sys.argv[2]
body=json.dumps({'rundown_id':rid}).encode()
req=urllib.request.Request('http://127.0.0.1:8103/v1/compile',data=body,method='POST',headers={
 'Content-Type':'application/json','Authorization':'Bearer '+token
})
with urllib.request.urlopen(req,timeout=10) as r:
    print(r.read().decode())
PY
)"

run_id="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["run_id"])' <<<"$compile_json")"

echo "===== COMPILED PREVIEW RUN ====="
echo "$compile_json"

echo "===== PLAYOUT QUEUE ====="
"${compose[@]}" exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c \
  "SELECT pr.id AS run_id,pr.state,pr.source_revision,qi.ordinal,qi.item_type,qi.source_ref,qi.title,qi.planned_duration_ms FROM playout_runs pr JOIN playout_queue_items qi ON qi.run_id=pr.id WHERE pr.id='$run_id' ORDER BY qi.ordinal;"

echo "Preview playout compiled safely. No production/public output was enabled."
