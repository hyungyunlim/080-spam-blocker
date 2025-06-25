#!/usr/bin/php
<?php
/**
 * 음성 분석 완료 후 SMS 알림 전송 스크립트
 * analyze_recording.php에서 분석 완료 후 호출됨
 */

// CLI에서만 실행 가능
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// 인자 확인
if ($argc < 6) {
    echo "Usage: {$argv[0]} <notification_phone> <target_number> <identification_number> <analysis_result> <confidence> <recording_file>\n";
    exit(1);
}

$notificationPhone = $argv[1];
$targetNumber = $argv[2];
$identificationNumber = $argv[3];
$analysisResult = $argv[4];
$confidence = intval($argv[5]);
$recordingFile = $argv[6];

// SMS 전송 클래스 포함
require_once __DIR__ . '/sms_sender.php';

try {
    $smsSender = new SMSSender();
    
    // 분석 완료 알림 SMS 전송
    $result = $smsSender->sendAnalysisCompleteNotification(
        $notificationPhone,
        $targetNumber,
        $identificationNumber,
        $analysisResult,
        $confidence,
        $recordingFile
    );
    
    // 로그 기록
    $smsSender->logSMS($result, 'analysis_complete_notification');
    
    // 결과 출력
    if ($result['success']) {
        echo "Analysis complete SMS sent successfully to {$result['phone']}\n";
        echo "Analysis result: {$analysisResult} (confidence: {$confidence}%)\n";
        echo "Recording file: {$recordingFile}\n";
    } else {
        echo "Failed to send analysis complete SMS: {$result['message']}\n";
    }
    
    // 상세 로그 기록
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'notification_phone' => $notificationPhone,
        'target_number' => $targetNumber,
        'identification_number' => $identificationNumber,
        'analysis_result' => $analysisResult,
        'confidence' => $confidence,
        'recording_file' => $recordingFile,
        'sms_result' => $result
    ];
    
    $logFile = '/var/log/asterisk/sms_analysis_log.json';
    $logEntry = json_encode($logData) . "\n";
    
    // 로그 파일에 기록
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    echo "Error sending analysis complete SMS: " . $e->getMessage() . "\n";
    
    // 오류 로그 기록
    $errorLog = '/var/log/asterisk/sms_analysis_error_log.txt';
    $errorEntry = "[" . date('Y-m-d H:i:s') . "] Error in analysis complete SMS: " . $e->getMessage() . "\n";
    @file_put_contents($errorLog, $errorEntry, FILE_APPEND | LOCK_EX);
}

exit(0);
?> 