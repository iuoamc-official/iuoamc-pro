from __future__ import annotations

import uuid
from fastapi import Depends, HTTPException
from pydantic import BaseModel

from packages.auth.security import require_permission
from app.main import app, db, room_event


class ParticipantMediaUpdate(BaseModel):
    microphone_enabled: bool | None = None
    camera_enabled: bool | None = None
    screen_share_enabled: bool | None = None


@app.get('/v1/rooms/{room_id}/scenes')
def list_scenes(
    room_id: uuid.UUID,
    _: dict = Depends(require_permission('broadcast.read')),
    conn=Depends(db),
):
    if not conn.execute('SELECT 1 FROM studio_rooms WHERE id=%s', (room_id,)).fetchone():
        raise HTTPException(404, 'Room not found')
    rows = conn.execute(
        '''SELECT id,name,layout_type,is_fallback,canvas,created_at,updated_at
           FROM studio_scenes WHERE room_id=%s ORDER BY created_at''',
        (room_id,),
    ).fetchall()
    return [
        {
            'id': str(r[0]), 'name': r[1], 'layout_type': r[2], 'is_fallback': r[3],
            'canvas': r[4], 'created_at': r[5], 'updated_at': r[6],
        }
        for r in rows
    ]


@app.patch('/v1/rooms/{room_id}/participants/{participant_id}/media')
def update_participant_media(
    room_id: uuid.UUID,
    participant_id: uuid.UUID,
    item: ParticipantMediaUpdate,
    actor: dict = Depends(require_permission('broadcast.write')),
    conn=Depends(db),
):
    current = conn.execute(
        '''SELECT microphone_enabled,camera_enabled,screen_share_enabled
           FROM studio_participants WHERE id=%s AND room_id=%s FOR UPDATE''',
        (participant_id, room_id),
    ).fetchone()
    if not current:
        raise HTTPException(404, 'Participant not found')
    mic = current[0] if item.microphone_enabled is None else item.microphone_enabled
    cam = current[1] if item.camera_enabled is None else item.camera_enabled
    screen = current[2] if item.screen_share_enabled is None else item.screen_share_enabled
    conn.execute(
        '''UPDATE studio_participants
           SET microphone_enabled=%s,camera_enabled=%s,screen_share_enabled=%s,updated_at=now()
           WHERE id=%s AND room_id=%s''',
        (mic, cam, screen, participant_id, room_id),
    )
    room_event(conn, room_id, 'participant.media.changed', actor.get('sub'))
    conn.commit()
    return {
        'participant_id': str(participant_id),
        'microphone': mic,
        'camera': cam,
        'screen_share': screen,
        'production_output': False,
    }
