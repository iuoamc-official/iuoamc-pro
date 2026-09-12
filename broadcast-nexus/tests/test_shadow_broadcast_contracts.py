from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_shadow_stack_is_local_only():
    text = read("compose.shadow.yaml")
    assert '127.0.0.1:58117:8117' in text
    assert '127.0.0.1:58118:80' in text
    assert 'internal: true' in text


def test_no_production_destinations_or_credentials():
    paths = [
        "compose.shadow.yaml",
        "services/shadow-media/source.sh",
        "services/shadow-media/encoder.sh",
        "services/shadow-validator/app/main.py",
        "docs/SHADOW-BROADCAST-v1.md",
    ]
    forbidden = [
        "a.rtmp.youtube.com",
        "facebook.com/live",
        "rtmp://",
        "rtmps://",
        "youtube_stream_key",
        "facebook_stream_key",
        "systemctl",
        "platform-iuoamc.uk",
    ]
    for path in paths:
        text = read(path).lower()
        for item in forbidden:
            assert item not in text


def test_dual_encoder_paths_exist():
    source = read("services/shadow-media/source.sh")
    compose = read("compose.shadow.yaml")
    assert "shadow-encoder-a:5000" in source
    assert "shadow-encoder-b:5000" in source
    assert "SHADOW_ENCODER_NAME: a" in compose
    assert "SHADOW_ENCODER_NAME: b" in compose


def test_validator_requires_real_hls_shape():
    text = read("services/shadow-validator/app/main.py")
    assert '#EXTM3U' in text
    assert 'endswith(".ts")' in text
    assert 'segment_count' in text
    assert 'production_outputs": []' in text
