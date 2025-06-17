<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/sms_sender.php';

// 이미 로그인되어 있으면 리다이렉트 처리
if (is_logged_in()) {
    $redirectUrl = $_SESSION['login_redirect'] ?? '/dashboard.php';
    unset($_SESSION['login_redirect']);
    header("Location: {$redirectUrl}");
    exit;
}

$message = '';
$db = new SQLite3(__DIR__.'/spam.db');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $phone = preg_replace('/[^0-9]/','', $_POST['phone'] ?? '');
    if($phone==='') {
        $message = '<div class="alert alert-error">❌ 전화번호를 입력하세요.</div>';
    } else {
        try {
            // ensure user row
            $db->exec("INSERT OR IGNORE INTO users(phone) VALUES('{$phone}')");
            $row = $db->querySingle("SELECT id,verified FROM users WHERE phone='{$phone}'", true);
            // always send code (login each time)
            $code = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
            $exp  = time()+600;
            $db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$row['id']},'{$code}',{$exp})");
            
            $result = (new SMSSender())->sendVerificationCode($phone,$code);
            
            if ($result['success']) {
                $_SESSION['login_phone']=$phone;
                session_write_close();
                header('Location: verify.php');
                exit;
            } else {
                $message = '<div class="alert alert-error">❌ SMS 전송 실패: ' . htmlspecialchars($result['message'] ?? '알 수 없는 오류') . '</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-error">❌ 오류가 발생했습니다: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 080 수신거부 자동화</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
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
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
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
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            transform: translateY(-1px);
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
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
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 24px;
            padding: 8px 16px;
            background: rgba(79, 70, 229, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .back-link:hover {
            background: rgba(79, 70, 229, 0.2);
            transform: translateX(-2px);
        }

        .info-notice {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 16px;
            margin-top: 24px;
            font-size: 0.85rem;
            color: #1e40af;
            text-align: center;
        }

        .features-list {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
        }

        .features-list h4 {
            color: #374151;
            margin-bottom: 12px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .features-list ul {
            list-style: none;
            padding: 0;
        }

        .features-list li {
            color: #64748b;
            font-size: 0.8rem;
            margin-bottom: 6px;
            padding-left: 20px;
            position: relative;
        }

        .features-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
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
            <span class="login-icon">📱</span>
            <h1 class="login-title">휴대폰 인증 로그인</h1>
            <p class="login-subtitle">안전한 SMS 인증으로 로그인하세요</p>
        </div>

        <?php echo $message; ?>

        <form method="post">
            <div class="form-group">
                <label for="phone">휴대폰 번호</label>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       placeholder="01012345678" 
                       required 
                       maxlength="11"
                       pattern="[0-9]{10,11}"
                       autocomplete="tel"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn">
                📲 인증번호 받기
            </button>
        </form>

        <div class="info-notice">
            📱 입력하신 번호로 6자리 인증번호가 전송됩니다
        </div>

        <div class="features-list">
            <h4>🎯 서비스 이용 안내</h4>
            <ul>
                <li>080 수신거부 통화 자동화</li>
                <li>실시간 처리 결과 알림</li>
                <li>통화 녹음 및 분석 제공</li>
                <li>처리 내역 웹에서 확인</li>
            </ul>
        </div>
    </div>

    <script>
        // 전화번호 입력 필드 자동 포커스
        document.getElementById('phone').focus();
        
        // 전화번호 입력시 자동 포맷팅
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            e.target.value = value;
        });
    </script>
</body>
</html> 