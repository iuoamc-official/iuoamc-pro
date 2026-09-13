from __future__ import annotations

import os
from fastapi import Depends, HTTPException
from packages.auth.security import require_permission
from app.main import app


def flag(name: str, default: str = "false") -> bool:
    return os.getenv(name, default).strip().lower() == "true"


def placeholder(value: str | None) -> bool:
    if not value:
        return True
    return value.startswith("CHANGE_ME_") or value.startswith("ci-local-placeholder")


@app.get('/v1/provider-config')
def provider_config(_: dict = Depends(require_permission('broadcast.read'))):
    provider = os.getenv('SFU_PROVIDER', 'loopback').strip().lower()
    if provider not in {'loopback', 'livekit'}:
        raise HTTPException(503, 'Unsupported staging SFU provider')
    sfu_enabled = flag('SFU_ENABLED')
    turn_enabled = flag('TURN_ENABLED')
    public_ingress = flag('PUBLIC_WEBRTC_INGRESS_ENABLED')
    api_key = os.getenv('SFU_API_KEY')
    api_secret = os.getenv('SFU_API_SECRET')
    turn_secret = os.getenv('TURN_SHARED_SECRET')
    credentials_ready = (
        provider == 'loopback' or
        (not placeholder(api_key) and not placeholder(api_secret))
    )
    turn_credentials_ready = (not turn_enabled) or (not placeholder(turn_secret))
    ready = (
        os.getenv('DEPLOYMENT_ENV', 'staging') == 'staging'
        and not public_ingress
        and credentials_ready
        and turn_credentials_ready
    )
    return {
        'provider': provider,
        'sfu_enabled': sfu_enabled,
        'turn_enabled': turn_enabled,
        'public_webrtc_ingress_enabled': public_ingress,
        'ready': ready,
        'mode': 'staging-isolated',
        'sfu_internal_url_configured': bool(os.getenv('SFU_INTERNAL_URL')),
        'turn_internal_url_configured': bool(os.getenv('TURN_INTERNAL_URL')),
        'credentials_ready': credentials_ready,
        'turn_credentials_ready': turn_credentials_ready,
        'credentials_exposed': False,
        'production_media_enabled': False,
    }


@app.get('/v1/readiness')
def readiness(_: dict = Depends(require_permission('broadcast.read'))):
    env = os.getenv('DEPLOYMENT_ENV', 'staging')
    public_ingress = flag('PUBLIC_WEBRTC_INGRESS_ENABLED')
    production_outputs = flag('PRODUCTION_OUTPUTS_ENABLED')
    return {
        'service': 'rtc-gateway',
        'phase': 33,
        'deployment_env': env,
        'safe_for_staging': env == 'staging' and not public_ingress and not production_outputs,
        'public_webrtc_ingress_enabled': public_ingress,
        'production_outputs_enabled': production_outputs,
        'external_turn_credentials_present': False,
        'external_sfu_credentials_present': False,
        'activation_requires_explicit_future_cutover': True,
    }
