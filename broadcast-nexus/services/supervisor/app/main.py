from __future__ import annotations
import os, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Broadcast Supervisor", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


ALLOWED = {
    "stopped": {"starting"},
    "starting": {"running", "failed", "stopping"},
    "running": {"degraded", "recovering", "stopping", "failed"},
    "degraded": {"recovering", "running", "stopping", "failed"},
    "recovering": {"running", "degraded", "stopping", "failed"},
    "stopping": {"stopped", "failed"},
    "failed": {"starting", "stopped"},
}


class RuntimeInit(BaseModel):
    channel_id: uuid.UUID
    source_mode: str = Field(pattern=r"^(playout|studio|emergency|shadow)$")


class TransitionRequest(BaseModel):
    to_state: str = Field(pattern=r"^(stopped|starting|running|degraded|recovering|stopping|failed)$")
    reason: str = Field(min_length=3, max_length=500)
    evidence: dict = {}


class ServiceState(BaseModel):
    service_name: str
    service_role: str
    instance_ref: str | None = None
    state: str
    healthy: bool | None = None
    details: dict = {}


@app.get("/health")
def health():
    return {"service":"broadcast-supervisor","status":"ok","production_switching":False}


@app.post("/v1/runtime", status_code=201)
def init_runtime(
    item: RuntimeInit,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    channel = conn.execute("SELECT 1 FROM channels WHERE id=%s", (item.channel_id,)).fetchone()
    if not channel:
        raise HTTPException(404, "Channel not found")
    row = conn.execute(
        """INSERT INTO channel_runtime_states(channel_id,source_mode)
           VALUES(%s,%s)
           ON CONFLICT(channel_id) DO UPDATE SET source_mode=EXCLUDED.source_mode,updated_at=now()
           RETURNING channel_id,desired_state,actual_state,generation""",
        (item.channel_id,item.source_mode),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'supervisor.runtime.init','channel',%s,'supervisor','{}'::jsonb)""",
        (actor.get("sub"),str(item.channel_id)),
    )
    conn.commit()
    return {"channel_id":str(row[0]),"desired_state":row[1],"actual_state":row[2],"generation":row[3]}


@app.post("/v1/runtime/{channel_id}/transition")
async def transition(
    channel_id: uuid.UUID,
    item: TransitionRequest,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    row = conn.execute(
        "SELECT actual_state,generation FROM channel_runtime_states WHERE channel_id=%s FOR UPDATE",
        (channel_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Channel runtime not initialized")
    current,generation = row
    if item.to_state == current:
        return {"channel_id":str(channel_id),"state":current,"generation":generation,"noop":True}
    if item.to_state not in ALLOWED.get(current,set()):
        raise HTTPException(409, f"Illegal transition {current} -> {item.to_state}")
    generation += 1
    conn.execute(
        """UPDATE channel_runtime_states
           SET desired_state=%s,actual_state=%s,generation=%s,last_transition_at=now(),updated_at=now()
           WHERE channel_id=%s""",
        (item.to_state,item.to_state,generation,channel_id),
    )
    conn.execute(
        """INSERT INTO supervisor_transitions(channel_id,generation,from_state,to_state,reason,requested_by,evidence)
           VALUES(%s,%s,%s,%s,%s,%s,%s::jsonb)""",
        (channel_id,generation,current,item.to_state,item.reason,actor.get("sub"),psycopg.types.json.Jsonb(item.evidence)),
    )
    conn.commit()
    await publish("broadcast.supervisor.transition", {
        "channel_id":str(channel_id),"generation":generation,"from":current,"to":item.to_state,
        "reason":item.reason,"production_switching":False,
    })
    return {"channel_id":str(channel_id),"from":current,"to":item.to_state,"generation":generation}


@app.put("/v1/runtime/{channel_id}/services")
def update_service_state(
    channel_id: uuid.UUID,
    item: ServiceState,
    _: dict = Depends(require_permission("broadcast.write")),
    conn=Depends(db),
):
    exists = conn.execute("SELECT 1 FROM channel_runtime_states WHERE channel_id=%s", (channel_id,)).fetchone()
    if not exists:
        raise HTTPException(404, "Channel runtime not initialized")
    conn.execute(
        """INSERT INTO supervisor_service_states(channel_id,service_name,service_role,instance_ref,state,healthy,last_seen_at,details)
           VALUES(%s,%s,%s,%s,%s,%s,now(),%s::jsonb)
           ON CONFLICT(channel_id,service_name,service_role) DO UPDATE
           SET instance_ref=EXCLUDED.instance_ref,state=EXCLUDED.state,healthy=EXCLUDED.healthy,
               last_seen_at=now(),details=EXCLUDED.details""",
        (channel_id,item.service_name,item.service_role,item.instance_ref,item.state,item.healthy,
         psycopg.types.json.Jsonb(item.details)),
    )
    conn.commit()
    return {"channel_id":str(channel_id),"service":item.service_name,"state":item.state}


@app.get("/v1/runtime/{channel_id}")
def runtime_snapshot(
    channel_id: uuid.UUID,
    _: dict = Depends(require_permission("broadcast.read")),
    conn=Depends(db),
):
    r = conn.execute(
        """SELECT desired_state,actual_state,source_mode,active_program_source,active_encoder,
                  active_distribution,health_state,recovery_state,generation,last_transition_at,metadata
           FROM channel_runtime_states WHERE channel_id=%s""",
        (channel_id,),
    ).fetchone()
    if not r:
        raise HTTPException(404, "Channel runtime not initialized")
    services = conn.execute(
        """SELECT service_name,service_role,instance_ref,state,healthy,last_seen_at,details
           FROM supervisor_service_states WHERE channel_id=%s ORDER BY service_name,service_role""",
        (channel_id,),
    ).fetchall()
    transitions = conn.execute(
        """SELECT generation,from_state,to_state,reason,requested_by,created_at,evidence
           FROM supervisor_transitions WHERE channel_id=%s ORDER BY id DESC LIMIT 20""",
        (channel_id,),
    ).fetchall()
    return {
        "channel_id":str(channel_id),
        "runtime":{
            "desired_state":r[0],"actual_state":r[1],"source_mode":r[2],
            "active_program_source":r[3],"active_encoder":r[4],"active_distribution":r[5],
            "health_state":r[6],"recovery_state":r[7],"generation":r[8],
            "last_transition_at":r[9],"metadata":r[10],
        },
        "services":[
            {"service_name":s[0],"service_role":s[1],"instance_ref":s[2],"state":s[3],
             "healthy":s[4],"last_seen_at":s[5],"details":s[6]} for s in services
        ],
        "recent_transitions":[
            {"generation":t[0],"from":t[1],"to":t[2],"reason":t[3],"requested_by":t[4],
             "created_at":t[5],"evidence":t[6]} for t in transitions
        ],
        "production_switching":False,
    }
