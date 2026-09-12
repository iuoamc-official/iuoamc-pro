from __future__ import annotations
import os, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Encoder Cluster", version="0.1.0")


def db():
    with psycopg.connect(os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")) as conn:
        yield conn


class NodeRegister(BaseModel):
    node_key: str = Field(pattern=r"^[a-z0-9][a-z0-9-]{1,62}$")
    display_name: str
    zone: str = "local"
    capabilities: dict = {}


class EncoderJobCreate(BaseModel):
    channel_id: uuid.UUID
    playout_run_id: uuid.UUID | None = None
    profile: str = "house-1080p25"
    requested_outputs: list[str] = []


@app.get("/health")
def health():
    return {"service": "encoder", "status": "ok", "phase": "isolated-control-only"}


@app.post("/v1/nodes", status_code=201)
async def register_node(
    item: NodeRegister,
    actor: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO encoder_nodes(node_key,display_name,zone,capabilities,last_heartbeat_at)
           VALUES(%s,%s,%s,%s::jsonb,now())
           ON CONFLICT(node_key) DO UPDATE SET
             display_name=EXCLUDED.display_name,
             zone=EXCLUDED.zone,
             capabilities=EXCLUDED.capabilities,
             last_heartbeat_at=now(),
             updated_at=now()
           RETURNING id,state,last_heartbeat_at""",
        (item.node_key, item.display_name, item.zone, psycopg.types.json.Jsonb(item.capabilities)),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'encoder.node.register','encoder_node',%s,'encoder','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("encoder.node.registered", {"node_id": str(row[0]), "node_key": item.node_key})
    return {"id": str(row[0]), "state": row[1], "last_heartbeat_at": row[2]}


@app.post("/v1/jobs", status_code=201)
async def create_job(
    item: EncoderJobCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    channel = conn.execute("SELECT 1 FROM channels WHERE id=%s", (item.channel_id,)).fetchone()
    if not channel:
        raise HTTPException(404, "Channel not found")
    row = conn.execute(
        """INSERT INTO encoder_jobs(channel_id,playout_run_id,profile,requested_outputs)
           VALUES(%s,%s,%s,%s::jsonb) RETURNING id,state,created_at""",
        (item.channel_id, item.playout_run_id, item.profile, psycopg.types.json.Jsonb(item.requested_outputs)),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'encoder.job.create','encoder_job',%s,'encoder','{}'::jsonb)""",
        (actor.get("sub"), str(row[0])),
    )
    conn.commit()
    await publish("encoder.job.created", {"job_id": str(row[0]), "channel_id": str(item.channel_id)})
    return {"id": str(row[0]), "state": row[1], "created_at": row[2]}


@app.get("/v1/jobs/{job_id}")
def get_job(
    job_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    row = conn.execute(
        """SELECT id,channel_id,playout_run_id,node_id,state,profile,requested_outputs,
                  started_at,stopped_at,failure_reason,created_at
           FROM encoder_jobs WHERE id=%s""",
        (job_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Encoder job not found")
    keys = ["id","channel_id","playout_run_id","node_id","state","profile","requested_outputs",
            "started_at","stopped_at","failure_reason","created_at"]
    data = dict(zip(keys,row))
    for k in ("id","channel_id","playout_run_id","node_id"):
        if data.get(k) is not None:
            data[k]=str(data[k])
    return data
