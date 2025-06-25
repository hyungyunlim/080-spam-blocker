<?php
// PHP 오류 로깅 설정
// Early exit for empty or non-POST requests (브라우저가 빈 요청 보낼 때 500 방지)
// 웹 요청이 아닌 CLI 호출인 경우, $_SERVER['REQUEST_METHOD'] 가 존재하지 않으므로 이 검사를 우회한다.
if (php_sapi_name() !== 'cli') {
if($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST)){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'no data']);
    exit;
    }
}
error_reporting(E_ALL);
ini_set('display_errors', 0); // 사용자에게는 오류를 보여주지 않음
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_error.log'); // 오류 로그 파일 위치

header('Content-Type: text/plain; charset=utf-8');

// 디버그 로그 파일 경로
$logFile = __DIR__ . '/logs/process_v2_debug.log';

// 로그 파일 초기화 또는 구분선 추가 (스크립트 실행 시작 지점)
@file_put_contents($logFile, "--- Script Start: " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

// 필수 클래스 포함
require_once __DIR__ . '/sms_sender.php';
// CLI 모드에서는 PatternManager 관련 기능 비활성화

// POST 데이터 로깅
@file_put_contents($logFile, "POST Data: " . json_encode($_POST) . "\n", FILE_APPEND);

// ============ CLI MODE SUPPORT ============
if (php_sapi_name() === 'cli') {
    $cliArgs = getopt('', [
        'phone:',      // --phone=080xxxxxx (필수)
        'notification::', // --notification=010...
        'id::',           // --id=1234 (식별번호)
        'auto',          // --auto 플래그 (dummy)
    ]);

    if (!isset($cliArgs['phone'])) {
        fwrite(STDERR, "--phone parameter required in CLI mode\n");
        exit(1);
    }

    // CLI 모드를 POST 에뮬레이션하여 동일 로직 재사용
    $_POST['spam_content']      = 'AUTO_CALL ' . $cliArgs['phone'];
    $_POST['notification_phone'] = $cliArgs['notification'] ?? '';
    if (isset($cliArgs['id'])) {
        $_POST['phone_number'] = $cliArgs['id'];
    }

    $isAuto = isset($cliArgs['auto']);
    if($isAuto){
        $GLOBALS['AUTO_CALL_MODE'] = true;
    }
}
// =========================================

// POST 데이터 변수 할당
$spamMessage = $_POST['spam_content'] ?? '';
$manualPhone = $_POST['phone_number'] ?? '';
$notificationPhone = $_POST['notification_phone'] ?? '';

// 1. 필수 파라미터 검증
if (empty($spamMessage)) {
    $errorMsg = "Error: Spam message is empty. Exiting.";
    @file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    die("오류: 광고 문자 내용이 비어있습니다.");
}

if (empty($notificationPhone)) {
    $errorMsg = "Error: Notification phone is empty. Exiting.";
    @file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    die("오류: 알림 받을 연락처가 비어있습니다.");
}
@file_put_contents($logFile, "Parameters validated successfully.\n", FILE_APPEND);

// 2. 080 번호 추출
preg_match('/080-?\d{3,4}-?\d{4}/', $spamMessage, $matches);
if (empty($matches)) {
    $errorMsg = "Error: 080 number not found in spam message. Exiting.";
    @file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    die("오류: 문자 내용에서 080으로 시작하는 번호를 찾을 수 없습니다.");
}
$phoneNumber = str_replace('-', '', $matches[0]);
@file_put_contents($logFile, "Extracted 080 number: " . $phoneNumber . "\n", FILE_APPEND);

// 3. 식별번호 결정 우선순위: (1) "식별번호" 키워드 뒤 4~8자리 -> (2) 문자 중 11자리 010번호 -> (3) 수동 입력 -> (4) 알림 연락처
$identificationNumber = '';

// ① "식별번호123456" 또는 "식별번호: 123456" 형태
if (preg_match('/식별번호\s*[:]?\s*([0-9]{4,11})/u', $spamMessage, $m)) {
    $identificationNumber = $m[1];
    @file_put_contents($logFile, "ID from SMS keyword: {$identificationNumber}\n", FILE_APPEND);
}

// ② 수동 입력 번호
if (empty($identificationNumber)) {
    $cleanManual = preg_replace('/[^0-9]/', '', $manualPhone);
    if (strlen($cleanManual) >= 4) {
        $identificationNumber = $cleanManual;
        @file_put_contents($logFile, "ID from manual phone: {$identificationNumber}\n", FILE_APPEND);
    }
}

// ③ 알림 연락처(SMS 발신자) fallback
if (empty($identificationNumber)) {
    $cleanNotify = preg_replace('/[^0-9]/', '', $notificationPhone);
    if (strlen($cleanNotify) >= 4 && $cleanNotify !== '01000000000') {
        $identificationNumber = $cleanNotify;
        @file_put_contents($logFile, "ID from notification phone: {$identificationNumber}\n", FILE_APPEND);
    } else {
        @file_put_contents($logFile, "WARNING: Identification number could not be determined.\n", FILE_APPEND);
    }
}

// 웹 요청일 경우 스팸 내용을 데이터베이스에 저장
if (php_sapi_name() !== 'cli' && !empty($spamMessage) && $spamMessage !== 'AUTO_CALL ' . $phoneNumber) {
    try {
        $dbPath = __DIR__ . '/spam.db';
        $db = new SQLite3($dbPath);
        
        // 동시 접근 경합 완화를 위한 설정
        $db->exec('PRAGMA journal_mode=WAL;');
        $db->busyTimeout(3000);
        
        // 사용자 ID 확인/생성
        $cleanNotifyDigits = preg_replace('/[^0-9]/', '', $notificationPhone);
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = :phone");
        $stmt->bindValue(':phone', $cleanNotifyDigits, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users(phone, verified, created_at) VALUES(:phone, 1, datetime('now'))");
            $stmt->bindValue(':phone', $cleanNotifyDigits, SQLITE3_TEXT);
            $stmt->execute();
            $userId = $db->lastInsertRowID();
        } else {
            $userId = $user['id'];
        }
        
        // SMS 내용 저장
        $stmt = $db->prepare('INSERT INTO sms_incoming (user_id, raw_text, phone080, identification, received_at, processed) VALUES (:uid, :raw, :ph080, :ident, datetime("now"), 1)');
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':raw', $spamMessage, SQLITE3_TEXT);
        $stmt->bindValue(':ph080', $phoneNumber, SQLITE3_TEXT);
        $stmt->bindValue(':ident', $identificationNumber, SQLITE3_TEXT);
        $stmt->execute();
        
        @file_put_contents($logFile, "Spam content saved to database for user {$userId}\n", FILE_APPEND);
        $db->close();
    } catch (Exception $e) {
        @file_put_contents($logFile, "Error saving spam content: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// 4. 패턴 데이터베이스 로드
$patternsFile = __DIR__ . '/patterns.json';
$patterns = [];
if (file_exists($patternsFile)) {
    $patternsData = json_decode(file_get_contents($patternsFile), true);
    if ($patternsData && isset($patternsData['patterns'])) {
        $patterns = $patternsData['patterns'];
        @file_put_contents($logFile, "Loaded " . count($patterns) . " patterns from patterns.json\n", FILE_APPEND);
    } else {
        @file_put_contents($logFile, "patterns.json is invalid or empty.\n", FILE_APPEND);
    }
} else {
    @file_put_contents($logFile, "patterns.json not found.\n", FILE_APPEND);
}

// 5. 패턴 조회 - 우선순위: 사용자 소유 → 성공한 커뮤니티 패턴 → 기본 패턴
$pattern = null;
$patternSource = 'none'; // 'user', 'community', 'default', 'none'

// 현재 사용자 식별
$currentUserPhone = preg_replace('/[^0-9]/', '', $notificationPhone);

// 1차: 현재 사용자가 소유한 패턴 찾기
if (isset($patterns[$phoneNumber])) {
    $candidatePattern = $patterns[$phoneNumber];
    if (!isset($candidatePattern['owner_phone']) || $candidatePattern['owner_phone'] === $currentUserPhone) {
        $pattern = $candidatePattern;
        $patternSource = 'user';
        @file_put_contents($logFile, "Found user-owned pattern for {$phoneNumber}\n", FILE_APPEND);
    }
}

// 2차: 사용자 패턴이 없으면 성공한 커뮤니티 패턴 찾기
if (!$pattern && isset($patterns[$phoneNumber])) {
    $candidatePattern = $patterns[$phoneNumber];
    
    // 다른 사용자 소유이지만 성공 기록이 있는 패턴
    if (isset($candidatePattern['owner_phone']) && 
        $candidatePattern['owner_phone'] !== $currentUserPhone &&
        isset($candidatePattern['success_stats']) &&
        $candidatePattern['success_stats']['success'] > 0) {
        
        $pattern = $candidatePattern;
        $patternSource = 'community';
        @file_put_contents($logFile, "Found community pattern for {$phoneNumber} (owner: {$candidatePattern['owner_phone']}, success: {$candidatePattern['success_stats']['success']})\n", FILE_APPEND);
    }
}

@file_put_contents($logFile, "Pattern search result for {$phoneNumber}: " . ($pattern ? "Found ({$patternSource})" : 'Not Found') . "\n", FILE_APPEND);

// 자동호출 지원 여부 확인
if ($pattern && isset($pattern['auto_supported']) && !$pattern['auto_supported']) {
    echo "⚠️  자동 수신거부가 불가능합니다.\n";
    echo "   이 음성 시스템은 본인확인을 위해 고객님의 전화번호 입력을 요구하거나,\n";
    echo "   통화가 시작되자마자 '1번을 누르세요' 라는 확인만 받습니다.\n";
    echo "   시스템이 대신 '1번'을 눌러줄 방법은 있지만, 사용자의 전화번호를 대신 입력할 수 없어서\n";
    echo "   자동 처리(수신거부 완료)까지 진행할 수 없습니다.\n";
    echo "   녹음 파일을 참고하여 직접 전화를 걸어 수신거부를 진행해주세요.\n";
    @file_put_contents($logFile, "Pattern for {$phoneNumber} is confirm_only – aborting automatic call.\n", FILE_APPEND);
    exit;
}

// 6. 패턴이 없을 경우 → ① 기본 패턴으로 먼저 시도, ② 실패 시 디스커버리 전환
if (!$pattern) {
    if (isset($patterns['default'])) {
        $pattern = $patterns['default'];
        $patternSource = 'default';
        $pattern['name'] = $pattern['name'] ?? '기본 패턴';
        echo "ℹ️  등록된 패턴이 없어 기본 패턴으로 먼저 시도합니다.\n";
        @file_put_contents($logFile, "Pattern not found – using default pattern first.\n", FILE_APPEND);
    } else {
        @file_put_contents($logFile, "Pattern not found and no default. Starting discovery.\n", FILE_APPEND);
    echo "🔍 패턴이 없습니다! 패턴 디스커버리를 시작합니다: {$phoneNumber}\n";
    
    $discovery = new PatternDiscovery();
    $result = $discovery->startDiscovery($phoneNumber, $notificationPhone);

    $smsSender = new SmsSender();
    $smsSender->logSMS($result, 'pattern_discovery_started');
    
    @file_put_contents($logFile, "Exiting after starting discovery.\n--- Script End ---\n\n", FILE_APPEND);
    exit("패턴 학습 중입니다. 완료 후 다시 시도해주세요.");
    }
}

// 7. 패턴이 존재할 경우, Call File 생성 준비
@file_put_contents($logFile, "Pattern found. Preparing to create Call File.\n", FILE_APPEND);

// 패턴 소스에 따른 메시지 표시
switch($patternSource) {
    case 'user':
        echo "✅ 사용자 패턴으로 수신거부를 시작합니다.\n";
        break;
    case 'community':
        echo "🌐 커뮤니티 검증 패턴으로 수신거부를 시작합니다.\n";
        echo "   (다른 사용자가 성공한 패턴을 사용합니다)\n";
        break;
    case 'default':
        echo "⚙️ 기본 패턴으로 수신거부를 시도합니다.\n";
        break;
}

$dtmfToSend = $pattern['dtmf_pattern'];

// 알림 연락처(숫자만)
$cleanNotifyDigits = preg_replace('/[^0-9]/', '', $notificationPhone);

// AUTO_CALL_MODE(=SMS 경로) 일 때는 {Phone} 을 발신자 번호(알림 연락처)로 사용하고,
// 수동(UI) 경로에서는 080 수신거부 대상 번호를 사용한다.
$isAuto = !empty($GLOBALS['AUTO_CALL_MODE']);
$valueForPhone = $isAuto ? $cleanNotifyDigits : $phoneNumber;

$tokens = [
    '{ID}'     => $identificationNumber,
    '{Notify}' => $cleanNotifyDigits,
    '{Phone}'  => $valueForPhone,
];

foreach ($tokens as $search => $replacement) {
    $dtmfToSend = str_ireplace($search, $replacement, $dtmfToSend);
}

$dtmfToSend = ltrim($dtmfToSend, ','); // 맨 앞 콤마 제거

@file_put_contents($logFile, "Final DTMF sequence: " . $dtmfToSend . "\n", FILE_APPEND);
echo "추출된 080번호: " . $phoneNumber . "\n";
echo "최종 식별번호: " . $identificationNumber . "\n";
echo "적용될 DTMF 패턴: " . $dtmfToSend . "\n";

// 8. Asterisk DB에 변수 저장
$uniqueId = uniqid();
// --- DB log (unsubscribe_calls) ----------------------------------
try{
    $dbPath= __DIR__.'/spam.db';
    $dbUC = new SQLite3($dbPath);
    $dbUC->exec('PRAGMA journal_mode=WAL;');
    $dbUC->busyTimeout(3000);
    $uidRow = $dbUC->querySingle("SELECT id FROM users WHERE phone='{$cleanNotifyDigits}'",true);
    $uidVal = $uidRow ? (int)$uidRow['id'] : null;
    $stmtUC = $dbUC->prepare('INSERT OR IGNORE INTO unsubscribe_calls (call_id,user_id,phone080,identification,created_at,status,pattern_source,notification_phone) VALUES (:cid,:uid,:p080,:ident,datetime("now"),"pending",:pattern_source,:notify)');
    $stmtUC->bindValue(':cid',$uniqueId,SQLITE3_TEXT);
    if($uidVal!==null){$stmtUC->bindValue(':uid',$uidVal,SQLITE3_INTEGER);} else {$stmtUC->bindValue(':uid',null,SQLITE3_NULL);}
    $stmtUC->bindValue(':p080',$phoneNumber,SQLITE3_TEXT);
    $stmtUC->bindValue(':ident',$identificationNumber,SQLITE3_TEXT);
    $stmtUC->bindValue(':pattern_source',$patternSource,SQLITE3_TEXT);
    $stmtUC->bindValue(':notify',$notificationPhone,SQLITE3_TEXT);
    $stmtUC->execute();
}catch(Throwable $e){ /* ignore db errors */ }

exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf {$dtmfToSend}\" 2>/dev/null");
exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf_sequence {$dtmfToSend}\" 2>/dev/null");
exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} notification_phone {$notificationPhone}\" 2>/dev/null");
exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} identification_number {$identificationNumber}\" 2>/dev/null");
exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} recnum {$phoneNumber}\" 2>/dev/null");
@file_put_contents($logFile, "Variables stored in AstDB for ID: {$uniqueId}\n", FILE_APPEND);

// 9. Call File 내용 생성
$callFileContent = "Channel: quectel/quectel0/{$phoneNumber}\n";
$callFileContent .= "CallerID: \"Spam Blocker\" <0212345678>\n";
$callFileContent .= "MaxRetries: 0\n";
$callFileContent .= "RetryTime: 60\n";
$callFileContent .= "WaitTime: 45\n";
$callFileContent .= "Context: callfile-handler\n";
$callFileContent .= "Extension: s\n";
$callFileContent .= "Priority: 1\n";
$callFileContent .= "Set: CALL_ID={$uniqueId}\n";
$callFileContent .= "Set: CALLFILE_ID={$uniqueId}\n";
$callFileContent .= "Set: __AUTO_SUCCESS_CHECK=1\n";
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

// 자동 호출 플래그 전달 (녹음 파일 이름 태깅에 사용)
if(!empty($GLOBALS['AUTO_CALL_MODE'])){
    $callFileContent .= "Set: __AUTO_CALL=1\n";
}

// 10. Call File 생성 – 반드시 .call 확장자를 사용 (queue_runner 는 *.call 검색)
// PrivateTmp=true 설정 때문에 시스템의 /tmp 대신 프로젝트 내부에 임시 파일 생성
$tempDir = __DIR__ . '/tmp_calls/';
$rawTemp = tempnam($tempDir, 'call_'); // 확장자 없음
$tempFile = $rawTemp . '.call';
rename($rawTemp, $tempFile);

file_put_contents($tempFile, $callFileContent);
@file_put_contents($logFile, "Temporary Call File created at: {$tempFile}\n", FILE_APPEND);

// ---- New: create initial call_progress log ----
$progressDir = '/var/log/asterisk/call_progress';
if (!is_dir($progressDir)) {
    mkdir($progressDir, 0775, true);
}
$progressLog = $progressDir . '/' . $uniqueId . '.log';
$initLine = date('Y-m-d H:i:s') . " [{$uniqueId}] CALL_CREATED TO_{$phoneNumber} ID_{$identificationNumber}\n";
file_put_contents($progressLog, $initLine, FILE_APPEND);
// ----------------------------------------------

chmod($tempFile, 0644);
exec("echo 'hacker03' | sudo -S chown asterisk:asterisk '{$tempFile}' 2>/dev/null");

// =================================================================================
//  모뎀 상태 확인 – quectel 채널이 활동 중이면 Busy 로 판단
//  Busy 시 call_queue 로, Idle(Free) 시 즉시 outgoing 으로 이동
// =================================================================================

$queueDir = __DIR__ . '/call_queue/';
if(!is_dir($queueDir)) mkdir($queueDir, 0775, true);

function modem_is_busy(): bool {
    $out = [];
    exec('echo "hacker03" | sudo -S /usr/sbin/asterisk -rx "quectel show devices" 2>/dev/null | grep quectel0', $modemStatus);
    exec('echo "hacker03" | sudo -S /usr/sbin/asterisk -rx "quectel show devices" 2>/dev/null | grep quectel0 | grep Free', $out);
    
    $status = isset($modemStatus[0]) ? trim($modemStatus[0]) : 'Unknown';
    $isFree = count($out) > 0;
    
    error_log("[PROCESS_V2] Modem status check: {$status}, isFree: " . ($isFree ? 'true' : 'false'));
    
    return !$isFree; // not Free → busy
}

$destinationDir = modem_is_busy() ? $queueDir : '/var/spool/asterisk/outgoing/';

$finalFile = $destinationDir . basename($tempFile);

if (rename($tempFile, $finalFile)) {
    $destLabel = ($destinationDir === $queueDir) ? 'Queued' : 'Spool';
    @file_put_contents($logFile, "Call File moved to: {$finalFile} ({$destLabel}).\n", FILE_APPEND);
    if($destinationDir === $queueDir){
        echo "성공: Call File이 큐에 등록되었습니다. 모뎀이 통화 중이므로 여유가 생기면 순차 발신됩니다.\n";
    } else {
        echo "성공: Call File이 생성되어 즉시 발신 대기 중입니다.\n";
    }
    echo "알림 연락처: {$notificationPhone}\n";

    $smsSender = new SmsSender();
    $result = $smsSender->sendProcessStartNotification($notificationPhone, $phoneNumber, $identificationNumber);
    $smsSender->logSMS($result, 'call_start_notification');

    // 패턴 사용 통계 업데이트 (웹 모드에서만)
    if (php_sapi_name() !== 'cli') {
        require_once __DIR__ . '/pattern_manager.php';
        try {
            $pm = new PatternManager(__DIR__ . '/patterns.json');
            $pm->recordPatternUsage($phoneNumber, true);
        } catch (Exception $e) {
            error_log('Pattern usage update failed: ' . $e->getMessage());
        }
    }
    
    // 수신거부 시작 알림 SMS 발송
    $smsSender = new SmsSender();
    $notificationResult = $smsSender->sendUnsubscribeNotification($notificationPhone, $phoneNumber, $identificationNumber, 'started');
    @file_put_contents($logFile, "Start notification sent: " . ($notificationResult['success'] ? 'success' : 'failed') . "\n", FILE_APPEND);
    
    echo "\n💡 안내: 전화 연결 후 '실패'로 표시되면 아래 방법을 시도해 보세요.\n • \"녹음 듣기\" 버튼으로 안내 음성을 확인합니다.\n • 화면의 '패턴 추가' 메뉴에서 안내에 맞게 버튼/번호 입력 순서를 저장하면 다음부터 자동으로 처리됩니다.";

} else {
    $errorMsg = "Error: Failed to move Call File to spool directory. Check permissions.";
    @file_put_contents($logFile, $errorMsg . "\n", FILE_APPEND);
    error_log($errorMsg . " Temp file: {$tempFile}, Final file: {$finalFile}");
    echo "오류: Call File을 생성하지 못했습니다.\n";
    
    $smsSender = new SmsSender();
    $failureResult = $smsSender->sendUnsubscribeNotification($notificationPhone, $phoneNumber, $identificationNumber, 'error');
    @file_put_contents($logFile, "Error notification sent: " . ($failureResult['success'] ? 'success' : 'failed') . "\n", FILE_APPEND);
}

@file_put_contents($logFile, "--- Script End ---\n\n", FILE_APPEND);

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