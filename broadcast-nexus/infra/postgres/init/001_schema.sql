CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    display_name TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code TEXT UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS channels (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    timezone TEXT NOT NULL DEFAULT 'UTC',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS broadcast_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    channel_id UUID REFERENCES channels(id) ON DELETE RESTRICT,
    title TEXT NOT NULL,
    mode TEXT NOT NULL CHECK (mode IN ('scheduled','live_studio','emergency','internal')),
    state TEXT NOT NULL DEFAULT 'created',
    planned_start TIMESTAMPTZ,
    actual_start TIMESTAMPTZ,
    actual_end TIMESTAMPTZ,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS media_assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    asset_key TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    media_type TEXT NOT NULL,
    mime_type TEXT,
    size_bytes BIGINT,
    duration_ms BIGINT,
    status TEXT NOT NULL DEFAULT 'registered',
    checksum_sha256 TEXT,
    storage_bucket TEXT NOT NULL,
    storage_object TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS audit_events (
    id BIGSERIAL PRIMARY KEY,
    event_id UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    actor_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    actor_label TEXT,
    action TEXT NOT NULL,
    resource_type TEXT NOT NULL,
    resource_id TEXT,
    source_service TEXT NOT NULL,
    request_id TEXT,
    ip_address INET,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_events(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_resource ON audit_events(resource_type, resource_id);
CREATE INDEX IF NOT EXISTS idx_media_status ON media_assets(status);
CREATE INDEX IF NOT EXISTS idx_sessions_channel_state ON broadcast_sessions(channel_id, state);

INSERT INTO roles(code, name) VALUES
('super_admin', 'Super Administrator'),
('broadcast_admin', 'Broadcast Administrator'),
('producer', 'Producer'),
('studio_host', 'Studio Host'),
('media_manager', 'Media Manager'),
('noc_operator', 'NOC Operator'),
('viewer', 'Viewer')
ON CONFLICT (code) DO NOTHING;

INSERT INTO permissions(code, description) VALUES
('broadcast.read', 'Read broadcast control state'),
('broadcast.write', 'Create/update broadcast sessions'),
('broadcast.start', 'Authorize a broadcast start'),
('broadcast.stop', 'Authorize a broadcast stop'),
('media.read', 'Read media library'),
('media.write', 'Create/update media metadata'),
('media.upload', 'Request media uploads'),
('audit.read', 'Read audit log'),
('noc.read', 'Read monitoring state'),
('noc.operate', 'Operate monitoring/failover controls')
ON CONFLICT (code) DO NOTHING;
