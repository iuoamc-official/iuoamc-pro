from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_compose_has_no_current_production_domain_or_stream_outputs():
    text = (ROOT / "compose.yaml").read_text()
    assert "platform-iuoamc.uk" not in text
    assert "rtmp://" not in text
    assert "rtmps://" not in text
    assert "srt://" not in text


def test_exposed_ports_are_loopback_only():
    text = (ROOT / "compose.yaml").read_text()
    for port in ("55432", "56379", "54222", "58222", "59000", "59001", "58100", "58101"):
        assert f'127.0.0.1:{port}:' in text


def test_repository_contains_no_private_secret_files():
    forbidden_names = {".env", "id_rsa", "id_ed25519"}
    forbidden_suffixes = {".pem", ".key", ".p12", ".pfx"}

    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        assert path.name not in forbidden_names
        assert path.suffix.lower() not in forbidden_suffixes


def test_phase_one_does_not_modify_parent_laravel_tree():
    # All Phase 1 implementation files must remain below broadcast-nexus/.
    assert ROOT.name == "broadcast-nexus"
