from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def test_webrtc_lab_assets_and_runtime_exist():
    assert (ROOT / 'services/dashboard-ui/webrtc/index.html').exists()
    assert (ROOT / 'services/rtc-gateway/app/phase32.py').exists()
    assert (ROOT / 'services/rtc-gateway/app/runtime.py').exists()
    docker = (ROOT / 'services/rtc-gateway/Dockerfile').read_text(encoding='utf-8')
    assert 'app.runtime:app' in docker


def test_browser_uses_real_media_capture_and_peer_connection():
    html = (ROOT / 'services/dashboard-ui/webrtc/index.html').read_text(encoding='utf-8')
    scripts = '\n'.join(re.findall(r'<script[^>]*>(.*?)</script>', html, flags=re.S | re.I))
    assert 'navigator.mediaDevices.getUserMedia' in scripts
    assert 'navigator.mediaDevices.getDisplayMedia' in scripts
    assert 'new RTCPeerConnection({iceServers:[]})' in scripts
    assert 'createOffer()' in scripts
    assert 'createAnswer()' in scripts
    assert 'localStorage' not in scripts
    assert 'sessionStorage' not in scripts


def test_webrtc_config_has_no_external_provider_connectivity():
    ext = (ROOT / 'services/rtc-gateway/app/phase32.py').read_text(encoding='utf-8')
    for needle in ("'ice_servers': []", "'sfu_endpoint': None", "'turn_endpoint': None", "'external_connectivity': False", "'production_webrtc': False"):
        assert needle in ext


def test_webrtc_lab_uses_control_plane_track_registry():
    html = (ROOT / 'services/dashboard-ui/webrtc/index.html').read_text(encoding='utf-8')
    for needle in ('/media-router-api', '/v1/sessions', '/v1/tracks', '/rtc-gateway-api', '/v1/webrtc/config'):
        assert needle in html


def test_staging_gateway_routes_isolated_webrtc_services():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    assert 'location /media-router-api/' in conf
    assert 'proxy_pass http://media-router:8105/;' in conf
    assert 'location /rtc-gateway-api/' in conf
    assert 'proxy_pass http://rtc-gateway:8107/;' in conf
    assert 'location /webrtc-control/' in conf
    assert 'auth_request /_session_validate;' in conf
    assert 'X-Production-Switching "false"' in conf
