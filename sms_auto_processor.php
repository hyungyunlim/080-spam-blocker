<?php
/**
 * sms_auto_processor.php
 * Dialplan [incoming-mobile], sms extension에서 호출.
 *   --caller   : 발신자 번호 (CALLERID(num))
 *   --msg_base64 : BASE64 인코딩된 SMS 본문 (Asterisk 변수 ${SMS_BASE64})
 *
 * 1. 메시지 디코딩
 * 2. 080 번호와 식별번호 추출
 * 3. process_v2.php 를 CLI 모드로 호출 (자동 플래그)
 * 4. 중복 방지: 직전 5분 내 동일 080+ID 조합이 이미 호출되었으면 스킵
 */

$options = getopt('', [
    'caller:',
    'msg_base64:'
]);

$caller = $options['caller'] ?? '';
$base64 = $options['msg_base64'] ?? '';
if ($base64 === '') {
    fwrite(STDERR, "msg_base64 parameter required\n");
    exit(1);
}

$smsRaw = base64_decode($base64);
if ($smsRaw === false) {
    fwrite(STDERR, "base64 decode failed\n");
    exit(1);
}

// === DB 저장 (SQLite) =====================================================
try {
    // central DB
    $dbPath = __DIR__ . '/spam.db';
    $db = new SQLite3($dbPath);
    // ensure schema present (users etc.)
    $schemaFile = __DIR__ . '/schema.sql';
    if(file_exists($schemaFile)){
        $db->exec(file_get_contents($schemaFile));
    }
    // ---------------- User handling ----------------
    $callerClean = preg_replace('/[^0-9]/','',$caller);
    $user = $db->querySingle("SELECT id, verified FROM users WHERE phone='{$callerClean}'", true);
    if(!$user){
        $db->exec("INSERT INTO users(phone) VALUES('{$callerClean}')");
        $user = ['id'=>$db->lastInsertRowID(),'verified'=>0];
    }
    $userId = (int)$user['id'];

    // insert incoming SMS
    $stmt = $db->prepare('INSERT INTO sms_incoming (user_id, raw_text, received_at, phone080, identification, processed) VALUES (:uid,:raw,:ts,:ph,:id,0)');
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':raw', $smsRaw, SQLITE3_TEXT);
    $stmt->bindValue(':ts', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->bindValue(':ph', '', SQLITE3_TEXT); // placeholder
    $stmt->bindValue(':id', '', SQLITE3_TEXT);
    $stmt->execute();
    $rowId = $db->lastInsertRowID();
} catch (Throwable $e) {
    // silent fail – DB error should not interrupt main flow
}

// 1. 080 번호 추출
if (!preg_match('/080-?\d{3,4}-?\d{4}/', $smsRaw, $m)) {
    // 광고번호가 없으면 무시
    exit(0);
}
$phone080 = str_replace('-', '', $m[0]);

// DB update with extracted phone and id
if(isset($db) && isset($rowId)){
    $upd = $db->prepare('UPDATE sms_incoming SET phone080=:ph, identification=:id WHERE id=:rid');
    $upd->bindValue(':ph', $phone080, SQLITE3_TEXT);
    $upd->bindValue(':id', '', SQLITE3_TEXT); // identification will be filled later
    $upd->bindValue(':rid', $rowId, SQLITE3_INTEGER);
    $upd->execute();
}

// 2. 식별번호 추출: "식별번호" 키워드 또는 010
$id = '';
if (preg_match('/식별번호\s*:?\s*([0-9]{4,8})/u', $smsRaw, $m2)) {
    $id = $m2[1];
} elseif (preg_match('/010[-\s]?\d{3,4}[-\s]?\d{4}(?:#)?/', $smsRaw, $m3)) {
    // 010 전화번호(하이픈/공백/#, 국제번호 제외) 패턴 매칭
    $id = preg_replace('/[^0-9]/', '', $m3[0]); // 숫자만 추출
}
if ($id === '') {
    // fallback: 발신자 전체 번호 사용 ("010xxxxxxxx" 형태)
    $cleanCaller = preg_replace('/[^0-9]/', '', $caller);
    if (strlen($cleanCaller) >= 8) {
        $id = $cleanCaller;
    } else {
        // 최종 안전망: 마지막 4자리
        $id = substr($cleanCaller, -4);
    }
}

// === Enhanced Duplicate Suppression ===============
// Startup Manager temporarily removed due to technical issues

// 1) Persistent check in SQLite – 1시간 내 동일 080번호+식별번호 중복 방지
$dupWindow = 3600; // 1 hour
try {
    if(isset($db)){
        $q = $db->prepare('SELECT COUNT(*) AS cnt FROM sms_incoming WHERE phone080=:ph AND identification=:id AND received_at >= datetime("now", "-1 hour")');
        $q->bindValue(':ph', $phone080, SQLITE3_TEXT);
        $q->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $q->execute()->fetchArray(SQLITE3_ASSOC);
        if($res && $res['cnt'] > 0){
            // 이미 처리된 조합 (1시간 내) - 1개 이상 있으면 중복
            exit(0);
        }
    }
} catch(Throwable $e){ }

// 1.5) 재시작 후 보안 모드 - REMOVED for now

// 2) Legacy lock-file 방식 – 2분 내 동일 요청 중복 방지
$lockKey = "/tmp/smslock_{$phone080}_{$id}";
$ttlSec  = 120; // 2 minutes
if (file_exists($lockKey) && time() - filemtime($lockKey) < $ttlSec) {
    exit(0);
}
@touch($lockKey);

// 3. 호출 진행 상황 체크 – 2분 내 동일 번호 처리 중 확인
$logDir = '/var/log/asterisk/call_progress';
$recentLogs = glob($logDir . '/*.log');
foreach ($recentLogs as $file) {
    if (filemtime($file) < time() - 120) continue; // 2분 이내 로그만 확인
    $cnt = file_get_contents($file);
    if (strpos($cnt, "TO_{$phone080}") !== false && strpos($cnt, $id) !== false) {
        // 이미 처리 중
        exit(0);
    }
}

// 4. process_v2.php 호출
$cmd = "php " . __DIR__ . "/process_v2.php --phone={$phone080} --id={$id} --notification={$caller} --auto";
error_log("SMS_AUTO: Executing command: " . $cmd);
$output = [];
$returnCode = 0;
exec($cmd . " 2>&1", $output, $returnCode);
error_log("SMS_AUTO: Command result - code: {$returnCode}, output: " . implode("\n", $output));

// final update of identification
if(isset($db) && isset($rowId)){
    $upd2 = $db->prepare('UPDATE sms_incoming SET identification=:id WHERE id=:rid');
    $upd2->bindValue(':id', $id, SQLITE3_TEXT);
    $upd2->bindValue(':rid', $rowId, SQLITE3_INTEGER);
    $upd2->execute();
}

// 정규화된 발신자 번호 (숫자만)
$callerClean = preg_replace('/[^0-9]/','',$caller);

// --- if user not verified: send code then exit ---
if(isset($user) && !$user['verified']){
    $code = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
    $exp  = time()+600;
    $db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$userId},'{$code}',{$exp})");
    require_once __DIR__ . '/sms_sender.php';
    (new SMSSender())->sendVerificationCode($callerClean, $code);
    exit(0);
}

exit(0);
?> 