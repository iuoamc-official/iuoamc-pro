from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_dashboard_api_files_exist():
    assert (ROOT / "services/dashboard-api/Dockerfile").exists()
    assert (ROOT / "services/dashboard-api/requirements.txt").exists()
    assert (ROOT / "services/dashboard-api/app/main.py").exists()


def test_dashboard_api_is_staging_safe_by_contract():
    code = (ROOT / "services/dashboard-api/app/main.py").read_text(encoding="utf-8")
    assert 'PRODUCTION_SWITCHING' in code
    assert 'PRODUCTION_OUTPUTS_ENABLED' in code
    assert 'PUBLIC_PUBLISHING_ENABLED' in code
    assert 'safe_for_staging' in code
    assert '/v1/summary' in code


def test_staging_compose_wires_dashboard_api_with_guards():
    compose = (ROOT / "compose.staging.yaml").read_text(encoding="utf-8")
    assert "dashboard-api:" in compose
    assert "broadcast-nexus/services/dashboard-api/Dockerfile" in compose
    assert 'PRODUCTION_SWITCHING: "false"' in compose
    assert 'PRODUCTION_OUTPUTS_ENABLED: "false"' in compose
    assert 'PUBLIC_PUBLISHING_ENABLED: "false"' in compose


def test_gateway_exposes_only_staging_dashboard_api_route():
    conf = (ROOT / "infra/nginx/staging.conf").read_text(encoding="utf-8")
    assert "location /dashboard-api/" in conf
    assert "proxy_pass http://dashboard-api:8121/;" in conf
    assert 'X-Production-Switching "false"' in conf


def test_dashboard_ui_uses_unified_snapshot():
    html = (ROOT / "services/dashboard-ui/index.html").read_text(encoding="utf-8")
    assert "/dashboard-api/v1/summary" in html
    assert "safe_for_staging" in html
    assert "v0.18" in html
    assert "Production outputs disabled" in html
