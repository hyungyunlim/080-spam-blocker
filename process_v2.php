<?php
// PHP 오류 로깅 설정
// Early exit for empty or non-POST requests (브라우저가 빈 요청 보낼 때 500 방지)
if($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST)){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'no data']);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', 0); // 사용자에게는 오류를 보여주지 않음
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_error.log'); // 오류 로그 파일 위치

header('Content-Type: text/plain; charset=utf-8');

// 디버그 로그 파일 경로
$logFile = __DIR__ . '/logs/process_v2_debug.log';

// 로그 파일 초기화 또는 구분선 추가 (스크립트 실행 시작 지점)
file_put_contents($logFile, "--- Script Start: " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

// 필수 클래스 포함
require_once __DIR__ . '/sms_sender.php';
require_once __DIR__ . '/pattern_discovery.php';

// POST 데이터 로깅
file_put_contents($logFile, "POST Data: " . json_encode($_POST) . "\n", FILE_APPEND);

// POST 데이터 변수 할당
$spamMessage = $_POST['spam_content'] ?? '';
$manualPhone = $_POST['phone_number'] ?? '';
$notificationPhone = $_POST['notification_phone'] ?? '';

// 1. 필수 파라미터 검증
if (empty($spamMessage)) {
    $errorMsg = "Error: Spam message is empty. Exiting.";
    file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    die("오류: 광고 문자 내용이 비어있습니다.");
}

if (empty($notificationPhone)) {
    $errorMsg = "Error: Notification phone is empty. Exiting.";
    file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    die("오류: 알림 받을 연락처가 비어있습니다.");
}
file_put_contents($logFile, "Parameters validated successfully.\n", FILE_APPEND);

// 2. 080 번호 추출
preg_match('/080-?\d{3,4}-?\d{4}/', $spamMessage, $matches);
if (empty($matches)) {
    $errorMsg = "Error: 080 number not found in spam message. Exiting.";
    file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    die("오류: 문자 내용에서 080으로 시작하는 번호를 찾을 수 없습니다.");
}
$phoneNumber = str_replace('-', '', $matches[0]);
file_put_contents($logFile, "Extracted 080 number: " . $phoneNumber . "\n", FILE_APPEND);

// 3. 식별번호 결정 우선순위: (1) "식별번호" 키워드 뒤 4~8자리 -> (2) 문자 중 11자리 010번호 -> (3) 수동 입력 -> (4) 알림 연락처
$identificationNumber = '';

// ① "식별번호123456" 또는 "식별번호: 123456" 형태
if (preg_match('/식별번호\s*[:]?\s*([0-9]{4,8})/u', $spamMessage, $m)) {
    $identificationNumber = $m[1];
    file_put_contents($logFile, "ID from SMS keyword: {$identificationNumber}\n", FILE_APPEND);
}

// ② 11자리 010 번호 (ID 대신 전화번호를 요구하는 일부 업체용)
if (empty($identificationNumber) && preg_match('/010\d{8}/', $spamMessage, $m2)) {
    $identificationNumber = $m2[0];
    file_put_contents($logFile, "ID from SMS 010-number: {$identificationNumber}\n", FILE_APPEND);
}

// ③ 수동 입력 번호
if (empty($identificationNumber)) {
    $cleanManual = preg_replace('/[^0-9]/', '', $manualPhone);
    if (strlen($cleanManual) >= 4) {
        $identificationNumber = $cleanManual;
        file_put_contents($logFile, "ID from manual phone: {$identificationNumber}\n", FILE_APPEND);
    }
}

// ④ 알림 연락처 fallback
if (empty($identificationNumber)) {
    $cleanNotify = preg_replace('/[^0-9]/', '', $notificationPhone);
    if (strlen($cleanNotify) >= 4) {
        $identificationNumber = $cleanNotify;
        file_put_contents($logFile, "ID from notification phone: {$identificationNumber}\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "WARNING: Identification number could not be determined.\n", FILE_APPEND);
    }
}

// 4. 패턴 데이터베이스 로드
$patternsFile = __DIR__ . '/patterns.json';
$patterns = [];
if (file_exists($patternsFile)) {
    $patternsData = json_decode(file_get_contents($patternsFile), true);
    if ($patternsData && isset($patternsData['patterns'])) {
        $patterns = $patternsData['patterns'];
        file_put_contents($logFile, "Loaded " . count($patterns) . " patterns from patterns.json\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "patterns.json is invalid or empty.\n", FILE_APPEND);
    }
} else {
    file_put_contents($logFile, "patterns.json not found.\n", FILE_APPEND);
}

// 5. 해당 번호의 패턴 조회
$pattern = $patterns[$phoneNumber] ?? null;
file_put_contents($logFile, "Pattern for {$phoneNumber}: " . ($pattern ? 'Found' : 'Not Found') . "\n", FILE_APPEND);

// 6. 패턴이 없을 경우, 패턴 디스커버리 실행
if (!$pattern) {
    file_put_contents($logFile, "Pattern not found. Starting pattern discovery for {$phoneNumber}.\n", FILE_APPEND);
    echo "🔍 패턴이 없습니다! 패턴 디스커버리를 시작합니다: {$phoneNumber}\n";
    
    $discovery = new PatternDiscovery();
    $result = $discovery->startDiscovery($phoneNumber, $notificationPhone);

    $smsSender = new SmsSender();
    $smsSender->logSMS($result, 'pattern_discovery_started');
    
    file_put_contents($logFile, "Exiting after starting discovery.\n--- Script End ---\n\n", FILE_APPEND);
    exit("패턴 학습 중입니다. 완료 후 다시 시도해주세요.");
}

// 7. 패턴이 존재할 경우, Call File 생성 준비
file_put_contents($logFile, "Pattern found. Preparing to create Call File.\n", FILE_APPEND);

$dtmfToSend = $pattern['dtmf_pattern'];
$dtmfToSend = str_replace('{ID}', $identificationNumber, $dtmfToSend);
$dtmfToSend = str_replace('{Phone}', $phoneNumber, $dtmfToSend);
$cleanNotifyDigits = preg_replace('/[^0-9]/', '', $notificationPhone);
$dtmfToSend = str_replace('{Notify}', $cleanNotifyDigits, $dtmfToSend);
$dtmfToSend = str_ireplace('{notify}', $cleanNotifyDigits, $dtmfToSend);
$dtmfToSend = str_ireplace('{phone}', $phoneNumber, $dtmfToSend);
$dtmfToSend = str_ireplace('{id}', $identificationNumber, $dtmfToSend);
$dtmfToSend = ltrim($dtmfToSend, ','); // 맨 앞 콤마 제거

file_put_contents($logFile, "Final DTMF sequence: " . $dtmfToSend . "\n", FILE_APPEND);
echo "추출된 080번호: " . $phoneNumber . "\n";
echo "최종 식별번호: " . $identificationNumber . "\n";
echo "적용될 DTMF 패턴: " . $dtmfToSend . "\n";

// 8. Asterisk DB에 변수 저장
$uniqueId = uniqid();
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf {$dtmfToSend}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf_sequence {$dtmfToSend}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} notification_phone {$notificationPhone}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} identification_number {$identificationNumber}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} recnum {$phoneNumber}\"");
file_put_contents($logFile, "Variables stored in AstDB for ID: {$uniqueId}\n", FILE_APPEND);

// 9. Call File 내용 생성
$callFileContent = "Channel: quectel/quectel0/{$phoneNumber}\n";
$callFileContent .= "CallerID: \"Spam Blocker\" <0212345678>\n";
$callFileContent .= "MaxRetries: 1\n";
$callFileContent .= "RetryTime: 60\n";
$callFileContent .= "WaitTime: 45\n";
$callFileContent .= "Context: callfile-handler\n";
$callFileContent .= "Extension: s\n";
$callFileContent .= "Priority: 1\n";
$callFileContent .= "Set: CALL_ID={$uniqueId}\n";
$callFileContent .= "Set: CALLFILE_ID={$uniqueId}\n";
$callFileContent .= "Set: __CONFIRM_WAIT={$pattern['confirmation_wait']}\n";
$callFileContent .= "Set: __TOTAL_DURATION={$pattern['total_duration']}\n";
$callFileContent .= "Set: __INITIAL_WAIT={$pattern['initial_wait']}\n";
$callFileContent .= "Set: __DTMF_TIMING={$pattern['dtmf_timing']}\n";
$confirmDelay = isset($pattern['confirm_delay']) ? $pattern['confirm_delay'] : (isset($pattern['confirmation_wait']) ? $pattern['confirmation_wait'] : 2);
$callFileContent .= "Set: __CONFIRM_DELAY={$confirmDelay}\n";
$callFileContent .= "Set: TOTAL_DURATION={$pattern['total_duration']}\n";
$confirmDtmfRaw = isset($pattern['confirmation_dtmf']) ? trim($pattern['confirmation_dtmf']) : '';
if ($confirmDtmfRaw !== '') {
    $callFileContent .= "Set: __CONFIRM_DTMF={$confirmDtmfRaw}\n";
    $confirmRepeat = isset($pattern['confirm_repeat']) ? $pattern['confirm_repeat'] : 1;
    $callFileContent .= "Set: __CONFIRM_REPEAT={$confirmRepeat}\n";
} else {
    // 확인 DTMF가 비어 있으면 반복 횟수를 0으로 설정하여 dialplan이 바로 넘어가도록 한다
    $callFileContent .= "Set: __CONFIRM_REPEAT=0\n";
}

// 10. Call File 생성 및 스풀 디렉토리로 이동
// PrivateTmp=true 설정 때문에 시스템의 /tmp 대신 프로젝트 내부에 임시 파일 생성
$tempDir = __DIR__ . '/tmp_calls/';
$tempFile = tempnam($tempDir, 'call_');

file_put_contents($tempFile, $callFileContent);
file_put_contents($logFile, "Temporary Call File created at: {$tempFile}\n", FILE_APPEND);

chown($tempFile, 'asterisk');
chgrp($tempFile, 'asterisk');

$spoolDir = '/var/spool/asterisk/outgoing/';
$finalFile = $spoolDir . basename($tempFile);

if (rename($tempFile, $finalFile)) {
    file_put_contents($logFile, "Call File moved to: {$finalFile}. Success.\n", FILE_APPEND);
    echo "성공: Call File이 생성되었습니다. Asterisk가 곧 전화를 걸 것입니다.\n";
    echo "알림 연락처: {$notificationPhone}\n";

    $smsSender = new SmsSender();
    $message = "[성공] 080 스팸 수신거부 요청 완료\n번호: {$phoneNumber}";
    $smsSender->sendSms($notificationPhone, $message);
    $smsSender->logSMS($message, 'call_file_created');

    // 패턴 사용 통계 업데이트
    require_once __DIR__ . '/PatternManager.php';
    try {
        $pm = new PatternManager();
        $pm->recordPatternUsage($phoneNumber, true);
    } catch (Exception $e) {
        error_log('Pattern usage update failed: ' . $e->getMessage());
    }
    echo "\n💡 팁: 이 번호가 처음이거나 패턴이 맞지 않으면, 녹음을 들어보고 patterns.json을 업데이트하세요!";

} else {
    $errorMsg = "Error: Failed to move Call File to spool directory. Check permissions.";
    file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    error_log($errorMsg . " Temp file: {$tempFile}, Final file: {$finalFile}");
    echo "오류: Call File을 생성하지 못했습니다.\n";
    
    $smsSender = new SmsSender();
    $message = "[실패] 080 스팸 수신거부 요청 실패\n번호: {$phoneNumber}";
    $smsSender->sendSms($notificationPhone, $message);
    $smsSender->logSMS($message, 'call_file_failed');
}

file_put_contents($logFile, "--- Script End ---\n\n", FILE_APPEND);

// 결과 메시지 생성 시, 각 줄 앞뒤 공백을 제거
function formatResultMessage($lines) {
    return implode("\n", array_map('trim', $lines));
}

// 예시:
// $lines = [
//     "추출된 080번호: $number",
//     "최종 식별번호: $id",
//     ...
// ];
// $result = formatResultMessage($lines);
// ...
// echo $result;
?> 