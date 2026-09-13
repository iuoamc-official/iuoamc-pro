from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_webrtc_services_are_local_only():
    compose = (ROOT / "compose.yaml").read_text()
    assert '127.0.0.1:58105:8105' in compose
    assert '127.0.0.1:58106:8106' in compose


def test_no_production_media_endpoints_in_webrtc_phase():
    paths = [
        ROOT / "services/media-router/app/main.py",
        ROOT / "services/iso-recorder/app/main.py",
        ROOT / "docs/WEBRTC-MEDIA-v1.md",
    ]
    text = "\n".join(p.read_text() for p in paths)
    forbidden = [
        "a.rtmp.youtube.com",
        "live-api-s.facebook.com",
        "rtmp://",
        "rtmps://",
        "platform-iuoamc.uk/tv?encoder=1",
    ]
    for marker in forbidden:
        assert marker not in text


def test_iso_worker_does_not_execute_media_tools():
    text = (ROOT / "services/iso-recorder/app/main.py").read_text()
    for marker in ("subprocess", "os.system", "ffmpeg", "gst-launch", "docker exec"):
        assert marker not in text.lower()


def test_media_router_is_provider_neutral():
    text = (ROOT / "services/media-router/app/main.py").read_text()
    assert 'provider: str' in text
    assert 'studio.track.published' in text
    assert 'studio.active_speaker.changed' in text


def test_schema_supports_track_and_recording_state():
    text = (ROOT / "infra/postgres/init/005_webrtc_media.sql").read_text()
    assert "CREATE TABLE IF NOT EXISTS media_tracks" in text
    assert "CREATE TABLE IF NOT EXISTS iso_recording_jobs" in text
    assert "screen_share_sessions" in text
    assert "active_speaker_events" in text
