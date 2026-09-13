from __future__ import annotations
import os, uuid
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC IPTV/HLS", version="0.1.0")


def db():
    with psycopg.connect(os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")) as conn:
        yield conn


class ManifestUpsert(BaseModel):
    channel_id: uuid.UUID
    variant: str = Field(pattern=r"^[a-z0-9][a-z0-9-]{1,63}$")
    path: str
    target_duration: int = Field(default=4, ge=1, le=30)
    playlist_window: int = Field(default=12, ge=3, le=200)


@app.get("/health")
def health():
    return {"service": "iptv", "status": "ok", "phase": "manifest-control-only"}


@app.post("/v1/manifests", status_code=201)
async def upsert_manifest(
    item: ManifestUpsert,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO hls_manifests(channel_id,variant,path,target_duration,playlist_window)
           VALUES(%s,%s,%s,%s,%s)
           ON CONFLICT(channel_id,variant) DO UPDATE SET
             path=EXCLUDED.path,
             target_duration=EXCLUDED.target_duration,
             playlist_window=EXCLUDED.playlist_window
           RETURNING id,state,created_at""",
        (item.channel_id,item.variant,item.path,item.target_duration,item.playlist_window),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'iptv.manifest.upsert','hls_manifest',%s,'iptv','{}'::jsonb)""",
        (actor.get("sub"),str(row[0])),
    )
    conn.commit()
    await publish("iptv.manifest.updated", {"manifest_id":str(row[0]),"channel_id":str(item.channel_id),"variant":item.variant})
    return {"id":str(row[0]),"state":row[1],"created_at":row[2]}


@app.get("/v1/channels/{channel_id}/manifests")
def manifests(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,variant,path,target_duration,playlist_window,state,last_segment_at,created_at
           FROM hls_manifests WHERE channel_id=%s ORDER BY variant""",
        (channel_id,),
    ).fetchall()
    return [dict(id=str(r[0]),variant=r[1],path=r[2],target_duration=r[3],playlist_window=r[4],
                 state=r[5],last_segment_at=r[6],created_at=r[7]) for r in rows]


@app.get("/v1/channels/{channel_id}/m3u")
def m3u_preview(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    channel = conn.execute("SELECT slug,name FROM channels WHERE id=%s",(channel_id,)).fetchone()
    if not channel:
        raise HTTPException(404,"Channel not found")
    rows = conn.execute(
        "SELECT variant,path FROM hls_manifests WHERE channel_id=%s ORDER BY variant",
        (channel_id,),
    ).fetchall()
    lines=["#EXTM3U"]
    for variant,path in rows:
        lines.append(f'#EXTINF:-1 group-title="IUOAMC" tvg-id="{channel[0]}-{variant}",{channel[1]} {variant}')
        lines.append(path)
    return {"content_type":"audio/x-mpegurl","playlist":"\n".join(lines)+"\n"}
