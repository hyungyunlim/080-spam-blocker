<?php
// SMS 알림 테스트 스크립트
$callId = '6850323dca035';
$logFile = "/var/log/asterisk/call_progress/{$callId}.log";

try {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG_START\n", FILE_APPEND);
    
    $notifyVal = trim(exec("sudo /usr/sbin/asterisk -rx \"database get CallFile/{$callId} notification_phone\""));
    $notifyPhone = preg_replace('/[^0-9]/', '', str_replace('Value:', '', $notifyVal));
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG notify_raw={$notifyVal} clean={$notifyPhone}\n", FILE_APPEND);

    $identVal = trim(exec("sudo /usr/sbin/asterisk -rx \"database get CallFile/{$callId} identification\""));
    $identNumber = preg_replace('/[^0-9]/', '', str_replace('Value:', '', $identVal));
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG ident_raw={$identVal} clean={$identNumber}\n", FILE_APPEND);

    $phoneNumber = '';
    if ($phoneNumber === '') {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $row = $db->querySingle("SELECT phone080, identification FROM unsubscribe_calls WHERE call_id = '{$callId}' LIMIT 1", true);
        if ($row && !empty($row['phone080'])) {
            $phoneNumber = $row['phone080'];
            $identNumber = $row['identification'];
            file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG DB_phone={$phoneNumber} DB_ident={$identNumber}\n", FILE_APPEND);
        }
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG final_notify={$notifyPhone} final_phone080={$phoneNumber}\n", FILE_APPEND);

    if ($notifyPhone !== '' && $phoneNumber !== '') {
        require_once __DIR__ . '/sms_sender.php';
        $smsSender = new SMSSender();
        $confidence = 75;
        $status = 'failed';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_DEBUG sending to {$notifyPhone}, status={$status}, conf={$confidence}\n", FILE_APPEND);
        
        $notificationResult = $smsSender->sendAnalysisCompleteNotification(
            $notifyPhone,
            $phoneNumber,
            $identNumber,
            $status,
            $confidence,
            '20250617-000327-FROM_AUTO-ID_6850323dca035-TO_0808895050.wav'
        );
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_SENT " . ($notificationResult['success'] ? 'SUCCESS' : 'FAILED') . " to {$notifyPhone} msg:" . ($notificationResult['message'] ?? 'no_msg') . "\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_SKIP notify={$notifyPhone} phone={$phoneNumber}\n", FILE_APPEND);
    }
} catch (Throwable $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] SMS_ERR " . $e->getMessage() . "\n", FILE_APPEND);
}

echo "SMS notification test completed. Check log file.\n";
?>