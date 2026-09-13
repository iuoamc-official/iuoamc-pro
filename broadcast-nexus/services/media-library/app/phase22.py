from __future__ import annotations

import os
import tempfile
import uuid

import psycopg
from fastapi import Depends, HTTPException, Request
from pydantic import BaseModel, Field

from app.main import app, db, s3
from packages.auth.security import require_permission
from packages.events.nats_client import publish


class AssetUpdate(BaseModel):
    title: str | None = Field(default=None, min_length=1, max_length=240)
    duration_ms: int | None = Field(default=None, ge=0)
    metadata: dict | None = None


def _audit(conn, actor: str | None, action: str, asset_id: uuid.UUID, payload: dict | None = None):
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,%s,'media_asset',%s,'media-library',%s::jsonb)""",
        (actor, action, str(asset_id), psycopg.types.json.Jsonb(payload or {})),
    )


@app.get('/v1/assets/{asset_id}')
def get_asset(
    asset_id: uuid.UUID,
    _: dict = Depends(require_permission('media.read')),
    conn=Depends(db),
):
    row = conn.execute(
        """SELECT id,asset_key,title,media_type,mime_type,size_bytes,duration_ms,status,
                  checksum_sha256,storage_bucket,storage_object,metadata,created_at,updated_at
           FROM media_assets WHERE id=%s""",
        (asset_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Asset not found')
    keys = ['id','asset_key','title','media_type','mime_type','size_bytes','duration_ms','status',
            'checksum_sha256','storage_bucket','storage_object','metadata','created_at','updated_at']
    data = dict(zip(keys,row))
    data['id'] = str(data['id'])
    return data


@app.patch('/v1/assets/{asset_id}')
def update_asset(
    asset_id: uuid.UUID,
    item: AssetUpdate,
    actor: dict = Depends(require_permission('media.write')),
    conn=Depends(db),
):
    current = conn.execute(
        'SELECT title,duration_ms,metadata FROM media_assets WHERE id=%s FOR UPDATE',
        (asset_id,),
    ).fetchone()
    if not current:
        raise HTTPException(404, 'Asset not found')
    title = item.title if item.title is not None else current[0]
    duration_ms = item.duration_ms if item.duration_ms is not None else current[1]
    metadata = item.metadata if item.metadata is not None else current[2]
    conn.execute(
        """UPDATE media_assets SET title=%s,duration_ms=%s,metadata=%s::jsonb,updated_at=now()
           WHERE id=%s""",
        (title,duration_ms,psycopg.types.json.Jsonb(metadata),asset_id),
    )
    _audit(conn, actor.get('sub'), 'media.asset.update', asset_id)
    conn.commit()
    return {'id':str(asset_id),'title':title,'duration_ms':duration_ms,'metadata':metadata}


@app.post('/v1/assets/{asset_id}/upload-complete')
async def upload_complete(
    asset_id: uuid.UUID,
    actor: dict = Depends(require_permission('media.upload')),
    conn=Depends(db),
):
    row = conn.execute(
        'SELECT storage_bucket,storage_object FROM media_assets WHERE id=%s FOR UPDATE',
        (asset_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Asset not found')
    try:
        head = s3().head_object(Bucket=row[0], Key=row[1])
    except Exception as exc:
        raise HTTPException(409, f'Uploaded object not found: {type(exc).__name__}') from exc
    size_bytes = int(head.get('ContentLength') or 0)
    conn.execute(
        "UPDATE media_assets SET size_bytes=%s,status='uploaded',updated_at=now() WHERE id=%s",
        (size_bytes,asset_id),
    )
    _audit(conn, actor.get('sub'), 'media.upload.complete', asset_id, {'size_bytes':size_bytes})
    conn.commit()
    await publish('media.asset.uploaded', {'asset_id':str(asset_id),'size_bytes':size_bytes})
    return {'id':str(asset_id),'status':'uploaded','size_bytes':size_bytes}


@app.get('/v1/assets/{asset_id}/download-url')
def download_url(
    asset_id: uuid.UUID,
    _: dict = Depends(require_permission('media.read')),
    conn=Depends(db),
):
    row = conn.execute(
        'SELECT storage_bucket,storage_object,status FROM media_assets WHERE id=%s',
        (asset_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Asset not found')
    if row[2] not in {'uploaded','ready'}:
        raise HTTPException(409, 'Asset is not uploaded yet')
    url = s3().generate_presigned_url(
        'get_object', Params={'Bucket':row[0],'Key':row[1]}, ExpiresIn=900
    )
    return {'url':url,'expires_in':900,'status':row[2]}


@app.post('/v1/assets/{asset_id}/upload-relay')
async def upload_relay(
    asset_id: uuid.UUID,
    request: Request,
    actor: dict = Depends(require_permission('media.upload')),
    conn=Depends(db),
):
    """Staging browser upload relay. Presigned upload endpoint remains available separately."""
    row = conn.execute(
        'SELECT storage_bucket,storage_object,mime_type FROM media_assets WHERE id=%s FOR UPDATE',
        (asset_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Asset not found')

    max_bytes = int(os.getenv('MEDIA_RELAY_MAX_BYTES','2147483648'))
    content_length = request.headers.get('content-length')
    if content_length and int(content_length) > max_bytes:
        raise HTTPException(413, 'File exceeds staging upload limit')

    written = 0
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(prefix='iuoamc-media-', delete=False) as tmp:
            tmp_path = tmp.name
            async for chunk in request.stream():
                written += len(chunk)
                if written > max_bytes:
                    raise HTTPException(413, 'File exceeds staging upload limit')
                tmp.write(chunk)
        extra = {'ContentType': row[2]} if row[2] else None
        if extra:
            s3().upload_file(tmp_path, row[0], row[1], ExtraArgs=extra)
        else:
            s3().upload_file(tmp_path, row[0], row[1])
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)

    conn.execute(
        "UPDATE media_assets SET size_bytes=%s,status='uploaded',updated_at=now() WHERE id=%s",
        (written,asset_id),
    )
    _audit(conn, actor.get('sub'), 'media.upload.relay', asset_id, {'size_bytes':written,'staging_only':True})
    conn.commit()
    await publish('media.asset.uploaded', {'asset_id':str(asset_id),'size_bytes':written,'relay':True})
    return {'id':str(asset_id),'status':'uploaded','size_bytes':written,'staging_only':True}
