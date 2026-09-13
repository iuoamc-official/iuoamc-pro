from __future__ import annotations

import hashlib
import hmac
import json
import os
import secrets
import time

import jwt
import redis
from fastapi import Cookie, FastAPI, Header, HTTPException, Response, status
from pydantic import BaseModel, Field

app = FastAPI(title="IUOAMC Operator Session Broker", version="0.32.0")

COOKIE_NAME = "iuoamc_nexus_session"
SESSION_PREFIX = "nexus:operator-session:"
ACCESS_FAILURE_KEY = "nexus:operator-access:failures"
DEFAULT_TTL = int(os.getenv("OPERATOR_SESSION_TTL_SECONDS", "1800"))
ACCESS_TTL = int(os.getenv("OPERATOR_ACCESS_SESSION_TTL_SECONDS", "43200"))
ACCESS_MAX_FAILURES = int(os.getenv("OPERATOR_ACCESS_MAX_FAILURES", "5"))
ACCESS_LOCK_SECONDS = int(os.getenv("OPERATOR_ACCESS_LOCK_SECONDS", "300"))


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


def set_session_cookie(response: Response, token: str, payload: dict, ttl: int) -> dict:
    session_id = secrets.token_urlsafe(32)
    record = {
        "token": token,
        "sub": payload.get("sub"),
        "permissions": payload.get("permissions", []),
        "exp": int(payload["exp"]),
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


class SessionExchange(BaseModel):
    token: str = Field(min_length=20, max_length=8192)


class AccessCodeExchange(BaseModel):
    access_code: str = Field(min_length=20, max_length=256)


@app.get("/health")
def health():
    try:
        redis_client().ping()
        redis_state = "ok"
    except Exception:
        redis_state = "down"
    access_configured = bool(os.getenv("OPERATOR_ACCESS_CODE_SHA256", "").strip())
    return {
        "service": "operator-auth",
        "status": "ok" if redis_state == "ok" else "degraded",
        "redis": redis_state,
        "cookie": {"http_only": True, "same_site": "strict"},
        "access_code_configured": access_configured,
        "production_switching": False,
    }


@app.post("/v1/session/exchange")
def exchange(item: SessionExchange, response: Response):
    payload = decode_operator_token(item.token)
    now = int(time.time())
    exp = int(payload["exp"])
    ttl = min(DEFAULT_TTL, exp - now)
    if ttl <= 1:
        raise HTTPException(status_code=401, detail="Operator token expired")
    return set_session_cookie(response, item.token, payload, ttl)


@app.post("/v1/session/access-code")
def exchange_access_code(item: AccessCodeExchange, response: Response):
    expected = os.getenv("OPERATOR_ACCESS_CODE_SHA256", "").strip().lower()
    if not expected:
        raise HTTPException(status_code=503, detail="Permanent access code is not configured")

    r = redis_client()
    failures = int(r.get(ACCESS_FAILURE_KEY) or "0")
    if failures >= ACCESS_MAX_FAILURES:
        ttl = r.ttl(ACCESS_FAILURE_KEY)
        raise HTTPException(status_code=429, detail=f"Access temporarily locked. Try again in {max(ttl, 1)} seconds")

    supplied = hashlib.sha256(item.access_code.encode("utf-8")).hexdigest()
    if not hmac.compare_digest(supplied, expected):
        failures = r.incr(ACCESS_FAILURE_KEY)
        if failures == 1:
            r.expire(ACCESS_FAILURE_KEY, ACCESS_LOCK_SECONDS)
        raise HTTPException(status_code=401, detail="Invalid operator access code")

    r.delete(ACCESS_FAILURE_KEY)
    now = int(time.time())
    ttl = max(300, ACCESS_TTL)
    payload = {
        "sub": "staging-admin",
        "permissions": ["*"],
        "iat": now,
        "exp": now + ttl,
        "iss": os.environ["JWT_ISSUER"],
        "aud": os.environ["JWT_AUDIENCE"],
    }
    token = jwt.encode(payload, os.environ["JWT_SECRET"], algorithm="HS256")
    return set_session_cookie(response, token, payload, ttl)


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
