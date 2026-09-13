from __future__ import annotations

import os
import uuid
from datetime import datetime

import psycopg
from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel, Field, model_validator

from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Broadcast Scheduler", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


class ProgramCreate(BaseModel):
    channel_id: uuid.UUID
    slug: str = Field(pattern=r"^[a-z0-9][a-z0-9-]{1,62}$")
    title: str
    description: str | None = None
    category: str | None = None
    default_duration_ms: int | None = Field(default=None, ge=1)


class PlaylistCreate(BaseModel):
    channel_id: uuid.UUID
    name: str
    loop_enabled: bool = False


class ScheduleSlotCreate(BaseModel):
    channel_id: uuid.UUID
    rundown_id: uuid.UUID | None = None
    playlist_id: uuid.UUID | None = None
    episode_id: uuid.UUID | None = None
    slot_type: str = Field(pattern=r"^(playlist|episode|live|filler|emergency)$")
    title: str
    starts_at: datetime
    ends_at: datetime
    priority: int = 100
    hard_start: bool = True

    @model_validator(mode="after")
    def validate_window(self):
        if self.ends_at <= self.starts_at:
            raise ValueError("ends_at must be after starts_at")
        return self


@app.get("/health")
def health():
    return {"service": "scheduler", "status": "ok", "phase": 2}


@app.post("/v1/programs", status_code=201)
async def create_program(
    item: ProgramCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    if not conn.execute("SELECT 1 FROM channels WHERE id=%s", (item.channel_id,)).fetchone():
        raise HTTPException(404, "Channel not found")

    row = conn.execute(
        """INSERT INTO programs(channel_id,slug,title,description,category,default_duration_ms)
           VALUES(%s,%s,%s,%s,%s,%s) RETURNING id,created_at""",
        (
            item.channel_id,
            item.slug,
            item.title,
            item.description,
            item.category,
            item.default_duration_ms,
        ),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'program.create','program',%s,'scheduler','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("schedule.program.created", {"program_id": str(row[0]), "channel_id": str(item.channel_id)})
    return {"id": str(row[0]), "created_at": row[1]}


@app.post("/v1/playlists", status_code=201)
async def create_playlist(
    item: PlaylistCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    if not conn.execute("SELECT 1 FROM channels WHERE id=%s", (item.channel_id,)).fetchone():
        raise HTTPException(404, "Channel not found")

    row = conn.execute(
        """INSERT INTO playlists(channel_id,name,loop_enabled)
           VALUES(%s,%s,%s) RETURNING id,created_at""",
        (item.channel_id, item.name, item.loop_enabled),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'playlist.create','playlist',%s,'scheduler','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("schedule.playlist.created", {"playlist_id": str(row[0]), "channel_id": str(item.channel_id)})
    return {"id": str(row[0]), "created_at": row[1]}


@app.get("/v1/channels/{channel_id}/slots")
def list_slots(
    channel_id: uuid.UUID,
    starts_from: datetime | None = None,
    ends_before: datetime | None = None,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    where = ["channel_id=%s"]
    args: list[object] = [channel_id]
    if starts_from is not None:
        where.append("ends_at > %s")
        args.append(starts_from)
    if ends_before is not None:
        where.append("starts_at < %s")
        args.append(ends_before)

    rows = conn.execute(
        f"""SELECT id,slot_type,title,starts_at,ends_at,priority,hard_start,state
             FROM schedule_slots WHERE {' AND '.join(where)} ORDER BY starts_at""",
        args,
    ).fetchall()
    return [
        {
            "id": str(r[0]),
            "slot_type": r[1],
            "title": r[2],
            "starts_at": r[3],
            "ends_at": r[4],
            "priority": r[5],
            "hard_start": r[6],
            "state": r[7],
        }
        for r in rows
    ]


@app.post("/v1/slots", status_code=201)
async def create_slot(
    item: ScheduleSlotCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    if not conn.execute("SELECT 1 FROM channels WHERE id=%s", (item.channel_id,)).fetchone():
        raise HTTPException(404, "Channel not found")

    conflicts = conn.execute(
        """SELECT id,title,starts_at,ends_at,priority
           FROM schedule_slots
           WHERE channel_id=%s
             AND state NOT IN ('cancelled','completed')
             AND starts_at < %s
             AND ends_at > %s
           ORDER BY starts_at""",
        (item.channel_id, item.ends_at, item.starts_at),
    ).fetchall()

    if conflicts and item.slot_type != "emergency":
        raise HTTPException(
            status_code=409,
            detail={
                "code": "schedule_conflict",
                "conflicts": [
                    {
                        "id": str(r[0]),
                        "title": r[1],
                        "starts_at": r[2].isoformat(),
                        "ends_at": r[3].isoformat(),
                        "priority": r[4],
                    }
                    for r in conflicts
                ],
            },
        )

    row = conn.execute(
        """INSERT INTO schedule_slots(
               channel_id,rundown_id,playlist_id,episode_id,slot_type,title,
               starts_at,ends_at,priority,hard_start
           ) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
           RETURNING id,created_at,state""",
        (
            item.channel_id,
            item.rundown_id,
            item.playlist_id,
            item.episode_id,
            item.slot_type,
            item.title,
            item.starts_at,
            item.ends_at,
            item.priority,
            item.hard_start,
        ),
    ).fetchone()

    conn.execute(
        """INSERT INTO epg_events(
               channel_id,schedule_slot_id,title,starts_at,ends_at,published
           ) VALUES(%s,%s,%s,%s,%s,FALSE)""",
        (item.channel_id, row[0], item.title, item.starts_at, item.ends_at),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'schedule.slot.create','schedule_slot',%s,'scheduler','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()

    await publish(
        "schedule.slot.created",
        {
            "slot_id": str(row[0]),
            "channel_id": str(item.channel_id),
            "starts_at": item.starts_at.isoformat(),
            "ends_at": item.ends_at.isoformat(),
        },
    )
    return {"id": str(row[0]), "state": row[2], "created_at": row[1]}


@app.get("/v1/channels/{channel_id}/conflicts")
def conflicts(
    channel_id: uuid.UUID,
    starts_at: datetime,
    ends_at: datetime,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    if ends_at <= starts_at:
        raise HTTPException(422, "ends_at must be after starts_at")

    rows = conn.execute(
        """SELECT id,title,slot_type,starts_at,ends_at,priority,state
           FROM schedule_slots
           WHERE channel_id=%s
             AND state NOT IN ('cancelled','completed')
             AND starts_at < %s
             AND ends_at > %s
           ORDER BY starts_at""",
        (channel_id, ends_at, starts_at),
    ).fetchall()
    return {
        "has_conflict": bool(rows),
        "conflicts": [
            {
                "id": str(r[0]),
                "title": r[1],
                "slot_type": r[2],
                "starts_at": r[3],
                "ends_at": r[4],
                "priority": r[5],
                "state": r[6],
            }
            for r in rows
        ],
    }
