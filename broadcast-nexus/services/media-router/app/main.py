from __future__ import annotations
import os, secrets, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Media Router Adapter", version="0.1.0")


def db():
    url = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(url) as conn:
        yield conn


class RouterProvision(BaseModel):
    studio_room_id: uuid.UUID
    provider: str = Field(default="adapter", pattern=r"^[a-z0-9_-]{2,32}$")
    region: str | None = None


class JoinSession(BaseModel):
    studio_room_id: uuid.UUID
    participant_id: uuid.UUID | None = None


class TrackPublish(BaseModel):
    participant_session_id: uuid.UUID
    kind: str = Field(pattern=r"^(audio|video|screen_video|screen_audio)$")
    source: str = Field(pattern=r"^(microphone|camera|screen_share|system_audio|unknown)$")
    codec: str | None = None
    width: int | None = Field(default=None, ge=1, le=16384)
    height: int | None = Field(default=None, ge=1, le=16384)
    fps: float | None = Field(default=None, ge=0, le=240)
    bitrate_bps: int | None = Field(default=None, ge=0)


class SpeakerSignal(BaseModel):
    participant_session_id: uuid.UUID | None = None
    audio_level: float = Field(ge=0, le=1)


@app.get("/health")
def health():
    return {"service": "media-router", "status": "ok", "phase": "webrtc-core"}


@app.post("/v1/routers", status_code=201)
async def provision_router(
    item: RouterProvision,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    room = conn.execute("SELECT 1 FROM studio_rooms WHERE id=%s", (item.studio_room_id,)).fetchone()
    if not room:
        raise HTTPException(404, "Studio room not found")
    router_key = "RTR-" + secrets.token_hex(8).upper()
    row = conn.execute(
        """INSERT INTO media_router_rooms(studio_room_id,router_key,provider,state,region)
           VALUES(%s,%s,%s,'ready',%s) RETURNING id,state,created_at""",
        (item.studio_room_id, router_key, item.provider, item.region),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'media_router.provision','media_router',%s,'media-router','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("studio.router.ready", {"router_id": str(row[0]), "studio_room_id": str(item.studio_room_id)})
    return {"id": str(row[0]), "router_key": router_key, "state": row[1], "created_at": row[2]}


@app.post("/v1/sessions", status_code=201)
async def join_session(
    item: JoinSession,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    room = conn.execute("SELECT 1 FROM studio_rooms WHERE id=%s", (item.studio_room_id,)).fetchone()
    if not room:
        raise HTTPException(404, "Studio room not found")
    if item.participant_id:
        participant = conn.execute(
            "SELECT 1 FROM studio_participants WHERE id=%s AND studio_room_id=%s",
            (item.participant_id, item.studio_room_id),
        ).fetchone()
        if not participant:
            raise HTTPException(404, "Participant not found in room")
    session_key = "SES-" + secrets.token_hex(12).upper()
    now = datetime.now(timezone.utc)
    row = conn.execute(
        """INSERT INTO participant_sessions(studio_room_id,participant_id,session_key,state,joined_at,last_seen_at)
           VALUES(%s,%s,%s,'connected',%s,%s) RETURNING id,state""",
        (item.studio_room_id, item.participant_id, session_key, now, now),
    ).fetchone()
    conn.commit()
    await publish("studio.participant.connected", {
        "session_id": str(row[0]), "studio_room_id": str(item.studio_room_id),
        "participant_id": str(item.participant_id) if item.participant_id else None,
    })
    return {"id": str(row[0]), "session_key": session_key, "state": row[1]}


@app.post("/v1/tracks", status_code=201)
async def publish_track(
    item: TrackPublish,
    _: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    session = conn.execute(
        "SELECT studio_room_id FROM participant_sessions WHERE id=%s AND state IN ('joining','connected','reconnecting')",
        (item.participant_session_id,),
    ).fetchone()
    if not session:
        raise HTTPException(404, "Active participant session not found")
    track_key = "TRK-" + secrets.token_hex(10).upper()
    row = conn.execute(
        """INSERT INTO media_tracks(studio_room_id,participant_session_id,track_key,kind,source,codec,width,height,fps,bitrate_bps)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) RETURNING id,state,published_at""",
        (session[0], item.participant_session_id, track_key, item.kind, item.source,
         item.codec, item.width, item.height, item.fps, item.bitrate_bps),
    ).fetchone()
    conn.commit()
    await publish("studio.track.published", {
        "track_id": str(row[0]), "session_id": str(item.participant_session_id), "kind": item.kind,
    })
    return {"id": str(row[0]), "track_key": track_key, "state": row[1], "published_at": row[2]}


@app.post("/v1/rooms/{room_id}/active-speaker")
async def active_speaker(
    room_id: uuid.UUID,
    item: SpeakerSignal,
    _: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    if item.participant_session_id:
        valid = conn.execute(
            "SELECT 1 FROM participant_sessions WHERE id=%s AND studio_room_id=%s",
            (item.participant_session_id, room_id),
        ).fetchone()
        if not valid:
            raise HTTPException(404, "Participant session not found in room")
    row = conn.execute(
        """INSERT INTO active_speaker_events(studio_room_id,participant_session_id,audio_level)
           VALUES(%s,%s,%s) RETURNING id,detected_at""",
        (room_id, item.participant_session_id, item.audio_level),
    ).fetchone()
    conn.commit()
    await publish("studio.active_speaker.changed", {
        "studio_room_id": str(room_id),
        "participant_session_id": str(item.participant_session_id) if item.participant_session_id else None,
        "audio_level": item.audio_level,
    })
    return {"event_id": row[0], "detected_at": row[1]}


@app.post("/v1/sessions/{session_id}/screen-share", status_code=201)
async def start_screen_share(
    session_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    session = conn.execute(
        "SELECT studio_room_id FROM participant_sessions WHERE id=%s AND state='connected'",
        (session_id,),
    ).fetchone()
    if not session:
        raise HTTPException(404, "Connected participant session not found")
    existing = conn.execute(
        "SELECT id FROM screen_share_sessions WHERE participant_session_id=%s AND state IN ('active','paused')",
        (session_id,),
    ).fetchone()
    if existing:
        raise HTTPException(409, "Screen share already active")
    row = conn.execute(
        """INSERT INTO screen_share_sessions(studio_room_id,participant_session_id,state)
           VALUES(%s,%s,'active') RETURNING id,started_at""",
        (session[0], session_id),
    ).fetchone()
    conn.commit()
    await publish("studio.screen_share.started", {"screen_share_id": str(row[0]), "session_id": str(session_id)})
    return {"id": str(row[0]), "started_at": row[1], "state": "active"}


@app.get("/v1/rooms/{room_id}/state")
def room_media_state(
    room_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn = Depends(db),
):
    sessions = conn.execute(
        """SELECT id,participant_id,state,network_quality,last_seen_at
           FROM participant_sessions WHERE studio_room_id=%s ORDER BY created_at""",
        (room_id,),
    ).fetchall()
    tracks = conn.execute(
        """SELECT id,participant_session_id,kind,source,state,codec,width,height,fps,bitrate_bps
           FROM media_tracks WHERE studio_room_id=%s AND state!='ended' ORDER BY published_at""",
        (room_id,),
    ).fetchall()
    return {
        "studio_room_id": str(room_id),
        "sessions": [dict(id=str(r[0]), participant_id=str(r[1]) if r[1] else None, state=r[2], network_quality=r[3], last_seen_at=r[4]) for r in sessions],
        "tracks": [dict(id=str(r[0]), participant_session_id=str(r[1]), kind=r[2], source=r[3], state=r[4], codec=r[5], width=r[6], height=r[7], fps=float(r[8]) if r[8] is not None else None, bitrate_bps=r[9]) for r in tracks],
    }
