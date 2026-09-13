from __future__ import annotations

import asyncio
import os
import time
from datetime import datetime, timezone

import httpx
import psycopg
from fastapi import FastAPI

app = FastAPI(title="IUOAMC Broadcast Nexus Dashboard API", version="0.19.0")

SERVICES = {
    "orchestrator": "http://orchestrator:8100/health",
    "media_library": "http://media-library:8101/health",
    "scheduler": "http://scheduler:8102/health",
    "playout": "http://playout:8103/health",
    "live_studio": "http://live-studio:8104/health",
    "media_router": "http://media-router:8105/health",
    "iso_recorder": "http://iso-recorder:8106/health",
    "rtc_gateway": "http://rtc-gateway:8107/health",
    "encoder": "http://encoder:8108/health",
    "distribution": "http://distribution:8109/health",
    "iptv": "http://iptv:8110/health",
    "monitoring": "http://monitoring:8111/health",
    "alerts": "http://alerts:8112/health",
    "failover": "http://failover:8113/health",
    "noc": "http://noc:8114/health",
    "control_room": "http://control-room:8115/health",
    "supervisor": "http://supervisor:8120/health",
}


def env_false(name: str) -> bool:
    return os.getenv(name, "false").strip().lower() in {"0", "false", "no", "off", ""}


def safety_envelope() -> dict:
    return {
        "environment": os.getenv("DEPLOYMENT_ENV", "staging"),
        "production_switching": not env_false("PRODUCTION_SWITCHING"),
        "production_outputs": not env_false("PRODUCTION_OUTPUTS_ENABLED"),
        "public_publishing": not env_false("PUBLIC_PUBLISHING_ENABLED"),
        "shadow_mode": os.getenv("SHADOW_MODE", "true").strip().lower() == "true",
    }


def database_url() -> str:
    return os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")


def serialize_slot(row) -> dict | None:
    if not row:
        return None
    return {
        "title": row[0],
        "subtitle": row[1] or "",
        "start": row[2].isoformat() if row[2] else "",
        "end": row[3].isoformat() if row[3] else "",
        "status": row[4] or "",
    }


async def probe_one(client: httpx.AsyncClient, name: str, url: str) -> tuple[str, dict]:
    started = time.perf_counter()
    try:
        response = await client.get(url)
        latency_ms = round((time.perf_counter() - started) * 1000, 1)
        payload = response.json() if response.headers.get("content-type", "").startswith("application/json") else {}
        ok = response.is_success and payload.get("status") == "ok"
        return name, {
            "status": "up" if ok else "degraded",
            "http_status": response.status_code,
            "latency_ms": latency_ms,
            "detail": payload,
        }
    except Exception as exc:
        latency_ms = round((time.perf_counter() - started) * 1000, 1)
        return name, {
            "status": "down",
            "http_status": None,
            "latency_ms": latency_ms,
            "error": type(exc).__name__,
        }


@app.get("/health")
def health():
    safety = safety_envelope()
    safe = not safety["production_switching"] and not safety["production_outputs"] and not safety["public_publishing"]
    return {
        "service": "dashboard-api",
        "status": "ok" if safe else "unsafe",
        "phase": 18,
        "safety": safety,
    }


@app.get("/v1/safety")
def safety():
    envelope = safety_envelope()
    envelope["safe_for_staging"] = (
        not envelope["production_switching"]
        and not envelope["production_outputs"]
        and not envelope["public_publishing"]
    )
    return envelope


@app.get("/v1/summary")
async def summary():
    timeout = httpx.Timeout(1.5, connect=0.75)
    async with httpx.AsyncClient(timeout=timeout) as client:
        results = await asyncio.gather(*(probe_one(client, name, url) for name, url in SERVICES.items()))

    services = dict(results)
    up = sum(1 for item in services.values() if item["status"] == "up")
    degraded = sum(1 for item in services.values() if item["status"] == "degraded")
    down = sum(1 for item in services.values() if item["status"] == "down")
    safety = safety_envelope()
    safe = not safety["production_switching"] and not safety["production_outputs"] and not safety["public_publishing"]

    return {
        "generated_at": datetime.now(timezone.utc),
        "phase": 18,
        "environment": safety["environment"],
        "overall": "healthy" if down == 0 and degraded == 0 and safe else "degraded",
        "counts": {"total": len(services), "up": up, "degraded": degraded, "down": down},
        "safety": {**safety, "safe_for_staging": safe},
        "services": services,
    }


@app.get("/v1/iuoamc-tv/status")
def iuoamc_tv_status():
    slug = os.getenv("IUOAMC_TV_CHANNEL_SLUG", "iuoamc-tv")
    safety = safety_envelope()
    payload = {
        "service": "iuoamc-tv-status",
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "environment": safety["environment"],
        "safe": not safety["production_switching"] and not safety["production_outputs"] and not safety["public_publishing"],
        "channel": None,
        "now": None,
        "next": None,
        "epg": [],
    }

    with psycopg.connect(database_url()) as conn:
        channel = conn.execute(
            "SELECT id,name,status,timezone FROM channels WHERE slug=%s",
            (slug,),
        ).fetchone()

        if not channel:
            payload["state"] = "channel_not_found"
            return payload

        channel_id = channel[0]
        payload["channel"] = {
            "id": str(channel_id),
            "slug": slug,
            "name": channel[1],
            "status": channel[2],
            "timezone": channel[3],
        }

        now_row = conn.execute(
            """SELECT title, COALESCE(metadata->>'subtitle',''), starts_at, ends_at, state
               FROM schedule_slots
               WHERE channel_id=%s
                 AND state NOT IN ('cancelled','completed')
                 AND starts_at <= now() AND ends_at > now()
               ORDER BY priority ASC, starts_at ASC
               LIMIT 1""",
            (channel_id,),
        ).fetchone()

        next_row = conn.execute(
            """SELECT title, COALESCE(metadata->>'subtitle',''), starts_at, ends_at, state
               FROM schedule_slots
               WHERE channel_id=%s
                 AND state NOT IN ('cancelled','completed')
                 AND starts_at > now()
               ORDER BY starts_at ASC, priority ASC
               LIMIT 1""",
            (channel_id,),
        ).fetchone()

        epg_rows = conn.execute(
            """SELECT title, COALESCE(metadata->>'subtitle',''), starts_at, ends_at, state
               FROM schedule_slots
               WHERE channel_id=%s
                 AND state NOT IN ('cancelled','completed')
                 AND ends_at > now() - interval '1 minute'
               ORDER BY starts_at ASC, priority ASC
               LIMIT 6""",
            (channel_id,),
        ).fetchall()

        payload["now"] = serialize_slot(now_row)
        payload["next"] = serialize_slot(next_row)
        payload["epg"] = [serialize_slot(row) for row in epg_rows if row]
        payload["state"] = "online"

    return payload
