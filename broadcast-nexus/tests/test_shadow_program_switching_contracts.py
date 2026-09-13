from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_program_switcher_is_shadow_only():
    text = read("services/shadow-program-switcher/app/main.py")
    assert 'SHADOW_MODE' in text
    assert 'production_connected' in text
    assert 'rtmp://' not in text.lower()
    assert 'rtmps://' not in text.lower()
    assert 'youtube' not in text.lower()
    assert 'systemctl' not in text.lower()
    assert 'subprocess' not in text.lower()


def test_program_output_binds_local_only():
    compose = read("compose.shadow.yaml")
    assert '127.0.0.1:58119:8119' in compose


def test_switching_has_hysteresis():
    text = read("services/shadow-program-switcher/app/main.py")
    assert 'SHADOW_FAILBACK_GOOD_CHECKS' in text
    assert 'a_good_streak' in text
    assert 'primary_a_unhealthy_backup_b_healthy' in text
    assert 'primary_a_recovered_failback' in text


def test_segments_are_source_pinned():
    text = read("services/shadow-program-switcher/app/main.py")
    assert '?source={source}' in text
    assert '/program/segments/' in text
    assert 'X-IUOAMC-Shadow-Source' in text


def test_e2e_test_only_stops_shadow_encoder():
    text = read("scripts/shadow-program-switch-test.sh")
    assert 'stop shadow-encoder-a' in text
    assert 'start shadow-encoder-a' in text
    assert 'platform-iuoamc.uk' not in text
    assert 'mca-tv-youtube' not in text
