from __future__ import annotations
import os, uuid
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Distribution Fabric", version="0.1.0")


def db():
    with psycopg.connect(os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")) as conn:
        yield conn


class DestinationCreate(BaseModel):
    channel_id: uuid.UUID
    code: str = Field(pattern=r"^[a-z0-9][a-z0-9-_]{1,63}$")
    kind: str = Field(pattern=r"^(youtube|iptv_hls|internal_hls|srt|rtmp|recording)$")
    enabled: bool = False
    isolation_mode: str = "independent"
    config: dict = {}


class HealthSample(BaseModel):
    output_session_id: uuid.UUID | None = None
    channel_id: uuid.UUID
    probe_type: str
    status: str
    latency_ms: int | None = None
    bitrate_kbps: int | None = None
    fps: float | None = None
    audio_present: bool | None = None
    video_present: bool | None = None
    frozen_frame: bool | None = None
    black_frame: bool | None = None
    payload: dict = {}


@app.get("/health")
def health():
    return {"service": "distribution", "status": "ok", "phase": "isolated-control-only"}


@app.post("/v1/destinations", status_code=201)
async def create_destination(
    item: DestinationCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO output_destinations(channel_id,code,kind,enabled,isolation_mode,config)
           VALUES(%s,%s,%s,%s,%s,%s::jsonb)
           ON CONFLICT(channel_id,code) DO UPDATE SET
             kind=EXCLUDED.kind, enabled=EXCLUDED.enabled,
             isolation_mode=EXCLUDED.isolation_mode, config=EXCLUDED.config,
             updated_at=now()
           RETURNING id,enabled""",
        (item.channel_id,item.code,item.kind,item.enabled,item.isolation_mode,
         psycopg.types.json.Jsonb(item.config)),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'distribution.destination.upsert','output_destination',%s,'distribution','{}'::jsonb)""",
        (actor.get("sub"),str(row[0])),
    )
    conn.commit()
    await publish("distribution.destination.updated", {"destination_id":str(row[0]),"kind":item.kind})
    return {"id":str(row[0]),"enabled":row[1]}


@app.get("/v1/channels/{channel_id}/destinations")
def list_destinations(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,code,kind,enabled,isolation_mode,config,created_at,updated_at
           FROM output_destinations WHERE channel_id=%s ORDER BY code""",
        (channel_id,),
    ).fetchall()
    return [
        dict(id=str(r[0]),code=r[1],kind=r[2],enabled=r[3],isolation_mode=r[4],
             config=r[5],created_at=r[6],updated_at=r[7]) for r in rows
    ]


@app.post("/v1/health-samples", status_code=202)
async def health_sample(
    item: HealthSample,
    _: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO stream_health_samples(
           output_session_id,channel_id,probe_type,status,latency_ms,bitrate_kbps,fps,
           audio_present,video_present,frozen_frame,black_frame,payload)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::jsonb)
           RETURNING id,sampled_at""",
        (item.output_session_id,item.channel_id,item.probe_type,item.status,item.latency_ms,
         item.bitrate_kbps,item.fps,item.audio_present,item.video_present,item.frozen_frame,
         item.black_frame,psycopg.types.json.Jsonb(item.payload)),
    ).fetchone()
    conn.commit()
    await publish("stream.health.sampled", {"sample_id":row[0],"channel_id":str(item.channel_id),"status":item.status})
    return {"id":row[0],"sampled_at":row[1]}


@app.get("/v1/channels/{channel_id}/health/latest")
def latest_health(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT probe_type,status,latency_ms,bitrate_kbps,fps,audio_present,video_present,
                  frozen_frame,black_frame,payload,sampled_at
           FROM stream_health_samples WHERE channel_id=%s
           ORDER BY sampled_at DESC LIMIT 50""",
        (channel_id,),
    ).fetchall()
    return [dict(probe_type=r[0],status=r[1],latency_ms=r[2],bitrate_kbps=r[3],fps=r[4],
                 audio_present=r[5],video_present=r[6],frozen_frame=r[7],black_frame=r[8],
                 payload=r[9],sampled_at=r[10]) for r in rows]
