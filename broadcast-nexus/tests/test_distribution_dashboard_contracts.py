from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_phase25_assets_exist():
    assert (ROOT / 'services/distribution/app/phase25.py').exists()
    assert (ROOT / 'services/distribution/app/runtime.py').exists()
    assert (ROOT / 'services/dashboard-ui/distribution/index.html').exists()


def test_distribution_runtime_extension_loaded():
    dockerfile = (ROOT / 'services/distribution/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/distribution/app/runtime.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase25' in runtime


def test_external_destinations_are_hard_locked():
    extension = (ROOT / 'services/distribution/app/phase25.py').read_text(encoding='utf-8')
    for needle in ('youtube', 'srt', 'rtmp', 'iptv_hls'):
        assert needle in extension
    assert 'External destination kind' in extension
    assert 'PRODUCTION_OUTPUTS_ENABLED' in extension
    assert 'production_execution' in extension
    assert "'external_destinations_locked': True" in extension


def test_distribution_dashboard_is_wired():
    html = (ROOT / 'services/dashboard-ui/distribution/index.html').read_text(encoding='utf-8')
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    assert '/distribution-api' in html
    assert '/encoder-api' in html
    assert 'EXTERNAL OUTPUTS LOCKED' in html
    assert "distribution:'/distribution-control/'" in nav
    assert 'location /distribution-api/' in conf
    assert 'proxy_pass http://distribution:8109/;' in conf
    assert 'location /distribution-control/' in conf
    assert 'proxy_pass http://dashboard-ui:80/distribution/;' in conf


def test_distribution_ui_does_not_persist_operator_token():
    html = (ROOT / 'services/dashboard-ui/distribution/index.html').read_text(encoding='utf-8')
    script = html.split('<script>', 1)[1].split('</script>', 1)[0]
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    assert "let token=''" in script
    assert 'Authorization' in script
