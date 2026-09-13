from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_failover_dashboard_assets_exist():
    assert (ROOT / 'services/dashboard-ui/failover/index.html').exists()


def test_failover_dashboard_is_decision_only():
    html = (ROOT / 'services/dashboard-ui/failover/index.html').read_text(encoding='utf-8')
    assert 'PRODUCTION SWITCHING OFF' in html
    assert 'not_implemented_in_isolated_phase' in html
    assert '/v1/groups/' in html
    assert '/v1/decisions/' in html
    assert '/v1/operations-snapshot' in html
    assert 'localStorage.setItem' not in html
    assert 'sessionStorage.setItem' not in html


def test_failover_route_is_staging_only_surface():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert 'location /failover-control/' in conf
    assert 'proxy_pass http://dashboard-ui:80/failover/;' in conf
    assert "failover:'/failover-control/'" in nav
    assert 'X-Production-Switching "false"' in conf


def test_failover_backend_remains_non_executing():
    backend = (ROOT / 'services/failover/app/main.py').read_text(encoding='utf-8')
    assert '"production_switching":False' in backend
    assert '"execution":"not_implemented_in_isolated_phase"' in backend
    assert 'no production switch is executed' in backend
