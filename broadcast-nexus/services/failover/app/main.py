from __future__ import annotations
import os, uuid
from datetime import datetime, timezone, timedelta
import psycopg
from fastapi import FastAPI, Depends, HTTPException
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Failover Decision Engine", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


@app.get("/health")
def health():
    return {"service":"failover","status":"ok","mode":"decision-only","production_switching":False}


def recent_states(conn, target_id: uuid.UUID, limit: int) -> list[str]:
    rows = conn.execute(
        "SELECT state FROM health_samples WHERE target_id=%s ORDER BY sampled_at DESC LIMIT %s",
        (target_id, limit),
    ).fetchall()
    return [r[0] for r in rows]


def healthy_enough(states: list[str], required: int) -> bool:
    return len(states) >= required and all(s == "healthy" for s in states[:required])


def bad_enough(states: list[str], required: int) -> bool:
    bad = {"critical", "degraded"}
    return len(states) >= required and all(s in bad for s in states[:required])


@app.post("/v1/groups/{group_id}/evaluate")
async def evaluate_group(
    group_id: uuid.UUID,
    _: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    group = conn.execute(
        "SELECT name,mode,enabled FROM redundancy_groups WHERE id=%s",
        (group_id,),
    ).fetchone()
    if not group or not group[2]:
        raise HTTPException(404, "Redundancy group not found or disabled")

    policy = conn.execute(
        """SELECT id,consecutive_bad_samples,recovery_good_samples,decision_cooldown_seconds,
                  require_manual_authorization
           FROM failover_policies WHERE group_id=%s AND enabled=TRUE ORDER BY created_at LIMIT 1""",
        (group_id,),
    ).fetchone()
    if not policy:
        raise HTTPException(409, "No active failover policy")

    policy_id,bad_n,good_n,cooldown,manual = policy
    members = conn.execute(
        """SELECT m.target_id,m.priority,m.role,t.display_name
           FROM redundancy_members m JOIN monitoring_targets t ON t.id=m.target_id
           WHERE m.group_id=%s AND m.eligible=TRUE AND t.enabled=TRUE
           ORDER BY m.priority ASC""",
        (group_id,),
    ).fetchall()
    if len(members) < 2:
        raise HTTPException(409, "Need at least two eligible redundancy members")

    latest_decision = conn.execute(
        "SELECT created_at FROM failover_decisions WHERE group_id=%s ORDER BY created_at DESC LIMIT 1",
        (group_id,),
    ).fetchone()
    if latest_decision and latest_decision[0] > datetime.now(timezone.utc) - timedelta(seconds=cooldown):
        return {"group_id":str(group_id),"decision":"hold","reason":"decision cooldown active"}

    primary = next((m for m in members if m[2] == "primary"), members[0])
    primary_states = recent_states(conn, primary[0], max(bad_n, good_n))

    candidate = None
    for member in members:
        if member[0] == primary[0]:
            continue
        states = recent_states(conn, member[0], good_n)
        if healthy_enough(states, good_n):
            candidate = member
            break

    if bad_enough(primary_states, bad_n) and candidate:
        decision = "recommend_failover"
        reason = f"primary unhealthy for {bad_n} consecutive samples; healthy standby available"
        evidence = {
            "primary": {"target_id":str(primary[0]),"name":primary[3],"states":primary_states[:bad_n]},
            "candidate": {"target_id":str(candidate[0]),"name":candidate[3],"states":recent_states(conn,candidate[0],good_n)},
            "manual_authorization_required": manual,
        }
        row = conn.execute(
            """INSERT INTO failover_decisions(group_id,policy_id,from_target_id,to_target_id,decision,reason,evidence)
               VALUES(%s,%s,%s,%s,%s,%s,%s::jsonb) RETURNING id,created_at""",
            (group_id,policy_id,primary[0],candidate[0],decision,reason,psycopg.types.json.Jsonb(evidence)),
        ).fetchone()
        conn.commit()
        await publish("noc.failover.recommended", {
            "decision_id":str(row[0]),"group_id":str(group_id),"from_target_id":str(primary[0]),
            "to_target_id":str(candidate[0]),"manual_authorization_required":manual,
        })
        return {"decision_id":str(row[0]),"decision":decision,"reason":reason,"evidence":evidence}

    return {"group_id":str(group_id),"decision":"hold","reason":"failover conditions not met"}


@app.post("/v1/decisions/{decision_id}/authorize")
async def authorize_decision(
    decision_id: uuid.UUID,
    actor: dict = Depends(require_permission("noc.operate")),
    conn=Depends(db),
):
    row = conn.execute(
        """UPDATE failover_decisions
           SET decision='authorized_failover',authorized_at=now(),authorized_by=%s
           WHERE id=%s AND decision='recommend_failover'
           RETURNING group_id,from_target_id,to_target_id""",
        (actor.get("sub"),decision_id),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Recommended failover decision not found")
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'failover.authorize','failover_decision',%s,'failover','{}'::jsonb)""",
        (actor.get("sub"),str(decision_id)),
    )
    conn.commit()
    await publish("noc.failover.authorized", {
        "decision_id":str(decision_id),"group_id":str(row[0]),
        "from_target_id":str(row[1]),"to_target_id":str(row[2]),
        "execution":"not_implemented_in_isolated_phase",
    })
    return {
        "decision_id":str(decision_id),"state":"authorized_failover",
        "execution":"not_implemented_in_isolated_phase",
        "note":"Authorization is recorded only; no production switch is executed by this service.",
    }


@app.get("/v1/groups/{group_id}/decisions")
def list_decisions(
    group_id: uuid.UUID,
    _: dict = Depends(require_permission("noc.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,decision,reason,evidence,created_at,authorized_at,authorized_by
           FROM failover_decisions WHERE group_id=%s ORDER BY created_at DESC LIMIT 100""",
        (group_id,),
    ).fetchall()
    return [
        {"id":str(r[0]),"decision":r[1],"reason":r[2],"evidence":r[3],"created_at":r[4],
         "authorized_at":r[5],"authorized_by":r[6]}
        for r in rows
    ]
