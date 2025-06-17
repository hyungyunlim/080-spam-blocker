-- Unified SQLite schema for 080 spam-blocker (spam.db)

-- Users (phone numbers)
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    phone         TEXT UNIQUE NOT NULL,
    verified      INTEGER DEFAULT 0,
    blocked       INTEGER DEFAULT 0,
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    verified_at   TEXT,
    last_login_at TEXT,
    last_access   TEXT
);

-- Seed default administrator account
-- 이 번호는 config/admin_config.php의 get_admin_phones_config() 값과 일치해야 합니다
INSERT OR IGNORE INTO users(phone, verified, created_at) VALUES
  ('01021918573', 1, CURRENT_TIMESTAMP);

-- Verification codes for phone confirmation
CREATE TABLE IF NOT EXISTS verification_codes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER,
    code        TEXT,
    expires_at  INTEGER,
    used        INTEGER DEFAULT 0,
    created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Raw incoming SMS
CREATE TABLE IF NOT EXISTS sms_incoming (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER,
    raw_text       TEXT,
    phone080       TEXT,
    identification TEXT,
    received_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    processed      INTEGER DEFAULT 0,
    call_id        TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- Outgoing unsubscribe calls + analysis result
CREATE TABLE IF NOT EXISTS unsubscribe_calls (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    call_id          TEXT UNIQUE,
    user_id          INTEGER,
    phone080         TEXT,
    identification   TEXT,
    created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
    status           TEXT DEFAULT 'pending',
    confidence       INTEGER,
    recording_file   TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- User notification preferences
CREATE TABLE IF NOT EXISTS user_notification_settings (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id              INTEGER UNIQUE,
    notify_on_start      INTEGER DEFAULT 1,    -- 처리 시작 알림
    notify_on_success    INTEGER DEFAULT 1,    -- 성공 알림
    notify_on_failure    INTEGER DEFAULT 1,    -- 실패 알림
    notify_on_retry      INTEGER DEFAULT 1,    -- 재시도 알림
    notification_mode    TEXT DEFAULT 'short', -- 'short' 또는 'detailed'
    created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at           TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
); 