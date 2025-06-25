PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE patterns (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    phone080           TEXT UNIQUE NOT NULL,
    name               TEXT NOT NULL,
    description        TEXT,
    initial_wait       INTEGER DEFAULT 3,
    dtmf_timing        INTEGER DEFAULT 6,
    dtmf_pattern       TEXT DEFAULT '{ID}#',
    confirmation_wait  INTEGER DEFAULT 2,
    confirmation_dtmf  TEXT DEFAULT '1',
    total_duration     INTEGER DEFAULT 30,
    confirm_delay      INTEGER DEFAULT 2,
    confirm_repeat     INTEGER DEFAULT 3,
    pattern_type       TEXT DEFAULT 'standard',
    auto_supported     INTEGER DEFAULT 1,
    notes              TEXT,
    usage_count        INTEGER DEFAULT 0,
    last_used          TEXT,
    created_at         TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at         TEXT DEFAULT CURRENT_TIMESTAMP,
    created_by         TEXT,
    updated_by         TEXT
);
INSERT INTO patterns VALUES(1,'default','기본값','알려지지 않은 번호용 기본 설정',4,2,'{ID}#',2,'1',19,2,3,'standard',1,'새로운 번호는 이 패턴으로 시작',0,NULL,'2025-06-12 08:26:17','2025-06-13 08:12:16','migration','migration');
COMMIT;
PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE pattern_variables (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    variable    TEXT UNIQUE NOT NULL,
    description TEXT NOT NULL,
    created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO pattern_variables VALUES(2,'{Phone}','휴대폰 번호','2025-06-17 11:59:54','2025-06-17 11:59:54');
INSERT INTO pattern_variables VALUES(3,'{Notify}','알림받을 번호','2025-06-17 11:59:54','2025-06-17 11:59:54');
INSERT INTO pattern_variables VALUES(4,'{ID}','광고 문자에서 추출한 식별번호','2025-06-17 11:59:54','2025-06-17 11:59:54');
COMMIT;
