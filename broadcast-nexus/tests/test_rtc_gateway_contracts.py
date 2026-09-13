from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_rtc_gateway_is_local_only():
    compose = (ROOT / "compose.yaml").read_text()
    assert '127.0.0.1:58107:8107' in compose


def test_join_tokens_are_hashed_at_rest():
    text = (ROOT / "services/rtc-gateway/app/main.py").read_text()
    assert "token_hash = sha256_text(raw)" in text
    assert "token_hash TEXT UNIQUE NOT NULL" in (ROOT / "infra/postgres/init/006_rtc_gateway.sql").read_text()


def test_gateway_has_no_real_provider_endpoint():
    text = (ROOT / "services/rtc-gateway/app/main.py").read_text()
    assert '"endpoint": None' in text
    forbidden = ["turn:", "turns:", "wss://", "rtmp://", "rtmps://"]
    for marker in forbidden:
        assert marker not in text


def test_gateway_grants_are_expiring_and_revocable():
    schema = (ROOT / "infra/postgres/init/006_rtc_gateway.sql").read_text()
    assert "expires_at TIMESTAMPTZ NOT NULL" in schema
    assert "revoked_at TIMESTAMPTZ" in schema
    assert "max_uses" in schema
    assert "use_count" in schema


def test_quality_telemetry_contract_exists():
    schema = (ROOT / "infra/postgres/init/006_rtc_gateway.sql").read_text()
    assert "rtc_quality_samples" in schema
    assert "packet_loss_pct" in schema
    assert "uplink_bitrate_bps" in schema
    assert "quality_score" in schema
