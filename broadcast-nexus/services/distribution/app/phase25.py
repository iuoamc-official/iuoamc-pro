from __future__ import annotations

import os
import uuid

import psycopg
from fastapi import Depends, HTTPException
from pydantic import BaseModel, Field

from app.main import app, db
from packages.auth.security import require_permission
from packages.events.nats_client import publish


class DestinationEnabledUpdate(BaseModel):
    enabled: bool


class SessionCreate(BaseModel):
    encoder_job_id: uuid.UUID
    destination_id: uuid.UUID


class SessionStateUpdate(BaseModel):
    state: str = Field(pattern=r"^(created|connecting|connected|degraded|disconnecting|disconnected|failed)$")
    last_error: str | None = Field(default=None, max_length=500)
    metrics: dict = {}


SESSION_ALLOWED = {
    "created": {"connecting", "failed"},
    "connecting": {"connected", "failed", "disconnecting"},
    "connected": {"degraded", "disconnecting", "failed"},
    "degraded": {"connected", "disconnecting", "failed"},
    "disconnecting": {"disconnected", "failed"},
    "disconnected": set(),
    "failed": {"connecting"},
}


def _production_outputs_enabled() -> bool:
    return os.getenv("PRODUCTION_OUTPUTS_ENABLED", "false").lower() == "true"


def _is_external_kind(kind: str) -> bool:
    return kind in {"youtube", "srt", "rtmp", "iptv_hls"}


@app.patch('/v1/destinations/{destination_id}/enabled')
async def set_destination_enabled(
    destination_id: uuid.UUID,
    item: DestinationEnabledUpdate,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    row = conn.execute(
        "SELECT channel_id,code,kind,enabled FROM output_destinations WHERE id=%s FOR UPDATE",
        (destination_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Destination not found')
    channel_id, code, kind, current = row
    if item.enabled and _production_outputs_enabled():
        raise HTTPException(503, 'Dashboard refuses destination enable while production outputs are enabled')
    if item.enabled and _is_external_kind(kind):
        raise HTTPException(403, f'External destination kind {kind} remains locked in staging')
    conn.execute(
        "UPDATE output_destinations SET enabled=%s,updated_at=now() WHERE id=%s",
        (item.enabled, destination_id),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'distribution.destination.enabled','output_destination',%s,'distribution',%s::jsonb)""",
        (actor.get('sub'), str(destination_id), psycopg.types.json.Jsonb({
            'from': current, 'to': item.enabled, 'kind': kind, 'production_execution': False,
        })),
    )
    conn.commit()
    await publish('distribution.destination.enabled', {
        'destination_id': str(destination_id), 'enabled': item.enabled,
        'kind': kind, 'production_execution': False,
    })
    return {
        'id': str(destination_id), 'channel_id': str(channel_id), 'code': code,
        'kind': kind, 'enabled': item.enabled, 'production_execution': False,
    }


@app.get('/v1/channels/{channel_id}/sessions')
def list_sessions(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT s.id,s.encoder_job_id,s.destination_id,d.code,d.kind,d.enabled,
                  s.state,s.attempt,s.last_error,s.connected_at,s.disconnected_at,s.metrics,s.created_at
           FROM output_sessions s
           JOIN output_destinations d ON d.id=s.destination_id
           JOIN encoder_jobs j ON j.id=s.encoder_job_id
           WHERE j.channel_id=%s ORDER BY s.created_at DESC LIMIT 200""",
        (channel_id,),
    ).fetchall()
    return [
        {
            'id': str(r[0]), 'encoder_job_id': str(r[1]), 'destination_id': str(r[2]),
            'destination_code': r[3], 'kind': r[4], 'destination_enabled': r[5],
            'state': r[6], 'attempt': r[7], 'last_error': r[8],
            'connected_at': r[9], 'disconnected_at': r[10], 'metrics': r[11],
            'created_at': r[12], 'production_execution': False,
        }
        for r in rows
    ]


@app.post('/v1/sessions', status_code=201)
async def create_session(
    item: SessionCreate,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    if _production_outputs_enabled():
        raise HTTPException(503, 'Distribution dashboard blocked when production outputs are enabled')
    job = conn.execute(
        "SELECT channel_id,state FROM encoder_jobs WHERE id=%s",
        (item.encoder_job_id,),
    ).fetchone()
    if not job:
        raise HTTPException(404, 'Encoder job not found')
    dest = conn.execute(
        "SELECT channel_id,code,kind,enabled FROM output_destinations WHERE id=%s",
        (item.destination_id,),
    ).fetchone()
    if not dest:
        raise HTTPException(404, 'Destination not found')
    if dest[0] != job[0]:
        raise HTTPException(409, 'Destination and encoder job belong to different channels')
    if _is_external_kind(dest[2]):
        raise HTTPException(403, f'External destination kind {dest[2]} remains locked in staging')
    if not dest[3]:
        raise HTTPException(409, 'Destination must be enabled before creating a session')
    row = conn.execute(
        """INSERT INTO output_sessions(encoder_job_id,destination_id,state,attempt,metrics)
           VALUES(%s,%s,'created',1,%s::jsonb) RETURNING id,state,created_at""",
        (item.encoder_job_id, item.destination_id, psycopg.types.json.Jsonb({'production_execution': False})),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'distribution.session.create','output_session',%s,'distribution',%s::jsonb)""",
        (actor.get('sub'), str(row[0]), psycopg.types.json.Jsonb({
            'encoder_job_id': str(item.encoder_job_id), 'destination_id': str(item.destination_id),
            'production_execution': False,
        })),
    )
    conn.commit()
    await publish('distribution.session.created', {
        'session_id': str(row[0]), 'destination_id': str(item.destination_id),
        'production_execution': False,
    })
    return {'id': str(row[0]), 'state': row[1], 'created_at': row[2], 'production_execution': False}


@app.patch('/v1/sessions/{session_id}/state')
async def update_session_state(
    session_id: uuid.UUID,
    item: SessionStateUpdate,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    if _production_outputs_enabled():
        raise HTTPException(503, 'Distribution dashboard blocked when production outputs are enabled')
    row = conn.execute(
        """SELECT s.state,s.destination_id,d.kind,d.enabled
           FROM output_sessions s JOIN output_destinations d ON d.id=s.destination_id
           WHERE s.id=%s FOR UPDATE""",
        (session_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Output session not found')
    current, destination_id, kind, enabled = row
    if _is_external_kind(kind):
        raise HTTPException(403, f'External destination kind {kind} remains locked in staging')
    if item.state in {'connecting', 'connected'} and not enabled:
        raise HTTPException(409, 'Destination is disabled')
    if item.state == current:
        return {'id': str(session_id), 'state': current, 'noop': True, 'production_execution': False}
    if item.state not in SESSION_ALLOWED.get(current, set()):
        raise HTTPException(409, f'Illegal distribution transition {current} -> {item.state}')

    connected_sql = "connected_at=COALESCE(connected_at,now())," if item.state == 'connected' else ""
    disconnected_sql = "disconnected_at=now()," if item.state in {'disconnected', 'failed'} else ""
    conn.execute(
        f"""UPDATE output_sessions SET {connected_sql}{disconnected_sql}state=%s,last_error=%s,metrics=%s::jsonb WHERE id=%s""",
        (item.state, item.last_error, psycopg.types.json.Jsonb({**item.metrics, 'production_execution': False}), session_id),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'distribution.session.state','output_session',%s,'distribution',%s::jsonb)""",
        (actor.get('sub'), str(session_id), psycopg.types.json.Jsonb({
            'from': current, 'to': item.state, 'destination_id': str(destination_id),
            'production_execution': False,
        })),
    )
    conn.commit()
    await publish('distribution.session.state', {
        'session_id': str(session_id), 'from': current, 'to': item.state,
        'production_execution': False,
    })
    return {'id': str(session_id), 'from': current, 'to': item.state, 'production_execution': False}


@app.get('/v1/safety')
def safety(_: dict = Depends(require_permission('broadcast.read'))):
    enabled = _production_outputs_enabled()
    return {
        'service': 'distribution',
        'deployment_env': os.getenv('DEPLOYMENT_ENV', 'development'),
        'production_outputs_enabled': enabled,
        'production_execution': False,
        'external_destinations_locked': True,
        'allowed_staging_kinds': ['internal_hls', 'recording'],
        'safe_for_staging': not enabled,
    }
