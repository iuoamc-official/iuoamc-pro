from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_encoder_dashboard_assets_exist():
    assert (ROOT / 'services/dashboard-ui/encoder/index.html').exists()
    assert (ROOT / 'services/encoder/app/phase24.py').exists()
    assert (ROOT / 'services/encoder/app/runtime.py').exists()


def test_encoder_runtime_extension_loaded():
    dockerfile = (ROOT / 'services/encoder/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/encoder/app/runtime.py').read_text(encoding='utf-8')
    phase = (ROOT / 'services/encoder/app/phase24.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase24' in runtime
    for needle in ("@app.get('/v1/nodes')", "@app.get('/v1/profiles')", "@app.get('/v1/channels/{channel_id}/jobs')", "@app.put('/v1/jobs/{job_id}/assignment')", "@app.patch('/v1/jobs/{job_id}/state')", "@app.get('/v1/safety')"):
        assert needle in phase


def test_encoder_control_is_non_production():
    phase = (ROOT / 'services/encoder/app/phase24.py').read_text(encoding='utf-8')
    html = (ROOT / 'services/dashboard-ui/encoder/index.html').read_text(encoding='utf-8')
    assert 'PRODUCTION_OUTPUTS_ENABLED' in phase
    assert 'production_execution' in phase
    assert 'ffmpeg_spawn_enabled' in phase
    assert 'external_destinations_enabled' in phase
    assert 'PRODUCTION OUTPUTS OFF' in html
    assert 'does not spawn FFmpeg' in html
    assert 'rtmp://' not in phase.lower()
    assert 'rtmps://' not in phase.lower()
    assert 'youtube.com' not in phase.lower()


def test_encoder_gateway_routes_are_isolated():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    assert 'location /encoder-api/' in conf
    assert 'proxy_pass http://encoder:8108/;' in conf
    assert 'location /encoder-control/' in conf
    assert 'proxy_pass http://dashboard-ui:80/encoder/;' in conf
    assert 'X-Production-Switching "false"' in conf


def test_dashboard_navigation_links_encoder():
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert "encoder:'/encoder-control/'" in nav


def test_operator_token_not_persisted():
    html = (ROOT / 'services/dashboard-ui/encoder/index.html').read_text(encoding='utf-8')
    script = html.split('<script>', 1)[1].split('</script>', 1)[0]
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    assert "let token=''" in script
    assert 'Authorization' in script
