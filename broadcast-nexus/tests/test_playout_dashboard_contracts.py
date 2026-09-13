from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_playout_assets_exist():
    assert (ROOT / 'services/dashboard-ui/playout/index.html').exists()
    assert (ROOT / 'services/playout/app/phase23.py').exists()
    assert (ROOT / 'services/playout/app/runtime.py').exists()


def test_playout_control_is_staging_only():
    html = (ROOT / 'services/dashboard-ui/playout/index.html').read_text(encoding='utf-8')
    assert 'STAGING ONLY' in html
    assert 'PRODUCTION OUTPUT OFF' in html
    assert 'Encoder/Distribution activation disabled' in html
    assert 'localStorage' not in html
    assert 'sessionStorage' not in html


def test_playout_runtime_extension_loaded():
    dockerfile = (ROOT / 'services/playout/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/playout/app/runtime.py').read_text(encoding='utf-8')
    extension = (ROOT / 'services/playout/app/phase23.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase23' in runtime
    assert "@app.get('/v1/channels/{channel_id}/runs')" in extension
    assert "@app.post('/v1/runs/{run_id}/transition')" in extension
    assert "'production_output': False" in extension


def test_gateway_and_navigation_bind_playout():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert 'location /playout-api/' in conf
    assert 'proxy_pass http://playout:8103/;' in conf
    assert 'location /playout-control/' in conf
    assert "playout:'/playout-control/'" in nav
    assert 'X-Production-Switching "false"' in conf


def test_playout_ui_binds_core_controls():
    html = (ROOT / 'services/dashboard-ui/playout/index.html').read_text(encoding='utf-8')
    for needle in ('/v1/compile', '/v1/channels/', '/v1/runs/', '/transition', '/v1/fallback/resolve'):
        assert needle in html
