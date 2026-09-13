CREATE TABLE IF NOT EXISTS studio_rooms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID REFERENCES channels(id) ON DELETE SET NULL,
    broadcast_session_id UUID REFERENCES broadcast_sessions(id) ON DELETE SET NULL,
    slug TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'draft' CHECK (state IN ('draft','waiting','ready','live','ended','archived')),
    room_mode TEXT NOT NULL DEFAULT 'interview' CHECK (room_mode IN ('interview','panel','training','event','internal')),
    max_participants INTEGER NOT NULL DEFAULT 12 CHECK (max_participants BETWEEN 1 AND 100),
    program_width INTEGER NOT NULL DEFAULT 1920,
    program_height INTEGER NOT NULL DEFAULT 1080,
    frame_rate INTEGER NOT NULL DEFAULT 25,
    owner_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS studio_participants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'guest' CHECK (role IN ('director','host','cohost','guest','producer','observer')),
    state TEXT NOT NULL DEFAULT 'invited' CHECK (state IN ('invited','waiting','connected','on_preview','on_program','disconnected','removed')),
    microphone_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    camera_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    screen_share_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    joined_at TIMESTAMPTZ,
    left_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS studio_invites (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    participant_id UUID REFERENCES studio_participants(id) ON DELETE CASCADE,
    token_hash TEXT UNIQUE NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    max_uses INTEGER NOT NULL DEFAULT 1 CHECK (max_uses > 0),
    uses INTEGER NOT NULL DEFAULT 0 CHECK (uses >= 0),
    revoked_at TIMESTAMPTZ,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS studio_buses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    bus_type TEXT NOT NULL CHECK (bus_type IN ('preview','program')),
    active_scene_id UUID,
    revision BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(room_id, bus_type)
);

CREATE TABLE IF NOT EXISTS studio_scenes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    layout_type TEXT NOT NULL DEFAULT 'single' CHECK (layout_type IN ('single','split2','split3','split4','grid','pip','presentation','custom')),
    canvas JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_fallback BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE studio_buses
    ADD CONSTRAINT studio_buses_scene_fk
    FOREIGN KEY (active_scene_id) REFERENCES studio_scenes(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS studio_scene_layers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scene_id UUID NOT NULL REFERENCES studio_scenes(id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    layer_type TEXT NOT NULL CHECK (layer_type IN ('participant','screen','media','image','color','lower_third','ticker','logo','clock','html')),
    source_ref TEXT,
    geometry JSONB NOT NULL DEFAULT '{}'::jsonb,
    style JSONB NOT NULL DEFAULT '{}'::jsonb,
    visible BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(scene_id, position)
);

CREATE TABLE IF NOT EXISTS graphics_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    template_type TEXT NOT NULL CHECK (template_type IN ('lower_third','ticker','logo','bug','clock','breaking','custom')),
    schema JSONB NOT NULL DEFAULT '{}'::jsonb,
    renderer JSONB NOT NULL DEFAULT '{}'::jsonb,
    version INTEGER NOT NULL DEFAULT 1,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS studio_graphics_instances (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    template_id UUID NOT NULL REFERENCES graphics_templates(id) ON DELETE RESTRICT,
    bus_type TEXT NOT NULL DEFAULT 'program' CHECK (bus_type IN ('preview','program')),
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    state TEXT NOT NULL DEFAULT 'hidden' CHECK (state IN ('hidden','queued','visible')),
    layer_order INTEGER NOT NULL DEFAULT 100,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS studio_recordings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    recording_type TEXT NOT NULL CHECK (recording_type IN ('program','preview','iso_participant','iso_screen','audio_mix')),
    participant_id UUID REFERENCES studio_participants(id) ON DELETE SET NULL,
    state TEXT NOT NULL DEFAULT 'requested' CHECK (state IN ('requested','recording','finalizing','completed','failed','cancelled')),
    storage_bucket TEXT,
    storage_object TEXT,
    duration_ms BIGINT,
    size_bytes BIGINT,
    checksum_sha256 TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    started_at TIMESTAMPTZ,
    ended_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS studio_events (
    id BIGSERIAL PRIMARY KEY,
    event_id UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES studio_rooms(id) ON DELETE CASCADE,
    event_type TEXT NOT NULL,
    actor_label TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_studio_rooms_state ON studio_rooms(state, updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_studio_participants_room_state ON studio_participants(room_id, state);
CREATE INDEX IF NOT EXISTS idx_studio_invites_room_expiry ON studio_invites(room_id, expires_at);
CREATE INDEX IF NOT EXISTS idx_studio_scenes_room ON studio_scenes(room_id, created_at);
CREATE INDEX IF NOT EXISTS idx_studio_recordings_room_state ON studio_recordings(room_id, state);
CREATE INDEX IF NOT EXISTS idx_studio_events_room_time ON studio_events(room_id, created_at DESC);
