from __future__ import annotations

import os
import uuid

import psycopg
from fastapi import Depends, HTTPException
from pydantic import BaseModel, Field

from app.main import app, db
from packages.auth.security import require_permission
from packages.events.nats_client import publish


class NodeStateUpdate(BaseModel):
    state: str = Field(pattern=r"^(standby|ready|busy|degraded|offline)$")


class JobAssignment(BaseModel):
    node_id: uuid.UUID


class JobStateUpdate(BaseModel):
    state: str = Field(pattern=r"^(queued|assigned|running|stopping|stopped|failed)$")
    failure_reason: str | None = Field(default=None, max_length=500)


JOB_ALLOWED = {
    "queued": {"assigned", "failed"},
    "assigned": {"running", "queued", "failed"},
    "running": {"stopping", "failed"},
    "stopping": {"stopped", "failed"},
    "stopped": set(),
    "failed": set(),
}

HOUSE_PROFILES = [
    {"code": "house-1080p25", "width": 1920, "height": 1080, "fps": 25, "video_codec": "h264", "audio_codec": "aac", "audio_hz": 48000},
    {"code": "house-720p25", "width": 1280, "height": 720, "fps": 25, "video_codec": "h264", "audio_codec": "aac", "audio_hz": 48000},
    {"code": "house-audio-only", "width": None, "height": None, "fps": None, "video_codec": None, "audio_codec": "aac", "audio_hz": 48000},
]


def _production_outputs_enabled() -> bool:
    return os.getenv("PRODUCTION_OUTPUTS_ENABLED", "false").lower() == "true"


@app.get('/v1/profiles')
def profiles(_: dict = Depends(require_permission('broadcast.read'))):
    return {"profiles": HOUSE_PROFILES, "production_execution": False}


@app.get('/v1/nodes')
def list_nodes(
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,node_key,display_name,zone,state,capabilities,last_heartbeat_at,created_at,updated_at
           FROM encoder_nodes ORDER BY display_name,node_key"""
    ).fetchall()
    return [
        {
            "id": str(r[0]), "node_key": r[1], "display_name": r[2], "zone": r[3],
            "state": r[4], "capabilities": r[5], "last_heartbeat_at": r[6],
            "created_at": r[7], "updated_at": r[8],
        }
        for r in rows
    ]


@app.patch('/v1/nodes/{node_id}/state')
async def update_node_state(
    node_id: uuid.UUID,
    item: NodeStateUpdate,
    actor: dict = Depends(require_permission('noc.operate')),
    conn=Depends(db),
):
    row = conn.execute(
        """UPDATE encoder_nodes SET state=%s,last_heartbeat_at=now(),updated_at=now()
           WHERE id=%s RETURNING node_key,state,last_heartbeat_at""",
        (item.state, node_id),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Encoder node not found')
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'encoder.node.state','encoder_node',%s,'encoder',%s::jsonb)""",
        (actor.get('sub'), str(node_id), psycopg.types.json.Jsonb({"state": item.state})),
    )
    conn.commit()
    await publish('encoder.node.state', {"node_id": str(node_id), "state": item.state})
    return {"id": str(node_id), "node_key": row[0], "state": row[1], "last_heartbeat_at": row[2]}


@app.get('/v1/channels/{channel_id}/jobs')
def list_jobs(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT j.id,j.channel_id,j.playout_run_id,j.node_id,n.display_name,j.state,j.profile,
                  j.requested_outputs,j.started_at,j.stopped_at,j.failure_reason,j.created_at
           FROM encoder_jobs j
           LEFT JOIN encoder_nodes n ON n.id=j.node_id
           WHERE j.channel_id=%s ORDER BY j.created_at DESC LIMIT 200""",
        (channel_id,),
    ).fetchall()
    return [
        {
            "id": str(r[0]), "channel_id": str(r[1]),
            "playout_run_id": str(r[2]) if r[2] else None,
            "node_id": str(r[3]) if r[3] else None,
            "node_name": r[4], "state": r[5], "profile": r[6],
            "requested_outputs": r[7], "started_at": r[8], "stopped_at": r[9],
            "failure_reason": r[10], "created_at": r[11],
            "production_outputs_enabled": False,
        }
        for r in rows
    ]


@app.put('/v1/jobs/{job_id}/assignment')
async def assign_job(
    job_id: uuid.UUID,
    item: JobAssignment,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    node = conn.execute("SELECT state FROM encoder_nodes WHERE id=%s", (item.node_id,)).fetchone()
    if not node:
        raise HTTPException(404, 'Encoder node not found')
    if node[0] in {'offline', 'degraded'}:
        raise HTTPException(409, f'Node is not assignable while {node[0]}')
    row = conn.execute(
        """SELECT state FROM encoder_jobs WHERE id=%s FOR UPDATE""",
        (job_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Encoder job not found')
    if row[0] not in {'queued', 'assigned'}:
        raise HTTPException(409, f'Cannot assign job while {row[0]}')
    conn.execute(
        """UPDATE encoder_jobs SET node_id=%s,state='assigned' WHERE id=%s""",
        (item.node_id, job_id),
    )
    conn.execute(
        """UPDATE encoder_nodes SET state='busy',last_heartbeat_at=now(),updated_at=now() WHERE id=%s""",
        (item.node_id,),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'encoder.job.assign','encoder_job',%s,'encoder',%s::jsonb)""",
        (actor.get('sub'), str(job_id), psycopg.types.json.Jsonb({"node_id": str(item.node_id)})),
    )
    conn.commit()
    await publish('encoder.job.assigned', {"job_id": str(job_id), "node_id": str(item.node_id)})
    return {"id": str(job_id), "node_id": str(item.node_id), "state": "assigned"}


@app.patch('/v1/jobs/{job_id}/state')
async def update_job_state(
    job_id: uuid.UUID,
    item: JobStateUpdate,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    if _production_outputs_enabled():
        raise HTTPException(503, 'Encoder dashboard refuses operation when production outputs are enabled')
    row = conn.execute(
        "SELECT state,node_id FROM encoder_jobs WHERE id=%s FOR UPDATE",
        (job_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Encoder job not found')
    current, node_id = row
    if item.state == current:
        return {"id": str(job_id), "state": current, "noop": True, "production_execution": False}
    if item.state not in JOB_ALLOWED.get(current, set()):
        raise HTTPException(409, f'Illegal encoder transition {current} -> {item.state}')
    if item.state == 'running' and not node_id:
        raise HTTPException(409, 'Running requires an assigned encoder node')

    started_sql = "started_at=COALESCE(started_at,now())," if item.state == 'running' else ""
    stopped_sql = "stopped_at=now()," if item.state in {'stopped', 'failed'} else ""
    conn.execute(
        f"""UPDATE encoder_jobs SET {started_sql}{stopped_sql}state=%s,failure_reason=%s WHERE id=%s""",
        (item.state, item.failure_reason, job_id),
    )
    if node_id and item.state in {'stopped', 'failed'}:
        conn.execute(
            "UPDATE encoder_nodes SET state='ready',last_heartbeat_at=now(),updated_at=now() WHERE id=%s",
            (node_id,),
        )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'encoder.job.state','encoder_job',%s,'encoder',%s::jsonb)""",
        (actor.get('sub'), str(job_id), psycopg.types.json.Jsonb({"from": current, "to": item.state, "production_execution": False})),
    )
    conn.commit()
    await publish('encoder.job.state', {
        "job_id": str(job_id), "from": current, "to": item.state,
        "production_outputs_enabled": False, "production_execution": False,
    })
    return {"id": str(job_id), "from": current, "to": item.state, "production_execution": False}


@app.get('/v1/safety')
def safety(_: dict = Depends(require_permission('broadcast.read'))):
    return {
        "service": "encoder",
        "deployment_env": os.getenv("DEPLOYMENT_ENV", "development"),
        "production_outputs_enabled": _production_outputs_enabled(),
        "production_execution": False,
        "ffmpeg_spawn_enabled": False,
        "external_destinations_enabled": False,
        "safe_for_staging": not _production_outputs_enabled(),
    }
