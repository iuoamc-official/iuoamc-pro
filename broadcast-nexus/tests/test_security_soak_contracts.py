from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_security_preflight_blocks_production_patterns():
    text = read("scripts/security-preflight.sh")
    for pattern in ("a.rtmp.youtube.com", "rtmp://", "rtmps://", "platform-iuoamc.uk"):
        assert pattern in text


def test_soak_never_enables_production_switching():
    text = read("scripts/soak-test.sh")
    assert 'production_switching' in text
    assert 'production_connected' in text
    assert 'systemctl' not in text
    assert 'a.rtmp.youtube.com' not in text
    assert 'rtmp://' not in text
    assert 'rtmps://' not in text


def test_soak_only_injects_shadow_encoder_failure():
    text = read("scripts/soak-test.sh")
    assert 'stop shadow-encoder-a' in text
    assert 'start shadow-encoder-a' in text
    assert 'shadow-encoder-b' in text


def test_security_document_requires_pre_staging_gates():
    text = read("docs/SECURITY-HARDENING-v1.md")
    assert "Mandatory pre-staging gates" in text
    assert "Production switching remains disabled" in text
