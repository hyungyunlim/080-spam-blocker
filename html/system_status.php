<?php
/**
 * system_status.php
 * 
 * SMS 처리 시스템의 전반적인 상태를 확인하는 진단 도구
 */

// CLI와 웹 모두 지원
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    require_once __DIR__ . '/auth.php';
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required\n");
    }
}

echo "=== 080 SMS 시스템 상태 확인 ===\n";
echo "검사 시간: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Startup Manager 상태
if (file_exists(__DIR__ . '/startup_manager.php')) {
    require_once __DIR__ . '/startup_manager.php';
    $sm = new StartupManager();
    $diag = $sm->getDiagnostics();
    
    echo "📊 시스템 시작 상태:\n";
    echo "  • 마지막 시작: " . $diag['startup_time'] . "\n";
    echo "  • 시작 유형: " . $diag['startup_type'] . "\n";
    echo "  • 재시작 후 경과: " . $diag['seconds_since_startup'] . "초\n";
    echo "  • 최근 재시작: " . ($diag['is_recent_startup'] ? '예 (보안모드)' : '아니오') . "\n";
    echo "  • 안전 임계점: " . $diag['safe_threshold'] . "\n\n";
} else {
    echo "❌ Startup Manager를 찾을 수 없습니다\n\n";
}

// 2. 큐 상태
$queueDir = __DIR__ . '/call_queue/';
$spoolDir = '/var/spool/asterisk/outgoing/';

echo "📞 Call Queue 상태:\n";
if (is_dir($queueDir)) {
    $queueFiles = glob($queueDir . '/*.call');
    echo "  • 대기 중인 호출: " . count($queueFiles) . "개\n";
    if (count($queueFiles) > 0) {
        $oldestQueue = min(array_map('filemtime', $queueFiles));
        echo "  • 가장 오래된 큐: " . date('Y-m-d H:i:s', $oldestQueue) . "\n";
    }
} else {
    echo "  • 큐 디렉토리 없음\n";
}

if (is_dir($spoolDir)) {
    $spoolFiles = glob($spoolDir . '/*.call');
    echo "  • 스풀 중인 호출: " . count($spoolFiles) . "개\n";
    if (count($spoolFiles) > 0) {
        $oldestSpool = min(array_map('filemtime', $spoolFiles));
        echo "  • 가장 오래된 스풀: " . date('Y-m-d H:i:s', $oldestSpool) . "\n";
    }
} else {
    echo "  • 스풀 디렉토리 접근 불가\n";
}
echo "\n";

// 3. 모뎀 상태
echo "📱 모뎀 상태:\n";
$out = [];
exec('/usr/sbin/asterisk -rx "core show channels concise" 2>/dev/null | grep quectel', $out);
if (empty($out)) {
    echo "  • 모뎀 채널: 유휴 상태\n";
} else {
    echo "  • 모뎀 채널: 사용 중 (" . count($out) . "개 채널)\n";
    foreach ($out as $channel) {
        echo "    - " . trim($channel) . "\n";
    }
}

// 포트 상태
$ports = ['/dev/ttyUSB3', '/dev/ttyUSB2', '/dev/ttyUSB1', '/dev/ttyUSB0'];
echo "  • 시리얼 포트:\n";
foreach ($ports as $port) {
    if (file_exists($port)) {
        $busy = [];
        exec('lsof -F -n ' . escapeshellarg($port) . ' 2>/dev/null', $busy);
        $status = empty($busy) ? '사용 가능' : '사용 중';
        echo "    - {$port}: {$status}\n";
    }
}
echo "\n";

// 4. 최근 SMS 처리 기록
echo "📨 최근 SMS 처리:\n";
try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    
    // 최근 24시간 SMS 수신
    $recentSms = $db->querySingle("SELECT COUNT(*) FROM sms_incoming WHERE received_at >= datetime('now', '-1 day')");
    echo "  • 24시간 내 수신: {$recentSms}건\n";
    
    // 최근 24시간 호출
    $recentCalls = $db->querySingle("SELECT COUNT(*) FROM unsubscribe_calls WHERE created_at >= datetime('now', '-1 day')");
    echo "  • 24시간 내 호출: {$recentCalls}건\n";
    
    // 최근 처리된 SMS (5개)
    $recent = $db->query("SELECT phone080, identification, received_at FROM sms_incoming ORDER BY received_at DESC LIMIT 5");
    echo "  • 최근 처리:\n";
    while ($row = $recent->fetchArray(SQLITE3_ASSOC)) {
        echo "    - " . $row['received_at'] . ": " . $row['phone080'] . " (ID: " . $row['identification'] . ")\n";
    }
    
    $db->close();
} catch (Exception $e) {
    echo "  • 데이터베이스 오류: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. 임시 파일 상태
echo "🗂️  임시 파일:\n";
$lockFiles = glob('/tmp/smslock_*');
echo "  • SMS 락 파일: " . count($lockFiles) . "개\n";

$stateFiles = glob('/tmp/call_queue_*');
echo "  • 큐 상태 파일: " . count($stateFiles) . "개\n";

$startupState = '/tmp/asterisk_startup_state.json';
if (file_exists($startupState)) {
    $age = time() - filemtime($startupState);
    echo "  • 시작 상태 파일: " . $age . "초 전 생성\n";
} else {
    echo "  • 시작 상태 파일: 없음\n";
}
echo "\n";

// 6. 서비스 상태 (systemctl)
echo "🔧 서비스 상태:\n";
$services = ['asterisk', 'call_queue_runner'];
foreach ($services as $service) {
    $status = [];
    exec("systemctl is-active {$service} 2>/dev/null", $status);
    $state = $status[0] ?? 'unknown';
    echo "  • {$service}: {$state}\n";
}
echo "\n";

// 7. 로그 파일 크기
echo "📋 로그 파일:\n";
$logFiles = [
    'startup.log',
    'sms_fetcher.log', 
    'sms_auto_processor.log',
    'queue_runner.log'
];

foreach ($logFiles as $logFile) {
    $path = __DIR__ . '/logs/' . $logFile;
    if (file_exists($path)) {
        $size = filesize($path);
        $lines = count(file($path));
        echo "  • {$logFile}: " . number_format($size) . " bytes, {$lines} lines\n";
    } else {
        echo "  • {$logFile}: 없음\n";
    }
}

echo "\n=== 검사 완료 ===\n";

// CLI에서 실행 시 권장사항 표시
if ($isCli) {
    echo "\n💡 권장사항:\n";
    if (isset($sm) && $sm->isRecentStartup()) {
        echo "  • 시스템이 최근 재시작되었습니다. 보안 모드가 활성화되어 있습니다.\n";
    }
    
    if (count($queueFiles ?? []) > 10) {
        echo "  • 큐에 많은 호출이 대기 중입니다. 모뎀 상태를 확인하세요.\n";
    }
    
    if (count($lockFiles) > 20) {
        echo "  • 임시 락 파일이 많습니다. 정리를 고려하세요.\n";
        echo "    php startup_manager.php cleanup\n";
    }
}
?>