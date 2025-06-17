#!/usr/bin/env php
<?php
/**
 * CLI SMS 발송 도구
 * 
 * 사용법:
 *   php send_sms_cli.php --to=01012345678 --message="광고 문자 내용"
 *   php send_sms_cli.php --to=01012345678 --spam="수신거부: 0808895050 식별번호123456"
 * 
 * 기능:
 * - 신규 사용자 자동 환영 메시지 및 인증번호 발송
 * - 광고 문자 자동 처리 및 알림
 * - 웹 URL 제공
 */

// CLI 모드에서 세션과 헤더 문제 방지
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
}

require_once __DIR__ . '/sms_sender.php';

// CLI 파라미터 파싱
$options = getopt('', [
    'to:',          // 수신 번호 (필수)
    'message::',    // 일반 메시지
    'spam::',       // 광고 문자 (자동 처리)
    'help',         // 도움말
]);

if (isset($options['help']) || !isset($options['to'])) {
    echo <<<HELP
📱 SMS CLI 발송 도구

사용법:
  php send_sms_cli.php --to=01012345678 --message="일반 메시지"
  php send_sms_cli.php --to=01012345678 --spam="수신거부: 0808895050 식별번호123456"

옵션:
  --to=PHONE      수신 번호 (필수)
  --message=TEXT  일반 메시지 발송
  --spam=TEXT     광고 문자 (자동 처리 + 알림)
  --help          이 도움말 표시

예시:
  # 일반 메시지 발송
  php send_sms_cli.php --to=01012345678 --message="안녕하세요!"
  
  # 광고 문자 시뮬레이션 (자동 처리)
  php send_sms_cli.php --to=01012345678 --spam="수신거부: 0808895050 식별번호123456"

HELP;
    exit(0);
}

$toPhone = $options['to'];
$message = $options['message'] ?? '';
$spamMessage = $options['spam'] ?? '';

if (empty($message) && empty($spamMessage)) {
    fwrite(STDERR, "❌ 오류: --message 또는 --spam 중 하나는 필수입니다.\n");
    fwrite(STDERR, "도움말: php send_sms_cli.php --help\n");
    exit(1);
}

// 전화번호 정규화
$cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 11) {
    fwrite(STDERR, "❌ 오류: 올바른 전화번호를 입력하세요. (010으로 시작하는 10-11자리)\n");
    exit(1);
}

echo "📱 SMS 발송 준비\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📞 수신번호: {$cleanPhone}\n";

try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    
    // 스키마 적용
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $db->exec(file_get_contents($schemaFile));
    }
    
    // 사용자 확인/생성
    $user = $db->querySingle("SELECT id, verified FROM users WHERE phone = '{$cleanPhone}'", true);
    $isNewUser = !$user;
    
    if ($isNewUser) {
        // 신규 사용자 생성
        $db->exec("INSERT INTO users (phone, verified) VALUES ('{$cleanPhone}', 0)");
        $userId = $db->lastInsertRowID();
        echo "👋 신규 사용자 등록\n";
        
        // 환영 메시지 + 인증번호 발송
        $smsSender = new SMSSender();
        
        // 1. 환영 메시지
        $welcomeMessage = "🎉 080 SMS 수신거부 서비스 가입을 환영합니다!\n\n인증번호가 발송됩니다. 인증 완료 후 서비스를 이용하실 수 있습니다.";
        $welcomeResult = $smsSender->sendSMS($cleanPhone, $welcomeMessage);
        
        if ($welcomeResult['success']) {
            echo "✅ 환영 메시지 발송 완료\n";
        } else {
            echo "⚠️  환영 메시지 발송 실패: " . ($welcomeResult['error'] ?? '알 수 없는 오류') . "\n";
        }
        
        // 잠시 대기 (SMS 순서 보장)
        sleep(2);
        
        // 2. 인증번호 발송
        $verificationCode = sprintf('%06d', mt_rand(0, 999999));
        $expiryTime = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // 기존 인증번호 삭제
        $db->exec("DELETE FROM verification_codes WHERE user_id = {$userId}");
        
        // 새 인증번호 저장
        $stmt = $db->prepare('INSERT INTO verification_codes (user_id, code, expires_at) VALUES (:user_id, :code, :expires)');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':code', $verificationCode, SQLITE3_TEXT);
        $stmt->bindValue(':expires', time() + 600, SQLITE3_INTEGER);
        $stmt->execute();
        
        $authMessage = "🔐 인증번호: {$verificationCode}\n\n웹사이트에서 인증 완료 후 서비스를 이용하세요.\n📱 " . getWebUrl();
        $authResult = $smsSender->sendSMS($cleanPhone, $authMessage);
        
        if ($authResult['success']) {
            echo "✅ 인증번호 발송 완료 (유효시간: 10분)\n";
            echo "🔢 인증번호: {$verificationCode}\n";
        } else {
            echo "❌ 인증번호 발송 실패: " . ($authResult['error'] ?? '알 수 없는 오류') . "\n";
        }
        
    } else {
        $userId = $user['id'];
        $isVerified = $user['verified'];
        echo "👤 기존 사용자 (인증: " . ($isVerified ? '완료' : '대기') . ")\n";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // 메시지 처리
    if (!empty($spamMessage)) {
        // 광고 문자 자동 처리
        echo "🤖 광고 문자 자동 처리 시작\n";
        echo "📝 내용: " . substr($spamMessage, 0, 50) . (strlen($spamMessage) > 50 ? '...' : '') . "\n";
        
        // Base64 인코딩
        $encodedMessage = base64_encode($spamMessage);
        
        // sms_auto_processor.php 호출
        $cmd = "php " . __DIR__ . "/sms_auto_processor.php --caller={$cleanPhone} --msg_base64=\"{$encodedMessage}\"";
        $output = [];
        $returnCode = 0;
        exec($cmd . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✅ 광고 문자 처리 완료\n";
            echo "📞 수신거부 통화가 자동으로 진행됩니다.\n";
            echo "📱 처리 결과는 SMS로 알림 예정\n";
            
            // 웹 URL 안내
            echo "🌐 진행 상황 확인: " . getWebUrl() . "\n";
        } else {
            echo "❌ 광고 문자 처리 실패\n";
            echo "⚠️  오류: " . implode("\n", $output) . "\n";
        }
        
    } elseif (!empty($message)) {
        // 일반 메시지 발송
        echo "📤 일반 메시지 발송\n";
        echo "📝 내용: " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '') . "\n";
        
        $smsSender = new SMSSender();
        $result = $smsSender->sendSMS($cleanPhone, $message);
        
        if ($result['success']) {
            echo "✅ 메시지 발송 완료\n";
            echo "📤 응답: " . ($result['message'] ?? '발송 대기 중') . "\n";
        } else {
            echo "❌ 메시지 발송 실패\n";
            echo "⚠️  오류: " . ($result['error'] ?? '알 수 없는 오류') . "\n";
        }
    }
    
} catch (Exception $e) {
    fwrite(STDERR, "❌ 시스템 오류: " . $e->getMessage() . "\n");
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✨ SMS CLI 도구 실행 완료\n";

/**
 * 웹 URL 생성
 */
function getWebUrl() {
    // 현재 도메인 감지 (nginx 설정 기반)
    $hostname = gethostname();
    $domain = '';
    
    // 일반적인 도메인 패턴들
    if (strpos($hostname, '.') !== false) {
        $domain = $hostname;
    } else {
        // 기본 로컬 접근
        $domain = 'localhost';
    }
    
    return "https://{$domain}/";
}
?>