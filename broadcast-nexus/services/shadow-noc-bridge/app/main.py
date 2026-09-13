from __future__ import annotations

import asyncio, os, uuid
from datetime import datetime, timezone
import httpx, psycopg

DB = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
VALIDATOR = os.getenv("SHADOW_VALIDATOR_URL", "http://shadow-validator:8117/v1/validate")
FAILOVER = os.getenv("SHADOW_FAILOVER_URL", "http://failover:8113")
LAB_KEY = os.environ["SHADOW_LAB_KEY"]
INTERVAL = int(os.getenv("SHADOW_SAMPLE_INTERVAL", "5"))


def bootstrap(conn):
    targets = {}
    for ref, name in (("shadow-a", "Shadow Encoder A HLS"), ("shadow-b", "Shadow Encoder B HLS")):
        row = conn.execute("SELECT id FROM monitoring_targets WHERE target_ref=%s", (ref,)).fetchone()
        if row:
            tid = row[0]
        else:
            tid = conn.execute(
                """INSERT INTO monitoring_targets(target_type,target_ref,display_name,expected_video,expected_audio)
                   VALUES('iptv',%s,%s,TRUE,TRUE) RETURNING id""", (ref, name)
            ).fetchone()[0]
        targets[ref] = tid

    group = conn.execute("SELECT id FROM redundancy_groups WHERE name='Shadow HLS A/B'").fetchone()
    if group:
        gid = group[0]
    else:
        gid = conn.execute(
            "INSERT INTO redundancy_groups(name,mode,enabled) VALUES('Shadow HLS A/B','active-passive',TRUE) RETURNING id"
        ).fetchone()[0]
        conn.execute(
            "INSERT INTO redundancy_members(group_id,target_id,priority,role,eligible) VALUES(%s,%s,10,'primary',TRUE)",
            (gid, targets['shadow-a'])
        )
        conn.execute(
            "INSERT INTO redundancy_members(group_id,target_id,priority,role,eligible) VALUES(%s,%s,20,'secondary',TRUE)",
            (gid, targets['shadow-b'])
        )
        conn.execute(
            """INSERT INTO failover_policies(group_id,consecutive_bad_samples,recovery_good_samples,
                      decision_cooldown_seconds,require_manual_authorization,enabled)
               VALUES(%s,3,2,20,FALSE,TRUE)""", (gid,)
        )
    conn.commit()
    return targets, gid


def write_sample(conn, target_id, check):
    ok = bool(check.get("ok"))
    status = check.get("status")
    state = "healthy" if ok else ("critical" if status == "down" else "degraded")
    conn.execute(
        """INSERT INTO health_samples(target_id,state,video_present,audio_present,frozen_frame,black_frame,
                  silence_detected,fps,bitrate_kbps,latency_ms,hls_age_seconds,details)
           VALUES(%s,%s,%s,%s,FALSE,FALSE,FALSE,%s,%s,%s,%s,%s::jsonb)""",
        (
            target_id, state, ok, ok,
            25.0 if ok else 0.0,
            2500 if ok else 0,
            int(check.get("latency_ms") or 0),
            0 if ok else 999,
            psycopg.types.json.Jsonb({"source":"shadow-validator", **check}),
        )
    )
    conn.commit()
    return state


async def evaluate(group_id):
    async with httpx.AsyncClient(timeout=4.0) as client:
        r = await client.post(
            f"{FAILOVER}/internal/shadow/groups/{group_id}/evaluate",
            headers={"X-Shadow-Lab-Key": LAB_KEY},
        )
        r.raise_for_status()
        return r.json()


async def run():
    with psycopg.connect(DB) as conn:
        targets, gid = bootstrap(conn)

    while True:
        try:
            async with httpx.AsyncClient(timeout=4.0) as client:
                result = (await client.get(VALIDATOR)).json()
            checks = result.get("checks", {})
            with psycopg.connect(DB) as conn:
                write_sample(conn, targets["shadow-a"], checks.get("encoder_a", {}))
                write_sample(conn, targets["shadow-b"], checks.get("encoder_b", {}))
            decision = await evaluate(gid)
            print({"time":datetime.now(timezone.utc).isoformat(),"overall":result.get("overall"),"decision":decision}, flush=True)
        except Exception as exc:
            print({"bridge_error":type(exc).__name__,"detail":str(exc)[:200]}, flush=True)
        await asyncio.sleep(INTERVAL)


if __name__ == "__main__":
    asyncio.run(run())
