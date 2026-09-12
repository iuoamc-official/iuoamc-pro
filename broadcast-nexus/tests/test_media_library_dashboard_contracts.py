from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_media_dashboard_assets_exist():
    assert (ROOT / 'services/dashboard-ui/media/index.html').exists()
    assert (ROOT / 'services/media-library/app/phase22.py').exists()
    assert (ROOT / 'services/media-library/app/runtime.py').exists()


def test_media_runtime_extension_is_loaded():
    dockerfile = (ROOT / 'services/media-library/Dockerfile').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/media-library/app/runtime.py').read_text(encoding='utf-8')
    extension = (ROOT / 'services/media-library/app/phase22.py').read_text(encoding='utf-8')
    assert 'app.runtime:app' in dockerfile
    assert 'import app.phase22' in runtime
    for needle in (
        "@app.get('/v1/assets/{asset_id}')",
        "@app.patch('/v1/assets/{asset_id}')",
        "@app.post('/v1/assets/{asset_id}/upload-complete')",
        "@app.get('/v1/assets/{asset_id}/download-url')",
        "@app.post('/v1/assets/{asset_id}/upload-relay')",
    ):
        assert needle in extension


def test_media_ui_binds_real_api_and_memory_only_token():
    html = (ROOT / 'services/dashboard-ui/media/index.html').read_text(encoding='utf-8')
    script = html.split('<script>',1)[1].split('</script>',1)[0]
    assert "let token=''" in script
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    for needle in ('/v1/assets','/upload-url','/upload-relay','/upload-complete','/download-url'):
        assert needle in html
    assert 'No public publishing' in html


def test_media_routes_are_staging_only():
    conf = (ROOT / 'infra/nginx/staging.conf').read_text(encoding='utf-8')
    nav = (ROOT / 'services/dashboard-ui/navigation.js').read_text(encoding='utf-8')
    assert 'location /media-library-api/' in conf
    assert 'proxy_pass http://media-library:8101/;' in conf
    assert 'location /media-control/' in conf
    assert "media:'/media-control/'" in nav
    assert 'X-Production-Switching "false"' in conf
