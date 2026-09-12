from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_supervisor_has_explicit_state_machine():
    text = read("services/supervisor/app/main.py")
    for state in ("stopped","starting","running","degraded","recovering","stopping","failed"):
        assert state in text
    assert "ALLOWED" in text
    assert "Illegal transition" in text


def test_supervisor_is_non_production():
    text = read("services/supervisor/app/main.py").lower()
    assert 'production_switching":false' in text.replace(" ", "")
    for forbidden in ("systemctl", "subprocess", "rtmp://", "rtmps://", "youtube.com/live"):
        assert forbidden not in text


def test_supervisor_schema_has_generation_and_audit():
    schema = read("infra/postgres/init/010_broadcast_supervisor.sql")
    for token in ("channel_runtime_states", "supervisor_transitions", "supervisor_service_states", "generation"):
        assert token in schema
