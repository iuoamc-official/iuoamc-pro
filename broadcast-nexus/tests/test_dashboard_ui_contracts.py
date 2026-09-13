from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_dashboard_ui_files_exist():
    assert (ROOT / "services/dashboard-ui/Dockerfile").exists()
    assert (ROOT / "services/dashboard-ui/index.html").exists()


def test_dashboard_is_staging_only():
    html = (ROOT / "services/dashboard-ui/index.html").read_text(encoding="utf-8")
    assert "STAGING" in html
    assert "PRODUCTION SWITCHING OFF" in html
    assert "Production outputs disabled" in html


def test_staging_gateway_routes_dashboard():
    conf = (ROOT / "infra/nginx/staging.conf").read_text(encoding="utf-8")
    assert "proxy_pass http://dashboard-ui:80/;" in conf
    assert 'X-Production-Switching "false"' in conf


def test_staging_compose_includes_dashboard():
    compose = (ROOT / "compose.staging.yaml").read_text(encoding="utf-8")
    assert "dashboard-ui:" in compose
    assert "broadcast-nexus/services/dashboard-ui/Dockerfile" in compose
