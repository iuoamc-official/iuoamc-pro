from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def test_channel_operations_assets_exist():
    assert (ROOT / 'services/dashboard-ui/channels/index.html').exists()
    assert (ROOT / 'services/dashboard-ui/navigation.js').exists()
    assert (ROOT / 'services/dashboard-ui/default.conf').exists()


def test_dashboard_navigation_opens_real_controls():
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert "studio:'/studio-control/'" in nav
    assert "channels:'/channels-control/'" in nav
    conf = (ROOT / 'services/dashboard-ui/default.conf').read_text(encoding='utf-8')
    assert 'navigation.js' in conf


def test_channel_operator_token_is_memory_only():
    html = (ROOT / 'services/dashboard-ui/channels/index.html').read_text(encoding='utf-8')
    scripts = '\n'.join(re.findall(r'<script[^>]*>(.*?)</script>', html, flags=re.S | re.I))
    assert 'localStorage' not in scripts
    assert 'sessionStorage' not in scripts
    assert "let token=''" in scripts
    assert 'Authorization' in scripts


def test_channel_controls_bind_existing_apis():
    html = (ROOT / 'services/dashboard-ui/channels/index.html').read_text(encoding='utf-8')
    for needle in ('/orchestrator-api', '/v1/channels', '/v1/sessions', '/supervisor', '/v1/runtime', '/transition'):
        assert needle in html
    assert 'PRODUCTION SWITCHING OFF' in html
    assert 'production_switching:false' in html


def test_staging_gateway_routes_channel_controls():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    assert 'location /orchestrator-api/' in conf
    assert 'proxy_pass http://orchestrator:8100/;' in conf
    assert 'location /channels-control/' in conf
    assert 'proxy_pass http://dashboard-ui:80/channels/;' in conf
    assert 'location /supervisor/' in conf
    assert 'proxy_set_header Authorization $http_authorization;' in conf
    assert 'X-Production-Switching "false"' in conf
