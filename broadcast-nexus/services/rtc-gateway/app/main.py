from __future__ import annotations
import hashlib, os, secrets, uuid
from datetime import datetime, timedelta, timezone
import psycopg
from fastapi import FastAPI, Depends, HTTPException, Request
from pydantic import BaseModel, Field
from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC RTC Gateway", version="0.1.0")


def db():
    url = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(url) as conn:
        yield conn


def sha256_text(value: str) -> str:
    return hashlib.sha256(value.encode()).hexdigest()


class GrantCreate(BaseModel):
    studio_room_id: uuid.UUID
    participant_id: uuid.UUID | None = None
    role: str = Field(default="guest", pattern=r"^(director|host|cohost|guest|producer|observer)$")
    can_publish_audio: bool = True
    can_publish_video: bool = True
    can_share_screen: bool = False
    can_subscribe: bool = True
    ttl_seconds: int = Field(default=900, ge=60, le=86400)
    max_uses: int = Field(default=1, ge=1, le=10)


class GrantExchange(BaseModel):
    token: str = Field(min_length=32, max_length=256)
    user_agent: str | None = Field(default=None, max_length=1024)


class QualitySample(BaseModel):
    rtt_ms: float | None = Field(default=None, ge=0, le=60000)
    jitter_ms: float | None = Field(default=None, ge=0, le=60000)
    packet_loss_pct: float | None = Field(default=None, ge=0, le=100)
    uplink_bitrate_bps: int | None = Field(default=None, ge=0)
    downlink_bitrate_bps: int | None = Field(default=None, ge=0)
    quality_score: int | None = Field(default=None, ge=0, le=5)


@app.get("/health")
def health():
    return {"service": "rtc-gateway", "status": "ok", "phase": "webrtc-core"}


@app.post("/v1/grants", status_code=201)
async def create_grant(
    item: GrantCreate,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    room = conn.execute("SELECT 1 FROM studio_rooms WHERE id=%s", (item.studio_room_id,)).fetchone()
    if not room:
        raise HTTPException(404, "Studio room not found")
    if item.participant_id:
        participant = conn.execute(
            "SELECT 1 FROM studio_participants WHERE id=%s AND studio_room_id=%s",
            (item.participant_id, item.studio_room_id),
        ).fetchone()
        if not participant:
            raise HTTPException(404, "Participant not found in studio room")
    raw = secrets.token_urlsafe(32)
    token_hash = sha256_text(raw)
    expires_at = datetime.now(timezone.utc) + timedelta(seconds=item.ttl_seconds)
    row = conn.execute(
        """INSERT INTO rtc_access_grants(
               studio_room_id,participant_id,token_hash,role,can_publish_audio,can_publish_video,
               can_share_screen,can_subscribe,max_uses,expires_at,created_by_label)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
           RETURNING id,created_at""",
        (item.studio_room_id, item.participant_id, token_hash, item.role,
         item.can_publish_audio, item.can_publish_video, item.can_share_screen,
         item.can_subscribe, item.max_uses, expires_at, actor.get("sub")),
    ).fetchone()
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'rtc.grant.create','rtc_access_grant',%s,'rtc-gateway',jsonb_build_object('room_id',%s,'role',%s))""",
        (actor.get("sub"), str(row[0]), str(item.studio_room_id), item.role),
    )
    conn.commit()
    await publish("studio.rtc.grant_created", {"grant_id": str(row[0]), "studio_room_id": str(item.studio_room_id)})
    return {"grant_id": str(row[0]), "token": raw, "expires_at": expires_at, "created_at": row[1]}


@app.post("/v1/grants/exchange")
async def exchange_grant(item: GrantExchange, request: Request, conn = Depends(db)):
    token_hash = sha256_text(item.token)
    row = conn.execute(
        """SELECT id,studio_room_id,participant_id,role,can_publish_audio,can_publish_video,
                  can_share_screen,can_subscribe,max_uses,use_count,expires_at,revoked_at
           FROM rtc_access_grants WHERE token_hash=%s FOR UPDATE""",
        (token_hash,),
    ).fetchone()
    if not row:
        raise HTTPException(401, "Invalid join token")
    now = datetime.now(timezone.utc)
    if row[11] is not None or row[10] <= now or row[9] >= row[8]:
        raise HTTPException(401, "Join token expired, revoked, or exhausted")
    connection_id = "RTC-" + secrets.token_hex(12).upper()
    ua_hash = sha256_text(item.user_agent or request.headers.get("user-agent", ""))
    ip = request.client.host if request.client else None
    session = conn.execute(
        """INSERT INTO rtc_gateway_sessions(access_grant_id,connection_id,state,client_ip,user_agent_hash)
           VALUES(%s,%s,'authorized',%s,%s) RETURNING id""",
        (row[0], connection_id, ip, ua_hash),
    ).fetchone()
    conn.execute("UPDATE rtc_access_grants SET use_count=use_count+1 WHERE id=%s", (row[0],))
    conn.commit()
    await publish("studio.rtc.authorized", {"gateway_session_id": str(session[0]), "studio_room_id": str(row[1])})
    return {
        "gateway_session_id": str(session[0]),
        "connection_id": connection_id,
        "studio_room_id": str(row[1]),
        "participant_id": str(row[2]) if row[2] else None,
        "role": row[3],
        "permissions": {
            "publish_audio": row[4], "publish_video": row[5],
            "share_screen": row[6], "subscribe": row[7],
        },
        "provider": {"type": "adapter", "endpoint": None, "ice": []},
    }


@app.post("/v1/sessions/{session_id}/quality", status_code=201)
async def quality_sample(
    session_id: uuid.UUID,
    item: QualitySample,
    conn = Depends(db),
):
    exists = conn.execute("SELECT 1 FROM rtc_gateway_sessions WHERE id=%s", (session_id,)).fetchone()
    if not exists:
        raise HTTPException(404, "RTC gateway session not found")
    row = conn.execute(
        """INSERT INTO rtc_quality_samples(
               gateway_session_id,rtt_ms,jitter_ms,packet_loss_pct,uplink_bitrate_bps,downlink_bitrate_bps,quality_score)
           VALUES(%s,%s,%s,%s,%s,%s,%s) RETURNING id,sampled_at""",
        (session_id, item.rtt_ms, item.jitter_ms, item.packet_loss_pct,
         item.uplink_bitrate_bps, item.downlink_bitrate_bps, item.quality_score),
    ).fetchone()
    conn.commit()
    await publish("studio.rtc.quality", {"gateway_session_id": str(session_id), "quality_score": item.quality_score})
    return {"id": row[0], "sampled_at": row[1]}


@app.post("/v1/grants/{grant_id}/revoke")
async def revoke_grant(
    grant_id: uuid.UUID,
    actor: dict = Depends(require_permission("broadcast.write")),
    conn = Depends(db),
):
    row = conn.execute(
        "UPDATE rtc_access_grants SET revoked_at=COALESCE(revoked_at,now()) WHERE id=%s RETURNING studio_room_id,revoked_at",
        (grant_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Grant not found")
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'rtc.grant.revoke','rtc_access_grant',%s,'rtc-gateway','{}'::jsonb)""",
        (actor.get("sub"), str(grant_id)),
    )
    conn.commit()
    await publish("studio.rtc.grant_revoked", {"grant_id": str(grant_id), "studio_room_id": str(row[0])})
    return {"id": str(grant_id), "revoked_at": row[1]}
