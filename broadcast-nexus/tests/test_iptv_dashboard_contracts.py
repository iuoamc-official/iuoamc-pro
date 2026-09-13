from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_iptv_phase26_assets_exist():
    assert (ROOT / 'services/iptv/app/phase26.py').exists()
    assert (ROOT / 'services/iptv/app/runtime.py').exists()
    assert (ROOT / 'services/dashboard-ui/iptv/index.html').exists()


def test_iptv_runtime_extension_is_loaded():
    dockerfile = (ROOT / 'services/iptv/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/iptv/app/runtime.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase26' in runtime


def test_iptv_safety_contracts():
    extension = (ROOT / 'services/iptv/app/phase26.py').read_text(encoding='utf-8')
    assert 'PUBLIC_PUBLISHING_ENABLED' in extension
    assert 'public_origin_enabled' in extension
    assert 'cdn_enabled' in extension
    assert 'production_hls_enabled' in extension
    assert '/staging-hls/' in extension
    assert "@app.get('/v1/safety')" in extension
    assert "@app.patch('/v1/manifests/{manifest_id}/state')" in extension


def test_iptv_dashboard_and_gateway_routes():
    html = (ROOT / 'services/dashboard-ui/iptv/index.html').read_text(encoding='utf-8')
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    default_conf = (ROOT / 'services/dashboard-ui/default.conf').read_text(encoding='utf-8')
    assert '/iptv-api' in html
    assert 'PUBLIC PUBLISHING OFF' in html
    assert '/staging-hls/' in html
    assert "iptv:'/iptv-control/'" in nav
    assert 'location /iptv-api/' in conf
    assert 'proxy_pass http://iptv:8110/;' in conf
    assert 'location /iptv-control/' in conf
    assert 'data-panel="iptv"' in default_conf


def test_iptv_token_not_persisted():
    html = (ROOT / 'services/dashboard-ui/iptv/index.html').read_text(encoding='utf-8')
    script = html.split('<script>', 1)[1].split('</script>', 1)[0]
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    assert "let token=''" in script
