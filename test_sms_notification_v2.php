<?php
// SMS 알림 테스트 스크립트 v2 - 데이터베이스에서 직접 가져오기
$callId = '6850323dca035';
$logFile = "/var/log/asterisk/call_progress/{$callId}.log";

try {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG_V2_START\n", FILE_APPEND);
    
    $db = new SQLite3(__DIR__ . '/spam.db');
    $row = $db->querySingle("SELECT phone080, identification, notification_phone FROM unsubscribe_calls WHERE call_id = '{$callId}' LIMIT 1", true);
    
    if ($row) {
        $phoneNumber = $row['phone080'];
        $identNumber = $row['identification'];
        $notifyPhone = $row['notification_phone'];
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG_V2 DB_phone={$phoneNumber} DB_ident={$identNumber} DB_notify={$notifyPhone}\n", FILE_APPEND);

        if ($notifyPhone !== '' && $phoneNumber !== '') {
            require_once __DIR__ . '/sms_sender.php';
            $smsSender = new SMSSender();
            $confidence = 75;
            $status = 'failed';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG_V2 sending to {$notifyPhone}, status={$status}, conf={$confidence}\n", FILE_APPEND);
            
            $notificationResult = $smsSender->sendAnalysisCompleteNotification(
                $notifyPhone,
                $phoneNumber,
                $identNumber,
                $status,
                $confidence,
                '20250617-000327-FROM_AUTO-ID_6850323dca035-TO_0808895050.wav'
            );
            file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_SENT_V2 " . ($notificationResult['success'] ? 'SUCCESS' : 'FAILED') . " to {$notifyPhone} msg:" . ($notificationResult['message'] ?? 'no_msg') . "\n", FILE_APPEND);
            
            if ($notificationResult['success']) {
                echo "✅ SMS 알림이 성공적으로 전송되었습니다!\n";
                echo "📱 수신자: {$notifyPhone}\n";
                echo "🎯 080번호: {$phoneNumber}\n";
                echo "🔑 식별번호: {$identNumber}\n";
                echo "📊 결과: {$status} (신뢰도: {$confidence}%)\n";
            } else {
                echo "❌ SMS 전송 실패: " . ($notificationResult['message'] ?? 'unknown error') . "\n";
            }
        } else {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_SKIP_V2 notify={$notifyPhone} phone={$phoneNumber}\n", FILE_APPEND);
            echo "⚠️ SMS 알림을 건너뜀: 알림번호 또는 080번호가 없음\n";
        }
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_ERR_V2 No call record found\n", FILE_APPEND);
        echo "❌ 통화 기록을 찾을 수 없습니다\n";
    }
} catch (Throwable $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_ERR_V2 " . $e->getMessage() . "\n", FILE_APPEND);
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
}
?>