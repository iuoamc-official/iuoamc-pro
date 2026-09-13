from __future__ import annotations

import json
import os
import secrets
import time

import jwt
import redis
from fastapi import Cookie, FastAPI, Header, HTTPException, Response, status
from pydantic import BaseModel, Field

app = FastAPI(title="IUOAMC Operator Session Broker", version="0.31.0")

COOKIE_NAME = "iuoamc_nexus_session"
SESSION_PREFIX = "nexus:operator-session:"
DEFAULT_TTL = int(os.getenv("OPERATOR_SESSION_TTL_SECONDS", "1800"))


def redis_client() -> redis.Redis:
    return redis.Redis.from_url(os.environ["REDIS_URL"], decode_responses=True)


def decode_operator_token(token: str) -> dict:
    try:
        return jwt.decode(
            token,
            os.environ["JWT_SECRET"],
            algorithms=["HS256"],
            issuer=os.environ["JWT_ISSUER"],
            audience=os.environ["JWT_AUDIENCE"],
            options={"require": ["exp", "iat", "sub", "iss", "aud"]},
        )
    except jwt.PyJWTError as exc:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid operator token") from exc


def session_record(session_id: str | None) -> dict:
    if not session_id:
        raise HTTPException(status_code=401, detail="Operator session required")
    raw = redis_client().get(SESSION_PREFIX + session_id)
    if not raw:
        raise HTTPException(status_code=401, detail="Operator session expired")
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise HTTPException(status_code=401, detail="Invalid operator session") from exc
    payload = decode_operator_token(data["token"])
    data["payload"] = payload
    return data


class SessionExchange(BaseModel):
    token: str = Field(min_length=20, max_length=8192)


@app.get("/health")
def health():
    try:
        redis_client().ping()
        redis_state = "ok"
    except Exception:
        redis_state = "down"
    return {
        "service": "operator-auth",
        "status": "ok" if redis_state == "ok" else "degraded",
        "redis": redis_state,
        "cookie": {"http_only": True, "same_site": "strict"},
        "production_switching": False,
    }


@app.post("/v1/session/exchange")
def exchange(item: SessionExchange, response: Response):
    payload = decode_operator_token(item.token)
    now = int(time.time())
    exp = int(payload["exp"])
    ttl = max(1, min(DEFAULT_TTL, exp - now))
    if ttl <= 1:
        raise HTTPException(status_code=401, detail="Operator token expired")

    session_id = secrets.token_urlsafe(32)
    record = {
        "token": item.token,
        "sub": payload.get("sub"),
        "permissions": payload.get("permissions", []),
        "exp": exp,
    }
    redis_client().setex(SESSION_PREFIX + session_id, ttl, json.dumps(record, separators=(",", ":")))

    secure_cookie = os.getenv("SESSION_COOKIE_SECURE", "false").lower() == "true"
    response.set_cookie(
        key=COOKIE_NAME,
        value=session_id,
        max_age=ttl,
        httponly=True,
        secure=secure_cookie,
        samesite="strict",
        path="/",
    )
    return {
        "authenticated": True,
        "subject": payload.get("sub"),
        "permissions": payload.get("permissions", []),
        "expires_in": ttl,
        "token_exposed_to_browser_after_exchange": False,
    }


@app.get("/v1/session")
def current_session(iuoamc_nexus_session: str | None = Cookie(default=None)):
    data = session_record(iuoamc_nexus_session)
    return {
        "authenticated": True,
        "subject": data.get("sub"),
        "permissions": data.get("permissions", []),
        "expires_at": data.get("exp"),
    }


@app.get("/v1/session/validate")
def validate_session(
    response: Response,
    iuoamc_nexus_session: str | None = Cookie(default=None),
    x_original_uri: str | None = Header(default=None),
):
    data = session_record(iuoamc_nexus_session)
    response.headers["Authorization"] = "Bearer " + data["token"]
    response.headers["X-IUOAMC-Operator"] = str(data.get("sub") or "operator")
    response.headers["X-IUOAMC-Original-URI"] = x_original_uri or ""
    response.status_code = 204
    return response


@app.post("/v1/session/logout", status_code=204)
def logout(response: Response, iuoamc_nexus_session: str | None = Cookie(default=None)):
    if iuoamc_nexus_session:
        redis_client().delete(SESSION_PREFIX + iuoamc_nexus_session)
    response.delete_cookie(COOKIE_NAME, path="/", samesite="strict")
    response.status_code = 204
    return response
