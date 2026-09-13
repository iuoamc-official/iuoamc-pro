from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
COMPOSE = (ROOT / "compose.yaml").read_text()
SCHEMA = (ROOT / "infra/postgres/init/004_live_studio.sql").read_text()
SERVICE = (ROOT / "services/live-studio/app/main.py").read_text()


def test_live_studio_is_local_only():
    assert '127.0.0.1:58104:8104' in COMPOSE


def test_live_studio_has_no_production_output_protocols():
    text = SERVICE.lower()
    assert 'rtmp://' not in text
    assert 'rtmps://' not in text
    assert 'srt://' not in text
    assert 'youtube.com/live' not in text


def test_preview_program_buses_exist():
    assert "'preview','program'" in SCHEMA
    assert 'studio_buses' in SCHEMA
    assert '/v1/rooms/{room_id}/take' in SERVICE


def test_guest_invites_are_hash_based():
    assert 'token_hash' in SCHEMA
    assert 'hashlib.sha256' in SERVICE
    assert 'secrets.token_urlsafe' in SERVICE


def test_recording_is_control_plane_only():
    assert 'studio_recordings' in SCHEMA
    assert 'studio.recording.requested' in SERVICE
    assert 'ffmpeg' not in SERVICE.lower()


def test_graphics_layer_model_exists():
    for layer in ('lower_third', 'ticker', 'logo', 'clock', 'html'):
        assert layer in SCHEMA


def test_room_state_machine_present():
    for state in ('draft', 'waiting', 'ready', 'live', 'ended', 'archived'):
        assert f'"{state}"' in SERVICE
