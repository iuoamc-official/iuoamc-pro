from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def test_phase30_system_health_contracts():
    phase = read("services/dashboard-api/app/phase30.py")
    runtime = read("services/dashboard-api/app/runtime.py")
    docker = read("services/dashboard-api/Dockerfile")
    ui = read("services/dashboard-ui/health/index.html")
    nav = read("services/dashboard-ui/navigation.js")
    nginx = read("infra/nginx/staging.conf")
    staging = read("compose.staging.yaml")

    assert "@app.get('/v1/system-health')" in phase
    assert "postgres" in phase and "redis" in phase and "nats" in phase and "minio" in phase
    assert "encoder_nodes" in phase and "hls_manifests" in phase and "channel_runtime_states" in phase
    assert "production_execution': False" in phase
    assert "import app.phase30" in runtime
    assert "app.runtime:app" in docker
    assert "/dashboard-api/v1/system-health" in ui
    assert "PRODUCTION OUTPUTS OFF" in ui
    assert "health:'/system-health/'" in nav
    assert "location /system-health/" in nginx
    assert 'PRODUCTION_SWITCHING: "false"' in staging
    assert 'PRODUCTION_OUTPUTS_ENABLED: "false"' in staging
    assert 'PUBLIC_PUBLISHING_ENABLED: "false"' in staging
