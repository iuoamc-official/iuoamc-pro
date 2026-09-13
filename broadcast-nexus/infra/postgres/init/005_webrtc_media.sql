CREATE TABLE IF NOT EXISTS media_router_rooms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    router_key TEXT UNIQUE NOT NULL,
    provider TEXT NOT NULL DEFAULT 'adapter',
    state TEXT NOT NULL DEFAULT 'provisioning' CHECK (state IN ('provisioning','ready','degraded','closed')),
    region TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS participant_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_id UUID REFERENCES studio_participants(id) ON DELETE SET NULL,
    session_key TEXT UNIQUE NOT NULL,
    state TEXT NOT NULL DEFAULT 'joining' CHECK (state IN ('joining','connected','reconnecting','left','failed')),
    joined_at TIMESTAMPTZ,
    left_at TIMESTAMPTZ,
    last_seen_at TIMESTAMPTZ,
    network_quality TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS media_tracks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_session_id UUID NOT NULL REFERENCES participant_sessions(id) ON DELETE CASCADE,
    track_key TEXT UNIQUE NOT NULL,
    kind TEXT NOT NULL CHECK (kind IN ('audio','video','screen_video','screen_audio')),
    source TEXT NOT NULL CHECK (source IN ('microphone','camera','screen_share','system_audio','unknown')),
    state TEXT NOT NULL DEFAULT 'published' CHECK (state IN ('published','muted','unpublished','ended')),
    codec TEXT,
    width INTEGER,
    height INTEGER,
    fps NUMERIC(6,2),
    bitrate_bps BIGINT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    published_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    ended_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS active_speaker_events (
    id BIGSERIAL PRIMARY KEY,
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_session_id UUID REFERENCES participant_sessions(id) ON DELETE SET NULL,
    audio_level NUMERIC(8,5),
    detected_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS screen_share_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_session_id UUID NOT NULL REFERENCES participant_sessions(id) ON DELETE CASCADE,
    state TEXT NOT NULL DEFAULT 'active' CHECK (state IN ('active','paused','ended')),
    started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    ended_at TIMESTAMPTZ,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb
);

CREATE TABLE IF NOT EXISTS iso_recording_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    studio_room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_session_id UUID REFERENCES participant_sessions(id) ON DELETE SET NULL,
    media_track_id UUID REFERENCES media_tracks(id) ON DELETE SET NULL,
    recording_kind TEXT NOT NULL CHECK (recording_kind IN ('participant_av','participant_audio','screen','program','preview','audio_mix')),
    state TEXT NOT NULL DEFAULT 'queued' CHECK (state IN ('queued','starting','recording','finalizing','completed','failed','cancelled')),
    storage_bucket TEXT,
    storage_object TEXT,
    started_at TIMESTAMPTZ,
    ended_at TIMESTAMPTZ,
    duration_ms BIGINT,
    checksum_sha256 TEXT,
    error_code TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_media_router_room ON media_router_rooms(studio_room_id, state);
CREATE INDEX IF NOT EXISTS idx_participant_sessions_room ON participant_sessions(studio_room_id, state);
CREATE INDEX IF NOT EXISTS idx_media_tracks_room_kind ON media_tracks(studio_room_id, kind, state);
CREATE INDEX IF NOT EXISTS idx_active_speaker_room_time ON active_speaker_events(studio_room_id, detected_at DESC);
CREATE INDEX IF NOT EXISTS idx_iso_recording_room_state ON iso_recording_jobs(studio_room_id, state);
