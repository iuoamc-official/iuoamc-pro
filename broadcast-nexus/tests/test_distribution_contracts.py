from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_distribution_services_are_local_only():
    text = (ROOT / "compose.yaml").read_text()
    for port in ("58108", "58109", "58110"):
        assert f'127.0.0.1:{port}:' in text


def test_no_production_stream_urls_in_new_services():
    paths = [
        ROOT / "services" / "encoder" / "app" / "main.py",
        ROOT / "services" / "distribution" / "app" / "main.py",
        ROOT / "services" / "iptv" / "app" / "main.py",
    ]
    forbidden = ("rtmp://", "rtmps://", "youtube.com/live2", "a.rtmp.youtube.com")
    for path in paths:
        text = path.read_text().lower()
        for token in forbidden:
            assert token not in text


def test_encoder_does_not_spawn_media_processes():
    text = (ROOT / "services" / "encoder" / "app" / "main.py").read_text().lower()
    for token in ("subprocess", "os.system", "ffmpeg", "gstreamer", "gst-launch"):
        assert token not in text


def test_destinations_default_disabled_in_schema():
    text = (ROOT / "infra" / "postgres" / "init" / "007_distribution.sql").read_text().lower()
    assert "enabled boolean not null default false" in text
    assert "isolation_mode text not null default 'independent'" in text
