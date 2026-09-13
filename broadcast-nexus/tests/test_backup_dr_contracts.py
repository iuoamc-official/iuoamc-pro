from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text()


def test_backup_scripts_do_not_touch_production():
    for path in ("scripts/backup-lab.sh", "scripts/restore-lab.sh"):
        text = read(path).lower()
        assert "platform-iuoamc.uk" not in text
        assert "rtmp://" not in text
        assert "rtmps://" not in text
        assert "systemctl" not in text


def test_staging_and_cutover_docs_are_planning_only():
    staging = read("docs/STAGING-DESIGN-v1.md")
    cutover = read("docs/CUTOVER-ROLLBACK-RUNBOOK-v1.md")
    assert "No production stream keys" in staging
    assert "Planning only" in cutover
    assert "No automated script" in cutover
