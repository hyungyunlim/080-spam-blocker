-- DTMF patterns for 080 unsubscribe numbers
CREATE TABLE IF NOT EXISTS patterns (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    phone080           TEXT UNIQUE NOT NULL,           -- 080 번호 (키)
    name               TEXT NOT NULL,                  -- 패턴 이름
    description        TEXT,                           -- 설명
    initial_wait       INTEGER DEFAULT 3,             -- 초기 대기 시간
    dtmf_timing        INTEGER DEFAULT 6,             -- DTMF 전송 타이밍
    dtmf_pattern       TEXT DEFAULT '{ID}#',          -- DTMF 패턴
    confirmation_wait  INTEGER DEFAULT 2,             -- 확인 대기 시간
    confirmation_dtmf  TEXT DEFAULT '1',              -- 확인 DTMF
    total_duration     INTEGER DEFAULT 30,            -- 총 통화 시간
    confirm_delay      INTEGER DEFAULT 2,             -- 확인 지연
    confirm_repeat     INTEGER DEFAULT 3,             -- 확인 반복
    pattern_type       TEXT DEFAULT 'standard',       -- 패턴 타입
    auto_supported     INTEGER DEFAULT 1,             -- 자동 지원 여부
    notes              TEXT,                           -- 메모
    usage_count        INTEGER DEFAULT 0,             -- 사용 횟수
    last_used          TEXT,                           -- 마지막 사용 시간
    created_at         TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at         TEXT DEFAULT CURRENT_TIMESTAMP,
    created_by         TEXT,                           -- 생성자 전화번호
    updated_by         TEXT                            -- 수정자 전화번호
);

-- Pattern variables (global)
CREATE TABLE IF NOT EXISTS pattern_variables (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    variable    TEXT UNIQUE NOT NULL,                  -- {ID}, {Phone} 등
    description TEXT NOT NULL,                         -- 변수 설명
    created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Insert default pattern
INSERT OR IGNORE INTO patterns (
    phone080, name, description, initial_wait, dtmf_timing, dtmf_pattern,
    confirmation_wait, confirmation_dtmf, total_duration, confirm_delay,
    confirm_repeat, pattern_type, notes, created_by
) VALUES (
    'default', '기본값', '알려지지 않은 번호용 기본 설정', 4, 2, '{ID}#',
    2, '1', 19, 2, 3, 'standard', '새로운 번호는 이 패턴으로 시작', 'system'
);

INSERT OR IGNORE INTO pattern_variables (variable, description) VALUES
    ('{ID}', '광고 문자에서 추출한 식별번호'),
    ('{Phone}', '휴대폰 번호'),
    ('{Notify}', '알림받을 번호');

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_patterns_phone080 ON patterns(phone080);
CREATE INDEX IF NOT EXISTS idx_patterns_pattern_type ON patterns(pattern_type);
CREATE INDEX IF NOT EXISTS idx_patterns_last_used ON patterns(last_used);