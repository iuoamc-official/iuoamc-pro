CREATE TABLE IF NOT EXISTS monitoring_targets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID REFERENCES channels(id) ON DELETE CASCADE,
    target_type TEXT NOT NULL CHECK (target_type IN ('encoder','distribution','iptv','internal','studio','playout')),
    target_ref TEXT NOT NULL,
    display_name TEXT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    expected_video BOOLEAN NOT NULL DEFAULT TRUE,
    expected_audio BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(target_type, target_ref)
);

CREATE TABLE IF NOT EXISTS health_samples (
    id BIGSERIAL PRIMARY KEY,
    target_id UUID NOT NULL REFERENCES monitoring_targets(id) ON DELETE CASCADE,
    sampled_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    state TEXT NOT NULL CHECK (state IN ('healthy','degraded','critical','unknown')),
    video_present BOOLEAN,
    audio_present BOOLEAN,
    frozen_frame BOOLEAN NOT NULL DEFAULT FALSE,
    black_frame BOOLEAN NOT NULL DEFAULT FALSE,
    silence_detected BOOLEAN NOT NULL DEFAULT FALSE,
    fps NUMERIC(8,3),
    bitrate_kbps INTEGER,
    audio_lufs NUMERIC(8,3),
    latency_ms INTEGER,
    packet_loss_pct NUMERIC(8,4),
    jitter_ms NUMERIC(10,3),
    timestamp_drift_ms INTEGER,
    hls_age_seconds INTEGER,
    details JSONB NOT NULL DEFAULT '{}'::jsonb
);

CREATE INDEX IF NOT EXISTS idx_health_samples_target_time
    ON health_samples(target_id, sampled_at DESC);

CREATE TABLE IF NOT EXISTS alert_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    metric TEXT NOT NULL,
    comparator TEXT NOT NULL CHECK (comparator IN ('eq','neq','gt','gte','lt','lte','true','false')),
    threshold_num NUMERIC(16,4),
    duration_seconds INTEGER NOT NULL DEFAULT 0 CHECK (duration_seconds >= 0),
    severity TEXT NOT NULL CHECK (severity IN ('info','warning','critical')),
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    target_type TEXT,
    cooldown_seconds INTEGER NOT NULL DEFAULT 60 CHECK (cooldown_seconds >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS alert_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    rule_id UUID REFERENCES alert_rules(id) ON DELETE SET NULL,
    target_id UUID NOT NULL REFERENCES monitoring_targets(id) ON DELETE CASCADE,
    severity TEXT NOT NULL CHECK (severity IN ('info','warning','critical')),
    state TEXT NOT NULL DEFAULT 'open' CHECK (state IN ('open','acknowledged','resolved','suppressed')),
    fingerprint TEXT NOT NULL,
    summary TEXT NOT NULL,
    details JSONB NOT NULL DEFAULT '{}'::jsonb,
    opened_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    acknowledged_at TIMESTAMPTZ,
    resolved_at TIMESTAMPTZ,
    UNIQUE(fingerprint, state)
);

CREATE INDEX IF NOT EXISTS idx_alert_events_open
    ON alert_events(state, severity, opened_at DESC);

CREATE TABLE IF NOT EXISTS redundancy_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID REFERENCES channels(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    mode TEXT NOT NULL DEFAULT 'active_standby' CHECK (mode IN ('active_standby','active_active')),
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS redundancy_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID NOT NULL REFERENCES redundancy_groups(id) ON DELETE CASCADE,
    target_id UUID NOT NULL REFERENCES monitoring_targets(id) ON DELETE CASCADE,
    priority INTEGER NOT NULL DEFAULT 100,
    role TEXT NOT NULL CHECK (role IN ('primary','secondary','peer')),
    eligible BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE(group_id, target_id)
);

CREATE TABLE IF NOT EXISTS failover_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID NOT NULL REFERENCES redundancy_groups(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    consecutive_bad_samples INTEGER NOT NULL DEFAULT 3 CHECK (consecutive_bad_samples >= 1),
    recovery_good_samples INTEGER NOT NULL DEFAULT 5 CHECK (recovery_good_samples >= 1),
    decision_cooldown_seconds INTEGER NOT NULL DEFAULT 120 CHECK (decision_cooldown_seconds >= 0),
    require_manual_authorization BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS failover_decisions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID NOT NULL REFERENCES redundancy_groups(id) ON DELETE CASCADE,
    policy_id UUID REFERENCES failover_policies(id) ON DELETE SET NULL,
    from_target_id UUID REFERENCES monitoring_targets(id) ON DELETE SET NULL,
    to_target_id UUID REFERENCES monitoring_targets(id) ON DELETE SET NULL,
    decision TEXT NOT NULL CHECK (decision IN ('hold','recommend_failover','recommend_failback','authorized_failover','cancelled')),
    reason TEXT NOT NULL,
    evidence JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    authorized_at TIMESTAMPTZ,
    authorized_by TEXT
);

CREATE INDEX IF NOT EXISTS idx_failover_decisions_group_time
    ON failover_decisions(group_id, created_at DESC);

CREATE TABLE IF NOT EXISTS noc_incidents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID REFERENCES channels(id) ON DELETE SET NULL,
    severity TEXT NOT NULL CHECK (severity IN ('info','warning','critical')),
    state TEXT NOT NULL DEFAULT 'open' CHECK (state IN ('open','investigating','mitigated','resolved')),
    title TEXT NOT NULL,
    summary TEXT,
    opened_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    resolved_at TIMESTAMPTZ
);

INSERT INTO alert_rules(name,metric,comparator,threshold_num,duration_seconds,severity,target_type,cooldown_seconds)
VALUES
('Frozen frame','frozen_frame','true',NULL,10,'critical',NULL,60),
('Black frame','black_frame','true',NULL,10,'critical',NULL,60),
('Audio silence','silence_detected','true',NULL,20,'warning',NULL,120),
('Low frame rate','fps','lt',20,10,'critical','encoder',60),
('Stale HLS','hls_age_seconds','gt',20,10,'critical','iptv',60),
('Timestamp drift','timestamp_drift_ms','gt',1000,10,'warning','encoder',120)
ON CONFLICT DO NOTHING;
