from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_scheduler_dashboard_assets_exist():
    assert (ROOT / 'services/dashboard-ui/scheduler/index.html').exists()
    assert (ROOT / 'services/scheduler/app/phase21.py').exists()
    assert (ROOT / 'services/scheduler/app/runtime.py').exists()


def test_scheduler_token_is_memory_only():
    html = (ROOT / 'services/dashboard-ui/scheduler/index.html').read_text(encoding='utf-8')
    script = html.split('<script>', 1)[1].split('</script>', 1)[0]
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    assert "let token=''" in script
    assert 'Authorization' in script


def test_scheduler_control_bindings_exist():
    html = (ROOT / 'services/dashboard-ui/scheduler/index.html').read_text(encoding='utf-8')
    for needle in ('/v1/programs','/episodes','/v1/playlists','/v1/rundowns','/v1/slots','/conflicts','/epg'):
        assert needle in html
    assert 'Public publishing and production outputs remain OFF' in html


def test_scheduler_extensions_are_loaded():
    dockerfile = (ROOT / 'services/scheduler/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/scheduler/app/runtime.py').read_text(encoding='utf-8')
    extension = (ROOT / 'services/scheduler/app/phase21.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase21' in runtime
    assert "@app.get('/v1/channels/{channel_id}/programs')" in extension
    assert "@app.get('/v1/channels/{channel_id}/epg')" in extension


def test_gateway_routes_scheduler_safely():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert 'location /scheduler-control/' in conf
    assert 'location /scheduler-api/' in conf
    assert 'proxy_pass http://scheduler:8102/;' in conf
    assert "scheduler:'/scheduler-control/'" in nav
    assert 'X-Production-Switching "false"' in conf
