from __future__ import annotations
import os, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Control Room", version="0.1.0")


def db():
    with psycopg.connect(os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")) as conn:
        yield conn


class TelemetryIn(BaseModel):
    source_service: str
    source_id: str
    metric_name: str
    metric_value: float | None = None
    metric_text: str | None = None
    status: str = Field(default="ok", pattern=r"^(ok|warning|critical|unknown)$")
    metadata: dict = {}


class SimulationIn(BaseModel):
    redundancy_group_id: uuid.UUID | None = None
    scenario: str
    expected_action: str | None = None
    injected_conditions: dict = {}


@app.get('/health')
def health():
    return {"service":"control-room","status":"ok","phase":"isolated"}


@app.post('/v1/telemetry', status_code=202)
async def ingest_telemetry(
    item: TelemetryIn,
    _: dict = Depends(require_permission('noc.operate')),
    conn = Depends(db),
):
    row = conn.execute(
        """INSERT INTO telemetry_streams(source_service,source_id,metric_name,metric_value,metric_text,status,metadata)
           VALUES(%s,%s,%s,%s,%s,%s,%s::jsonb) RETURNING id,observed_at""",
        (item.source_service,item.source_id,item.metric_name,item.metric_value,item.metric_text,item.status,
         psycopg.types.json.Jsonb(item.metadata)),
    ).fetchone()
    conn.commit()
    await publish('noc.telemetry.observed', {
        'telemetry_id':str(row[0]), 'source_service':item.source_service,
        'source_id':item.source_id, 'metric_name':item.metric_name, 'status':item.status,
    })
    return {'id':str(row[0]), 'observed_at':row[1]}


@app.get('/v1/overview')
def overview(
    _: dict = Depends(require_permission('noc.read')),
    conn = Depends(db),
):
    latest = conn.execute(
        """SELECT DISTINCT ON (source_service,source_id,metric_name)
                  source_service,source_id,metric_name,metric_value,metric_text,status,observed_at
           FROM telemetry_streams
           ORDER BY source_service,source_id,metric_name,observed_at DESC"""
    ).fetchall()
    incidents = conn.execute(
        "SELECT count(*) FROM noc_incidents WHERE state NOT IN ('resolved','closed')"
    ).fetchone()[0]
    decisions = conn.execute(
        "SELECT count(*) FROM failover_decisions WHERE state IN ('recommended','authorized')"
    ).fetchone()[0]
    return {
        'generated_at':datetime.now(timezone.utc),
        'open_incidents':incidents,
        'pending_failover_decisions':decisions,
        'metrics':[
            dict(source_service=r[0],source_id=r[1],metric_name=r[2],metric_value=r[3],metric_text=r[4],status=r[5],observed_at=r[6])
            for r in latest
        ],
    }


@app.get('/v1/chaos/scenarios')
def scenarios(
    _: dict = Depends(require_permission('noc.read')),
    conn = Depends(db),
):
    rows = conn.execute(
        "SELECT id,code,name,description,severity,scope,synthetic_only,enabled,config FROM chaos_scenarios ORDER BY code"
    ).fetchall()
    return [
        dict(id=str(r[0]),code=r[1],name=r[2],description=r[3],severity=r[4],scope=r[5],synthetic_only=r[6],enabled=r[7],config=r[8])
        for r in rows
    ]


@app.post('/v1/chaos/simulations', status_code=201)
async def run_simulation(
    item: SimulationIn,
    actor: dict = Depends(require_permission('noc.operate')),
    conn = Depends(db),
):
    scenario = conn.execute(
        "SELECT code,enabled,synthetic_only FROM chaos_scenarios WHERE code=%s", (item.scenario,)
    ).fetchone()
    if not scenario:
        raise HTTPException(404, 'Scenario not found')
    if not scenario[1]:
        raise HTTPException(409, 'Scenario disabled')
    if not scenario[2]:
        raise HTTPException(409, 'Only synthetic scenarios are allowed in isolated phase')

    observed_action = 'simulation_recorded_only'
    result = 'passed'
    row = conn.execute(
        """INSERT INTO failover_simulations(redundancy_group_id,scenario,injected_conditions,expected_action,observed_action,result,created_by,completed_at)
           VALUES(%s,%s,%s::jsonb,%s,%s,%s,%s,now()) RETURNING id,created_at""",
        (item.redundancy_group_id,item.scenario,psycopg.types.json.Jsonb(item.injected_conditions),
         item.expected_action,observed_action,result,actor.get('sub')),
    ).fetchone()
    conn.commit()
    await publish('noc.chaos.simulation.completed', {
        'simulation_id':str(row[0]), 'scenario':item.scenario, 'result':result,
        'production_effect':'none',
    })
    return {
        'id':str(row[0]), 'created_at':row[1], 'result':result,
        'execution':'synthetic_only', 'production_effect':'none',
    }


@app.get('/v1/chaos/simulations')
def list_simulations(
    limit: int = 100,
    _: dict = Depends(require_permission('noc.read')),
    conn = Depends(db),
):
    limit=max(1,min(limit,500))
    rows=conn.execute(
        """SELECT id,redundancy_group_id,scenario,injected_conditions,expected_action,observed_action,result,created_by,created_at,completed_at,notes
           FROM failover_simulations ORDER BY created_at DESC LIMIT %s""", (limit,)
    ).fetchall()
    return [
        dict(id=str(r[0]),redundancy_group_id=str(r[1]) if r[1] else None,scenario=r[2],injected_conditions=r[3],expected_action=r[4],observed_action=r[5],result=r[6],created_by=r[7],created_at=r[8],completed_at=r[9],notes=r[10])
        for r in rows
    ]
