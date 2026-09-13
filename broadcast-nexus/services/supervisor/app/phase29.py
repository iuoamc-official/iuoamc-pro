from __future__ import annotations

import os

from fastapi import Depends

from app.main import app, db
from packages.auth.security import require_permission


@app.get('/v1/runtimes')
def list_runtimes(
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT c.id,c.slug,c.name,c.status,
                  r.desired_state,r.actual_state,r.source_mode,r.active_program_source,
                  r.active_encoder,r.active_distribution,r.health_state,r.recovery_state,
                  r.generation,r.last_transition_at,r.updated_at
           FROM channels c
           LEFT JOIN channel_runtime_states r ON r.channel_id=c.id
           ORDER BY c.name,c.slug"""
    ).fetchall()
    payload=[]
    for r in rows:
        services=[]
        if r[4] is not None:
            svc_rows=conn.execute(
                """SELECT service_name,service_role,state,healthy,last_seen_at
                   FROM supervisor_service_states WHERE channel_id=%s
                   ORDER BY service_name,service_role""",
                (r[0],),
            ).fetchall()
            services=[{
                'service_name':s[0],'service_role':s[1],'state':s[2],
                'healthy':s[3],'last_seen_at':s[4],
            } for s in svc_rows]
        payload.append({
            'channel_id':str(r[0]),'slug':r[1],'name':r[2],'channel_status':r[3],
            'initialized':r[4] is not None,
            'desired_state':r[4],'actual_state':r[5],'source_mode':r[6],
            'active_program_source':r[7],'active_encoder':r[8],
            'active_distribution':r[9],'health_state':r[10],'recovery_state':r[11],
            'generation':r[12] or 0,'last_transition_at':r[13],'updated_at':r[14],
            'services':services,
        })
    return {
        'production_switching':False,
        'deployment_env':os.getenv('DEPLOYMENT_ENV','development'),
        'channels':payload,
    }


@app.get('/v1/safety')
def safety(_: dict = Depends(require_permission('broadcast.read'))):
    return {
        'service':'broadcast-supervisor',
        'deployment_env':os.getenv('DEPLOYMENT_ENV','development'),
        'production_switching':False,
        'production_outputs_enabled':os.getenv('PRODUCTION_OUTPUTS_ENABLED','false').lower() == 'true',
        'public_publishing_enabled':os.getenv('PUBLIC_PUBLISHING_ENABLED','false').lower() == 'true',
        'transition_execution':'control-plane-only',
        'safe_for_staging':(
            os.getenv('PRODUCTION_SWITCHING','false').lower() != 'true'
            and os.getenv('PRODUCTION_OUTPUTS_ENABLED','false').lower() != 'true'
            and os.getenv('PUBLIC_PUBLISHING_ENABLED','false').lower() != 'true'
        ),
    }
