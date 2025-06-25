<?php
// 세션 테스트 페이지 - 도메인별 세션 상태 확인
require_once __DIR__ . '/auth.php';

$debug = debug_session_info();
?>
<!DOCTYPE html>
<html>
<head>
    <title>세션 디버그</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: monospace; padding: 20px; }
        .debug { background: #f5f5f5; padding: 15px; margin: 10px 0; border: 1px solid #ccc; }
        .status { color: <?php echo is_logged_in() ? 'green' : 'red'; ?>; font-weight: bold; }
    </style>
</head>
<body>
    <h1>세션 상태 확인</h1>
    
    <div class="status">
        로그인 상태: <?php echo is_logged_in() ? '✅ 로그인됨' : '❌ 비로그인'; ?>
    </div>
    
    <?php if (is_logged_in()): ?>
        <p>현재 사용자: <?php echo htmlspecialchars(current_user_phone()); ?></p>
        <p>관리자 여부: <?php echo is_admin() ? '예' : '아니오'; ?></p>
    <?php endif; ?>
    
    <div class="debug">
        <h3>세션 디버그 정보</h3>
        <pre><?php echo htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </div>
    
    <div class="debug">
        <h3>쿠키 정보</h3>
        <pre><?php echo htmlspecialchars(json_encode($_COOKIE, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </div>
    
    <p><a href="index.php">메인 페이지로 돌아가기</a></p>
    <p><a href="logout.php">로그아웃</a></p>
</body>
</html>