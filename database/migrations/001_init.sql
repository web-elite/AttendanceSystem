-- 001_init.sql — Initial schema
-- SQLite, WAL mode

PRAGMA journal_mode=WAL;
PRAGMA busy_timeout=5000;
PRAGMA foreign_keys=ON;

-- ── users ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'employee' CHECK (role IN ('admin','employee')),
    is_active     INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_users_username ON users (username);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);

-- ── punches ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS punches (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    type           TEXT    NOT NULL CHECK (type IN ('in','out')),
    method         TEXT    NOT NULL DEFAULT 'manual' CHECK (method IN ('qrcode','location','manual')),
    punched_at     TEXT    NOT NULL,
    lat            REAL,
    lng            REAL,
    distance_m     REAL,
    inside_fence   INTEGER,
    qr_token_hash  TEXT,
    ip             TEXT,
    user_agent     TEXT,
    note           TEXT,
    edited_by      INTEGER REFERENCES users (id) ON DELETE SET NULL,
    edited_at      TEXT,
    is_estimate    INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_punches_user_date ON punches (user_id, punched_at);
CREATE INDEX IF NOT EXISTS idx_punches_date ON punches (punched_at);

-- ── qr_tokens ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS qr_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT    NOT NULL,
    nonce      TEXT    NOT NULL UNIQUE,
    issued_at  TEXT    NOT NULL,
    expires_at TEXT    NOT NULL,
    used_at    TEXT,
    used_by    TEXT
);
CREATE INDEX IF NOT EXISTS idx_qr_tokens_nonce ON qr_tokens (nonce);
CREATE INDEX IF NOT EXISTS idx_qr_tokens_used ON qr_tokens (used_at);

-- ── leave_requests ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leave_requests (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    type       TEXT    NOT NULL CHECK (type IN ('hourly','daily')),
    start_at   TEXT    NOT NULL,
    end_at     TEXT    NOT NULL,
    reason     TEXT    NOT NULL,
    status     TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    reviewed_by INTEGER REFERENCES users (id) ON DELETE SET NULL,
    reviewed_at TEXT,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_leave_user ON leave_requests (user_id);
CREATE INDEX IF NOT EXISTS idx_leave_status ON leave_requests (status);

-- ── holidays ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS holidays (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    date       TEXT    NOT NULL,        -- YYYY-MM-DD (Gregorian)
    title      TEXT    NOT NULL,
    is_annual  INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER REFERENCES users (id) ON DELETE SET NULL,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_holidays_date ON holidays (date);

-- ── settings ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- ── audit_logs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id  INTEGER REFERENCES users (id) ON DELETE SET NULL,
    action    TEXT NOT NULL,
    entity    TEXT NOT NULL,
    entity_id INTEGER,
    meta      TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_logs (actor_id);

-- ── rate_limits ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    ip TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rate_action ON rate_limits (action, created_at);
