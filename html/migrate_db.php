<?php
/**
 * 데이터베이스 마이그레이션 스크립트
 * 사용자 테이블에 필요한 컬럼들을 추가합니다.
 */

try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    
    echo "데이터베이스 마이그레이션 시작...\n";
    
    // 현재 users 테이블 구조 확인
    $tableInfo = $db->query("PRAGMA table_info(users)");
    $existingColumns = [];
    
    while ($row = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
        $existingColumns[] = $row['name'];
    }
    
    echo "현재 users 테이블 컬럼: " . implode(', ', $existingColumns) . "\n";
    
    // 필요한 컬럼들 추가
    $newColumns = [
        'last_access' => 'DATETIME',
        'blocked' => 'INTEGER DEFAULT 0',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
    ];
    
    foreach ($newColumns as $column => $type) {
        if (!in_array($column, $existingColumns)) {
            $sql = "ALTER TABLE users ADD COLUMN {$column} {$type}";
            $result = $db->exec($sql);
            
            if ($result) {
                echo "✅ {$column} 컬럼 추가 완료\n";
            } else {
                echo "❌ {$column} 컬럼 추가 실패: " . $db->lastErrorMsg() . "\n";
            }
        } else {
            echo "ℹ️ {$column} 컬럼은 이미 존재합니다\n";
        }
    }
    
    // unsubscribe_calls 테이블이 없으면 생성
    $tablesResult = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='unsubscribe_calls'");
    if (!$tablesResult->fetchArray()) {
        $createCallsTable = "
            CREATE TABLE unsubscribe_calls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                target_number TEXT NOT NULL,
                notification_phone TEXT NOT NULL,
                identification_number TEXT,
                call_file_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME,
                completed_at DATETIME,
                success INTEGER DEFAULT 0,
                error_message TEXT,
                recording_path TEXT,
                analysis_result TEXT
            )
        ";
        
        if ($db->exec($createCallsTable)) {
            echo "✅ unsubscribe_calls 테이블 생성 완료\n";
        } else {
            echo "❌ unsubscribe_calls 테이블 생성 실패: " . $db->lastErrorMsg() . "\n";
        }
    } else {
        echo "ℹ️ unsubscribe_calls 테이블은 이미 존재합니다\n";
    }
    
    // 기존 사용자들의 created_at 업데이트 (NULL인 경우)
    $updateResult = $db->exec("UPDATE users SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL");
    if ($updateResult) {
        echo "✅ 기존 사용자 created_at 업데이트 완료\n";
    }

    // unsubscribe_calls 테이블에 company_name 컬럼 추가
    $callsTableInfo = $db->query("PRAGMA table_info(unsubscribe_calls)");
    $existingCallsColumns = [];
    while ($row = $callsTableInfo->fetchArray(SQLITE3_ASSOC)) {
        $existingCallsColumns[] = $row['name'];
    }

    if (!in_array('company_name', $existingCallsColumns)) {
        $sql = "ALTER TABLE unsubscribe_calls ADD COLUMN company_name TEXT";
        if ($db->exec($sql)) {
            echo "✅ unsubscribe_calls 테이블에 company_name 컬럼 추가 완료\n";
        } else {
            echo "❌ unsubscribe_calls 테이블에 company_name 컬럼 추가 실패: " . $db->lastErrorMsg() . "\n";
        }
    } else {
        echo "ℹ️ company_name 컬럼은 이미 unsubscribe_calls 테이블에 존재합니다\n";
    }
    
    echo "데이터베이스 마이그레이션 완료!\n";
    
    $db->close();
    
} catch (Exception $e) {
    echo "❌ 마이그레이션 오류: " . $e->getMessage() . "\n";
    exit(1);
}
?>