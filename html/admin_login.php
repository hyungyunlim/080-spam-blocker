<?php
require_once __DIR__ . '/auth.php';

// 이미 로그인되어 있고 어드민이면 admin.php로 리다이렉션
if (is_logged_in() && is_admin()) {
    header('Location: admin.php');
    exit;
}

// 이미 로그인되어 있지만 어드민이 아니면 메인으로
if (is_logged_in() && !is_admin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$step = 'phone'; // phone, verify

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_code') {
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        
        if (empty($phone)) {
            $message = '<div class="alert alert-error">❌ 전화번호를 입력하세요.</div>';
        } else {
            // 어드민 번호인지 확인
            $admin_phones = get_admin_phones();
            if (!in_array($phone, $admin_phones)) {
                $message = '<div class="alert alert-error">❌ 관리자 권한이 없는 번호입니다.</div>';
            } else {
                require_once 'sms_sender.php';
                try {
                    $db = new SQLite3(__DIR__ . '/spam.db');
                    $db->exec("INSERT OR IGNORE INTO users(phone) VALUES('{$phone}')");
                    $row = $db->querySingle("SELECT id FROM users WHERE phone='{$phone}'", true);
                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $exp = time() + 600;
                    $db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$row['id']},'{$code}',{$exp})");
                    
                    $result = (new SMSSender())->sendVerificationCode($phone, $code);
                    
                    if ($result['success']) {
                        $_SESSION['admin_verification_phone'] = $phone;
                        $_SESSION['admin_verification_expires'] = $exp;
                        $step = 'verify';
                        $message = '<div class="alert alert-success">✅ 인증번호가 전송되었습니다.</div>';
                    } else {
                        $message = '<div class="alert alert-error">❌ SMS 전송 실패: ' . $result['message'] . '</div>';
                    }
                    $db->close();
                } catch (Exception $e) {
                    $message = '<div class="alert alert-error">❌ 오류가 발생했습니다: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'verify_code') {
        $code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
        $phone = $_SESSION['admin_verification_phone'] ?? '';
        
        if (empty($code) || empty($phone)) {
            $message = '<div class="alert alert-error">❌ 인증번호를 입력하세요.</div>';
            $step = 'verify';
        } else {
            try {
                $db = new SQLite3(__DIR__ . '/spam.db');
                $row = $db->querySingle("
                    SELECT vc.*, u.id as user_id, u.phone 
                    FROM verification_codes vc 
                    JOIN users u ON vc.user_id = u.id 
                    WHERE u.phone = '{$phone}' AND vc.code = '{$code}' AND vc.expires_at > " . time() . "
                    ORDER BY vc.id DESC LIMIT 1
                ", true);
                
                if ($row) {
                    // 로그인 성공
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['phone'] = $row['phone'];
                    unset($_SESSION['admin_verification_phone']);
                    unset($_SESSION['admin_verification_expires']);
                    
                    // 마지막 접속 시간 업데이트
                    update_last_access($row['phone']);
                    
                    // 어드민 페이지로 리다이렉션
                    header('Location: admin.php');
                    exit;
                } else {
                    $message = '<div class="alert alert-error">❌ 인증번호가 잘못되었거나 만료되었습니다.</div>';
                    $step = 'verify';
                }
                $db->close();
            } catch (Exception $e) {
                $message = '<div class="alert alert-error">❌ 인증 중 오류가 발생했습니다.</div>';
                $step = 'verify';
            }
        }
    }
}

// 세션에 인증 대기 상태가 있으면 verify 단계로
if (isset($_SESSION['admin_verification_phone']) && isset($_SESSION['admin_verification_expires'])) {
    if ($_SESSION['admin_verification_expires'] > time()) {
        $step = 'verify';
    } else {
        // 만료된 세션 정리
        unset($_SESSION['admin_verification_phone']);
        unset($_SESSION['admin_verification_expires']);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 로그인 - 080 수신거부 자동화</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            display: block;
        }

        .login-title {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .verification-input {
            text-align: center;
            font-size: 1.2rem !important;
            letter-spacing: 4px;
            font-weight: 600;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: #6b7280;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .countdown {
            text-align: center;
            margin-top: 16px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .countdown.warning {
            color: #dc2626;
            font-weight: 600;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 8px;
            transition: all 0.3s ease;
        }

        .step.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.1);
        }

        .step.completed {
            background: #10b981;
            color: white;
        }

        .step.inactive {
            background: #e5e7eb;
            color: #9ca3af;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 24px;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .back-link:hover {
            background: rgba(102, 126, 234, 0.2);
            transform: translateX(-2px);
        }

        .security-notice {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 16px;
            margin-top: 24px;
            font-size: 0.85rem;
            color: #92400e;
            text-align: center;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 32px 24px;
                margin: 10px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .login-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="index.php" class="back-link">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            메인으로 돌아가기
        </a>

        <div class="login-header">
            <span class="login-icon">👑</span>
            <h1 class="login-title">관리자 로그인</h1>
            <p class="login-subtitle">시스템 관리를 위한 인증이 필요합니다</p>
        </div>

        <!-- 단계 표시기 -->
        <div class="step-indicator">
            <div class="step <?php echo $step === 'phone' ? 'active' : ($step === 'verify' ? 'completed' : 'inactive'); ?>">1</div>
            <div class="step <?php echo $step === 'verify' ? 'active' : 'inactive'; ?>">2</div>
        </div>

        <?php echo $message; ?>

        <?php if ($step === 'phone'): ?>
        <!-- 전화번호 입력 단계 -->
        <form method="post">
            <input type="hidden" name="action" value="send_code">
            
            <div class="form-group">
                <label for="phone">관리자 전화번호</label>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       placeholder="01012345678" 
                       required 
                       maxlength="11"
                       pattern="[0-9]{11}"
                       autocomplete="tel">
            </div>

            <button type="submit" class="btn">
                📱 인증번호 받기
            </button>
        </form>

        <div class="security-notice">
            🔒 관리자 권한이 있는 번호만 로그인할 수 있습니다
        </div>

        <?php else: ?>
        <!-- 인증번호 입력 단계 -->
        <form method="post">
            <input type="hidden" name="action" value="verify_code">
            
            <div class="form-group">
                <label for="code">인증번호 (6자리)</label>
                <input type="text" 
                       id="code" 
                       name="code" 
                       placeholder="000000" 
                       required 
                       maxlength="6"
                       pattern="[0-9]{6}"
                       class="verification-input"
                       autocomplete="one-time-code">
            </div>

            <button type="submit" class="btn">
                🔐 로그인
            </button>

            <button type="button" class="btn btn-secondary" onclick="location.reload()">
                🔄 다시 받기
            </button>
        </form>

        <?php if (isset($_SESSION['admin_verification_expires'])): ?>
        <div class="countdown" id="countdown">
            남은 시간: <span id="timer"></span>
        </div>

        <script>
            const expiresAt = <?php echo $_SESSION['admin_verification_expires']; ?>;
            const countdownEl = document.getElementById('countdown');
            const timerEl = document.getElementById('timer');
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiresAt - now;
                
                if (remaining <= 0) {
                    location.reload();
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (remaining <= 60) {
                    countdownEl.classList.add('warning');
                }
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
            
            // 인증번호 입력 필드 자동 포커스
            document.getElementById('code').focus();
        </script>
        <?php endif; ?>

        <div class="security-notice">
            🛡️ 보안을 위해 10분 후 자동으로 만료됩니다
        </div>
        <?php endif; ?>
    </div>
</body>
</html>