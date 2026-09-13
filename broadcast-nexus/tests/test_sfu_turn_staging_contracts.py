from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_phase33_runtime_and_api_exist():
    phase = (ROOT / 'services/rtc-gateway/app/phase33.py').read_text(encoding='utf-8')
    runtime = (ROOT / 'services/rtc-gateway/app/runtime.py').read_text(encoding='utf-8')
    assert "@app.get('/v1/provider-config')" in phase
    assert "@app.get('/v1/readiness')" in phase
    assert 'PUBLIC_WEBRTC_INGRESS_ENABLED' in phase
    assert "production_media_enabled': False" in phase
    assert 'import app.phase33' in runtime


def test_sfu_turn_overlay_is_loopback_only():
    overlay = (ROOT / 'compose.sfu-staging.yaml').read_text(encoding='utf-8')
    assert 'profiles: ["sfu-lab"]' in overlay
    assert '127.0.0.1:57880:7880' in overlay
    assert '127.0.0.1:53478:3478/tcp' in overlay
    assert '127.0.0.1:53478:3478/udp' in overlay
    assert 'PUBLIC_WEBRTC_INGRESS_ENABLED' in overlay
    assert 'CHANGE_ME_TURN_SHARED_SECRET' in overlay


def test_default_staging_flags_keep_sfu_turn_disabled():
    env = (ROOT / '.env.example').read_text(encoding='utf-8')
    staging = (ROOT / 'compose.staging.yaml').read_text(encoding='utf-8')
    assert 'SFU_ENABLED=false' in env
    assert 'TURN_ENABLED=false' in env
    assert 'PUBLIC_WEBRTC_INGRESS_ENABLED=false' in env
    assert 'SFU_PROVIDER=loopback' in env
    assert 'PUBLIC_WEBRTC_INGRESS_ENABLED: "false"' in staging
    assert 'PRODUCTION_OUTPUTS_ENABLED: "false"' in staging


def test_no_real_sfu_turn_secrets_committed():
    env = (ROOT / '.env.example').read_text(encoding='utf-8')
    assert 'SFU_API_KEY=CHANGE_ME_SFU_API_KEY' in env
    assert 'SFU_API_SECRET=CHANGE_ME_SFU_API_SECRET' in env
    assert 'TURN_SHARED_SECRET=CHANGE_ME_TURN_SHARED_SECRET' in env
