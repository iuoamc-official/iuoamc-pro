from __future__ import annotations
import os, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC ISO Recording Worker", version="0.1.0")


def db():
    url = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(url) as conn:
        yield conn


class RecordingCreate(BaseModel):
    studio_room_id: uuid.UUID
    participant_session_id: uuid.UUID | None = None
    media_track_id: uuid.UUID | None = None
    recording_kind: str = Field(pattern=r"^(participant_av|participant_audio|screen|program|preview|audio_mix)$")


class RecordingComplete(BaseModel):
    storage_bucket: str
    storage_object: str
    duration_ms: int = Field(ge=0)
    checksum_sha256: str = Field(pattern=r"^[a-fA-F0-9]{64}$")


@app.get("/health")
def health():
    return {"service": "iso-recorder", "status": "ok", "phase": "webrtc-core"}


@app.post("/v1/jobs", status_code=201)
async def create_job(
    item: RecordingCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    room = conn.execute("SELECT 1 FROM studio_rooms WHERE id=%s", (item.studio_room_id,)).fetchone()
    if not room:
        raise HTTPException(404, "Studio room not found")
    if item.participant_session_id:
        session = conn.execute(
            "SELECT 1 FROM participant_sessions WHERE id=%s AND studio_room_id=%s",
            (item.participant_session_id, item.studio_room_id),
        ).fetchone()
        if not session:
            raise HTTPException(404, "Participant session not found in room")
    if item.media_track_id:
        track = conn.execute(
            "SELECT 1 FROM media_tracks WHERE id=%s AND studio_room_id=%s",
            (item.media_track_id, item.studio_room_id),
        ).fetchone()
        if not track:
            raise HTTPException(404, "Media track not found in room")
    row = conn.execute(
        """INSERT INTO iso_recording_jobs(studio_room_id,participant_session_id,media_track_id,recording_kind,state)
           VALUES(%s,%s,%s,%s,'queued') RETURNING id,state,created_at""",
        (item.studio_room_id, item.participant_session_id, item.media_track_id, item.recording_kind),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'iso_recording.queue','iso_recording_job',%s,'iso-recorder','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("studio.recording.queued", {"job_id": str(row[0]), "studio_room_id": str(item.studio_room_id), "kind": item.recording_kind})
    return {"id": str(row[0]), "state": row[1], "created_at": row[2]}


@app.post("/v1/jobs/{job_id}/claim")
async def claim_job(
    job_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    row = conn.execute(
        """UPDATE iso_recording_jobs SET state='starting',updated_at=now()
           WHERE id=%s AND state='queued' RETURNING studio_room_id,recording_kind""",
        (job_id,),
    ).fetchone()
    if not row:
        raise HTTPException(409, "Recording job is not claimable")
    conn.commit()
    await publish("studio.recording.starting", {"job_id": str(job_id), "studio_room_id": str(row[0]), "kind": row[1]})
    return {"id": str(job_id), "state": "starting"}


@app.post("/v1/jobs/{job_id}/started")
async def mark_started(
    job_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    row = conn.execute(
        """UPDATE iso_recording_jobs SET state='recording',started_at=COALESCE(started_at,now()),updated_at=now()
           WHERE id=%s AND state='starting' RETURNING studio_room_id""",
        (job_id,),
    ).fetchone()
    if not row:
        raise HTTPException(409, "Recording job is not starting")
    conn.commit()
    await publish("studio.recording.started", {"job_id": str(job_id), "studio_room_id": str(row[0])})
    return {"id": str(job_id), "state": "recording"}


@app.post("/v1/jobs/{job_id}/complete")
async def complete_job(
    job_id: uuid.UUID,
    item: RecordingComplete,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    row = conn.execute(
        """UPDATE iso_recording_jobs
           SET state='completed',storage_bucket=%s,storage_object=%s,duration_ms=%s,checksum_sha256=%s,
               ended_at=now(),updated_at=now()
           WHERE id=%s AND state IN ('starting','recording','finalizing')
           RETURNING studio_room_id,recording_kind""",
        (item.storage_bucket, item.storage_object, item.duration_ms, item.checksum_sha256.lower(), job_id),
    ).fetchone()
    if not row:
        raise HTTPException(409, "Recording job cannot be completed from current state")
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'iso_recording.complete','iso_recording_job',%s,'iso-recorder','{}'::jsonb)""",
        (actor.get("sub"), str(job_id)),
    )
    conn.commit()
    await publish("studio.recording.completed", {"job_id": str(job_id), "studio_room_id": str(row[0]), "kind": row[1]})
    return {"id": str(job_id), "state": "completed"}


@app.post("/v1/jobs/{job_id}/fail")
async def fail_job(
    job_id: uuid.UUID,
    error_code: str,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    error_code = error_code[:96]
    row = conn.execute(
        """UPDATE iso_recording_jobs SET state='failed',error_code=%s,ended_at=now(),updated_at=now()
           WHERE id=%s AND state NOT IN ('completed','cancelled','failed') RETURNING studio_room_id""",
        (error_code, job_id),
    ).fetchone()
    if not row:
        raise HTTPException(409, "Recording job is already terminal or missing")
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'iso_recording.fail','iso_recording_job',%s,'iso-recorder',jsonb_build_object('error_code',%s))""",
        (actor.get("sub"), str(job_id), error_code),
    )
    conn.commit()
    await publish("studio.recording.failed", {"job_id": str(job_id), "studio_room_id": str(row[0]), "error_code": error_code})
    return {"id": str(job_id), "state": "failed", "error_code": error_code}


@app.get("/v1/rooms/{room_id}/jobs")
def room_jobs(
    room_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn = Depends(db),
):
    rows = conn.execute(
        """SELECT id,participant_session_id,media_track_id,recording_kind,state,storage_bucket,storage_object,
                  duration_ms,checksum_sha256,error_code,created_at,started_at,ended_at
           FROM iso_recording_jobs WHERE studio_room_id=%s ORDER BY created_at DESC LIMIT 200""",
        (room_id,),
    ).fetchall()
    return [
        dict(id=str(r[0]), participant_session_id=str(r[1]) if r[1] else None,
             media_track_id=str(r[2]) if r[2] else None, recording_kind=r[3], state=r[4],
             storage_bucket=r[5], storage_object=r[6], duration_ms=r[7], checksum_sha256=r[8],
             error_code=r[9], created_at=r[10], started_at=r[11], ended_at=r[12])
        for r in rows
    ]
