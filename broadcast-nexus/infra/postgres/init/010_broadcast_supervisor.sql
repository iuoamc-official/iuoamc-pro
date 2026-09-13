CREATE TABLE IF NOT EXISTS channel_runtime_states (
    channel_id UUID PRIMARY KEY REFERENCES channels(id) ON DELETE CASCADE,
    desired_state TEXT NOT NULL DEFAULT 'stopped',
    actual_state TEXT NOT NULL DEFAULT 'stopped',
    source_mode TEXT NOT NULL DEFAULT 'playout',
    active_program_source TEXT,
    active_encoder TEXT,
    active_distribution TEXT,
    health_state TEXT NOT NULL DEFAULT 'unknown',
    recovery_state TEXT NOT NULL DEFAULT 'idle',
    generation BIGINT NOT NULL DEFAULT 0,
    last_transition_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    CHECK (desired_state IN ('stopped','starting','running','degraded','recovering','stopping')),
    CHECK (actual_state IN ('stopped','starting','running','degraded','recovering','stopping','failed')),
    CHECK (source_mode IN ('playout','studio','emergency','shadow'))
);

CREATE TABLE IF NOT EXISTS supervisor_transitions (
    id BIGSERIAL PRIMARY KEY,
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    generation BIGINT NOT NULL,
    from_state TEXT,
    to_state TEXT NOT NULL,
    reason TEXT NOT NULL,
    requested_by TEXT,
    evidence JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS supervisor_service_states (
    id BIGSERIAL PRIMARY KEY,
    channel_id UUID NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    service_name TEXT NOT NULL,
    service_role TEXT NOT NULL,
    instance_ref TEXT,
    state TEXT NOT NULL DEFAULT 'unknown',
    healthy BOOLEAN,
    last_seen_at TIMESTAMPTZ,
    details JSONB NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE(channel_id, service_name, service_role)
);

CREATE INDEX IF NOT EXISTS idx_supervisor_transitions_channel_time
ON supervisor_transitions(channel_id, created_at DESC);
