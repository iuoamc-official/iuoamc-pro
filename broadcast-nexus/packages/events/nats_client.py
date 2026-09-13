from __future__ import annotations

import json
import os
from typing import Any

import nats


async def publish(subject: str, payload: dict[str, Any]) -> None:
    """Publish an internal event. If NATS is not configured, do nothing.

    This helper is intentionally isolated from the existing IUOAMC application.
    """
    url = os.getenv("NATS_URL")
    if not url:
        return

    nc = await nats.connect(url, connect_timeout=2, max_reconnect_attempts=1)
    try:
        await nc.publish(subject, json.dumps(payload, separators=(",", ":")).encode())
        await nc.flush(timeout=2)
    finally:
        await nc.close()
