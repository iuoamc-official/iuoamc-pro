from __future__ import annotations

import os
import uuid
from datetime import datetime

import psycopg
from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel, Field

from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(
    title="IUOAMC Broadcast Orchestrator",
    version="0.1.0",
    docs_url="/docs",
    redoc_url="/redoc",
)


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


class ChannelCreate(BaseModel):
    slug: str = Field(pattern=r"^[a-z0-9][a-z0-9-]{1,62}$")
    name: str
    timezone: str = "UTC"


class SessionCreate(BaseModel):
    channel_id: uuid.UUID
    title: str
    mode: str = Field(pattern=r"^(scheduled|live_studio|emergency|internal)$")
    planned_start: datetime | None = None


@app.get("/health")
def health():
    return {"service": "orchestrator", "status": "ok", "phase": 1}


@app.get("/v1/channels")
def list_channels(
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        "SELECT id,slug,name,status,timezone,created_at FROM channels ORDER BY created_at DESC"
    ).fetchall()
    return [
        {
            "id": str(r[0]),
            "slug": r[1],
            "name": r[2],
            "status": r[3],
            "timezone": r[4],
            "created_at": r[5],
        }
        for r in rows
    ]


@app.post("/v1/channels", status_code=201)
async def create_channel(
    item: ChannelCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    row = conn.execute(
        "INSERT INTO channels(slug,name,timezone) VALUES(%s,%s,%s) RETURNING id,created_at",
        (item.slug, item.name, item.timezone),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'channel.create','channel',%s,'orchestrator','{\"phase\":1}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("broadcast.channel.created", {"channel_id": str(row[0]), "slug": item.slug})
    return {"id": str(row[0]), "created_at": row[1]}


@app.post("/v1/sessions", status_code=201)
async def create_session(
    item: SessionCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    if not conn.execute("SELECT 1 FROM channels WHERE id=%s", (item.channel_id,)).fetchone():
        raise HTTPException(404, "Channel not found")

    row = conn.execute(
        """INSERT INTO broadcast_sessions(channel_id,title,mode,planned_start)
           VALUES(%s,%s,%s,%s) RETURNING id,created_at,state""",
        (item.channel_id, item.title, item.mode, item.planned_start),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'session.create','broadcast_session',%s,'orchestrator','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish(
        "broadcast.session.created",
        {"session_id": str(row[0]), "channel_id": str(item.channel_id)},
    )
    return {"id": str(row[0]), "state": row[2], "created_at": row[1]}


@app.get("/v1/audit")
def audit(
    limit: int = 100,
    _: dict = Depends(require_permission("audit.read")),
    conn=Depends(db),
):
    limit = max(1, min(limit, 500))
    rows = conn.execute(
        """SELECT event_id,actor_label,action,resource_type,resource_id,source_service,payload,created_at
           FROM audit_events ORDER BY id DESC LIMIT %s""",
        (limit,),
    ).fetchall()
    return [
        {
            "event_id": str(r[0]),
            "actor": r[1],
            "action": r[2],
            "resource_type": r[3],
            "resource_id": r[4],
            "source_service": r[5],
            "payload": r[6],
            "created_at": r[7],
        }
        for r in rows
    ]
