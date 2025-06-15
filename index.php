<?php 
require_once __DIR__.'/auth.php'; 
$IS_LOGGED=is_logged_in(); 
$CUR_PHONE=current_user_phone(); 
$IS_ADMIN=is_admin();

// 로그인한 사용자의 마지막 접속 시간 업데이트
if ($IS_LOGGED) {
    update_last_access();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>080 수신거부 자동화 시스템</title>
    <!-- Preload JavaScript files -->
    <link rel="preload" href="login_flow.js?v=<?php echo time(); ?>" as="script">
    <link rel="preload" href="assets/modal.js?v=2" as="script">
    <link rel="preload" href="assets/app.js?v=<?php echo time(); ?>" as="script">
    
    <!-- Critical inline configuration (minimal) -->
    <script>
        window.IS_LOGGED=<?php echo $IS_LOGGED?'true':'false';?>;
        window.CUR_PHONE=<?php echo json_encode($CUR_PHONE);?>;
        window.IS_ADMIN=<?php echo $IS_ADMIN?'true':'false';?>;
        window.AUTH_FLOW=<?php echo $authFlow ?: 'null';?>;
    </script>
    <!-- Favicon and PWA Manifest -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3e%3ctext y='0.9em' font-size='100'%3e🚫%3c/text%3e%3c/svg%3e">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="080 차단">
    
    <?php
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // 페이지 로드 시 처리
        $actionResult = '';
        $authFlow = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                // 인증 관련 액션
                if ($_POST['action'] === 'send_verification') {
                    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
                    if ($phone === '') {
                        $authFlow = json_encode(['status' => 'error', 'message' => '전화번호를 입력하세요.']);
                    } else {
                        require_once 'sms_sender.php';
                        $db = new SQLite3(__DIR__ . '/spam.db');
                        $stmt = $db->prepare("INSERT OR IGNORE INTO users(phone) VALUES(:phone)");
                        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                        $stmt->execute();
                        
                        $stmt = $db->prepare("SELECT id FROM users WHERE phone = :phone");
                        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                        $result = $stmt->execute();
                        $row = $result->fetchArray(SQLITE3_ASSOC);
                        
                        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $exp = time() + 600;
                        
                        $stmt = $db->prepare("INSERT INTO verification_codes(user_id,code,expires_at) VALUES(:user_id,:code,:exp)");
                        $stmt->bindValue(':user_id', $row['id'], SQLITE3_INTEGER);
                        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
                        $stmt->bindValue(':exp', $exp, SQLITE3_INTEGER);
                        $stmt->execute();
                        $result = (new SMSSender())->sendVerificationCode($phone, $code);
                        
                        if ($result['success']) {
                            $_SESSION['verification_phone'] = $phone;
                            $authFlow = json_encode(['status' => 'code_sent', 'phone' => $phone, 'expires_at' => $exp]);
                        } else {
                            $authFlow = json_encode(['status' => 'error', 'message' => 'SMS 전송 실패: ' . $result['message']]);
                        }
                    }
                } elseif ($_POST['action'] === 'verify_code') {
                    $code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
                    $phone = $_SESSION['verification_phone'] ?? '';
                    
                    if ($code === '' || $phone === '') {
                        $authFlow = json_encode(['status' => 'error', 'message' => '인증번호를 입력하세요.']);
                    } else {
                        $db = new SQLite3(__DIR__ . '/spam.db');
                        $stmt = $db->prepare("
                            SELECT vc.*, u.id as user_id, u.phone 
                            FROM verification_codes vc 
                            JOIN users u ON vc.user_id = u.id 
                            WHERE u.phone = :phone AND vc.code = :code AND vc.expires_at > :time
                            ORDER BY vc.id DESC LIMIT 1
                        ");
                        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
                        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $row = $result->fetchArray(SQLITE3_ASSOC);
                        
                        if ($row) {
                            // 로그인 성공
                            $_SESSION['user_id'] = $row['user_id'];
                            $_SESSION['phone'] = $row['phone'];
                            unset($_SESSION['verification_phone']);
                            $authFlow = json_encode(['status' => 'logged_in', 'phone' => $row['phone']]);
                        } else {
                            $authFlow = json_encode(['status' => 'error', 'message' => '인증번호가 잘못되었거나 만료되었습니다.']);
                        }
                    }
                }
                // 기존 액션들
                elseif ($_POST['action'] === 'make_call' && isset($_POST['id'])) {
                    require_once 'call_processor.php';
                    $processor = new CallProcessor();
                    $actionResult = $processor->makeCall($_POST['id'], $_POST['phone_number']);
                } elseif ($_POST['action'] === 'start_discovery') {
                    require_once 'pattern_discovery.php';
                    $discovery = new PatternDiscovery();
                    $actionResult = $discovery->startDiscovery($_POST['discovery_phone_number'], $_POST['notification_phone_number']);
                }
            }
        }
    ?>

    <!-- Critical CSS for above-the-fold content -->
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;line-height:1.6;color:#333;background:#f5f7fa;min-height:100vh}
        .container{max-width:1200px;margin:0 auto;padding:20px}
        .header{text-align:center;color:#2c3e50;margin-bottom:40px;padding:40px 20px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.1)}
        .header h1{font-size:clamp(1.8rem,4vw,2.5rem);margin-bottom:10px;font-weight:700}
        .header p{font-size:clamp(1rem,2.5vw,1.1rem);opacity:0.9}
        .card{background:white;padding:30px;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.1);margin-bottom:30px}
        .btn{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:none;padding:12px 24px;border-radius:8px;cursor:pointer;transition:all 0.3s ease;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600}
        .btn:hover{transform:translateY(-2px) scale(1.02);box-shadow:0 8px 25px rgba(102,126,234,0.4)}
        .btn:focus{outline:none}
        .btn:not(:active):not(:hover){transform:translateY(0) scale(1)}
    </style>
    
    <!-- Preload critical fonts -->
    <link rel="preload" href="data:font/woff2;base64," as="font" type="font/woff2" crossorigin>
    
    <!-- Load CSS asynchronously -->
    <link rel="preload" href="assets/style.css?v=<?php echo time(); ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="assets/modal.css?v=2" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="assets/style.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="assets/modal.css?v=2">
    </noscript>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚫 080 수신거부 자동화 시스템</h1>
            <p>스팸 문자의 080 번호를 자동으로 추출하여 수신거부 전화를 대신 걸어드립니다</p>
        </div>

        <?php if ($IS_LOGGED): ?>
        <!-- 사용자 상태 바 -->
        <div class="user-status-bar">
            <div class="user-info-section">
                <div class="user-profile-mini">
                    <span class="user-icon"><?php echo $IS_ADMIN ? '👑' : '👤'; ?></span>
                    <span class="user-phone"><?php echo htmlspecialchars($CUR_PHONE); ?></span>
                    <?php if ($IS_ADMIN): ?>
                    <span class="admin-badge">ADMIN</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="user-actions-section">
                <?php if ($IS_ADMIN): ?>
                <a href="admin.php" class="status-action-btn admin-btn" title="관리자">
                    <span class="btn-icon">⚙️</span>
                    <span class="btn-text">관리</span>
                </a>
                <?php endif; ?>
                <a href="notification_settings.php" class="status-action-btn notification-btn" title="알림 설정">
                    <span class="btn-icon">🔔</span>
                    <span class="btn-text">알림</span>
                </a>
                <button type="button" class="status-action-btn delete-btn" onclick="confirmAccountDeletion()" title="회원 탈퇴">
                    <span class="btn-icon">🗑️</span>
                    <span class="btn-text">탈퇴</span>
                </button>
                <a href="logout.php" class="status-action-btn logout-btn" title="로그아웃">
                    <span class="btn-icon">🚪</span>
                    <span class="btn-text">로그아웃</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 메인 입력 카드 -->
        <?php include __DIR__.'/partials/spam_form.php'; ?>

        <?php if ($IS_LOGGED): ?>
        <!-- 녹음 파일 목록 카드 -->
        <?php include __DIR__.'/partials/recordings_list.php'; ?>

        <!-- 패턴 요약 카드 -->
        <?php include __DIR__.'/partials/pattern_summary.php'; ?>
        <?php endif; ?>
    </div>

    <div id="toast" class="toast-notification"></div>

    <div id="progressContainer" style="display: none; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3>패턴 분석 진행상황</h3>
        <div class="progress" style="height: 25px; margin-bottom: 10px;">
            <div id="analysisProgress" class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" style="width: 0%;" 
                 aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <span id="progressText">0%</span>
            </div>
        </div>
        <div id="progressMessage" style="margin-bottom: 10px;"></div>
        <div id="analysisSteps"></div>
    </div>

    <!-- Load scripts with improved performance -->
    <script src="login_flow.js?v=<?php echo time(); ?>" defer></script>
    <script src="assets/modal.js?v=2" defer></script>
    <script src="assets/app.js?v=<?php echo time(); ?>" defer></script>
    
    <?php
    // Add performance monitoring in development
    if (isset($_GET['debug']) || $_SERVER['HTTP_HOST'] === 'localhost') {
        require_once __DIR__ . '/performance.php';
        echo PerformanceOptimizer::addPerformanceMonitoring();
    }
    ?>
    
    <!-- Service Worker Registration -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js')
                .then(function(registration) {
                    console.log('ServiceWorker registration successful');
                })
                .catch(function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
        });
    }
    </script>
</body>
</html>
