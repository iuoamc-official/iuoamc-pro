CREATE TABLE IF NOT EXISTS playout_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    frame_rate NUMERIC(6,3) NOT NULL DEFAULT 25.000,
    width INTEGER NOT NULL DEFAULT 1920,
    height INTEGER NOT NULL DEFAULT 1080,
    audio_sample_rate INTEGER NOT NULL DEFAULT 48000,
    timezone TEXT NOT NULL DEFAULT 'UTC',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(channel_id, name)
);

CREATE TABLE IF NOT EXISTS playout_runs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE RESTRICT,
    rundown_id UUID REFERENCES rundowns(id) ON DELETE SET NULL,
    state TEXT NOT NULL DEFAULT 'compiled' CHECK (state IN ('compiled','ready','running','paused','completed','failed','cancelled')),
    source_revision TEXT NOT NULL,
    scheduled_start TIMESTAMPTZ,
    started_at TIMESTAMPTZ,
    ended_at TIMESTAMPTZ,
    queue_hash TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS playout_queue_items (
    id BIGSERIAL PRIMARY KEY,
    run_id UUID NOT NULL REFERENCES playout_runs(id) ON DELETE CASCADE,
    ordinal INTEGER NOT NULL,
    item_type TEXT NOT NULL CHECK (item_type IN ('asset','live','slate','filler','marker')),
    source_ref TEXT,
    title TEXT NOT NULL,
    planned_start TIMESTAMPTZ,
    planned_duration_ms BIGINT NOT NULL CHECK (planned_duration_ms >= 0),
    transition TEXT NOT NULL DEFAULT 'cut' CHECK (transition IN ('cut','fade','dip')),
    fallback_policy TEXT NOT NULL DEFAULT 'channel_default',
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE(run_id, ordinal)
);

CREATE TABLE IF NOT EXISTS fallback_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    priority INTEGER NOT NULL DEFAULT 100,
    trigger_type TEXT NOT NULL CHECK (trigger_type IN ('missing_asset','source_timeout','decode_error','live_unavailable','manual')),
    action_type TEXT NOT NULL CHECK (action_type IN ('play_asset','play_slate','play_filler','hold_last','skip')),
    asset_id UUID REFERENCES media_assets(id) ON DELETE SET NULL,
    slate_text TEXT,
    max_duration_ms BIGINT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(channel_id, code)
);

CREATE TABLE IF NOT EXISTS playout_events (
    id BIGSERIAL PRIMARY KEY,
    run_id UUID REFERENCES playout_runs(id) ON DELETE CASCADE,
    queue_item_id BIGINT REFERENCES playout_queue_items(id) ON DELETE SET NULL,
    event_type TEXT NOT NULL,
    severity TEXT NOT NULL DEFAULT 'info' CHECK (severity IN ('debug','info','warning','error','critical')),
    message TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_playout_runs_channel_state ON playout_runs(channel_id, state);
CREATE INDEX IF NOT EXISTS idx_playout_queue_run_ordinal ON playout_queue_items(run_id, ordinal);
CREATE INDEX IF NOT EXISTS idx_playout_events_run_created ON playout_events(run_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_fallback_channel_trigger ON fallback_policies(channel_id, trigger_type, priority);
