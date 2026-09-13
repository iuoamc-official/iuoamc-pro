from __future__ import annotations

import os
import uuid
from urllib.parse import urlparse

from fastapi import Depends, HTTPException
from pydantic import BaseModel, Field

from app.main import app, db
from packages.auth.security import require_permission
from packages.events.nats_client import publish


class ManifestStateUpdate(BaseModel):
    state: str = Field(pattern=r"^(idle|ready|active|stale|error)$")


def _public_publishing_enabled() -> bool:
    return os.getenv("PUBLIC_PUBLISHING_ENABLED", "false").lower() == "true"


def _safe_staging_path(path: str) -> bool:
    parsed = urlparse(path)
    if parsed.scheme or parsed.netloc:
        return False
    return path.startswith("/staging-hls/") and ".." not in path


@app.get('/v1/safety')
def safety(_: dict = Depends(require_permission('broadcast.read'))):
    enabled = _public_publishing_enabled()
    return {
        "service": "iptv",
        "deployment_env": os.getenv("DEPLOYMENT_ENV", "development"),
        "public_publishing_enabled": enabled,
        "public_origin_enabled": False,
        "cdn_enabled": False,
        "production_hls_enabled": False,
        "safe_for_staging": not enabled,
    }


@app.patch('/v1/manifests/{manifest_id}/state')
async def set_manifest_state(
    manifest_id: uuid.UUID,
    item: ManifestStateUpdate,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    if _public_publishing_enabled():
        raise HTTPException(503, 'IPTV dashboard refuses operation when public publishing is enabled')
    row = conn.execute(
        "SELECT channel_id,variant,path,state FROM hls_manifests WHERE id=%s FOR UPDATE",
        (manifest_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Manifest not found')
    if not _safe_staging_path(row[2]):
        raise HTTPException(409, 'Manifest path is outside the isolated staging HLS namespace')
    conn.execute(
        "UPDATE hls_manifests SET state=%s,last_segment_at=CASE WHEN %s='active' THEN now() ELSE last_segment_at END WHERE id=%s",
        (item.state, item.state, manifest_id),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'iptv.manifest.state','hls_manifest',%s,'iptv',%s::jsonb)""",
        (actor.get('sub'), str(manifest_id), __import__('psycopg').types.json.Jsonb({"from": row[3], "to": item.state, "public_publishing": False})),
    )
    conn.commit()
    await publish('iptv.manifest.state', {
        "manifest_id": str(manifest_id), "channel_id": str(row[0]), "variant": row[1],
        "from": row[3], "to": item.state, "public_publishing": False,
    })
    return {"id": str(manifest_id), "from": row[3], "to": item.state, "public_publishing": False}


@app.get('/v1/channels/{channel_id}/preview')
def channel_preview(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    channel = conn.execute("SELECT slug,name FROM channels WHERE id=%s", (channel_id,)).fetchone()
    if not channel:
        raise HTTPException(404, 'Channel not found')
    rows = conn.execute(
        """SELECT id,variant,path,target_duration,playlist_window,state,last_segment_at
           FROM hls_manifests WHERE channel_id=%s ORDER BY variant""",
        (channel_id,),
    ).fetchall()
    manifests = []
    for r in rows:
        manifests.append({
            "id": str(r[0]), "variant": r[1], "path": r[2],
            "target_duration": r[3], "playlist_window": r[4], "state": r[5],
            "last_segment_at": r[6], "safe_staging_path": _safe_staging_path(r[2]),
        })
    return {
        "channel_id": str(channel_id), "slug": channel[0], "name": channel[1],
        "manifests": manifests,
        "public_publishing_enabled": False,
        "public_origin_enabled": False,
        "preview_only": True,
    }
