from __future__ import annotations

import hashlib
import os
import secrets
import uuid
from datetime import datetime, timedelta, timezone

import psycopg
from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel, Field

from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(
    title="IUOAMC Live Studio Core",
    version="0.1.0",
    docs_url="/docs",
    redoc_url="/redoc",
)


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


def audit(conn, actor: str | None, action: str, resource_type: str, resource_id: str, payload: str = "{}"):
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,%s,%s,%s,'live-studio',%s::jsonb)""",
        (actor, action, resource_type, resource_id, payload),
    )


def room_event(conn, room_id: uuid.UUID, event_type: str, actor: str | None, payload: str = "{}"):
    conn.execute(
        """INSERT INTO studio_events(room_id,event_type,actor_label,payload)
           VALUES(%s,%s,%s,%s::jsonb)""",
        (room_id, event_type, actor, payload),
    )


class RoomCreate(BaseModel):
    slug: str = Field(pattern=r"^[a-z0-9][a-z0-9-]{1,62}$")
    title: str = Field(min_length=1, max_length=200)
    channel_id: uuid.UUID | None = None
    broadcast_session_id: uuid.UUID | None = None
    room_mode: str = Field(default="interview", pattern=r"^(interview|panel|training|event|internal)$")
    max_participants: int = Field(default=12, ge=1, le=100)


class ParticipantCreate(BaseModel):
    display_name: str = Field(min_length=1, max_length=160)
    role: str = Field(default="guest", pattern=r"^(director|host|cohost|guest|producer|observer)$")


class SceneCreate(BaseModel):
    name: str = Field(min_length=1, max_length=160)
    layout_type: str = Field(default="single", pattern=r"^(single|split2|split3|split4|grid|pip|presentation|custom)$")
    canvas: dict = {}
    is_fallback: bool = False


class BusUpdate(BaseModel):
    scene_id: uuid.UUID


class RoomStateUpdate(BaseModel):
    state: str = Field(pattern=r"^(waiting|ready|live|ended|archived)$")


class RecordingRequest(BaseModel):
    recording_type: str = Field(pattern=r"^(program|preview|iso_participant|iso_screen|audio_mix)$")
    participant_id: uuid.UUID | None = None


@app.get("/health")
def health():
    return {
        "service": "live-studio",
        "status": "ok",
        "phase": "control-plane-only",
        "production_media_router": False,
        "production_outputs": False,
    }


@app.get("/v1/rooms")
def list_rooms(
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,slug,title,state,room_mode,max_participants,program_width,program_height,frame_rate,created_at,updated_at
           FROM studio_rooms ORDER BY created_at DESC LIMIT 200"""
    ).fetchall()
    return [
        {
            "id": str(r[0]), "slug": r[1], "title": r[2], "state": r[3], "room_mode": r[4],
            "max_participants": r[5], "program_width": r[6], "program_height": r[7],
            "frame_rate": r[8], "created_at": r[9], "updated_at": r[10],
        }
        for r in rows
    ]


@app.post("/v1/rooms", status_code=201)
async def create_room(
    item: RoomCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    try:
        row = conn.execute(
            """INSERT INTO studio_rooms(channel_id,broadcast_session_id,slug,title,room_mode,max_participants)
               VALUES(%s,%s,%s,%s,%s,%s) RETURNING id,created_at""",
            (item.channel_id, item.broadcast_session_id, item.slug, item.title, item.room_mode, item.max_participants),
        ).fetchone()
    except psycopg.errors.UniqueViolation:
        conn.rollback()
        raise HTTPException(409, "Room slug already exists")

    room_id = row[0]
    fallback_scene = conn.execute(
        """INSERT INTO studio_scenes(room_id,name,layout_type,is_fallback)
           VALUES(%s,'Default Single','single',TRUE) RETURNING id""",
        (room_id,),
    ).fetchone()[0]
    conn.execute(
        """INSERT INTO studio_buses(room_id,bus_type,active_scene_id) VALUES
           (%s,'preview',%s),(%s,'program',%s)""",
        (room_id, fallback_scene, room_id, fallback_scene),
    )
    audit(conn, actor.get("sub"), "studio.room.create", "studio_room", str(room_id))
    room_event(conn, room_id, "room.created", actor.get("sub"))
    conn.commit()
    await publish("studio.room.created", {"room_id": str(room_id), "slug": item.slug})
    return {"id": str(room_id), "created_at": row[1]}


@app.post("/v1/rooms/{room_id}/participants", status_code=201)
async def add_participant(
    room_id: uuid.UUID,
    item: ParticipantCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    room = conn.execute("SELECT 1 FROM studio_rooms WHERE id=%s", (room_id,)).fetchone()
    if not room:
        raise HTTPException(404, "Room not found")
    row = conn.execute(
        """INSERT INTO studio_participants(room_id,display_name,role)
           VALUES(%s,%s,%s) RETURNING id,created_at""",
        (room_id, item.display_name, item.role),
    ).fetchone()
    room_event(conn, room_id, "participant.created", actor.get("sub"), '{"role":"' + item.role + '"}')
    conn.commit()
    await publish("studio.participant.created", {"room_id": str(room_id), "participant_id": str(row[0])})
    return {"id": str(row[0]), "created_at": row[1]}


@app.post("/v1/rooms/{room_id}/participants/{participant_id}/invite", status_code=201)
def create_invite(
    room_id: uuid.UUID,
    participant_id: uuid.UUID,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    participant = conn.execute(
        "SELECT 1 FROM studio_participants WHERE id=%s AND room_id=%s",
        (participant_id, room_id),
    ).fetchone()
    if not participant:
        raise HTTPException(404, "Participant not found")
    token = secrets.token_urlsafe(32)
    token_hash = hashlib.sha256(token.encode()).hexdigest()
    expires = datetime.now(timezone.utc) + timedelta(hours=12)
    row = conn.execute(
        """INSERT INTO studio_invites(room_id,participant_id,token_hash,expires_at,created_by)
           VALUES(%s,%s,%s,%s,NULL) RETURNING id""",
        (room_id, participant_id, token_hash, expires),
    ).fetchone()
    room_event(conn, room_id, "invite.created", actor.get("sub"))
    conn.commit()
    return {"invite_id": str(row[0]), "token": token, "expires_at": expires}


@app.post("/v1/rooms/{room_id}/scenes", status_code=201)
async def create_scene(
    room_id: uuid.UUID,
    item: SceneCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    room = conn.execute("SELECT 1 FROM studio_rooms WHERE id=%s", (room_id,)).fetchone()
    if not room:
        raise HTTPException(404, "Room not found")
    row = conn.execute(
        """INSERT INTO studio_scenes(room_id,name,layout_type,canvas,is_fallback)
           VALUES(%s,%s,%s,%s::jsonb,%s) RETURNING id,created_at""",
        (room_id, item.name, item.layout_type, __import__('json').dumps(item.canvas), item.is_fallback),
    ).fetchone()
    room_event(conn, room_id, "scene.created", actor.get("sub"))
    conn.commit()
    await publish("studio.scene.created", {"room_id": str(room_id), "scene_id": str(row[0])})
    return {"id": str(row[0]), "created_at": row[1]}


@app.put("/v1/rooms/{room_id}/buses/{bus_type}")
async def set_bus(
    room_id: uuid.UUID,
    bus_type: str,
    item: BusUpdate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    if bus_type not in {"preview", "program"}:
        raise HTTPException(400, "Invalid bus type")
    scene = conn.execute(
        "SELECT 1 FROM studio_scenes WHERE id=%s AND room_id=%s",
        (item.scene_id, room_id),
    ).fetchone()
    if not scene:
        raise HTTPException(404, "Scene not found")
    row = conn.execute(
        """UPDATE studio_buses SET active_scene_id=%s,revision=revision+1,updated_at=now()
           WHERE room_id=%s AND bus_type=%s RETURNING revision""",
        (item.scene_id, room_id, bus_type),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Bus not found")
    room_event(conn, room_id, f"bus.{bus_type}.changed", actor.get("sub"))
    conn.commit()
    await publish(f"studio.bus.{bus_type}.changed", {
        "room_id": str(room_id), "scene_id": str(item.scene_id), "revision": row[0]
    })
    return {"bus": bus_type, "scene_id": str(item.scene_id), "revision": row[0]}


@app.post("/v1/rooms/{room_id}/take")
async def take_preview_to_program(
    room_id: uuid.UUID,
    actor: dict = Depends(require_permission("broadcast.start")),
    conn=Depends(db),
):
    preview = conn.execute(
        "SELECT active_scene_id,revision FROM studio_buses WHERE room_id=%s AND bus_type='preview' FOR UPDATE",
        (room_id,),
    ).fetchone()
    if not preview or not preview[0]:
        raise HTTPException(409, "Preview bus has no active scene")
    program = conn.execute(
        """UPDATE studio_buses SET active_scene_id=%s,revision=revision+1,updated_at=now()
           WHERE room_id=%s AND bus_type='program' RETURNING revision""",
        (preview[0], room_id),
    ).fetchone()
    room_event(conn, room_id, "program.take", actor.get("sub"))
    audit(conn, actor.get("sub"), "studio.program.take", "studio_room", str(room_id))
    conn.commit()
    await publish("studio.program.take", {
        "room_id": str(room_id), "scene_id": str(preview[0]), "program_revision": program[0]
    })
    return {"scene_id": str(preview[0]), "program_revision": program[0]}


@app.put("/v1/rooms/{room_id}/state")
async def set_room_state(
    room_id: uuid.UUID,
    item: RoomStateUpdate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    current = conn.execute("SELECT state FROM studio_rooms WHERE id=%s FOR UPDATE", (room_id,)).fetchone()
    if not current:
        raise HTTPException(404, "Room not found")
    allowed = {
        "draft": {"waiting", "archived"},
        "waiting": {"ready", "ended"},
        "ready": {"live", "ended"},
        "live": {"ended"},
        "ended": {"archived"},
        "archived": set(),
    }
    if item.state not in allowed.get(current[0], set()):
        raise HTTPException(409, f"Invalid transition {current[0]} -> {item.state}")
    conn.execute("UPDATE studio_rooms SET state=%s,updated_at=now() WHERE id=%s", (item.state, room_id))
    room_event(conn, room_id, f"room.state.{item.state}", actor.get("sub"))
    audit(conn, actor.get("sub"), f"studio.room.{item.state}", "studio_room", str(room_id))
    conn.commit()
    await publish(f"studio.room.{item.state}", {"room_id": str(room_id)})
    return {"id": str(room_id), "state": item.state}


@app.post("/v1/rooms/{room_id}/recordings", status_code=201)
async def request_recording(
    room_id: uuid.UUID,
    item: RecordingRequest,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    if item.recording_type.startswith("iso_") and not item.participant_id:
        raise HTTPException(400, "ISO recording requires participant_id")
    row = conn.execute(
        """INSERT INTO studio_recordings(room_id,recording_type,participant_id)
           VALUES(%s,%s,%s) RETURNING id,state,created_at""",
        (room_id, item.recording_type, item.participant_id),
    ).fetchone()
    room_event(conn, room_id, "recording.requested", actor.get("sub"))
    conn.commit()
    await publish("studio.recording.requested", {
        "room_id": str(room_id), "recording_id": str(row[0]), "type": item.recording_type
    })
    return {"id": str(row[0]), "state": row[1], "created_at": row[2]}


@app.get("/v1/rooms/{room_id}/state")
def room_state(
    room_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    room = conn.execute(
        "SELECT id,slug,title,state,room_mode,program_width,program_height,frame_rate FROM studio_rooms WHERE id=%s",
        (room_id,),
    ).fetchone()
    if not room:
        raise HTTPException(404, "Room not found")
    buses = conn.execute(
        "SELECT bus_type,active_scene_id,revision,updated_at FROM studio_buses WHERE room_id=%s ORDER BY bus_type",
        (room_id,),
    ).fetchall()
    participants = conn.execute(
        "SELECT id,display_name,role,state,microphone_enabled,camera_enabled,screen_share_enabled FROM studio_participants WHERE room_id=%s ORDER BY created_at",
        (room_id,),
    ).fetchall()
    return {
        "room": {
            "id": str(room[0]), "slug": room[1], "title": room[2], "state": room[3], "room_mode": room[4],
            "program": {"width": room[5], "height": room[6], "frame_rate": room[7]},
        },
        "buses": [
            {"type": b[0], "scene_id": str(b[1]) if b[1] else None, "revision": b[2], "updated_at": b[3]}
            for b in buses
        ],
        "participants": [
            {"id": str(p[0]), "display_name": p[1], "role": p[2], "state": p[3],
             "microphone": p[4], "camera": p[5], "screen_share": p[6]}
            for p in participants
        ],
        "media_router": "not-attached-in-this-phase",
        "production_output": False,
    }
