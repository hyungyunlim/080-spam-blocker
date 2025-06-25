<?php
/**
 * auto_success_checker.php
 * 통화 완료 후 자동으로 success checker를 실행하고 결과 알림을 발송
 * Usage: php auto_success_checker.php --callid=ID --recording=/path/to.wav
 */

$options = getopt('', [
    'callid:',
    'recording:'
]);

$callId = $options['callid'] ?? '';
$recordingPath = $options['recording'] ?? '';

if (!$callId) {
    error_log("[AUTO_SUCCESS] Missing callid parameter");
    exit(1);
}

// 데이터베이스에서 통화 정보 조회
try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    $stmt = $db->prepare('SELECT user_id, phone080, identification, notification_phone FROM unsubscribe_calls WHERE call_id = :callid');
    $stmt->bindValue(':callid', $callId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $callInfo = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$callInfo) {
        error_log("[AUTO_SUCCESS] Call ID {$callId} not found in database");
        exit(1);
    }
    
    $notificationPhone = $callInfo['notification_phone'];
    $targetNumber = $callInfo['phone080'];
    $identificationNumber = $callInfo['identification'];
    
    error_log("[AUTO_SUCCESS] Processing call {$callId} for {$notificationPhone}");
    
} catch (Exception $e) {
    error_log("[AUTO_SUCCESS] Database error: " . $e->getMessage());
    exit(1);
}

// 녹음 파일이 없으면 기본 경로에서 찾기
if (!$recordingPath || !file_exists($recordingPath)) {
    $monitorDir = '/var/spool/asterisk/monitor';
    $pattern = "*ID_{$callId}*.wav";
    $files = glob($monitorDir . '/' . $pattern);
    
    if (!empty($files)) {
        $recordingPath = $files[0];
        error_log("[AUTO_SUCCESS] Found recording: {$recordingPath}");
    } else {
        error_log("[AUTO_SUCCESS] No recording found for {$callId}");
        // 녹음 없이도 처리 진행 (단순히 완료 알림만 발송)
        require_once __DIR__ . '/sms_sender.php';
        $sender = new SmsSender();
        $sender->sendAnalysisCompleteNotification(
            $notificationPhone,
            $targetNumber, 
            $identificationNumber,
            'attempted',
            0,
            ''
        );
        exit(0);
    }
}

// Success checker 실행
if ($recordingPath && file_exists($recordingPath)) {
    error_log("[AUTO_SUCCESS] Running success checker for {$recordingPath}");
    $cmd = "php " . __DIR__ . "/success_checker.php --callfile={$callId} --record={$recordingPath}";
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0) {
        error_log("[AUTO_SUCCESS] Success checker completed for {$callId}");
        
        // STT 결과 확인
        $logFile = "/var/log/asterisk/call_progress/{$callId}.log";
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            
            if (strpos($logContent, 'UNSUB_success') !== false) {
                $status = 'success';
                $confidence = 95; // 성공 시 기본 신뢰도
            } elseif (strpos($logContent, 'UNSUB_failed') !== false) {
                $status = 'failed';
                $confidence = 0;
            } else {
                $status = 'uncertain';
                $confidence = 50;
            }
            
            // 데이터베이스 상태 업데이트
            $updateStmt = $db->prepare('UPDATE unsubscribe_calls SET status = :status, confidence = :confidence WHERE call_id = :callid');
            $updateStmt->bindValue(':status', $status, SQLITE3_TEXT);
            $updateStmt->bindValue(':confidence', $confidence, SQLITE3_INTEGER);
            $updateStmt->bindValue(':callid', $callId, SQLITE3_TEXT);
            $updateStmt->execute();
            
            // 결과 알림 발송
            require_once __DIR__ . '/sms_sender.php';
            $sender = new SmsSender();
            $result = $sender->sendAnalysisCompleteNotification(
                $notificationPhone,
                $targetNumber, 
                $identificationNumber,
                $status,
                $confidence,
                $recordingPath
            );
            
            error_log("[AUTO_SUCCESS] Notification sent to {$notificationPhone}, status: {$status}");
        }
    } else {
        error_log("[AUTO_SUCCESS] Success checker failed for {$callId}");
    }
} else {
    error_log("[AUTO_SUCCESS] Recording file not found: {$recordingPath}");
}

$db->close();
?>