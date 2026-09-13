from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_noc_dashboard_assets_exist():
    assert (ROOT / 'services/dashboard-ui/noc/index.html').exists()
    assert (ROOT / 'services/noc/app/phase27.py').exists()
    assert (ROOT / 'services/noc/app/runtime.py').exists()


def test_noc_dashboard_token_is_memory_only():
    html = (ROOT / 'services/dashboard-ui/noc/index.html').read_text(encoding='utf-8')
    script = html.split('<script>', 1)[1].split('</script>', 1)[0]
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    assert "let token=''" in script
    assert 'Authorization' in script


def test_noc_dashboard_binds_operations_endpoints():
    html = (ROOT / 'services/dashboard-ui/noc/index.html').read_text(encoding='utf-8')
    for needle in (
        '/v1/operations-snapshot',
        '/v1/targets',
        '/v1/samples',
        '/v1/evaluate/',
        '/v1/incidents',
        '/v1/redundancy/groups',
        '/v1/groups/',
        '/authorize',
    ):
        assert needle in html
    assert 'FAILOVER EXECUTION OFF' in html


def test_noc_extension_is_loaded_and_decision_only():
    dockerfile = (ROOT / 'services/noc/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/noc/app/runtime.py').read_text(encoding='utf-8')
    extension = (ROOT / 'services/noc/app/phase27.py').read_text(encoding='utf-8')
    failover = (ROOT / 'services/failover/app/main.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase27' in runtime
    assert "@app.get('/v1/operations-snapshot')" in extension
    assert "@app.post('/v1/redundancy/groups'" in extension
    assert "'production_switching': False" in extension
    assert 'not_implemented_in_isolated_phase' in failover


def test_staging_gateway_routes_noc_apis_safely():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    for location in (
        'location /monitoring-api/',
        'location /alerts-api/',
        'location /failover-api/',
        'location /noc-api/',
        'location /noc-control/',
    ):
        assert location in conf
    assert 'proxy_pass http://monitoring:8111/;' in conf
    assert 'proxy_pass http://alerts:8112/;' in conf
    assert 'proxy_pass http://failover:8113/;' in conf
    assert 'proxy_pass http://noc:8114/;' in conf
    assert 'X-Production-Switching "false"' in conf


def test_dashboard_navigation_routes_noc_control():
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert "noc:'/noc-control/'" in nav
