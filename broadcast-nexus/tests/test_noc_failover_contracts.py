from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_failover_is_decision_only():
    text = read("services/failover/app/main.py")
    assert 'production_switching' in text
    assert 'execution":"not_implemented_in_isolated_phase"' in text
    assert "systemctl" not in text
    assert "subprocess" not in text


def test_no_production_stream_destinations_in_noc_layer():
    paths = [
        "services/monitoring/app/main.py",
        "services/alerts/app/main.py",
        "services/failover/app/main.py",
        "services/noc/app/main.py",
        "docs/NOC-FAILOVER-v1.md",
    ]
    forbidden = ["rtmp://", "rtmps://", "youtube.com/live", "a.rtmp.youtube.com"]
    for path in paths:
        text = read(path).lower()
        for item in forbidden:
            assert item not in text


def test_noc_services_bind_local_only():
    compose = read("compose.noc.yaml")
    for port in (58111, 58112, 58113, 58114):
        assert f'127.0.0.1:{port}:' in compose


def test_required_detection_fields_exist():
    schema = read("infra/postgres/init/008_noc_failover.sql")
    for field in (
        "frozen_frame",
        "black_frame",
        "silence_detected",
        "timestamp_drift_ms",
        "hls_age_seconds",
        "failover_decisions",
        "noc_incidents",
    ):
        assert field in schema
