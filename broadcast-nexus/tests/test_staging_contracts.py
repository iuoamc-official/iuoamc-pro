from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_staging_guards_are_explicit():
    text = read("compose.staging.yaml")
    assert 'PRODUCTION_SWITCHING: "false"' in text
    assert 'PRODUCTION_OUTPUTS_ENABLED: "false"' in text
    assert 'PUBLIC_PUBLISHING_ENABLED: "false"' in text


def test_staging_gateway_is_local_only():
    text = read("compose.staging.yaml")
    assert '127.0.0.1:58200:80' in text


def test_staging_scripts_do_not_touch_production_controls():
    paths = [
        "scripts/staging-preflight.sh",
        "scripts/deploy-staging.sh",
        "scripts/rollback-staging.sh",
        "scripts/staging-smoke-test.sh",
    ]
    forbidden = ["systemctl", "a.rtmp.youtube.com", "rtmp://", "rtmps://", "platform-iuoamc.uk"]
    for path in paths:
        text = read(path).lower()
        for item in forbidden:
            assert item.lower() not in text


def test_staging_runbook_requires_human_cutover_approval():
    text = read("docs/STAGING-DEPLOYMENT-v1.md").lower()
    assert "human approves" in text
    assert "does not configure production youtube" in text
