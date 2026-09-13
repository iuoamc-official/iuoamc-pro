CREATE TABLE IF NOT EXISTS telemetry_streams (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_service TEXT NOT NULL,
    source_id TEXT NOT NULL,
    metric_name TEXT NOT NULL,
    metric_value DOUBLE PRECISION,
    metric_text TEXT,
    status TEXT NOT NULL DEFAULT 'ok',
    observed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb
);

CREATE INDEX IF NOT EXISTS idx_telemetry_streams_source_time
    ON telemetry_streams(source_service, source_id, observed_at DESC);

CREATE TABLE IF NOT EXISTS failover_simulations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    redundancy_group_id UUID,
    scenario TEXT NOT NULL,
    injected_conditions JSONB NOT NULL DEFAULT '{}'::jsonb,
    expected_action TEXT,
    observed_action TEXT,
    result TEXT NOT NULL DEFAULT 'pending',
    created_by TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    completed_at TIMESTAMPTZ,
    notes TEXT
);

CREATE INDEX IF NOT EXISTS idx_failover_simulations_created_at
    ON failover_simulations(created_at DESC);

CREATE TABLE IF NOT EXISTS chaos_scenarios (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    severity TEXT NOT NULL DEFAULT 'medium',
    scope TEXT NOT NULL,
    synthetic_only BOOLEAN NOT NULL DEFAULT TRUE,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    config JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO chaos_scenarios(code,name,description,severity,scope,synthetic_only)
VALUES
('freeze_frame','Frozen frame','Simulate repeated identical video frames','high','video',TRUE),
('black_frame','Black frame','Simulate sustained black frames','high','video',TRUE),
('silence','Silence','Simulate sustained audio silence','high','audio',TRUE),
('bitrate_drop','Bitrate drop','Simulate bitrate degradation','medium','encoder',TRUE),
('packet_loss','Packet loss','Simulate network packet loss telemetry','high','network',TRUE),
('hls_stale','Stale HLS','Simulate HLS manifest freshness failure','high','distribution',TRUE),
('primary_down','Primary unavailable','Simulate primary encoder outage','critical','failover',TRUE)
ON CONFLICT (code) DO NOTHING;
