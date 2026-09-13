CREATE TABLE IF NOT EXISTS rtc_access_grants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_id UUID REFERENCES studio_participants(id) ON DELETE CASCADE,
    token_hash TEXT UNIQUE NOT NULL,
    role TEXT NOT NULL DEFAULT 'guest',
    can_publish_audio BOOLEAN NOT NULL DEFAULT TRUE,
    can_publish_video BOOLEAN NOT NULL DEFAULT TRUE,
    can_share_screen BOOLEAN NOT NULL DEFAULT FALSE,
    can_subscribe BOOLEAN NOT NULL DEFAULT TRUE,
    max_uses INTEGER NOT NULL DEFAULT 1 CHECK (max_uses >= 1 AND max_uses <= 10),
    use_count INTEGER NOT NULL DEFAULT 0 CHECK (use_count >= 0),
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    created_by_label TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS rtc_gateway_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    access_grant_id UUID NOT NULL REFERENCES rtc_access_grants(id) ON DELETE RESTRICT,
    participant_session_id UUID REFERENCES participant_sessions(id) ON DELETE SET NULL,
    connection_id TEXT UNIQUE NOT NULL,
    state TEXT NOT NULL DEFAULT 'authorized' CHECK (state IN ('authorized','connected','reconnecting','closed','failed')),
    client_ip INET,
    user_agent_hash TEXT,
    connected_at TIMESTAMPTZ,
    disconnected_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS rtc_quality_samples (
    id BIGSERIAL PRIMARY KEY,
    gateway_session_id UUID NOT NULL REFERENCES rtc_gateway_sessions(id) ON DELETE CASCADE,
    rtt_ms NUMERIC(10,2),
    jitter_ms NUMERIC(10,2),
    packet_loss_pct NUMERIC(7,4),
    uplink_bitrate_bps BIGINT,
    downlink_bitrate_bps BIGINT,
    quality_score INTEGER CHECK (quality_score BETWEEN 0 AND 5),
    sampled_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS rtc_ice_policy (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID REFERENCES studio_rooms(id) ON DELETE CASCADE,
    policy_name TEXT NOT NULL,
    transport_policy TEXT NOT NULL DEFAULT 'all' CHECK (transport_policy IN ('all','relay')),
    provider_ref TEXT,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_rtc_grants_room_expiry ON rtc_access_grants(studio_room_id, expires_at);
CREATE INDEX IF NOT EXISTS idx_rtc_gateway_grant_state ON rtc_gateway_sessions(access_grant_id, state);
CREATE INDEX IF NOT EXISTS idx_rtc_quality_session_time ON rtc_quality_samples(gateway_session_id, sampled_at DESC);
