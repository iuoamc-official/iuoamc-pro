from __future__ import annotations

import asyncio
import os
from datetime import datetime, timezone

import httpx
import psycopg

from app.main import SERVICES, app, probe_one, safety_envelope


async def tcp_probe(host: str, port: int, timeout: float = 0.8) -> dict:
    started = asyncio.get_running_loop().time()
    try:
        reader, writer = await asyncio.wait_for(asyncio.open_connection(host, port), timeout=timeout)
        writer.close()
        await writer.wait_closed()
        return {"status": "up", "latency_ms": round((asyncio.get_running_loop().time() - started) * 1000, 1)}
    except Exception as exc:
        return {"status": "down", "latency_ms": round((asyncio.get_running_loop().time() - started) * 1000, 1), "error": type(exc).__name__}


async def http_probe(url: str, timeout: float = 1.0) -> dict:
    started = asyncio.get_running_loop().time()
    try:
        async with httpx.AsyncClient(timeout=timeout) as client:
            response = await client.get(url)
        return {
            "status": "up" if response.is_success else "degraded",
            "http_status": response.status_code,
            "latency_ms": round((asyncio.get_running_loop().time() - started) * 1000, 1),
        }
    except Exception as exc:
        return {"status": "down", "latency_ms": round((asyncio.get_running_loop().time() - started) * 1000, 1), "error": type(exc).__name__}


def database_snapshot() -> dict:
    dsn = os.getenv("DATABASE_URL", "").replace("postgresql+psycopg://", "postgresql://")
    if not dsn:
        return {"status": "unknown", "reason": "DATABASE_URL not configured"}
    try:
        with psycopg.connect(dsn, connect_timeout=1) as conn:
            encoder = conn.execute(
                "SELECT state,count(*) FROM encoder_nodes GROUP BY state ORDER BY state"
            ).fetchall()
            hls = conn.execute(
                "SELECT state,count(*) FROM hls_manifests GROUP BY state ORDER BY state"
            ).fetchall()
            runtime = conn.execute(
                "SELECT actual_state,count(*) FROM channel_runtime_states GROUP BY actual_state ORDER BY actual_state"
            ).fetchall()
            alerts = conn.execute(
                "SELECT state,count(*) FROM alert_events WHERE state IN ('open','acknowledged') GROUP BY state"
            ).fetchall()
        return {
            "status": "up",
            "encoder_nodes": {r[0]: r[1] for r in encoder},
            "hls_manifests": {r[0]: r[1] for r in hls},
            "channel_runtimes": {r[0]: r[1] for r in runtime},
            "alerts": {r[0]: r[1] for r in alerts},
        }
    except Exception as exc:
        return {"status": "down", "error": type(exc).__name__}


@app.get('/v1/system-health')
async def system_health():
    timeout = httpx.Timeout(1.5, connect=0.75)
    async with httpx.AsyncClient(timeout=timeout) as client:
        service_pairs = await asyncio.gather(*(probe_one(client, name, url) for name, url in SERVICES.items()))
    services = dict(service_pairs)

    postgres_tcp, redis_tcp, nats_tcp, nats_http, minio_http = await asyncio.gather(
        tcp_probe('postgres', 5432),
        tcp_probe('redis', 6379),
        tcp_probe('nats', 4222),
        http_probe('http://nats:8222/healthz'),
        http_probe('http://minio:9000/minio/health/live'),
    )
    db = await asyncio.to_thread(database_snapshot)
    infrastructure = {
        'postgres': {**postgres_tcp, 'database': db},
        'redis': redis_tcp,
        'nats': {'client_port': nats_tcp, 'monitoring': nats_http},
        'minio': minio_http,
    }

    service_counts = {
        'total': len(services),
        'up': sum(1 for x in services.values() if x['status'] == 'up'),
        'degraded': sum(1 for x in services.values() if x['status'] == 'degraded'),
        'down': sum(1 for x in services.values() if x['status'] == 'down'),
    }
    infra_states = []
    for name, item in infrastructure.items():
        if name == 'nats':
            infra_states.append('up' if item['client_port']['status'] == 'up' and item['monitoring']['status'] == 'up' else 'down')
        else:
            infra_states.append(item.get('status', item.get('database', {}).get('status', 'unknown')))

    safety = safety_envelope()
    safe = not safety['production_switching'] and not safety['production_outputs'] and not safety['public_publishing']
    overall = 'healthy'
    if service_counts['down'] or service_counts['degraded'] or any(s != 'up' for s in infra_states) or not safe:
        overall = 'degraded'

    return {
        'generated_at': datetime.now(timezone.utc),
        'phase': 30,
        'overall': overall,
        'service_counts': service_counts,
        'services': services,
        'infrastructure': infrastructure,
        'broadcast_state': db if db.get('status') == 'up' else {},
        'safety': {**safety, 'safe_for_staging': safe},
        'production_execution': False,
    }
