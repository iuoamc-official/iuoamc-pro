from __future__ import annotations

import time
from dataclasses import dataclass
from typing import Any

import httpx
from fastapi import FastAPI

app = FastAPI(title="IUOAMC Shadow Broadcast Validator", version="0.1.0")

STREAMS = {
    "encoder_a": "http://shadow-hls/a/index.m3u8",
    "encoder_b": "http://shadow-hls/b/index.m3u8",
}


@dataclass
class Check:
    ok: bool
    status: str
    detail: dict[str, Any]


async def check_manifest(name: str, url: str) -> Check:
    started = time.perf_counter()
    try:
        async with httpx.AsyncClient(timeout=3.0) as client:
            r = await client.get(url, headers={"Cache-Control": "no-cache"})
        elapsed_ms = round((time.perf_counter() - started) * 1000, 2)
        text = r.text if r.status_code == 200 else ""
        has_header = text.startswith("#EXTM3U")
        segments = [line.strip() for line in text.splitlines() if line.strip().endswith(".ts")]
        ok = r.status_code == 200 and has_header and len(segments) >= 2
        return Check(
            ok=ok,
            status="healthy" if ok else "degraded",
            detail={
                "name": name,
                "http_status": r.status_code,
                "latency_ms": elapsed_ms,
                "segment_count": len(segments),
                "latest_segment": segments[-1] if segments else None,
                "manifest_bytes": len(r.content),
            },
        )
    except Exception as exc:
        return Check(False, "down", {"name": name, "error": type(exc).__name__})


@app.get("/health")
async def health():
    return {"service": "shadow-validator", "status": "ok", "production_connected": False}


@app.get("/v1/validate")
async def validate():
    checks = {name: await check_manifest(name, url) for name, url in STREAMS.items()}
    healthy = [name for name, result in checks.items() if result.ok]
    overall = "healthy" if len(healthy) == len(checks) else ("degraded" if healthy else "down")
    return {
        "mode": "isolated_shadow",
        "overall": overall,
        "healthy_encoders": healthy,
        "checks": {name: {"ok": r.ok, "status": r.status, **r.detail} for name, r in checks.items()},
        "production_outputs": [],
    }
