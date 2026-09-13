from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]


def test_phase29_supervisor_dashboard_contracts():
    phase=(ROOT/'services/supervisor/app/phase29.py').read_text()
    runtime=(ROOT/'services/supervisor/app/runtime.py').read_text()
    docker=(ROOT/'services/supervisor/Dockerfile').read_text()
    ui=(ROOT/'services/dashboard-ui/supervisor/index.html').read_text()
    nav=(ROOT/'services/dashboard-ui/navigation.js').read_text()
    nginx=(ROOT/'infra/nginx/staging.conf').read_text()

    assert "@app.get('/v1/runtimes')" in phase
    assert "@app.get('/v1/safety')" in phase
    assert "production_switching':False" in phase
    assert "transition_execution':'control-plane-only'" in phase
    assert 'import app.phase29' in runtime
    assert 'app.runtime:app' in docker

    script=ui.split('<script>',1)[1].split('</script>',1)[0]
    assert "let token=''" in script
    assert 'localStorage' not in script
    assert 'sessionStorage' not in script
    assert '/v1/runtimes' in ui
    assert '/v1/safety' in ui
    assert '/transition' in ui
    assert 'PRODUCTION SWITCHING OFF' in ui

    assert "supervisor:'/supervisor-control/'" in nav
    assert 'location /supervisor-control/' in nginx
    assert 'X-Production-Switching "false"' in nginx
