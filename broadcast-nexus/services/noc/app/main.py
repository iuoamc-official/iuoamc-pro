from __future__ import annotations
import os, uuid
from datetime import datetime, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel, Field
from packages.auth.security import require_permission

app = FastAPI(title="IUOAMC NOC API", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


class IncidentCreate(BaseModel):
    channel_id: uuid.UUID | None = None
    severity: str = Field(pattern=r"^(info|warning|critical)$")
    title: str = Field(min_length=1,max_length=200)
    summary: str | None = None


@app.get("/health")
def health():
    return {"service":"noc","status":"ok","phase":"mission-critical-foundation"}


@app.get("/v1/dashboard")
def dashboard(
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    target_rows = conn.execute(
        """SELECT COALESCE(s.state,'unknown') state,count(*)
           FROM monitoring_targets t
           LEFT JOIN LATERAL (
             SELECT state FROM health_samples hs WHERE hs.target_id=t.id ORDER BY sampled_at DESC LIMIT 1
           ) s ON TRUE
           WHERE t.enabled=TRUE
           GROUP BY COALESCE(s.state,'unknown')"""
    ).fetchall()
    alert_rows = conn.execute(
        "SELECT severity,count(*) FROM alert_events WHERE state IN ('open','acknowledged') GROUP BY severity"
    ).fetchall()
    incidents = conn.execute(
        "SELECT count(*) FROM noc_incidents WHERE state <> 'resolved'"
    ).fetchone()[0]
    recommendations = conn.execute(
        "SELECT count(*) FROM failover_decisions WHERE decision='recommend_failover' AND created_at > now()-interval '24 hours'"
    ).fetchone()[0]
    return {
        "generated_at":datetime.now(timezone.utc),
        "targets":{r[0]:r[1] for r in target_rows},
        "alerts":{r[0]:r[1] for r in alert_rows},
        "open_incidents":incidents,
        "failover_recommendations_24h":recommendations,
        "production_switching_enabled":False,
    }


@app.post("/v1/incidents", status_code=201)
def create_incident(
    item: IncidentCreate,
    actor: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO noc_incidents(channel_id,severity,title,summary)
           VALUES(%s,%s,%s,%s) RETURNING id,opened_at""",
        (item.channel_id,item.severity,item.title,item.summary),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'incident.create','noc_incident',%s,'noc','{}'::jsonb)""",
        (actor.get("sub"),str(row[0])),
    )
    conn.commit()
    return {"id":str(row[0]),"opened_at":row[1],"state":"open"}


@app.get("/v1/incidents")
def list_incidents(
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,severity,state,title,summary,opened_at,updated_at,resolved_at
           FROM noc_incidents ORDER BY opened_at DESC LIMIT 300"""
    ).fetchall()
    return [
        {"id":str(r[0]),"severity":r[1],"state":r[2],"title":r[3],"summary":r[4],
         "opened_at":r[5],"updated_at":r[6],"resolved_at":r[7]}
        for r in rows
    ]


@app.post("/v1/incidents/{incident_id}/resolve")
def resolve_incident(
    incident_id: uuid.UUID,
    actor: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        """UPDATE noc_incidents SET state='resolved',updated_at=now(),resolved_at=now()
           WHERE id=%s AND state <> 'resolved' RETURNING id""",
        (incident_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404,"Open incident not found")
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'incident.resolve','noc_incident',%s,'noc','{}'::jsonb)""",
        (actor.get("sub"),str(incident_id)),
    )
    conn.commit()
    return {"id":str(incident_id),"state":"resolved"}
