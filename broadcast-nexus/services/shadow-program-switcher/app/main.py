from __future__ import annotations

import asyncio
import os
import time
from pathlib import PurePosixPath

import httpx
from fastapi import FastAPI, HTTPException, Response

app = FastAPI(title="IUOAMC Shadow Program Switcher", version="0.1.0")

VALIDATOR_URL = os.getenv("SHADOW_VALIDATOR_URL", "http://shadow-validator:8117/v1/validate")
SOURCE_BASE = {
    "a": os.getenv("SHADOW_SOURCE_A", "http://shadow-hls/a"),
    "b": os.getenv("SHADOW_SOURCE_B", "http://shadow-hls/b"),
}
CHECK_INTERVAL = float(os.getenv("SHADOW_SWITCH_CHECK_INTERVAL", "2"))
FAILBACK_GOOD_CHECKS = int(os.getenv("SHADOW_FAILBACK_GOOD_CHECKS", "5"))

state = {
    "active": "a",
    "reason": "initial_primary",
    "last_switch_monotonic": time.monotonic(),
    "a_good_streak": 0,
    "last_validation": {},
    "switch_count": 0,
}


def _healthy(check: dict) -> bool:
    return bool(check.get("ok")) and check.get("status") == "healthy"


async def _poll_loop() -> None:
    while True:
        await asyncio.sleep(CHECK_INTERVAL)
        try:
            async with httpx.AsyncClient(timeout=3.0) as client:
                response = await client.get(VALIDATOR_URL, headers={"Cache-Control": "no-cache"})
                response.raise_for_status()
                data = response.json()
            checks = data.get("checks", {})
            a_ok = _healthy(checks.get("encoder_a", {}))
            b_ok = _healthy(checks.get("encoder_b", {}))
            state["last_validation"] = {"a": a_ok, "b": b_ok, "overall": data.get("overall")}

            if state["active"] == "a":
                state["a_good_streak"] = FAILBACK_GOOD_CHECKS if a_ok else 0
                if not a_ok and b_ok:
                    state["active"] = "b"
                    state["reason"] = "primary_a_unhealthy_backup_b_healthy"
                    state["last_switch_monotonic"] = time.monotonic()
                    state["switch_count"] += 1
                    state["a_good_streak"] = 0
            else:
                if a_ok:
                    state["a_good_streak"] += 1
                else:
                    state["a_good_streak"] = 0

                if not b_ok and a_ok:
                    state["active"] = "a"
                    state["reason"] = "backup_b_unhealthy_primary_a_healthy"
                    state["last_switch_monotonic"] = time.monotonic()
                    state["switch_count"] += 1
                    state["a_good_streak"] = FAILBACK_GOOD_CHECKS
                elif a_ok and state["a_good_streak"] >= FAILBACK_GOOD_CHECKS:
                    state["active"] = "a"
                    state["reason"] = "primary_a_recovered_failback"
                    state["last_switch_monotonic"] = time.monotonic()
                    state["switch_count"] += 1
        except Exception as exc:
            state["last_validation"] = {"error": type(exc).__name__}


@app.on_event("startup")
async def startup() -> None:
    if os.getenv("SHADOW_MODE", "false").lower() != "true":
        raise RuntimeError("shadow-program-switcher refuses to start unless SHADOW_MODE=true")
    asyncio.create_task(_poll_loop())


@app.get("/health")
def health():
    return {
        "service": "shadow-program-switcher",
        "status": "ok",
        "mode": "isolated_shadow",
        "production_connected": False,
        "active": state["active"],
    }


@app.get("/v1/state")
def get_state():
    return {
        "mode": "isolated_shadow",
        "production_connected": False,
        "active": state["active"],
        "reason": state["reason"],
        "switch_count": state["switch_count"],
        "a_good_streak": state["a_good_streak"],
        "last_validation": state["last_validation"],
    }


@app.get("/program/index.m3u8")
async def program_manifest():
    source = state["active"]
    url = f"{SOURCE_BASE[source]}/index.m3u8"
    async with httpx.AsyncClient(timeout=3.0) as client:
        response = await client.get(url, headers={"Cache-Control": "no-cache"})
    if response.status_code != 200 or not response.text.startswith("#EXTM3U"):
        raise HTTPException(503, "Active shadow manifest unavailable")

    rewritten = []
    for line in response.text.splitlines():
        stripped = line.strip()
        if stripped and not stripped.startswith("#"):
            name = PurePosixPath(stripped).name
            rewritten.append(f"/program/segments/{name}?source={source}")
        else:
            rewritten.append(line)
    body = "\n".join(rewritten) + "\n"
    return Response(
        content=body,
        media_type="application/vnd.apple.mpegurl",
        headers={
            "Cache-Control": "no-store",
            "X-IUOAMC-Shadow-Active": source,
            "X-IUOAMC-Production-Connected": "false",
        },
    )


@app.get("/program/segments/{segment_name}")
async def program_segment(segment_name: str, source: str):
    if source not in SOURCE_BASE:
        raise HTTPException(400, "Invalid shadow source")
    safe_name = PurePosixPath(segment_name).name
    if safe_name != segment_name or not safe_name.endswith(".ts"):
        raise HTTPException(400, "Invalid segment")
    async with httpx.AsyncClient(timeout=5.0) as client:
        response = await client.get(f"{SOURCE_BASE[source]}/{safe_name}")
    if response.status_code != 200:
        raise HTTPException(404, "Shadow segment unavailable")
    return Response(
        content=response.content,
        media_type="video/mp2t",
        headers={"Cache-Control": "no-store", "X-IUOAMC-Shadow-Source": source},
    )
