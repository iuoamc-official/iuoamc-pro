from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_operator_session_broker_and_ui_contracts():
    auth = (ROOT / "services/operator-auth/app/main.py").read_text()
    compose = (ROOT / "compose.staging.yaml").read_text()
    gateway = (ROOT / "infra/nginx/staging.conf").read_text()
    bootstrap = (ROOT / "services/dashboard-ui/session-bootstrap.js").read_text()
    login = (ROOT / "services/dashboard-ui/auth/index.html").read_text()
    dashboard_nginx = (ROOT / "services/dashboard-ui/default.conf").read_text()

    assert 'httponly=True' in auth
    assert 'samesite="strict"' in auth
    assert 'SESSION_PREFIX' in auth
    assert 'redis_client().setex' in auth
    assert 'decode_operator_token' in auth
    assert 'production_switching": False' in auth

    assert 'operator-auth:' in compose
    assert 'REDIS_URL:' in compose
    assert 'PRODUCTION_SWITCHING: "false"' in compose
    assert 'SESSION_COOKIE_SECURE: "false"' in compose

    assert 'location = /_session_validate' in gateway
    assert 'internal;' in gateway
    assert 'auth_request /_session_validate;' in gateway
    assert 'auth_request_set $session_authorization $upstream_http_authorization;' in gateway
    assert 'proxy_set_header Authorization $session_authorization;' in gateway
    assert 'location = /auth-api/v1/session/exchange' in gateway
    assert 'location = /auth-api/v1/session/logout' in gateway
    assert 'location /auth-api/' not in gateway

    combined = bootstrap + login
    assert 'localStorage' not in combined
    assert 'sessionStorage' not in combined
    assert '/auth-api/v1/session' in bootstrap
    assert '/auth-control/' in bootstrap
    assert "jwtInput.value='session-cookie'" in bootstrap
    assert '/auth-api/v1/session/exchange' in login
    assert 'session-bootstrap.js' in dashboard_nginx


def test_operator_session_never_enables_production_outputs():
    compose = (ROOT / "compose.staging.yaml").read_text()
    gateway = (ROOT / "infra/nginx/staging.conf").read_text()
    assert 'PRODUCTION_SWITCHING: "true"' not in compose
    assert 'PRODUCTION_OUTPUTS_ENABLED: "true"' not in compose
    assert 'PUBLIC_PUBLISHING_ENABLED: "true"' not in compose
    assert 'X-Production-Switching "false"' in gateway
