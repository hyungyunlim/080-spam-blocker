#!/usr/bin/php
<?php
/**
 * 080 수신거부 완료 후 SMS 알림 전송 스크립트
 * Asterisk에서 통화 완료 후 호출됨
 */

// CLI에서만 실행 가능
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// 인자 확인
if ($argc < 5) {
    echo "Usage: {$argv[0]} <callfile_id> <notification_phone> <target_number> <identification_number>\n";
    exit(1);
}

$callFileId = $argv[1];
$notificationPhone = $argv[2];
$targetNumber = $argv[3];
$identificationNumber = $argv[4];

// SMS 전송 클래스 포함
require_once __DIR__ . '/sms_sender.php';

try {
    $smsSender = new SMSSender();
    
    // 처리 완료 알림 SMS 전송
    $result = $smsSender->sendUnsubscribeNotification(
        $notificationPhone,
        $targetNumber,
        $identificationNumber,
        'completed'
    );
    
    // 로그 기록
    $smsSender->logSMS($result, 'completion_notification');
    
    // 결과 출력 (Asterisk 로그에 기록됨)
    if ($result['success']) {
        echo "SMS notification sent successfully to {$result['phone']}\n";
    } else {
        echo "Failed to send SMS notification: {$result['message']}\n";
    }
    
    // 상세 로그 기록
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'callfile_id' => $callFileId,
        'notification_phone' => $notificationPhone,
        'target_number' => $targetNumber,
        'identification_number' => $identificationNumber,
        'sms_result' => $result
    ];
    
    $logFile = '/var/log/asterisk/sms_completion_log.json';
    $logEntry = json_encode($logData) . "\n";
    
    // 로그 파일에 기록
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    echo "Error sending SMS notification: " . $e->getMessage() . "\n";
    
    // 오류 로그 기록
    $errorLog = '/var/log/asterisk/sms_error_log.txt';
    $errorEntry = "[" . date('Y-m-d H:i:s') . "] Error in completion SMS: " . $e->getMessage() . "\n";
    @file_put_contents($errorLog, $errorEntry, FILE_APPEND | LOCK_EX);
}

exit(0);
?> 