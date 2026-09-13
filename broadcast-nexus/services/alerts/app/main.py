from __future__ import annotations
import hashlib, os, uuid
from datetime import datetime, timezone, timedelta
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Alert Engine", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


def compare(metric: str, comparator: str, threshold, row: dict) -> bool:
    value = row.get(metric)
    if comparator == "true":
        return value is True
    if comparator == "false":
        return value is False
    if value is None:
        return False
    if comparator == "eq": return value == threshold
    if comparator == "neq": return value != threshold
    if comparator == "gt": return value > threshold
    if comparator == "gte": return value >= threshold
    if comparator == "lt": return value < threshold
    if comparator == "lte": return value <= threshold
    return False


def fingerprint(rule_id: uuid.UUID, target_id: uuid.UUID) -> str:
    return hashlib.sha256(f"{rule_id}:{target_id}".encode()).hexdigest()


@app.get("/health")
def health():
    return {"service": "alerts", "status": "ok", "phase": "noc"}


@app.post("/v1/evaluate/{target_id}")
async def evaluate_target(
    target_id: uuid.UUID,
    _: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    target = conn.execute(
        "SELECT target_type,display_name FROM monitoring_targets WHERE id=%s AND enabled=TRUE",
        (target_id,),
    ).fetchone()
    if not target:
        raise HTTPException(404, "Target not found")

    latest = conn.execute(
        """SELECT state,video_present,audio_present,frozen_frame,black_frame,silence_detected,
                  fps,bitrate_kbps,audio_lufs,latency_ms,packet_loss_pct,jitter_ms,timestamp_drift_ms,
                  hls_age_seconds,sampled_at
           FROM health_samples WHERE target_id=%s ORDER BY sampled_at DESC LIMIT 1""",
        (target_id,),
    ).fetchone()
    if not latest:
        return {"target_id": str(target_id), "evaluated": 0, "alerts": []}

    keys = ["state","video_present","audio_present","frozen_frame","black_frame","silence_detected",
            "fps","bitrate_kbps","audio_lufs","latency_ms","packet_loss_pct","jitter_ms",
            "timestamp_drift_ms","hls_age_seconds","sampled_at"]
    sample = dict(zip(keys, latest))

    rules = conn.execute(
        """SELECT id,name,metric,comparator,threshold_num,duration_seconds,severity,cooldown_seconds
           FROM alert_rules WHERE enabled=TRUE AND (target_type IS NULL OR target_type=%s)""",
        (target[0],),
    ).fetchall()

    opened = []
    for r in rules:
        rid,name,metric,cmp,threshold,duration,severity,cooldown = r
        triggered = compare(metric, cmp, threshold, sample)
        fp = fingerprint(rid, target_id)
        existing = conn.execute(
            "SELECT id,state FROM alert_events WHERE fingerprint=%s AND state IN ('open','acknowledged') ORDER BY opened_at DESC LIMIT 1",
            (fp,),
        ).fetchone()

        if triggered and not existing:
            if duration > 0:
                since = datetime.now(timezone.utc) - timedelta(seconds=duration)
                history = conn.execute(
                    "SELECT count(*) FROM health_samples WHERE target_id=%s AND sampled_at >= %s",
                    (target_id, since),
                ).fetchone()[0]
                if history < 2:
                    continue
            summary = f"{name}: {target[1]}"
            event = conn.execute(
                """INSERT INTO alert_events(rule_id,target_id,severity,fingerprint,summary,details)
                   VALUES(%s,%s,%s,%s,%s,%s::jsonb) RETURNING id,opened_at""",
                (rid,target_id,severity,fp,summary,psycopg.types.json.Jsonb({"metric":metric,"sample":sample.get(metric)})),
            ).fetchone()
            opened.append({"id": str(event[0]), "severity": severity, "summary": summary})
        elif not triggered and existing:
            conn.execute(
                "UPDATE alert_events SET state='resolved',resolved_at=now() WHERE id=%s",
                (existing[0],),
            )

    conn.commit()
    for item in opened:
        await publish("noc.alert.opened", {"target_id": str(target_id), **item})
    return {"target_id": str(target_id), "evaluated": len(rules), "alerts": opened}


@app.get("/v1/alerts")
def list_alerts(
    state: str = "open",
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT a.id,a.severity,a.state,a.summary,a.opened_at,a.acknowledged_at,a.resolved_at,
                  t.display_name,t.target_type
           FROM alert_events a JOIN monitoring_targets t ON t.id=a.target_id
           WHERE a.state=%s ORDER BY a.opened_at DESC LIMIT 300""",
        (state,),
    ).fetchall()
    return [
        {"id":str(r[0]),"severity":r[1],"state":r[2],"summary":r[3],"opened_at":r[4],
         "acknowledged_at":r[5],"resolved_at":r[6],"target":r[7],"target_type":r[8]}
        for r in rows
    ]


@app.post("/v1/alerts/{alert_id}/ack")
def acknowledge(
    alert_id: uuid.UUID,
    actor: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        "UPDATE alert_events SET state='acknowledged',acknowledged_at=now() WHERE id=%s AND state='open' RETURNING id",
        (alert_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Open alert not found")
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'alert.acknowledge','alert',%s,'alerts','{}'::jsonb)""",
        (actor.get("sub"), str(alert_id)),
    )
    conn.commit()
    return {"id": str(alert_id), "state": "acknowledged"}
