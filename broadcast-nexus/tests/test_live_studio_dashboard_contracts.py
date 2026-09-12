from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def test_studio_control_assets_exist():
    assert (ROOT / 'services/dashboard-ui/studio/index.html').exists()
    assert (ROOT / 'services/dashboard-ui/phase19-nav.js').exists()
    assert (ROOT / 'services/live-studio/app/phase19.py').exists()
    assert (ROOT / 'services/live-studio/app/runtime.py').exists()


def test_operator_token_is_memory_only():
    html = (ROOT / 'services/dashboard-ui/studio/index.html').read_text(encoding='utf-8')
    scripts = '\n'.join(re.findall(r'<script[^>]*>(.*?)</script>', html, flags=re.S | re.I))
    assert 'localStorage' not in scripts
    assert 'sessionStorage' not in scripts
    assert "let token=''" in scripts
    assert 'Authorization' in scripts


def test_live_studio_controls_are_bound():
    html = (ROOT / 'services/dashboard-ui/studio/index.html').read_text(encoding='utf-8')
    for needle in ('/v1/rooms', '/participants', '/scenes', '/buses/preview', '/take', '/state'):
        assert needle in html
    assert 'PRODUCTION OUTPUT OFF' in html


def test_gateway_routes_studio_safely():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    assert 'location /studio-control/' in conf
    assert 'location /live-studio-api/' in conf
    assert 'proxy_pass http://live-studio:8104/;' in conf
    assert 'X-Production-Switching "false"' in conf


def test_studio_extension_is_loaded():
    dockerfile = (ROOT / 'services/live-studio/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/live-studio/app/runtime.py').read_text(encoding='utf-8')
    extension = (ROOT / 'services/live-studio/app/phase19.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase19' in runtime
    assert "@app.patch('/v1/rooms/{room_id}/participants/{participant_id}/media')" in extension
    assert "@app.get('/v1/rooms/{room_id}/scenes')" in extension
