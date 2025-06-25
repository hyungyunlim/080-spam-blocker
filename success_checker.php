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

// 원격 분석 시도 (M1 맥미니)
require_once __DIR__ . '/remote_analyzer_client.php';

$analysisResult = null;
$remoteAnalysisUsed = false;

try {
    $analysisResult = performRemoteAnalysis($wav, $callId);
    if ($analysisResult) {
        $remoteAnalysisUsed = true;
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] REMOTE_ANALYSIS_SUCCESS\n", FILE_APPEND);
    }
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] REMOTE_ANALYSIS_FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
}

// 원격 분석 실패 시 로컬 분석으로 폴백
if (!$analysisResult) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] LOCAL_ANALYSIS_START\n", FILE_APPEND);
    
    $analysisDir = __DIR__ . '/analysis_results';
    if (!is_dir($analysisDir)) {
        @mkdir($analysisDir, 0775, true);
    }

    $python = '/usr/bin/python3';
    $analyzer = __DIR__ . '/simple_analyzer.py';
    $cmd = sprintf('%s %s --file %s --output_dir %s --model tiny 2>/dev/null',
        escapeshellcmd($python),
        escapeshellarg($analyzer),
        escapeshellarg($wav),
        escapeshellarg($analysisDir)
    );

    // 실행 (동기) – 로컬에서는 tiny 모델 사용으로 속도 향상
    exec($cmd);
}

// 분석 결과 처리 (원격 또는 로컬)
$success = false;
$data = [];

if ($analysisResult && $remoteAnalysisUsed) {
    // 원격 분석 결과 사용
    $data = $analysisResult;
    $success = ($data['analysis']['status'] === 'success');
    $txt = $data['transcription'] ?? '';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] USING_REMOTE_RESULT confidence=" . ($data['analysis']['confidence'] ?? 'N/A') . "\n", FILE_APPEND);
} else {
    // 로컬 분석 결과 사용
    $base = pathinfo($wav, PATHINFO_FILENAME);
    $jsonFile = $analysisDir . '/analysis_' . $base . '.json';
    
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
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] USING_LOCAL_RESULT\n", FILE_APPEND);
    }
}

$status = $success ? 'success' : 'failed';
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] UNSUB_{$status}\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] STT_DONE\n", FILE_APPEND);

// 데이터베이스 업데이트 전 변수 상태 로깅
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] DEBUG_VARS success={$success} status={$status} data_exists=" . (!empty($data) ? 'YES' : 'NO') . "\n", FILE_APPEND);

/* ---------- unsubscribe_calls DB 업데이트 (우선 처리) ---------- */
try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    $conf = isset($data['analysis']['confidence']) ? (int)$data['analysis']['confidence'] : null;

    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] DB_UPDATE_START status={$status} conf={$conf} wav=" . basename($wav) . "\n", FILE_APPEND);

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
    $result = $stmt->execute();
    
    $changes = $db->changes();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] DB_UPDATE_DONE rows_affected={$changes}\n", FILE_APPEND);
    
    if ($changes === 0) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] DB_UPDATE_WARNING no_rows_updated\n", FILE_APPEND);
    }
} catch (Throwable $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] UC_DB_ERR "
                      . $e->getMessage() . "\n", FILE_APPEND);
}

// === 패턴 사용 통계 업데이트 ===
try {
    // CLI 환경에서 패턴 매니저 웹 UI 충돌 우회
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] PM_SKIP CLI_mode_skip_web_ui_conflicts\n", FILE_APPEND);
    
    // AstDB에서 전화번호만 조회하여 로깅
    $recVal = trim(exec("sudo /usr/sbin/asterisk -rx \"database get CallFile/{$callId} recnum\""));
    $phoneNumber = preg_replace('/[^0-9]/', '', str_replace('Value:','', $recVal));
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] PM_INFO phone={$phoneNumber} success={$success}\n", FILE_APPEND);
    
    // TODO: 나중에 CLI 전용 패턴 매니저 구현 시 여기서 통계 업데이트
    
} catch (Throwable $e) {
    // 로깅만, 실패해도 메인 로직에는 영향 없도록
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] PM_ERROR " . $e->getMessage() . "\n", FILE_APPEND);
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