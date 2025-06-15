<?php
/**
 * 회원 탈퇴 API
 * 사용자의 개인정보는 삭제하되, 패턴은 익명화하여 보존
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않은 메소드입니다.']);
    exit;
}

// 확인 토큰 검증
$confirmToken = $_POST['confirm_token'] ?? '';
if ($confirmToken !== 'DELETE_MY_ACCOUNT') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '확인 토큰이 일치하지 않습니다.']);
    exit;
}

try {
    $currentUserId = $_SESSION['user_id'];
    $currentUserPhone = current_user_phone();
    
    $dbPath = __DIR__ . '/../spam.db';
    $db = new SQLite3($dbPath);
    
    // 트랜잭션 시작
    $db->exec('BEGIN TRANSACTION');
    
    // 1. 사용자의 통화 기록 삭제
    $db->exec("DELETE FROM unsubscribe_calls WHERE user_id = {$currentUserId}");
    
    // 2. 사용자의 SMS 수신 기록 삭제  
    $db->exec("DELETE FROM sms_incoming WHERE user_id = {$currentUserId}");
    
    // 3. 사용자의 인증 코드 기록 삭제
    $db->exec("DELETE FROM verification_codes WHERE user_id = {$currentUserId}");
    
    // 4. 패턴에서 소유자 정보 익명화 (패턴 자체는 보존)
    $patterns = [];
    $patternsFile = __DIR__ . '/../patterns.json';
    
    if (file_exists($patternsFile)) {
        $patternsData = json_decode(file_get_contents($patternsFile), true);
        if ($patternsData && isset($patternsData['patterns'])) {
            $patterns = $patternsData['patterns'];
            $patternsModified = false;
            
            foreach ($patterns as $phoneNumber => &$pattern) {
                if (isset($pattern['owner_phone']) && $pattern['owner_phone'] === $currentUserPhone) {
                    // 소유자 정보를 익명화
                    $pattern['owner_phone'] = 'anonymous_' . substr(md5($currentUserPhone), 0, 8);
                    $pattern['anonymized_at'] = date('Y-m-d H:i:s');
                    $pattern['original_owner_removed'] = true;
                    
                    // 패턴 이름도 익명화
                    if (isset($pattern['name']) && strpos($pattern['name'], $currentUserPhone) !== false) {
                        $pattern['name'] = str_replace($currentUserPhone, '익명사용자', $pattern['name']);
                    }
                    
                    $patternsModified = true;
                }
            }
            
            // 패턴 파일 업데이트
            if ($patternsModified) {
                $patternsData['patterns'] = $patterns;
                file_put_contents($patternsFile, json_encode($patternsData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
        }
    }
    
    // 5. 사용자 계정 삭제
    $db->exec("DELETE FROM users WHERE id = {$currentUserId}");
    
    // 트랜잭션 커밋
    $db->exec('COMMIT');
    $db->close();
    
    // 6. 세션 정리
    session_destroy();
    
    // 성공 로그
    error_log("Account deleted successfully for user ID: {$currentUserId}, phone: {$currentUserPhone}");
    
    echo json_encode([
        'success' => true, 
        'message' => '회원 탈퇴가 완료되었습니다. 그동안 이용해 주셔서 감사합니다.',
        'patterns_anonymized' => $patternsModified ?? false
    ]);
    
} catch (Exception $e) {
    // 트랜잭션 롤백
    if (isset($db)) {
        $db->exec('ROLLBACK');
        $db->close();
    }
    
    error_log("Account deletion failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '탈퇴 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.'
    ]);
}
?>