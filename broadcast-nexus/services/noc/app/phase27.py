from __future__ import annotations

import uuid
from datetime import datetime, timezone

import psycopg
from fastapi import Depends, HTTPException
from pydantic import BaseModel, Field

from app.main import app, db
from packages.auth.security import require_permission
from packages.events.nats_client import publish


class IncidentStateUpdate(BaseModel):
    state: str = Field(pattern=r"^(open|investigating|mitigated|resolved)$")


class RedundancyGroupCreate(BaseModel):
    channel_id: uuid.UUID | None = None
    name: str = Field(min_length=1, max_length=180)
    mode: str = Field(default="active_standby", pattern=r"^(active_standby|active_active)$")


class RedundancyMemberCreate(BaseModel):
    target_id: uuid.UUID
    priority: int = Field(default=100, ge=1, le=10000)
    role: str = Field(pattern=r"^(primary|secondary|peer)$")
    eligible: bool = True


class FailoverPolicyCreate(BaseModel):
    name: str = Field(min_length=1, max_length=180)
    consecutive_bad_samples: int = Field(default=3, ge=1, le=100)
    recovery_good_samples: int = Field(default=5, ge=1, le=100)
    decision_cooldown_seconds: int = Field(default=120, ge=0, le=86400)
    require_manual_authorization: bool = True


@app.get('/v1/operations-snapshot')
def operations_snapshot(
    _: dict = Depends(require_permission('noc.read')),
    conn=Depends(db),
):
    targets = conn.execute(
        """SELECT t.id,t.channel_id,t.target_type,t.target_ref,t.display_name,t.enabled,
                  s.state,s.sampled_at,s.video_present,s.audio_present,s.frozen_frame,s.black_frame,
                  s.silence_detected,s.fps,s.bitrate_kbps,s.audio_lufs,s.latency_ms,s.packet_loss_pct,
                  s.jitter_ms,s.timestamp_drift_ms,s.hls_age_seconds
           FROM monitoring_targets t
           LEFT JOIN LATERAL (
             SELECT * FROM health_samples hs WHERE hs.target_id=t.id ORDER BY sampled_at DESC LIMIT 1
           ) s ON TRUE
           ORDER BY t.display_name"""
    ).fetchall()
    alerts = conn.execute(
        """SELECT a.id,a.target_id,a.severity,a.state,a.summary,a.details,a.opened_at,a.acknowledged_at,a.resolved_at,
                  t.display_name,t.target_type
           FROM alert_events a JOIN monitoring_targets t ON t.id=a.target_id
           WHERE a.state IN ('open','acknowledged') ORDER BY a.opened_at DESC LIMIT 300"""
    ).fetchall()
    incidents = conn.execute(
        """SELECT id,channel_id,severity,state,title,summary,opened_at,updated_at,resolved_at
           FROM noc_incidents ORDER BY opened_at DESC LIMIT 300"""
    ).fetchall()
    groups = conn.execute(
        """SELECT g.id,g.channel_id,g.name,g.mode,g.enabled,g.created_at,
                  p.id,p.name,p.consecutive_bad_samples,p.recovery_good_samples,
                  p.decision_cooldown_seconds,p.require_manual_authorization,p.enabled
           FROM redundancy_groups g
           LEFT JOIN LATERAL (
             SELECT * FROM failover_policies fp WHERE fp.group_id=g.id ORDER BY fp.created_at DESC LIMIT 1
           ) p ON TRUE
           ORDER BY g.name"""
    ).fetchall()
    members = conn.execute(
        """SELECT m.group_id,m.id,m.target_id,m.priority,m.role,m.eligible,t.display_name,t.target_type
           FROM redundancy_members m JOIN monitoring_targets t ON t.id=m.target_id
           ORDER BY m.group_id,m.priority"""
    ).fetchall()
    decisions = conn.execute(
        """SELECT id,group_id,from_target_id,to_target_id,decision,reason,evidence,created_at,authorized_at,authorized_by
           FROM failover_decisions ORDER BY created_at DESC LIMIT 200"""
    ).fetchall()

    member_map: dict[str, list[dict]] = {}
    for r in members:
        member_map.setdefault(str(r[0]), []).append({
            'id': str(r[1]), 'target_id': str(r[2]), 'priority': r[3], 'role': r[4],
            'eligible': r[5], 'display_name': r[6], 'target_type': r[7],
        })
    decision_map: dict[str, list[dict]] = {}
    for r in decisions:
        decision_map.setdefault(str(r[1]), []).append({
            'id': str(r[0]), 'from_target_id': str(r[2]) if r[2] else None,
            'to_target_id': str(r[3]) if r[3] else None, 'decision': r[4], 'reason': r[5],
            'evidence': r[6], 'created_at': r[7], 'authorized_at': r[8], 'authorized_by': r[9],
            'production_switching': False,
        })

    target_payload = [{
        'id': str(r[0]), 'channel_id': str(r[1]) if r[1] else None, 'target_type': r[2],
        'target_ref': r[3], 'display_name': r[4], 'enabled': r[5], 'state': r[6] or 'unknown',
        'sampled_at': r[7], 'video_present': r[8], 'audio_present': r[9],
        'frozen_frame': r[10], 'black_frame': r[11], 'silence_detected': r[12],
        'fps': r[13], 'bitrate_kbps': r[14], 'audio_lufs': r[15], 'latency_ms': r[16],
        'packet_loss_pct': r[17], 'jitter_ms': r[18], 'timestamp_drift_ms': r[19],
        'hls_age_seconds': r[20],
    } for r in targets]

    counts = {'healthy': 0, 'degraded': 0, 'critical': 0, 'unknown': 0}
    for t in target_payload:
        counts[t['state']] = counts.get(t['state'], 0) + 1

    return {
        'generated_at': datetime.now(timezone.utc),
        'production_switching': False,
        'execution_mode': 'decision-only',
        'counts': {
            'targets': len(target_payload),
            'target_states': counts,
            'open_alerts': sum(1 for a in alerts if a[3] == 'open'),
            'acknowledged_alerts': sum(1 for a in alerts if a[3] == 'acknowledged'),
            'open_incidents': sum(1 for i in incidents if i[3] != 'resolved'),
            'redundancy_groups': len(groups),
        },
        'targets': target_payload,
        'alerts': [{
            'id': str(r[0]), 'target_id': str(r[1]), 'severity': r[2], 'state': r[3],
            'summary': r[4], 'details': r[5], 'opened_at': r[6], 'acknowledged_at': r[7],
            'resolved_at': r[8], 'target': r[9], 'target_type': r[10],
        } for r in alerts],
        'incidents': [{
            'id': str(r[0]), 'channel_id': str(r[1]) if r[1] else None, 'severity': r[2],
            'state': r[3], 'title': r[4], 'summary': r[5], 'opened_at': r[6],
            'updated_at': r[7], 'resolved_at': r[8],
        } for r in incidents],
        'redundancy_groups': [{
            'id': str(r[0]), 'channel_id': str(r[1]) if r[1] else None, 'name': r[2],
            'mode': r[3], 'enabled': r[4], 'created_at': r[5],
            'policy': None if r[6] is None else {
                'id': str(r[6]), 'name': r[7], 'consecutive_bad_samples': r[8],
                'recovery_good_samples': r[9], 'decision_cooldown_seconds': r[10],
                'require_manual_authorization': r[11], 'enabled': r[12],
            },
            'members': member_map.get(str(r[0]), []),
            'recent_decisions': decision_map.get(str(r[0]), [])[:20],
        } for r in groups],
    }


@app.patch('/v1/incidents/{incident_id}/state')
async def set_incident_state(
    incident_id: uuid.UUID,
    item: IncidentStateUpdate,
    actor: dict = Depends(require_permission('noc.operate')),
    conn=Depends(db),
):
    resolved_sql = ",resolved_at=now()" if item.state == 'resolved' else ",resolved_at=NULL"
    row = conn.execute(
        f"UPDATE noc_incidents SET state=%s,updated_at=now(){resolved_sql} WHERE id=%s RETURNING id",
        (item.state, incident_id),
    ).fetchone()
    if not row:
        raise HTTPException(404, 'Incident not found')
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'incident.state','noc_incident',%s,'noc',%s::jsonb)""",
        (actor.get('sub'), str(incident_id), psycopg.types.json.Jsonb({'state': item.state})),
    )
    conn.commit()
    await publish('noc.incident.state', {'incident_id': str(incident_id), 'state': item.state})
    return {'id': str(incident_id), 'state': item.state}


@app.post('/v1/redundancy/groups', status_code=201)
async def create_redundancy_group(
    item: RedundancyGroupCreate,
    actor: dict = Depends(require_permission('noc.operate')),
    conn=Depends(db),
):
    row = conn.execute(
        """INSERT INTO redundancy_groups(channel_id,name,mode)
           VALUES(%s,%s,%s) RETURNING id,created_at""",
        (item.channel_id, item.name, item.mode),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'redundancy.group.create','redundancy_group',%s,'noc',%s::jsonb)""",
        (actor.get('sub'), str(row[0]), psycopg.types.json.Jsonb({'mode': item.mode})),
    )
    conn.commit()
    await publish('noc.redundancy.group.created', {'group_id': str(row[0]), 'mode': item.mode})
    return {'id': str(row[0]), 'created_at': row[1], 'production_switching': False}


@app.post('/v1/redundancy/groups/{group_id}/members', status_code=201)
async def add_redundancy_member(
    group_id: uuid.UUID,
    item: RedundancyMemberCreate,
    actor: dict = Depends(require_permission('noc.operate')),
    conn=Depends(db),
):
    if not conn.execute('SELECT 1 FROM redundancy_groups WHERE id=%s', (group_id,)).fetchone():
        raise HTTPException(404, 'Redundancy group not found')
    if not conn.execute('SELECT 1 FROM monitoring_targets WHERE id=%s AND enabled=TRUE', (item.target_id,)).fetchone():
        raise HTTPException(404, 'Monitoring target not found or disabled')
    row = conn.execute(
        """INSERT INTO redundancy_members(group_id,target_id,priority,role,eligible)
           VALUES(%s,%s,%s,%s,%s)
           ON CONFLICT(group_id,target_id) DO UPDATE SET priority=EXCLUDED.priority,role=EXCLUDED.role,eligible=EXCLUDED.eligible
           RETURNING id""",
        (group_id, item.target_id, item.priority, item.role, item.eligible),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'redundancy.member.upsert','redundancy_group',%s,'noc',%s::jsonb)""",
        (actor.get('sub'), str(group_id), psycopg.types.json.Jsonb({'target_id': str(item.target_id), 'role': item.role})),
    )
    conn.commit()
    return {'id': str(row[0]), 'group_id': str(group_id), 'target_id': str(item.target_id)}


@app.post('/v1/redundancy/groups/{group_id}/policy', status_code=201)
async def create_failover_policy(
    group_id: uuid.UUID,
    item: FailoverPolicyCreate,
    actor: dict = Depends(require_permission('noc.operate')),
    conn=Depends(db),
):
    if not conn.execute('SELECT 1 FROM redundancy_groups WHERE id=%s', (group_id,)).fetchone():
        raise HTTPException(404, 'Redundancy group not found')
    conn.execute('UPDATE failover_policies SET enabled=FALSE WHERE group_id=%s AND enabled=TRUE', (group_id,))
    row = conn.execute(
        """INSERT INTO failover_policies(
               group_id,name,consecutive_bad_samples,recovery_good_samples,decision_cooldown_seconds,require_manual_authorization)
           VALUES(%s,%s,%s,%s,%s,%s) RETURNING id,created_at""",
        (group_id, item.name, item.consecutive_bad_samples, item.recovery_good_samples,
         item.decision_cooldown_seconds, item.require_manual_authorization),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'failover.policy.create','redundancy_group',%s,'noc',%s::jsonb)""",
        (actor.get('sub'), str(group_id), psycopg.types.json.Jsonb({
            'policy_id': str(row[0]), 'manual_authorization': item.require_manual_authorization,
        })),
    )
    conn.commit()
    await publish('noc.failover.policy.created', {'group_id': str(group_id), 'policy_id': str(row[0])})
    return {'id': str(row[0]), 'created_at': row[1], 'execution': 'decision-only'}


@app.get('/v1/safety')
def safety(_: dict = Depends(require_permission('noc.read'))):
    return {
        'service': 'noc',
        'production_switching': False,
        'execution_mode': 'decision-only',
        'automatic_failover_execution': False,
        'authorization_executes_switch': False,
        'safe_for_staging': True,
    }
