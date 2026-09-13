from __future__ import annotations

import os

from fastapi import Depends

from app.main import app
from packages.auth.security import require_permission


@app.get('/v1/webrtc/config')
def webrtc_config(_: dict = Depends(require_permission('broadcast.read'))):
    """Return the isolated WebRTC lab envelope.

    Phase 32 deliberately exposes no external SFU/TURN endpoint and no
    production ICE credentials. Browser media is transported only through
    an in-page RTCPeerConnection loopback pair while the control-plane track
    registry remains active.
    """
    return {
        'mode': 'isolated-browser-loopback',
        'real_media_capture': True,
        'real_rtc_peer_connection': True,
        'provider': 'local-loopback',
        'ice_servers': [],
        'sfu_endpoint': None,
        'turn_endpoint': None,
        'turn_credentials_present': False,
        'external_connectivity': False,
        'production_webrtc': False,
        'production_outputs_enabled': os.getenv('PRODUCTION_OUTPUTS_ENABLED', 'false').lower() == 'true',
        'safe_for_staging': os.getenv('PRODUCTION_OUTPUTS_ENABLED', 'false').lower() != 'true',
    }
