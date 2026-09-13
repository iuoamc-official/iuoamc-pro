from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_control_room_is_local_only():
    compose = read('compose.noc.yaml')
    assert '127.0.0.1:58115:8115' in compose
    assert '127.0.0.1:58116:80' in compose


def test_chaos_is_synthetic_only():
    api = read('services/control-room/app/main.py')
    assert "Only synthetic scenarios are allowed in isolated phase" in api
    assert "production_effect':'none'" in api
    assert 'systemctl' not in api
    assert 'subprocess' not in api


def test_dashboard_has_no_production_destination():
    text = read('apps/noc-dashboard/index.html').lower()
    forbidden = ['rtmp://', 'rtmps://', 'a.rtmp.youtube.com', 'platform-iuoamc.uk']
    for item in forbidden:
        assert item not in text


def test_control_room_schema_contains_simulation_tables():
    schema = read('infra/postgres/init/009_control_room.sql')
    for name in ('telemetry_streams', 'failover_simulations', 'chaos_scenarios'):
        assert name in schema
