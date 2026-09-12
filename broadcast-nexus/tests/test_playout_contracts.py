from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_playout_service_exists():
    app = ROOT / "services/playout/app/main.py"
    text = app.read_text()
    assert "def canonical_hash" in text
    assert '"/v1/compile"' in text
    assert '"/v1/fallback/resolve"' in text
    assert '"/v1/epg/generate"' in text


def test_playout_has_no_production_outputs():
    text = (ROOT / "services/playout/app/main.py").read_text().lower()
    forbidden = [
        "rtmp://",
        "rtmps://",
        "youtube.com/live",
        "a.rtmp.youtube.com",
        "facebook.com",
        "platform-iuoamc.uk",
    ]
    for item in forbidden:
        assert item not in text


def test_playout_port_is_loopback_only():
    text = (ROOT / "compose.yaml").read_text()
    assert '127.0.0.1:58103:8103' in text


def test_playout_schema_has_queue_hash_and_fallbacks():
    text = (ROOT / "infra/postgres/init/003_playout.sql").read_text()
    assert "queue_hash TEXT NOT NULL" in text
    assert "CREATE TABLE IF NOT EXISTS fallback_policies" in text
    assert "CREATE TABLE IF NOT EXISTS playout_events" in text
