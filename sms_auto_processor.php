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
    // 동시 접속 경합 감소: WAL 모드 + 3초 busy timeout
    $db->exec('PRAGMA journal_mode=WAL;');
    $db->busyTimeout(3000);
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
        error_log("[SMS_AUTO] New user created with phone: {$callerClean}");
    } else {
        error_log("[SMS_AUTO] Existing user found: {$callerClean}, verified: {$user['verified']}");
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

// 1. 인증번호 처리 우선 확인 (6자리 숫자)
if (preg_match('/^\s*(\d{6})\s*$/u', $smsRaw, $codeMatch)) {
    $verificationCode = $codeMatch[1];
    
    // 사용자 확인 - 이미 위에서 로드됨
    if (!isset($user) || $user['verified']) {
        // 이미 인증된 사용자이거나 사용자 정보가 없으면 무시
        error_log("[SMS_AUTO] Verification code from already verified user or unknown user: {$callerClean}");
        exit(0);
    }
    
    // 인증코드 검증
    $vc = $db->querySingle("SELECT id, expires_at FROM verification_codes WHERE user_id={$userId} AND code='{$verificationCode}' AND used=0 ORDER BY id DESC LIMIT 1", true);
    
    if (!$vc) {
        // 잘못된 인증번호 - 직접 Asterisk 명령어 사용
        exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx 'quectel sms quectel0 {$callerClean} \"잘못된 인증번호입니다. 다시 확인해주세요.\"' 2>/dev/null");
        exit(0);
    }
    
    if (time() > $vc['expires_at']) {
        // 만료된 인증번호 - 직접 Asterisk 명령어 사용
        exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx 'quectel sms quectel0 {$callerClean} \"인증번호가 만료되었습니다. 스팸 문자를 다시 전송해주세요.\"' 2>/dev/null");
        exit(0);
    }
    
    // 인증 성공 처리
    $db->exec("UPDATE users SET verified=1, verified_at=datetime('now') WHERE id={$userId}");
    $db->exec("UPDATE verification_codes SET used=1 WHERE id={$vc['id']}");
    
    // 대기 중인 SMS 처리
    $res = $db->query("SELECT id, raw_text, phone080, identification FROM sms_incoming WHERE user_id={$userId} AND processed=0");
    $processedCount = 0;
    
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $smsText = $row['raw_text'];
        $phone080_db = $row['phone080'];
        $identification_db = $row['identification'];
        
        // 만약 DB에 정보가 없다면 SMS 텍스트에서 다시 추출
        if (empty($phone080_db)) {
            if (preg_match('/080-?\d{3,4}-?\d{4}/', $smsText, $m)) {
                $phone080_db = str_replace('-', '', $m[0]);
            }
        }
        
        if (empty($identification_db)) {
            // 식별번호 추출 시도
            if (preg_match('/식별번호\s*:?\s*([0-9]{4,11})/u', $smsText, $m2)) {
                $identification_db = $m2[1];
            } elseif (preg_match('/010[-\s]?\d{3,4}[-\s]?\d{4}(?:#)?/', $smsText, $m3)) {
                $identification_db = preg_replace('/[^0-9]/', '', $m3[0]);
            } else {
                // fallback: 발신자 번호 사용
                $identification_db = $callerClean;
            }
        }
        
        // 여전히 080번호가 없으면 건너뛰기
        if (empty($phone080_db)) {
            continue;
        }
        
        // DB 업데이트
        $db->exec("UPDATE sms_incoming SET phone080='{$phone080_db}', identification='{$identification_db}' WHERE id={$row['id']}");
        
        // CLI 스크립트로 백그라운드 처리
        $cmd = sprintf(
            'cd %s && php process_cli.php --phone=%s --id=%s --notification=%s --auto >> /tmp/call_process.log 2>&1 &',
            escapeshellarg(__DIR__),
            escapeshellarg($phone080_db),
            escapeshellarg($identification_db),
            escapeshellarg($callerClean)
        );
        
        exec($cmd);
        // DB 락 문제 해결을 위한 재시도 로직
        $maxRetries = 1;
        $success = false;
        for ($i = 0; $i < $maxRetries && !$success; $i++) {
            $result = @$db->exec("UPDATE sms_incoming SET processed=1 WHERE id={$row['id']}");
            if ($result !== false) {
                $success = true;
                error_log("[SMS_AUTO] SMS {$row['id']} marked as processed on attempt " . ($i + 1));
            } else {
                $error = $db->lastErrorMsg();
                if ($i == $maxRetries - 1) {
                    error_log("[SMS_AUTO] Failed to update SMS processed status after {$maxRetries} retries: {$error}");
                } else {
                    error_log("[SMS_AUTO] DB update failed on attempt " . ($i + 1) . ": {$error}, retrying...");
                    usleep(100000); // 0.1초 대기
                }
            }
        }
        $processedCount++;
        
        // 모뎀 SMS 발송 완료 대기 (모바일 인증 후 즉시 통화 방지)
        error_log("[SMS_AUTO] Waiting 10 seconds for modem to complete SMS sending before processing call for {$phone080_db}");
        sleep(10); // 10초 대기
        error_log("[SMS_AUTO] Wait completed, starting background call processing for {$phone080_db}");
    }
    
    // 성공 메시지 전송 - 직접 Asterisk 명령어 사용
    if ($processedCount > 0) {
        error_log("[SMS_AUTO] Authentication completed for {$callerClean}, processed {$processedCount} pending SMS");
        exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx 'quectel sms quectel0 {$callerClean} \"✅ 인증 완료! {$processedCount}건의 수신거부 요청을 처리했습니다.\"' 2>/dev/null");
    } else {
        error_log("[SMS_AUTO] Authentication completed for {$callerClean}, no pending SMS");
        exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx 'quectel sms quectel0 {$callerClean} \"✅ 인증이 완료되었습니다. 이제 스팸 문자를 전송하면 자동으로 수신거부 처리됩니다.\"' 2>/dev/null");
    }
    
    exit(0);
}

// 2. 080 번호 추출
if (!preg_match('/080-?\d{3,4}-?\d{4}/', $smsRaw, $m)) {
    // 광고번호가 없고 인증번호도 아니면 무시
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

// 3. 식별번호 추출: "식별번호" 키워드 또는 010
$id = '';
if (preg_match('/식별번호\s*:?\s*([0-9]{4,11})/u', $smsRaw, $m2)) {
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

// 정규화된 발신자 번호 (숫자만)
$callerClean = preg_replace('/[^0-9]/','',$caller);

// --- Authentication check FIRST: if user not verified, send code then exit ---
if(isset($user) && !$user['verified']){
    $code = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
    $exp  = time()+600;
    $db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$userId},'{$code}',{$exp})");
    
    // 개선된 인증 안내 메시지 전송
    $verificationMsg = "[080 수신거부 서비스]\n\n" .
                      "본인 인증이 필요합니다.\n" .
                      "인증번호: {$code}\n\n" .
                      "📱 이 번호로 인증번호를 답장해주세요\n" .
                      "⏰ 10분 내 유효\n\n" .
                      "인증 후 자동으로 수신거부 처리됩니다.";
    
    exec("echo 'hacker03' | sudo -S /usr/sbin/asterisk -rx 'quectel sms quectel0 {$callerClean} \"{$verificationMsg}\"' 2>/dev/null");
    exit(0);
}

// 5. process_v2.php 호출 (인증된 사용자만)
$cmd = "php " . __DIR__ . "/process_v2.php --phone={$phone080} --id={$id} --notification={$caller} --auto >> /tmp/call_process.log 2>&1 &";
exec($cmd);

// final update of identification
if(isset($db) && isset($rowId)){
    $upd2 = $db->prepare('UPDATE sms_incoming SET identification=:id WHERE id=:rid');
    $upd2->bindValue(':id', $id, SQLITE3_TEXT);
    $upd2->bindValue(':rid', $rowId, SQLITE3_INTEGER);
    $upd2->execute();
}

exit(0);
?> 