from __future__ import annotations

import hashlib
import json
import os
import uuid
from datetime import date, datetime, timezone
from typing import Literal

import psycopg
from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel, Field

from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Deterministic Playout", version="0.2.0")


def db():
    url = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(url) as conn:
        yield conn


def canonical_hash(payload: object) -> str:
    raw = json.dumps(payload, sort_keys=True, separators=(",", ":"), default=str).encode()
    return hashlib.sha256(raw).hexdigest()


class CompileRequest(BaseModel):
    rundown_id: uuid.UUID


class FallbackRequest(BaseModel):
    channel_id: uuid.UUID
    trigger_type: Literal["missing_asset", "source_timeout", "decode_error", "live_unavailable", "manual"]


class EpgRequest(BaseModel):
    channel_id: uuid.UUID
    start_date: date
    days: int = Field(default=1, ge=1, le=14)
    language: str = Field(default="en", min_length=2, max_length=8)


@app.get("/health")
def health():
    return {"service": "playout", "status": "ok", "phase": 3}


@app.post("/v1/compile", status_code=201)
async def compile_rundown(
    item: CompileRequest,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    rundown = conn.execute(
        "SELECT id, channel_id, scheduled_date, version, state FROM rundowns WHERE id=%s",
        (item.rundown_id,),
    ).fetchone()
    if not rundown:
        raise HTTPException(404, "Rundown not found")

    slots = conn.execute(
        """
        SELECT id, slot_type, title, starts_at, ends_at, playlist_id, episode_id,
               priority, hard_start, metadata
        FROM schedule_slots
        WHERE rundown_id=%s
        ORDER BY starts_at ASC, priority ASC, id ASC
        """,
        (item.rundown_id,),
    ).fetchall()
    if not slots:
        raise HTTPException(409, "Rundown has no schedule slots")

    queue = []
    previous_end = None
    for ordinal, r in enumerate(slots, start=1):
        slot_id, slot_type, title, starts_at, ends_at, playlist_id, episode_id, priority, hard_start, metadata = r
        if previous_end and starts_at < previous_end:
            raise HTTPException(409, f"Overlapping schedule at slot {slot_id}")
        duration_ms = int((ends_at - starts_at).total_seconds() * 1000)
        if duration_ms <= 0:
            raise HTTPException(409, f"Invalid duration at slot {slot_id}")

        source_ref = None
        item_type = "live" if slot_type == "live" else "asset"
        if slot_type in {"filler", "emergency"}:
            item_type = "filler" if slot_type == "filler" else "slate"
        if playlist_id:
            source_ref = f"playlist:{playlist_id}"
        elif episode_id:
            source_ref = f"episode:{episode_id}"
        elif slot_type == "live":
            source_ref = f"live:{slot_id}"

        queue.append({
            "ordinal": ordinal,
            "slot_id": str(slot_id),
            "item_type": item_type,
            "source_ref": source_ref,
            "title": title,
            "planned_start": starts_at.isoformat(),
            "planned_duration_ms": duration_ms,
            "transition": "cut",
            "fallback_policy": "channel_default",
            "payload": {
                "priority": priority,
                "hard_start": hard_start,
                "slot_type": slot_type,
                "metadata": metadata or {},
            },
        })
        previous_end = ends_at

    queue_hash = canonical_hash(queue)
    source_revision = f"rundown:{item.rundown_id}:v{rundown[3]}"

    existing = conn.execute(
        "SELECT id, queue_hash, state FROM playout_runs WHERE source_revision=%s ORDER BY created_at DESC LIMIT 1",
        (source_revision,),
    ).fetchone()
    if existing and existing[1] == queue_hash:
        return {"run_id": str(existing[0]), "queue_hash": queue_hash, "state": existing[2], "reused": True}

    run = conn.execute(
        """
        INSERT INTO playout_runs(channel_id,rundown_id,state,source_revision,scheduled_start,queue_hash,metadata)
        VALUES(%s,%s,'compiled',%s,%s,%s,%s::jsonb)
        RETURNING id, created_at
        """,
        (rundown[1], item.rundown_id, source_revision, slots[0][3], queue_hash,
         json.dumps({"compiler": "deterministic-v1", "items": len(queue)})),
    ).fetchone()

    for q in queue:
        conn.execute(
            """
            INSERT INTO playout_queue_items(
                run_id,ordinal,item_type,source_ref,title,planned_start,planned_duration_ms,
                transition,fallback_policy,payload
            ) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::jsonb)
            """,
            (
                run[0], q["ordinal"], q["item_type"], q["source_ref"], q["title"],
                q["planned_start"], q["planned_duration_ms"], q["transition"],
                q["fallback_policy"], json.dumps(q["payload"]),
            ),
        )

    conn.execute(
        """
        INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
        VALUES(%s,'playout.compile','playout_run',%s,'playout',%s::jsonb)
        """,
        (actor.get("sub"), str(run[0]), json.dumps({"queue_hash": queue_hash, "items": len(queue)})),
    )
    conn.commit()
    await publish("playout.run.compiled", {"run_id": str(run[0]), "queue_hash": queue_hash, "items": len(queue)})
    return {"run_id": str(run[0]), "queue_hash": queue_hash, "items": len(queue), "reused": False}


@app.get("/v1/runs/{run_id}")
def get_run(
    run_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    run = conn.execute(
        "SELECT id,channel_id,rundown_id,state,source_revision,scheduled_start,queue_hash,metadata,created_at FROM playout_runs WHERE id=%s",
        (run_id,),
    ).fetchone()
    if not run:
        raise HTTPException(404, "Playout run not found")
    items = conn.execute(
        """
        SELECT id,ordinal,item_type,source_ref,title,planned_start,planned_duration_ms,
               transition,fallback_policy,payload
        FROM playout_queue_items WHERE run_id=%s ORDER BY ordinal
        """,
        (run_id,),
    ).fetchall()
    return {
        "id": str(run[0]), "channel_id": str(run[1]), "rundown_id": str(run[2]) if run[2] else None,
        "state": run[3], "source_revision": run[4], "scheduled_start": run[5],
        "queue_hash": run[6], "metadata": run[7], "created_at": run[8],
        "items": [
            {"id": r[0], "ordinal": r[1], "item_type": r[2], "source_ref": r[3], "title": r[4],
             "planned_start": r[5], "planned_duration_ms": r[6], "transition": r[7],
             "fallback_policy": r[8], "payload": r[9]}
            for r in items
        ],
    }


@app.post("/v1/fallback/resolve")
async def resolve_fallback(
    item: FallbackRequest,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    row = conn.execute(
        """
        SELECT id,code,priority,action_type,asset_id,slate_text,max_duration_ms
        FROM fallback_policies
        WHERE channel_id=%s AND trigger_type=%s AND is_active=TRUE
        ORDER BY priority ASC, created_at ASC LIMIT 1
        """,
        (item.channel_id, item.trigger_type),
    ).fetchone()
    if not row:
        result = {"policy": "implicit-safe-default", "action_type": "skip", "asset_id": None,
                  "slate_text": None, "max_duration_ms": None}
    else:
        result = {"policy_id": str(row[0]), "policy": row[1], "priority": row[2], "action_type": row[3],
                  "asset_id": str(row[4]) if row[4] else None, "slate_text": row[5], "max_duration_ms": row[6]}
    await publish("playout.fallback.resolved", {"channel_id": str(item.channel_id), "trigger": item.trigger_type, **result})
    return result


@app.post("/v1/epg/generate")
async def generate_epg(
    item: EpgRequest,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    start = datetime.combine(item.start_date, datetime.min.time(), tzinfo=timezone.utc)
    end = start.replace() + __import__("datetime").timedelta(days=item.days)
    slots = conn.execute(
        """
        SELECT id,title,starts_at,ends_at,slot_type,metadata
        FROM schedule_slots
        WHERE channel_id=%s AND starts_at < %s AND ends_at > %s
        ORDER BY starts_at
        """,
        (item.channel_id, end, start),
    ).fetchall()
    count = 0
    for r in slots:
        conn.execute(
            """
            INSERT INTO epg_events(channel_id,schedule_slot_id,external_id,title,description,category,starts_at,ends_at,language,published)
            VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,FALSE)
            ON CONFLICT DO NOTHING
            """,
            (item.channel_id, r[0], f"slot-{r[0]}", r[1], None, r[4], r[2], r[3], item.language),
        )
        count += 1
    conn.execute(
        """
        INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
        VALUES(%s,'epg.generate','channel',%s,'playout',%s::jsonb)
        """,
        (actor.get("sub"), str(item.channel_id), json.dumps({"events": count, "start": str(item.start_date), "days": item.days})),
    )
    conn.commit()
    await publish("epg.generated", {"channel_id": str(item.channel_id), "events": count})
    return {"channel_id": str(item.channel_id), "events": count, "published": False}
