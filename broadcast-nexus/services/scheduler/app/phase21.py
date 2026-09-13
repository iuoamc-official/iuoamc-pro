from __future__ import annotations

import uuid
from datetime import date

import psycopg
from fastapi import Depends, HTTPException
from pydantic import BaseModel, Field

from app.main import app, db
from packages.auth.security import require_permission


class EpisodeCreate(BaseModel):
    title: str = Field(min_length=1, max_length=200)
    episode_number: int | None = Field(default=None, ge=1)
    description: str | None = None
    duration_ms: int | None = Field(default=None, ge=1)


class RundownCreate(BaseModel):
    channel_id: uuid.UUID
    name: str = Field(min_length=1, max_length=200)
    scheduled_date: date


@app.get('/v1/channels/{channel_id}/programs')
def list_programs(channel_id: uuid.UUID, _: dict = Depends(require_permission('broadcast.read')), conn=Depends(db)):
    rows = conn.execute(
        "SELECT id,slug,title,description,category,default_duration_ms,status,created_at,updated_at FROM programs WHERE channel_id=%s ORDER BY created_at DESC",
        (channel_id,),
    ).fetchall()
    return [dict(id=str(r[0]),slug=r[1],title=r[2],description=r[3],category=r[4],default_duration_ms=r[5],status=r[6],created_at=r[7],updated_at=r[8]) for r in rows]


@app.post('/v1/programs/{program_id}/episodes', status_code=201)
def create_episode(program_id: uuid.UUID, item: EpisodeCreate, _: dict = Depends(require_permission('broadcast.write')), conn=Depends(db)):
    if not conn.execute('SELECT 1 FROM programs WHERE id=%s', (program_id,)).fetchone():
        raise HTTPException(404, 'Program not found')
    row = conn.execute(
        "INSERT INTO episodes(program_id,episode_number,title,description,duration_ms) VALUES(%s,%s,%s,%s,%s) RETURNING id,status,created_at",
        (program_id,item.episode_number,item.title,item.description,item.duration_ms),
    ).fetchone()
    conn.commit()
    return {'id':str(row[0]),'status':row[1],'created_at':row[2]}


@app.get('/v1/programs/{program_id}/episodes')
def list_episodes(program_id: uuid.UUID, _: dict = Depends(require_permission('broadcast.read')), conn=Depends(db)):
    rows = conn.execute(
        "SELECT id,episode_number,title,description,duration_ms,status,created_at FROM episodes WHERE program_id=%s ORDER BY COALESCE(episode_number,2147483647),created_at",
        (program_id,),
    ).fetchall()
    return [dict(id=str(r[0]),episode_number=r[1],title=r[2],description=r[3],duration_ms=r[4],status=r[5],created_at=r[6]) for r in rows]


@app.get('/v1/channels/{channel_id}/playlists')
def list_playlists(channel_id: uuid.UUID, _: dict = Depends(require_permission('broadcast.read')), conn=Depends(db)):
    rows = conn.execute(
        "SELECT id,name,status,loop_enabled,created_at,updated_at FROM playlists WHERE channel_id=%s ORDER BY created_at DESC",
        (channel_id,),
    ).fetchall()
    return [dict(id=str(r[0]),name=r[1],status=r[2],loop_enabled=r[3],created_at=r[4],updated_at=r[5]) for r in rows]


@app.post('/v1/rundowns', status_code=201)
def create_rundown(item: RundownCreate, _: dict = Depends(require_permission('broadcast.write')), conn=Depends(db)):
    if not conn.execute('SELECT 1 FROM channels WHERE id=%s', (item.channel_id,)).fetchone():
        raise HTTPException(404, 'Channel not found')
    row = conn.execute(
        "INSERT INTO rundowns(channel_id,name,scheduled_date) VALUES(%s,%s,%s) RETURNING id,state,version,created_at",
        (item.channel_id,item.name,item.scheduled_date),
    ).fetchone()
    conn.commit()
    return {'id':str(row[0]),'state':row[1],'version':row[2],'created_at':row[3]}


@app.get('/v1/channels/{channel_id}/rundowns')
def list_rundowns(channel_id: uuid.UUID, _: dict = Depends(require_permission('broadcast.read')), conn=Depends(db)):
    rows = conn.execute(
        "SELECT id,name,scheduled_date,state,version,created_at,updated_at FROM rundowns WHERE channel_id=%s ORDER BY scheduled_date DESC,version DESC",
        (channel_id,),
    ).fetchall()
    return [dict(id=str(r[0]),name=r[1],scheduled_date=r[2],state=r[3],version=r[4],created_at=r[5],updated_at=r[6]) for r in rows]


@app.get('/v1/channels/{channel_id}/epg')
def list_epg(channel_id: uuid.UUID, _: dict = Depends(require_permission('broadcast.read')), conn=Depends(db)):
    rows = conn.execute(
        "SELECT id,schedule_slot_id,title,subtitle,category,starts_at,ends_at,language,published FROM epg_events WHERE channel_id=%s ORDER BY starts_at DESC LIMIT 300",
        (channel_id,),
    ).fetchall()
    return [dict(id=str(r[0]),schedule_slot_id=str(r[1]) if r[1] else None,title=r[2],subtitle=r[3],category=r[4],starts_at=r[5],ends_at=r[6],language=r[7],published=r[8]) for r in rows]


@app.post('/v1/epg/{event_id}/publish')
def publish_epg(event_id: uuid.UUID, _: dict = Depends(require_permission('broadcast.write')), conn=Depends(db)):
    row = conn.execute("UPDATE epg_events SET published=TRUE,updated_at=now() WHERE id=%s RETURNING id", (event_id,)).fetchone()
    if not row:
        raise HTTPException(404, 'EPG event not found')
    conn.commit()
    return {'id':str(event_id),'published':True,'public_output':False}
