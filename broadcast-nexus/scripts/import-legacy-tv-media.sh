#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMPORT_DIR="${LEGACY_TV_IMPORT_DIR:-/opt/iuoamc-broadcast/import/legacy-tv}"
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
    echo "ERROR: $flag must remain false for legacy media import (current: $value)" >&2
    exit 1
  fi
done

required=(
  branding/mca-tv-official-logo.png
  programs/mca-tv-episode-02-mise-en-place.mp4
  programs/mca-tv-episode-03-knife-skills.mp4
  transitions/mca-tv-official-ident-3s.mp4
  transitions/mca-tv-official-up-next-3s.mp4
  covers/mca-tv-episode-02-mise-en-place.jpg
  covers/mca-tv-episode-03-knife-skills.jpg
  metadata/epg.xml
  metadata/playlist.m3u
  SHA256SUMS.txt
  legacy-media-manifest.json
)

for rel in "${required[@]}"; do
  [[ -f "$IMPORT_DIR/$rel" ]] || { echo "ERROR: missing $IMPORT_DIR/$rel" >&2; exit 1; }
done

echo "===== VERIFY LOCAL SHA256 ====="
(
  cd "$IMPORT_DIR"
  sha256sum -c SHA256SUMS.txt
)

compose=(docker compose -f compose.yaml -f compose.noc.yaml -f compose.supervisor.yaml -f compose.staging.yaml)

postgres_cid="$(${compose[@]} ps -q postgres)"
[[ -n "$postgres_cid" ]] || { echo "ERROR: postgres container is not running" >&2; exit 1; }

bucket="${MINIO_BUCKET:-iuoamc-media}"
package="IUOAMC-TV-LEGACY-MIGRATION-001"
source_url="https://platform-iuoamc.uk/tv"

sql_asset() {
  local asset_key="$1" title="$2" media_type="$3" mime="$4" rel="$5" role="$6"
  local sha size storage_object
  sha="$(awk -v f="$rel" '$2==f {print $1}' "$IMPORT_DIR/SHA256SUMS.txt")"
  [[ -n "$sha" ]] || { echo "ERROR: checksum not found for $rel" >&2; exit 1; }
  size="$(stat -c '%s' "$IMPORT_DIR/$rel")"
  storage_object="legacy-tv/$rel"

  "${compose[@]}" exec -T postgres psql \
    -v ON_ERROR_STOP=1 \
    -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
    -v asset_key="$asset_key" \
    -v title="$title" \
    -v media_type="$media_type" \
    -v mime="$mime" \
    -v size_bytes="$size" \
    -v checksum="$sha" \
    -v bucket="$bucket" \
    -v storage_object="$storage_object" \
    -v role="$role" \
    -v package="$package" \
    -v source_url="$source_url" <<'SQL'
DO $$
DECLARE
  existing_id uuid;
  new_id uuid;
BEGIN
  SELECT id INTO existing_id
  FROM media_assets
  WHERE storage_bucket = :'bucket' AND storage_object = :'storage_object'
  LIMIT 1;

  IF existing_id IS NULL THEN
    new_id := gen_random_uuid();
    INSERT INTO media_assets(
      id, asset_key, title, media_type, mime_type, size_bytes, status,
      checksum_sha256, storage_bucket, storage_object, metadata
    ) VALUES (
      new_id, :'asset_key', :'title', :'media_type', :'mime', :'size_bytes'::bigint, 'ready',
      :'checksum', :'bucket', :'storage_object',
      jsonb_build_object(
        'source','legacy-channel',
        'source_url',:'source_url',
        'migration_package',:'package',
        'legacy_object',:'storage_object',
        'asset_role',:'role',
        'channel','IUOAMC TV',
        'channel_slug','iuoamc-tv',
        'staging_only',true,
        'production_outputs_enabled',false,
        'public_publishing_enabled',false
      )
    );
    INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
    VALUES(
      'legacy-media-import','media.legacy.import','media_asset',new_id::text,'media-library',
      jsonb_build_object('package',:'package','storage_object',:'storage_object','checksum_sha256',:'checksum')
    );
  ELSE
    UPDATE media_assets
    SET title = :'title',
        media_type = :'media_type',
        mime_type = :'mime',
        size_bytes = :'size_bytes'::bigint,
        status = 'ready',
        checksum_sha256 = :'checksum',
        metadata = coalesce(metadata,'{}'::jsonb) || jsonb_build_object(
          'source','legacy-channel',
          'source_url',:'source_url',
          'migration_package',:'package',
          'legacy_object',:'storage_object',
          'asset_role',:'role',
          'channel','IUOAMC TV',
          'channel_slug','iuoamc-tv',
          'staging_only',true,
          'production_outputs_enabled',false,
          'public_publishing_enabled',false
        ),
        updated_at = now()
    WHERE id = existing_id;
  END IF;
END $$;
SQL
}

# Keep the new Nexus channel isolated. This does not alter the legacy platform/channel.
"${compose[@]}" exec -T postgres psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<'SQL'
INSERT INTO channels(slug,name,status,timezone)
VALUES('iuoamc-tv','IUOAMC TV','draft','UTC')
ON CONFLICT (slug) DO UPDATE SET name=EXCLUDED.name, updated_at=now();
SQL

sql_asset "AST-LEGACY-TV-LOGO-001" "IUOAMC TV Official Logo" "image" "image/png" "branding/mca-tv-official-logo.png" "LOGO"
sql_asset "AST-LEGACY-TV-PROG-002" "Mise en Place" "video" "video/mp4" "programs/mca-tv-episode-02-mise-en-place.mp4" "PROGRAM"
sql_asset "AST-LEGACY-TV-PROG-003" "Knife Skills" "video" "video/mp4" "programs/mca-tv-episode-03-knife-skills.mp4" "PROGRAM"
sql_asset "AST-LEGACY-TV-IDENT-001" "IUOAMC TV Official Ident 3s" "video" "video/mp4" "transitions/mca-tv-official-ident-3s.mp4" "IDENT"
sql_asset "AST-LEGACY-TV-UPNEXT-001" "IUOAMC TV Up Next 3s" "video" "video/mp4" "transitions/mca-tv-official-up-next-3s.mp4" "UP_NEXT"
sql_asset "AST-LEGACY-TV-COVER-002" "Mise en Place Cover" "image" "image/jpeg" "covers/mca-tv-episode-02-mise-en-place.jpg" "COVER"
sql_asset "AST-LEGACY-TV-COVER-003" "Knife Skills Cover" "image" "image/jpeg" "covers/mca-tv-episode-03-knife-skills.jpg" "COVER"
sql_asset "AST-LEGACY-TV-EPG-001" "Legacy IUOAMC TV EPG" "document" "application/xml" "metadata/epg.xml" "EPG"
sql_asset "AST-LEGACY-TV-PLAYLIST-001" "Legacy IUOAMC TV Playlist" "document" "audio/x-mpegurl" "metadata/playlist.m3u" "PLAYLIST"

echo "===== REGISTERED LEGACY ASSETS ====="
"${compose[@]}" exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c \
  "SELECT asset_key,title,media_type,status,size_bytes,checksum_sha256,storage_object FROM media_assets WHERE metadata->>'migration_package'='$package' ORDER BY asset_key;"

echo "===== CHANNEL ====="
"${compose[@]}" exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c \
  "SELECT slug,name,status,timezone FROM channels WHERE slug='iuoamc-tv';"

echo "Legacy TV media registry import completed safely. Production outputs remain disabled."
