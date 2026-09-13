CREATE TABLE IF NOT EXISTS encoder_nodes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    node_key TEXT UNIQUE NOT NULL,
    display_name TEXT NOT NULL,
    zone TEXT NOT NULL DEFAULT 'local',
    state TEXT NOT NULL DEFAULT 'standby',
    capabilities JSONB NOT NULL DEFAULT '{}'::jsonb,
    last_heartbeat_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS output_destinations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    kind TEXT NOT NULL CHECK (kind IN ('youtube','iptv_hls','internal_hls','srt','rtmp','recording')),
    enabled BOOLEAN NOT NULL DEFAULT FALSE,
    isolation_mode TEXT NOT NULL DEFAULT 'independent',
    config JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(channel_id, code)
);

CREATE TABLE IF NOT EXISTS encoder_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    playout_run_id UUID REFERENCES playout_runs(id) ON DELETE SET NULL,
    node_id UUID REFERENCES encoder_nodes(id) ON DELETE SET NULL,
    state TEXT NOT NULL DEFAULT 'queued',
    profile TEXT NOT NULL DEFAULT 'house-1080p25',
    requested_outputs JSONB NOT NULL DEFAULT '[]'::jsonb,
    started_at TIMESTAMPTZ,
    stopped_at TIMESTAMPTZ,
    failure_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS output_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    encoder_job_id UUID NOT NULL REFERENCES encoder_jobs(id) ON DELETE CASCADE,
    destination_id UUID NOT NULL REFERENCES output_destinations(id) ON DELETE RESTRICT,
    state TEXT NOT NULL DEFAULT 'created',
    attempt INTEGER NOT NULL DEFAULT 1,
    last_error TEXT,
    connected_at TIMESTAMPTZ,
    disconnected_at TIMESTAMPTZ,
    metrics JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS stream_health_samples (
    id BIGSERIAL PRIMARY KEY,
    output_session_id UUID REFERENCES output_sessions(id) ON DELETE CASCADE,
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    probe_type TEXT NOT NULL,
    status TEXT NOT NULL,
    latency_ms INTEGER,
    bitrate_kbps INTEGER,
    fps NUMERIC(8,3),
    audio_present BOOLEAN,
    video_present BOOLEAN,
    frozen_frame BOOLEAN,
    black_frame BOOLEAN,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    sampled_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS hls_manifests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    variant TEXT NOT NULL,
    path TEXT NOT NULL,
    target_duration INTEGER NOT NULL DEFAULT 4,
    playlist_window INTEGER NOT NULL DEFAULT 12,
    state TEXT NOT NULL DEFAULT 'idle',
    last_segment_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(channel_id, variant)
);

CREATE INDEX IF NOT EXISTS idx_encoder_jobs_channel_state ON encoder_jobs(channel_id, state);
CREATE INDEX IF NOT EXISTS idx_output_sessions_job ON output_sessions(encoder_job_id, state);
CREATE INDEX IF NOT EXISTS idx_health_channel_sampled ON stream_health_samples(channel_id, sampled_at DESC);
