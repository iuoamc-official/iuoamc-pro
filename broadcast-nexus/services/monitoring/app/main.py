from __future__ import annotations
import os, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC NOC Monitoring", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


class TargetCreate(BaseModel):
    channel_id: uuid.UUID | None = None
    target_type: str = Field(pattern=r"^(encoder|distribution|iptv|internal|studio|playout)$")
    target_ref: str = Field(min_length=1, max_length=180)
    display_name: str = Field(min_length=1, max_length=180)
    expected_video: bool = True
    expected_audio: bool = True


class SampleCreate(BaseModel):
    target_id: uuid.UUID
    video_present: bool | None = None
    audio_present: bool | None = None
    frozen_frame: bool = False
    black_frame: bool = False
    silence_detected: bool = False
    fps: float | None = None
    bitrate_kbps: int | None = None
    audio_lufs: float | None = None
    latency_ms: int | None = None
    packet_loss_pct: float | None = None
    jitter_ms: float | None = None
    timestamp_drift_ms: int | None = None
    hls_age_seconds: int | None = None
    details: dict = {}


def derive_state(sample: SampleCreate) -> str:
    critical = (
        sample.frozen_frame
        or sample.black_frame
        or (sample.video_present is False)
        or (sample.fps is not None and sample.fps < 15)
        or (sample.hls_age_seconds is not None and sample.hls_age_seconds > 30)
    )
    if critical:
        return "critical"
    degraded = (
        sample.silence_detected
        or (sample.audio_present is False)
        or (sample.fps is not None and sample.fps < 22)
        or (sample.packet_loss_pct is not None and sample.packet_loss_pct > 3)
        or (sample.timestamp_drift_ms is not None and abs(sample.timestamp_drift_ms) > 1000)
    )
    return "degraded" if degraded else "healthy"


@app.get("/health")
def health():
    return {"service": "monitoring", "status": "ok", "phase": "noc"}


@app.post("/v1/targets", status_code=201)
def create_target(
    item: TargetCreate,
    _: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO monitoring_targets(channel_id,target_type,target_ref,display_name,expected_video,expected_audio)
           VALUES(%s,%s,%s,%s,%s,%s)
           RETURNING id,created_at""",
        (item.channel_id,item.target_type,item.target_ref,item.display_name,item.expected_video,item.expected_audio),
    ).fetchone()
    conn.commit()
    return {"id": str(row[0]), "created_at": row[1]}


@app.post("/v1/samples", status_code=201)
async def ingest_sample(
    item: SampleCreate,
    _: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    target = conn.execute(
        "SELECT target_type,target_ref,display_name FROM monitoring_targets WHERE id=%s AND enabled=TRUE",
        (item.target_id,),
    ).fetchone()
    if not target:
        raise HTTPException(404, "Monitoring target not found or disabled")

    state = derive_state(item)
    row = conn.execute(
        """INSERT INTO health_samples(
               target_id,state,video_present,audio_present,frozen_frame,black_frame,silence_detected,
               fps,bitrate_kbps,audio_lufs,latency_ms,packet_loss_pct,jitter_ms,timestamp_drift_ms,
               hls_age_seconds,details)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::jsonb)
           RETURNING id,sampled_at""",
        (
            item.target_id,state,item.video_present,item.audio_present,item.frozen_frame,item.black_frame,
            item.silence_detected,item.fps,item.bitrate_kbps,item.audio_lufs,item.latency_ms,
            item.packet_loss_pct,item.jitter_ms,item.timestamp_drift_ms,item.hls_age_seconds,
            psycopg.types.json.Jsonb(item.details),
        ),
    ).fetchone()
    conn.commit()
    await publish("noc.health.sampled", {
        "sample_id": row[0], "target_id": str(item.target_id), "state": state,
        "target_type": target[0], "target_ref": target[1],
    })
    return {"sample_id": row[0], "sampled_at": row[1], "state": state}


@app.get("/v1/targets")
def list_targets(
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT t.id,t.target_type,t.target_ref,t.display_name,t.enabled,
                  s.state,s.sampled_at,s.fps,s.bitrate_kbps,s.frozen_frame,s.black_frame,s.silence_detected,
                  s.hls_age_seconds
           FROM monitoring_targets t
           LEFT JOIN LATERAL (
               SELECT * FROM health_samples hs WHERE hs.target_id=t.id ORDER BY hs.sampled_at DESC LIMIT 1
           ) s ON TRUE
           ORDER BY t.display_name"""
    ).fetchall()
    return [
        {
            "id": str(r[0]), "target_type": r[1], "target_ref": r[2], "display_name": r[3],
            "enabled": r[4], "state": r[5] or "unknown", "sampled_at": r[6], "fps": r[7],
            "bitrate_kbps": r[8], "frozen_frame": r[9], "black_frame": r[10],
            "silence_detected": r[11], "hls_age_seconds": r[12],
        }
        for r in rows
    ]


@app.get("/v1/overview")
def overview(
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT COALESCE(s.state,'unknown') AS state, count(*)
           FROM monitoring_targets t
           LEFT JOIN LATERAL (
             SELECT state FROM health_samples hs WHERE hs.target_id=t.id ORDER BY sampled_at DESC LIMIT 1
           ) s ON TRUE
           WHERE t.enabled=TRUE
           GROUP BY COALESCE(s.state,'unknown')"""
    ).fetchall()
    counts = {r[0]: r[1] for r in rows}
    return {"targets": sum(counts.values()), "states": counts, "generated_at": datetime.now(timezone.utc)}
