<?php
// success_checker.php : determine if unsubscribe call succeeded using STT keyword match
// Usage: php success_checker.php --callfile=ID --record=/path/to.wav

// Attempt to include Composer autoloader if present (optional)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$options = getopt('', [
    'callfile:',
    'record:'
]);

$callId = $options['callfile'] ?? '';
$wav    = $options['record']  ?? '';
$logDir = '/var/log/asterisk/call_progress';
$fileReady = file_exists($wav);
if (!$callId || !$fileReady) {
    exit(1);
}
$logFile = $logDir . "/{$callId}.log";
$data = [];

// 일부 Dialplan 경로에서는 녹음 파일이 아직 디스크에 flush 되지 않은 상태로
// 스크립트가 먼저 호출될 수 있다. 최대 15초까지 폴링하여 파일이 생성되면 진행한다.
$maxWait = 15; // 초
$elapsed = 0;
while ($wav && !file_exists($wav) && $elapsed < $maxWait) {
    sleep(1);
    $elapsed++;
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] STT_START\n", FILE_APPEND);

$analysisDir = __DIR__ . '/analysis_results';
if (!is_dir($analysisDir)) {
    @mkdir($analysisDir, 0775, true);
}

$python = '/usr/bin/python3';
$analyzer = __DIR__ . '/simple_analyzer.py';
$cmd = sprintf('%s %s --file %s --output_dir %s --model base 2>/dev/null',
    escapeshellcmd($python),
    escapeshellarg($analyzer),
    escapeshellarg($wav),
    escapeshellarg($analysisDir)
);

// 실행 (동기) – 녹음 길이가 이미 30초 내외라 STT time 10~15s 예상
exec($cmd);

$base = pathinfo($wav, PATHINFO_FILENAME);
$jsonFile = $analysisDir . '/analysis_' . $base . '.json';

$success = false;
if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);
    if ($data && isset($data['analysis']['status'])) {
        $status = $data['analysis']['status'];
        if ($status === 'success') {
            $success = true;
        } else {
            $success = false;
        }
        // transcription 내용도 로그 목적을 위해 재사용할 수 있음
        $txt = $data['transcription'] ?? '';
    }
}

$status = $success ? 'success' : 'failed';
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] UNSUB_{$status}\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] STT_DONE\n", FILE_APPEND);

// === 패턴 사용 통계 업데이트 ===
require_once __DIR__ . '/pattern_manager.php';
try {
    $pm = new PatternManager(__DIR__ . '/patterns.json');
    // Dialed 080 번호 AstDB 에서 가져오기
    $recVal = trim(exec("sudo /usr/sbin/asterisk -rx \"database get CallFile/{$callId} recnum\""));
    $phoneNumber = preg_replace('/[^0-9]/', '', str_replace('Value:','', $recVal));
    if ($phoneNumber !== '') {
        $pm->recordPatternUsage($phoneNumber, $success);
    }
} catch (Throwable $e) {
    // 로깅만, 실패해도 메인 로직에는 영향 없도록
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] PM_ERROR " . $e->getMessage() . "\n", FILE_APPEND);
}

/* ---------- unsubscribe_calls DB 업데이트 ---------- */
try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    $conf = isset($data['analysis']['confidence']) ? (int)$data['analysis']['confidence'] : null;

    $stmt = $db->prepare(
        'UPDATE unsubscribe_calls
         SET status = :st,
             confidence = :cf,
             recording_file = :rf
         WHERE call_id = :cid'
    );
    $stmt->bindValue(':st', $status, SQLITE3_TEXT);
    $stmt->bindValue(':cf', $conf !== null ? $conf : null,
                     $conf !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(':rf', basename($wav), SQLITE3_TEXT);
    $stmt->bindValue(':cid', $callId, SQLITE3_TEXT);
    $stmt->execute();
} catch (Throwable $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] UC_DB_ERR "
                      . $e->getMessage() . "\n", FILE_APPEND);
}

/* ---------- 결과 SMS 알림 ---------- */
try {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG_START\n", FILE_APPEND);
    
    // 먼저 데이터베이스에서 정보 가져오기 (sudo 없이 접근 가능)
    $row = $db->querySingle("SELECT phone080, identification, notification_phone FROM unsubscribe_calls WHERE call_id = '{$callId}' LIMIT 1", true);
    
    $notifyPhone = '';
    $identNumber = '';
    $phoneNumber = '';
    
    if ($row) {
        $phoneNumber = $row['phone080'] ?? '';
        $identNumber = $row['identification'] ?? '';
        $notifyPhone = $row['notification_phone'] ?? '';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG DB_phone={$phoneNumber} DB_ident={$identNumber} DB_notify={$notifyPhone}\n", FILE_APPEND);
    }
    
    // AstDB에서 백업으로 가져오기 (필요시에만)
    if ($notifyPhone === '') {
        $notifyVal = trim(exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx \"database get CallFile/{$callId} notification_phone\" 2>/dev/null"));
        $notifyPhone = preg_replace('/[^0-9]/', '', str_replace('Value:', '', $notifyVal));
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG AstDB_fallback_notify={$notifyPhone}\n", FILE_APPEND);
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG final_notify={$notifyPhone} final_phone080={$phoneNumber}\n", FILE_APPEND);

    if ($notifyPhone !== '' && $phoneNumber !== '') {
        require_once __DIR__ . '/sms_sender.php';
        $smsSender = new SMSSender();
        $confidence = $conf ?? 0;
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG sending to {$notifyPhone}, status={$status}, conf={$confidence}\n", FILE_APPEND);
        
        $notificationResult = $smsSender->sendAnalysisCompleteNotification(
            $notifyPhone,
            $phoneNumber,
            $identNumber,
            $status,
            $confidence,
            basename($wav)
        );
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_SENT " . ($notificationResult['success'] ? 'SUCCESS' : 'FAILED') . " to {$notifyPhone} msg:" . ($notificationResult['message'] ?? 'no_msg') . "\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_SKIP notify={$notifyPhone} phone={$phoneNumber}\n", FILE_APPEND);
    }
} catch (Throwable $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_ERR "
                      . $e->getMessage() . "\n", FILE_APPEND);
}

exit(0); 