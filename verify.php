<?php
require_once __DIR__.'/auth.php';
$db=new SQLite3(__DIR__.'/spam.db');
$phone=$_SESSION['login_phone']??'';
if($phone==''){header('Location: login.php');exit;}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $code=$_POST['code']??'';
 $phoneClean=preg_replace('/[^0-9]/','',$phone);
 $user=$db->querySingle("SELECT id FROM users WHERE phone='{$phoneClean}'",true);
 if($user){
   $vc=$db->querySingle("SELECT id,expires_at FROM verification_codes WHERE user_id={$user['id']} AND code='{$code}' AND used=0",true);
   if($vc && time() <= $vc['expires_at']){
      $db->exec("UPDATE users SET verified=1,verified_at=datetime('now') WHERE id={$user['id']}");
      $db->exec("UPDATE verification_codes SET used=1 WHERE id={$vc['id']}");

      unset($_SESSION['login_phone']);
      $_SESSION['user_id']=$user['id'];
      $_SESSION['phone']=$phoneClean;
      
      // 로그인 후 리다이렉트 처리
      $redirectUrl = $_SESSION['login_redirect'] ?? null;
      unset($_SESSION['login_redirect']);
      
      session_write_close();
      require_once __DIR__.'/auth.php';
      set_flash('휴대폰 인증이 완료되었습니다.');
      
      // 리다이렉트 URL이 있으면 바로 이동 (음성파일 등), 없으면 메인페이지로
      if ($redirectUrl) {
          header("Location: {$redirectUrl}");
      } else {
          // pending SMS 처리는 일반 로그인에서만 (음성파일 접근이 아닌 경우)
          $res=$db->query("SELECT id, phone080, identification FROM sms_incoming WHERE user_id={$user['id']} AND processed=0");
          while($row=$res->fetchArray(SQLITE3_ASSOC)){
              $cmd='php '. __DIR__.'/process_v2.php --phone='.$row['phone080'].' --id='.$row['identification'].' --notification='.$phoneClean.' --auto > /dev/null 2>&1 &';
              exec($cmd);
              $db->exec("UPDATE sms_incoming SET processed=1 WHERE id={$row['id']}");
          }
          require __DIR__.'/verify_success.php';
      }
      exit;
   } else { $err='인증번호가 올바르지 않거나 만료되었습니다.'; }
 }
}

$message = $err ? '<div class="alert alert-error">❌ ' . htmlspecialchars($err) . '</div>' : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>인증번호 확인 - 080 수신거부 자동화</title>
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

        .phone-display {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
            font-weight: 600;
            color: #1e40af;
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

        .step.completed {
            background: #10b981;
            color: white;
        }

        .step.active {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            transform: scale(1.1);
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
            font-size: 1.2rem;
            transition: all 0.3s ease;
            background: #f9fafb;
            text-align: center;
            letter-spacing: 4px;
            font-weight: 600;
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
            margin-bottom: 12px;
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

        .btn-secondary {
            background: #6b7280;
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
        <a href="login.php" class="back-link">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            다른 번호로 로그인
        </a>

        <div class="login-header">
            <span class="login-icon">🔐</span>
            <h1 class="login-title">인증번호 확인</h1>
            <p class="login-subtitle">SMS로 전송된 6자리 인증번호를 입력하세요</p>
        </div>

        <!-- 단계 표시기 -->
        <div class="step-indicator">
            <div class="step completed">1</div>
            <div class="step active">2</div>
        </div>

        <!-- 전화번호 표시 -->
        <div class="phone-display">
            📱 <?php echo htmlspecialchars($phone); ?>로 전송됨
        </div>

        <?php echo $message; ?>

        <form method="post">
            <div class="form-group">
                <label for="code">인증번호 (6자리)</label>
                <input type="text" 
                       id="code" 
                       name="code" 
                       placeholder="000000" 
                       required 
                       maxlength="6"
                       pattern="[0-9]{6}"
                       autocomplete="one-time-code">
            </div>

            <button type="submit" class="btn">
                ✅ 인증 완료
            </button>

            <button type="button" class="btn btn-secondary" onclick="location.href='login.php'">
                🔄 새 인증번호 받기
            </button>
        </form>

        <div class="info-notice">
            ⏰ 인증번호는 10분 후 자동으로 만료됩니다
        </div>
    </div>

    <script>
        // 인증번호 입력 필드 자동 포커스
        document.getElementById('code').focus();
        
        // 숫자만 입력 허용
        document.getElementById('code').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 6) {
                value = value.slice(0, 6);
            }
            e.target.value = value;
        });
        
        // 6자리 입력 완료시 자동 제출
        document.getElementById('code').addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                // 1초 후 자동 제출 (사용자가 확인할 시간 제공)
                setTimeout(() => {
                    if (e.target.value.length === 6) {
                        e.target.closest('form').submit();
                    }
                }, 500);
            }
        });
    </script>
</body>
</html> 