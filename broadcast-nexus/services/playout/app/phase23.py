from __future__ import annotations

import json
import uuid
from datetime import datetime, timezone

import psycopg
from fastapi import Depends, HTTPException
from pydantic import BaseModel, Field

from app.main import app, db
from packages.auth.security import require_permission
from packages.events.nats_client import publish


class RunTransition(BaseModel):
    state: str = Field(pattern=r"^(ready|running|paused|completed|failed|cancelled)$")
    reason: str = Field(min_length=3, max_length=300)


ALLOWED = {
    "compiled": {"ready", "cancelled"},
    "ready": {"running", "cancelled"},
    "running": {"paused", "completed", "failed", "cancelled"},
    "paused": {"running", "completed", "failed", "cancelled"},
    "completed": set(),
    "failed": set(),
    "cancelled": set(),
}


@app.get('/v1/channels/{channel_id}/runs')
def list_runs(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,rundown_id,state,source_revision,scheduled_start,started_at,ended_at,queue_hash,metadata,created_at
           FROM playout_runs WHERE channel_id=%s ORDER BY created_at DESC LIMIT 100""",
        (channel_id,),
    ).fetchall()
    return [
        {
            'id': str(r[0]), 'rundown_id': str(r[1]) if r[1] else None, 'state': r[2],
            'source_revision': r[3], 'scheduled_start': r[4], 'started_at': r[5], 'ended_at': r[6],
            'queue_hash': r[7], 'metadata': r[8], 'created_at': r[9], 'production_output': False,
        }
        for r in rows
    ]


@app.post('/v1/runs/{run_id}/transition')
async def transition_run(
    run_id: uuid.UUID,
    item: RunTransition,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    row = conn.execute(
        'SELECT channel_id,state FROM playout_runs WHERE id=%s FOR UPDATE',
        (run_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Playout run not found')
    current = row[1]
    if item.state == current:
        return {'id': str(run_id), 'state': current, 'noop': True, 'production_output': False}
    if item.state not in ALLOWED.get(current, set()):
        raise HTTPException(409, f'Illegal playout transition {current} -> {item.state}')

    started_at = 'now()' if item.state == 'running' and current != 'paused' else 'started_at'
    ended_at = 'now()' if item.state in {'completed','failed','cancelled'} else 'ended_at'
    conn.execute(
        f"UPDATE playout_runs SET state=%s, started_at={started_at}, ended_at={ended_at} WHERE id=%s",
        (item.state, run_id),
    )
    conn.execute(
        """INSERT INTO playout_events(run_id,event_type,severity,message,payload)
           VALUES(%s,%s,'info',%s,%s::jsonb)""",
        (run_id, f'run.{item.state}', item.reason, json.dumps({'from': current, 'to': item.state, 'production_output': False})),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'playout.transition','playout_run',%s,'playout',%s::jsonb)""",
        (actor.get('sub'), str(run_id), json.dumps({'from': current, 'to': item.state, 'reason': item.reason})),
    )
    conn.commit()
    await publish('playout.run.transitioned', {
        'run_id': str(run_id), 'channel_id': str(row[0]), 'from': current, 'to': item.state,
        'production_output': False,
    })
    return {'id': str(run_id), 'from': current, 'to': item.state, 'production_output': False}


@app.get('/v1/runs/{run_id}/events')
def run_events(
    run_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,event_type,severity,message,payload,created_at
           FROM playout_events WHERE run_id=%s ORDER BY id DESC LIMIT 100""",
        (run_id,),
    ).fetchall()
    return [dict(id=r[0],event_type=r[1],severity=r[2],message=r[3],payload=r[4],created_at=r[5]) for r in rows]


@app.get('/v1/channels/{channel_id}/fallback-policies')
def fallback_policies(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,code,priority,trigger_type,action_type,asset_id,slate_text,max_duration_ms,is_active,created_at
           FROM fallback_policies WHERE channel_id=%s ORDER BY priority,code""",
        (channel_id,),
    ).fetchall()
    return [
        dict(id=str(r[0]),code=r[1],priority=r[2],trigger_type=r[3],action_type=r[4],
             asset_id=str(r[5]) if r[5] else None,slate_text=r[6],max_duration_ms=r[7],is_active=r[8],created_at=r[9])
        for r in rows
    ]


@app.get('/v1/control-safety')
def control_safety():
    return {
        'service': 'playout', 'staging_only': True, 'production_output': False,
        'encoder_activation': False, 'distribution_activation': False,
        'checked_at': datetime.now(timezone.utc),
    }
