from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_shadow_bridge_has_no_production_destinations():
    text = read("services/shadow-noc-bridge/app/main.py").lower()
    for forbidden in ("rtmp://", "rtmps://", "youtube.com/live", "platform-iuoamc.uk"):
        assert forbidden not in text


def test_shadow_failover_endpoint_requires_secret_and_mode():
    text = read("services/failover/app/main.py")
    assert "SHADOW_MODE" in text
    assert "SHADOW_LAB_KEY" in text
    assert "X-Shadow-Lab-Key" in text
    assert "hmac.compare_digest" in text
    assert '"production_switching":False' in text or '"production_switching": False' in text


def test_e2e_only_stops_shadow_encoder():
    text = read("scripts/shadow-e2e-test.sh")
    assert "stop shadow-encoder-a" in text
    assert "systemctl" not in text
    assert "mca-tv-youtube" not in text
    assert "platform-iuoamc.uk" not in text


def test_stale_hls_detection_present():
    text = read("services/shadow-validator/app/main.py")
    assert "STALE_AFTER_SECONDS" in text
    assert "stale_for_seconds" in text
    assert "LAST_PROGRESS" in text
