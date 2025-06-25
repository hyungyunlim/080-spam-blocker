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
    <script>
        window.IS_LOGGED=<?php echo $IS_LOGGED?'true':'false';?>;
        window.CUR_PHONE=<?php echo json_encode($CUR_PHONE);?>;
        window.IS_ADMIN=<?php echo $IS_ADMIN?'true':'false';?>;
        window.AUTH_FLOW=<?php echo $authFlow ?: 'null';?>;
    </script>
    <script src="login_flow.js"></script>
    <!-- Favicon to avoid 404 -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3e%3ctext y='0.9em' font-size='100'%3e🚫%3c/text%3e%3c/svg%3e">
    
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
                        $db->exec("INSERT OR IGNORE INTO users(phone) VALUES('{$phone}')");
                        $row = $db->querySingle("SELECT id FROM users WHERE phone='{$phone}'", true);
                        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $exp = time() + 600;
                        $db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$row['id']},'{$code}',{$exp})");
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

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: headerGlow 4s ease-in-out infinite alternate;
        }

        @keyframes headerGlow {
            0% { transform: rotate(0deg) scale(1); }
            100% { transform: rotate(180deg) scale(1.1); }
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            opacity: 0;
            transform: translateY(20px);
            animation: cardFadeIn 0.6s ease-out forwards;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes cardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.15);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.7s ease;
        }

        .card:hover::before {
            left: 100%;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f7fafc;
            position: relative;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .form-group input:hover,
        .form-group textarea:hover {
            border-color: #cbd5e0;
            transform: translateY(-1px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .help-text {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
            transform: translateX(-100%) skewX(-20deg);
            transition: transform 0.6s ease;
        }

        .btn:hover::before {
            transform: translateX(100%) skewX(-20deg);
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }

        .btn:active {
            transform: translateY(-1px) scale(1.02);
            transition: all 0.1s ease;
        }

        .btn:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
        }

        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(74, 85, 104, 0.4);
        }

        .result-box {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            white-space: pre-wrap;
            min-height: 60px;
            font-family: 'SF Mono', 'Consolas', monospace;
            font-size: 14px;
        }

        /* Recording Items */
        .recording-item {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .recording-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .recording-item:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .recording-item:hover::before {
            transform: scaleY(1);
        }

        .recording-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .recording-title {
            font-weight: 600;
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .recording-datetime {
            font-size: 14px;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Labels */
        .label {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .label-unsubscribe {
            background: #e0f2fe;
            color: #0369a1;
        }

        .label-discovery {
            background: #fef3c7;
            color: #92400e;
        }

        .label-registered {
            background: #dbeafe;
            color: #1e40af;
        }

        .label-unverified {
            background: #ffcdd2;
            color: #c62828;
        }

        .label-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .label-auto {
            background: #e5e7eb;
            color: #374151;
        }

        /* Analysis Results */
        .analysis-result {
            margin-top: 15px;
            padding: 16px;
            border-radius: 10px;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }

        .result-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .result-failure {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .result-uncertain {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .result-unknown {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

       /* Audio Player Custom Styling */
       .audio-container {
            width: 100%;
            margin-top: 12px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        audio {
            width: 100%;
            height: 48px;
            display: block;
            outline: none;
        }

        /* 브라우저별 오디오 컨트롤 스타일 통일 */
        audio::-webkit-media-controls-enclosure {
            background-color: transparent;
        }

        audio::-webkit-media-controls-panel {
            background-color: transparent;
        }

        /* 모바일에서 오디오 플레이어 스타일 */
        @media (max-width: 768px) {
            audio {
                height: 54px;
            }
            
            .audio-container {
                padding: 6px;
            }
        }

        /* 모바일에서 오디오 플레이어 스타일 */
        @media (max-width: 768px) {
            audio {
                height: 54px;
        }

            .audio-container {
                min-height: 70px;
            }
        }

        /* 전체 내용 보기 텍스트 초기 상태 */
        .transcription-text {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
        }

        .transcription-text pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
        }

        /* 분석 다시하기 버튼 스타일 */
        .reanalyze-btn {
            background: #f59e0b;
            color: white;
        }

        .reanalyze-btn:hover {
            background: #d97706;
        }

        /* Transcription */
        .transcription-container {
            margin-top: 12px;
        }

        .transcription-text {
            margin-top: 10px;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid #e2e8f0;
        }

        .transcription-text pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            font-family: inherit;
        }

        /* Spinner Animation */
        .spinner {
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Toast Notifications */
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #2d3748;
            color: white;
            padding: 16px 24px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast-notification.error {
            background: #e53e3e;
        }

        .toast-notification::before {
            content: '✓';
            font-size: 20px;
            font-weight: bold;
        }

        .toast-notification.error::before {
            content: '✕';
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 30px 20px;
                margin-bottom: 20px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .recording-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .toast-notification {
                left: 20px;
                right: 20px;
                bottom: 20px;
            }
        }

        /* Smooth Transitions */
        * {
            transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
            }

        /* Header Login Status */
        .header-login-status {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
            animation: slideInFromRight 0.5s ease-out;
        }

        .user-info-compact {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 13px;
        }

        .user-icon {
            font-size: 16px;
            opacity: 0.8;
        }

        .user-phone {
            font-size: 13px;
        }

        .admin-indicator {
            font-size: 10px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .logout-btn-compact {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .logout-btn-compact::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .logout-btn-compact:hover::before {
            left: 100%;
        }

        .logout-btn-compact:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .logout-icon {
            font-size: 12px;
        }

        @keyframes slideInFromRight {
            0% {
                opacity: 0;
                transform: translateX(30px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Focus Styles for Accessibility */
        button:focus,
        input:focus,
        textarea:focus,
        a:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
            }

        /* Dynamic Input Container */
        .dynamic-input-container {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            transform: translateY(-10px);
                opacity: 0;
            }

        .dynamic-input-container.show {
                transform: translateY(0);
            opacity: 1;
        }

        /* ID Selection */
        .id-selection-container {
            background: #fef3c7;
            border: 2px solid #fde68a;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .id-option {
            display: flex;
            align-items: center;
            padding: 12px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .id-option:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .id-option input[type="radio"]:checked + label {
            font-weight: 600;
            color: #667eea;
        }

        .delete-btn {
            background: #ef4444;
            color: white;
        }
        .delete-btn:hover {
            background: #dc2626;
        }

        .progress {
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            background-color: #007bff;
            color: white;
            text-align: center;
            line-height: 25px;
            transition: width 0.3s ease;
        }

        .progress-bar-striped {
            background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
            background-size: 1rem 1rem;
        }

        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }

        @keyframes progress-bar-stripes {
            from { background-position: 1rem 0; }
            to { background-position: 0 0; }
        }

        .step-progress {
            margin-bottom: 15px;
        }

        .step-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        /* Real-time call log inside call-progress */
        .call-log {
            max-height: 160px;
            overflow-y: auto;
            font-size: 12px;
            background: #ffffff;
            padding: 8px;
            margin-top: 8px;
            border-radius: 4px;
            border: 1px solid #e0f2fe;
            white-space: pre-line;
        }

        /* Verification Section Styles */
        .verification-container {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            margin-bottom: 15px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .verification-container:not(.show) {
            opacity: 0;
            transform: translateY(-10px);
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
            margin-top: 0;
            margin-bottom: 0;
        }

        .verification-container.show {
            opacity: 1;
            transform: translateY(0);
            max-height: 500px;
        }

        .verification-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px 12px 0 0;
        }

        .verification-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .verification-icon {
            font-size: 20px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }

        .verification-title h3 {
            margin: 0 0 2px 0;
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
        }

        .verification-title p {
            margin: 0;
            font-size: 13px;
            color: #718096;
        }

        .verification-content {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .verification-input-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .verification-input-group label {
            font-weight: 600;
            color: #4a5568;
            font-size: 16px;
        }

        .verification-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .verification-input-wrapper input {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            letter-spacing: 4px;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .verification-input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .countdown-timer {
            font-weight: 600;
            color: #f56565;
            background: #fed7d7;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            min-width: 60px;
            text-align: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .verification-help {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #718096;
            background: #f7fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .verification-actions {
            display: flex;
            justify-content: center;
        }

        .verification-btn {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(72, 187, 120, 0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .verification-btn:hover {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            box-shadow: 0 6px 20px rgba(72, 187, 120, 0.4);
            transform: translateY(-2px);
        }

        .verification-btn:disabled {
            background: #cbd5e0;
            box-shadow: none;
            transform: none;
            cursor: not-allowed;
        }

        .verification-btn .btn-icon {
            font-size: 18px;
        }

        .verification-message {
            padding: 16px 20px;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            transform: translateY(-10px);
            opacity: 0;
            min-height: 20px;
        }

        .verification-message:not(:empty) {
            transform: translateY(0);
            opacity: 1;
        }

        /* Verification Message States */
        .verify-msg {
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 12px;
            display: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .verify-msg::before {
            margin-right: 6px;
        }

        .verify-msg.show {
            display: block;
            animation: messageSlideIn 0.4s ease-out;
        }

        @keyframes messageSlideIn {
            0% {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .verify-msg.sending {
            background: linear-gradient(135deg, #ebf8ff 0%, #dbeafe 100%);
            color: #2b6cb0;
            border: 1px solid #93c5fd;
        }

        .verify-msg.sending::before {
            content: '📤';
        }

        .verify-msg.success {
            background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%);
            color: #276749;
            border: 1px solid #9ae6b4;
        }

        .verify-msg.success::before {
            content: '✅';
        }

        .verify-msg.error {
            background: linear-gradient(135deg, #fed7d7 0%, #fecaca 100%);
            color: #c53030;
            border: 1px solid #f87171;
        }

        .verify-msg.error::before {
            content: '❌';
        }

        .verify-msg.checking {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            color: #6b46c1;
            border: 1px solid #c4b5fd;
        }

        .verify-msg.checking::before {
            content: '🔍';
        }

        /* Admin Badge */
        .admin-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                padding: 30px 20px;
                margin-bottom: 20px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header-login-status {
                flex-direction: column;
                gap: 8px;
                margin-left: 0;
                margin-top: 10px;
                align-items: flex-end;
            }
            
            .user-info-compact {
                font-size: 12px;
            }
            
            .logout-btn-compact {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .btn {
                font-size: 14px;
                padding: 10px 20px;
            }
            
            .recording-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .toast-notification {
                left: 20px;
                right: 20px;
                bottom: 20px;
            }
            
            .form-group input,
            .form-group textarea {
                padding: 12px 16px;
            }
            
            /* Verification Responsive */
            .verification-container {
                padding: 16px;
                margin-top: 12px;
                margin-bottom: 12px;
            }
            
            .verification-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
                margin-bottom: 12px;
                padding-bottom: 10px;
            }
            
            .verification-icon {
                width: 28px;
                height: 28px;
                font-size: 18px;
            }
            
            .verification-title h3 {
                font-size: 15px;
            }
            
            .verification-title p {
                font-size: 12px;
            }
            
            .verification-input-wrapper {
                flex-direction: column;
                gap: 8px;
            }
            
            .verification-input-wrapper input {
                padding: 12px 14px;
                font-size: 16px;
                letter-spacing: 2px;
            }
            
            .countdown-timer {
                align-self: center;
            }
            
            .verify-msg {
                padding: 8px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚫 080 수신거부 자동화 시스템</h1>
            <p>스팸 문자의 080 번호를 자동으로 추출하여 수신거부 전화를 대신 걸어드립니다</p>
        </div>


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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
                        // URL에서 analysis_id 파라미터 확인
            const urlParams = new URLSearchParams(window.location.search);
            const analysisId = urlParams.get('analysis_id');
            
            console.log('Page loaded, analysis_id:', analysisId);
            
            if (analysisId) {
                checkPatternAnalysisProgress(analysisId);
            }
            
            // 초기 녹음 목록 로드
            getRecordings();
            
            // localStorage에서 진행 중인 분석 복원
            const persistedAnalyses = JSON.parse(localStorage.getItem('activeAnalyses') || '[]');
            persistedAnalyses.forEach(([filename, analysisId]) => {
                activeAnalysisMap.set(filename, analysisId);
            });
            
            // 5초 주기로 녹음 목록 자동 갱신 (탭이 활성화된 경우에만)
            setInterval(() => {
                if (!document.hidden && !document.querySelector('.call-progress') && !document.querySelector('.analysis-progress')) {
                    getRecordings();
                }
            }, 5000);

            // 전역 progressContainer는 숨김 처리
            const globalProgress = document.getElementById('progressContainer');
            if (globalProgress) globalProgress.style.display = 'none';
            });

            const spamContent = document.getElementById('spamContent');
            const dynamicContainer = document.getElementById('dynamicInputContainer');
            const detectedIdSection = document.getElementById('detectedIdSection');
            const multipleIdSection = document.getElementById('multipleIdSection');
            const phoneInputSection = document.getElementById('phoneInputSection');
            const detectedIdText = document.getElementById('detectedIdText');
            const idOptions = document.getElementById('idOptions');
            const confirmationContainer = document.getElementById('confirmationContainer');
            const selectedIdDisplay = document.getElementById('selectedIdDisplay');
            const confirmButton = document.getElementById('confirmSelection');
            const cancelButton = document.getElementById('cancelSelection');
            const spamForm = document.getElementById('spamForm');
            const resultArea = document.getElementById('resultArea');
            const recordingsList = document.getElementById('recordingsList');
            const refreshBtn = document.getElementById('refreshBtn');
            
            let confirmedId = null;
            let lastRecordingsUpdate = null;
            
            // 텍스트영역 자동 크기 조절
            function autoResize(textarea) {
                if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
                }

                spamContent.addEventListener('input', function() {
                        autoResize(this);
                    // 새 입력이 시작되면 이전 결과 박스를 숨긴다
                    if (resultArea) {
                        resultArea.style.display = 'none';
                        resultArea.innerHTML = '';
                    }
                        const text = this.value.trim();
                        if (text.length > 10) {
                            analyzeText(text);
                        } else {
                            hideDynamicInput();
                    }
                });

            spamContent.addEventListener('keydown', function(e){
                // Enter 키 단독 입력으로 폼이 제출되는 것을 방지 (Shift+Enter 는 줄바꿈 허용)
                if(e.key === 'Enter' && !e.shiftKey){
                    e.stopPropagation();
                    e.preventDefault();
                    // 문단 구분을 위해 줄바꿈만 삽입
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    const value = this.value;
                    this.value = value.substring(0, start) + '\n' + value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 1;
                    autoResize(this);
                        }
                });

            function analyzeText(text) {
            // 080 번호: 하이픈이 섞여 있어도 인식 (예: 080-8888-5050)
            const phone_080_pattern = /080[-0-9]{7,12}/g;
            const rawPhones = text.match(phone_080_pattern) || [];
            // 하이픈 제거 후 중복 제거
            const phoneNumbers = [...new Set(rawPhones.map(p => p.replace(/[^0-9]/g, '')))];
                
                if (phoneNumbers.length === 0) {
                    hideDynamicInput();
                    return;
                }

            const id_patterns = [
                // 명시적인 키워드 기반 패턴 (인증번호/식별번호/고객번호/등록번호/확인번호 뒤에 숫자 4~8자리)
                /(?:인증번호|식별번호|고객번호|등록번호|확인번호)\s*[:\-]?\s*(\d{4,8})/gi,
                // "번호는 123456" 같은 형태 지원
                /번호(?:는|:)?\s*(\d{4,8})/gi
                ];
                
            let foundIds = [];
            id_patterns.forEach(pattern => {
                let match;
                while ((match = pattern.exec(text)) !== null) {
                    if (!phoneNumbers.some(p => p.includes(match[1]))) {
                            foundIds.push(match[1]);
                        }
                }
            });
            
            foundIds = [...new Set(foundIds)]; // 중복 제거

            showDynamicInput(foundIds, phoneNumbers);
        }

        function showDynamicInput(foundIds, phoneNumbers) {
            dynamicContainer.classList.add('show');
                detectedIdSection.style.display = 'none';
            multipleIdSection.style.display = 'none';
                phoneInputSection.style.display = 'none';
                confirmationContainer.classList.remove('show');
                confirmedId = null;
                
            if (foundIds.length === 1) {
                confirmedId = foundIds[0];
                detectedIdText.innerHTML = `080번호: <strong>${phoneNumbers.join(', ')}</strong><br>식별번호: <strong>${confirmedId}</strong>`;
                detectedIdSection.style.display = 'block';
            } else if (foundIds.length > 1) {
                multipleIdSection.style.display = 'block';
                idOptions.innerHTML = '';
                foundIds.forEach((id, index) => {
                    idOptions.innerHTML += `
                        <div class="id-option">
                        <input type="radio" id="id${index}" name="selectedId" value="${id}">
                        <label for="id${index}">${id}</label>
                        </div>
                    `;
                });
                idOptions.innerHTML += `
                    <div class="id-option-custom">
                        <input type="radio" id="customId" name="selectedId" value="custom">
                        <label for="customId">직접 입력:</label>
                        <input type="text" id="customIdInput" class="id-custom-input">
                    </div>
                `;
            } else {
                // 식별번호는 없지만 080 수신거부 번호는 파싱됨 – 사용자에게 번호만 안내
                phoneInputSection.style.display = 'none';
                detectedIdText.innerHTML = `080번호: <strong>${phoneNumbers.join(', ')}</strong>`;
                detectedIdSection.style.display = 'block';
            }
        }
        
        idOptions.addEventListener('change', (e) => {
            if (e.target.name === 'selectedId') {
                showConfirmation();
            }
        });
        
        document.getElementById('customIdInput')?.addEventListener('input', showConfirmation);

            function showConfirmation() {
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
                if (!selectedRadio) return;
                
            let selectedValue = (selectedRadio.value === 'custom') 
                ? document.getElementById('customIdInput').value.trim() 
                : selectedRadio.value;

            if(selectedValue) {
                selectedIdDisplay.textContent = selectedValue;
                confirmationContainer.classList.add('show');
            } else {
                confirmationContainer.classList.remove('show');
            }
            }

        confirmButton.addEventListener('click', () => {
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
            if (!selectedRadio) return;
            
            confirmedId = (selectedRadio.value === 'custom')
                ? document.getElementById('customIdInput').value.trim()
                : selectedRadio.value;

            if (confirmedId) {
                detectedIdText.innerHTML = `✅ 선택된 식별번호: <strong>${confirmedId}</strong>`;
                    detectedIdSection.style.display = 'block';
                    multipleIdSection.style.display = 'none';
                confirmationContainer.classList.remove('show');
                }
            });

        cancelButton.addEventListener('click', () => {
                confirmationContainer.classList.remove('show');
            const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
            if(selectedRadio) selectedRadio.checked = false;
                confirmedId = null;
            });

            function hideDynamicInput() {
                dynamicContainer.classList.remove('show');
        }

        spamForm.addEventListener('submit', function(e) {
            e.preventDefault();
            resultArea.style.display = 'block';
            resultArea.innerHTML = '처리 중...';
            
            const formData = new FormData(this);
            if (confirmedId) {
                formData.append('id', confirmedId);
            }
            // 폼 액션(process_v2.php)으로 전송
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // 서버에서 HTML이 넘어와도 태그를 제거하고 텍스트만 표시
                const safeText = typeof data === 'string' ? data.replace(/(<([^>]+)>)/gi, '').trimStart() : data;
                resultArea.textContent = safeText;
                
                // 패턴탐색이 시작된 경우 감지
                if (safeText.includes('패턴 디스커버리를 시작합니다') || safeText.includes('패턴 학습 중입니다')) {
                    // 패턴탐색 시작 후 즉시 녹음 상태 추적 시작
                    setTimeout(() => {
                        startMonitoringPatternDiscovery();
                    }, 3000); // 3초 후 모니터링 시작
                }
                
                getRecordings();
            })
            .catch(error => {
                resultArea.textContent = '오류 발생: ' + error;
            });
        });

        let autoAnalysisSet = new Set();

        // 진행 중인 analysis_id를 추적 (filename -> analysis_id)
        const persistedAnalyses = JSON.parse(localStorage.getItem('activeAnalyses') || '[]');
        const activeAnalysisMap = new Map(persistedAnalyses);

        function persistActiveAnalyses() {
            localStorage.setItem('activeAnalyses', JSON.stringify([...activeAnalysisMap]));
        }


        // 기존 getRecordings 함수 내부에서, 진행 중인 analysis_id가 있으면 해당 항목에 프로그레스바 추가
        function getRecordings() {
            fetch('get_recordings.php')
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 401) {
                            throw new Error('로그인이 필요합니다');
                        }
                        throw new Error(`서버 오류: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        recordingsList.innerHTML = `<div class="analysis-result result-failure">${data.error || '오류 발생'}</div>`;
                        return;
                    }

                    // DOM 업데이트 필요 여부와 관계없이 자동 분석 및 진행 상태 체크는 항상 수행
                    if (data.recordings && data.recordings.length > 0) {
                        // 1. 자동 분석 트리거 (DOM 업데이트 전에 먼저 체크)
                        data.recordings.forEach(rec => {
                            if (rec.ready_for_analysis && !autoAnalysisSet.has(rec.filename)) {
                                // DOM에서 버튼 찾기
                                const btn = document.querySelector(`button.analyze-btn[data-file="${rec.filename}"]`);
                                if (btn && !btn.disabled) {
                                    autoAnalysisSet.add(rec.filename);
                                    handleAnalysisClick(btn);
                                }
                            }
                        });

                        // 2. 통화 진행바 트리거 (DOM 업데이트 전에 체크)
                        data.recordings.forEach(rec => {
                            if (rec.analysis_result === '미분석' && !rec.ready_for_analysis) {
                                const btnEl = document.querySelector(`button.analyze-btn[data-file="${rec.filename}"]`);
                                const recordingItem = btnEl ? btnEl.closest('.recording-item') : null;
                                if (recordingItem && !recordingItem.querySelector('.call-progress')) {
                                    trackCallProgress(recordingItem, rec.filename);
                                }
                            }
                        });

                        // 3. 진행 중인 분석 재개 (localStorage에서 복원)
                        activeAnalysisMap.forEach((analysisId, filename) => {
                            const rec = data.recordings.find(r => r.filename === filename);
                            if (rec && rec.analysis_result === '미분석') {
                                const btnEl = document.querySelector(`button.analyze-btn[data-file="${filename}"]`);
                                const recordingItem = btnEl ? btnEl.closest('.recording-item') : null;
                                if (recordingItem && !recordingItem.querySelector('.analysis-progress')) {
                                    const progressContainer = createProgressUI(recordingItem);
                                    const button = recordingItem.querySelector('.analyze-btn');
                                    if (rec.call_type === 'discovery') {
                                        // 전화번호 추출
                                        let phoneNumber = '';
                                        if (rec.filename.match(/discovery-(\d+)/)) {
                                            phoneNumber = rec.filename.match(/discovery-(\d+)/)[1];
                                        }
                                        trackPatternAnalysisProgress(analysisId, progressContainer, button, button.innerHTML, phoneNumber, filename);
                                    } else {
                                        trackAnalysisProgress(analysisId, progressContainer, button, button.innerHTML);
                                    }
                                }
                            }
                        });

                        // 4. DOM 업데이트는 실제로 변경이 있을 때만
                        if (lastRecordingsUpdate === null || data.updated > lastRecordingsUpdate) {
                            lastRecordingsUpdate = data.updated;

                            // 기존 DOM 업데이트 로직
                            const existingItems = new Map();
                            recordingsList.querySelectorAll('.recording-item').forEach(item => {
                                const audio = item.querySelector('audio');
                                if (audio) {
                                    const src = audio.getAttribute('src');
                                    const match = src.match(/file=([^&]+)/);
                                    if (match) {
                                        existingItems.set(decodeURIComponent(match[1]), item);
                                    }
                                }
                            });

                            const newItems = [];
                            data.recordings.forEach(rec => {
                                let item = existingItems.get(rec.filename);
                                if (item) {
                                    existingItems.delete(rec.filename);
                                } else {
                                    item = createRecordingItem(rec);
                                }
                                newItems.push(item);
                            });

                            existingItems.forEach(item => item.remove());
                            recordingsList.innerHTML = '';
                            newItems.forEach(item => recordingsList.appendChild(item));
                        }
                    } else {
                        recordingsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #888;">표시할 녹음 파일이 없습니다.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching recordings:', error);
                    recordingsList.innerHTML = `<div class="analysis-result result-failure">녹음 목록을 불러오는 데 실패했습니다: ${error.message}</div>`;
                });
        }

        function startMonitoringPatternDiscovery() {
            const checkInterval = setInterval(() => {
                fetch('get_recordings.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.recordings) {
                            // 최신 discovery 녹음 찾기
                            const discoveryRecording = data.recordings.find(rec => 
                                rec.call_type === 'discovery' && 
                                rec.analysis_result === '미분석' &&
                                (Date.now() - rec.file_mtime * 1000) < 60000 // 1분 이내 생성
                            );
                            
                            if (discoveryRecording) {
                                // 통화 진행 상태 추적
                                const recordingItem = document.querySelector(`[data-file="${discoveryRecording.filename}"]`)?.closest('.recording-item');
                                if (recordingItem && !recordingItem.querySelector('.call-progress')) {
                                    trackCallProgress(recordingItem, discoveryRecording.filename);
                                }
                                
                                // ready_for_analysis가 true가 되면 자동 분석 시작
                                if (discoveryRecording.ready_for_analysis && !autoAnalysisSet.has(discoveryRecording.filename)) {
                                    const btn = document.querySelector(`button.analyze-btn[data-file="${discoveryRecording.filename}"]`);
                                    if (btn && !btn.disabled) {
                                        autoAnalysisSet.add(discoveryRecording.filename);
                                        handleAnalysisClick(btn);
                                    }
                                }
                                
                                clearInterval(checkInterval); // 녹음 찾으면 모니터링 중지
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error monitoring pattern discovery:', error);
                    });
            }, 2000); // 2초마다 체크
            
            // 5분 후 자동으로 모니터링 중지
            setTimeout(() => {
                clearInterval(checkInterval);
            }, 300000);
        }

        // 데이터 객체를 받아 녹음 항목 DOM 요소를 생성하는 함수
        function createRecordingItem(rec) {
            const item = document.createElement('div');
            item.className = 'recording-item';
            
            const statusColor = rec.analysis_result === '성공' ? 'result-success' : 
                                rec.analysis_result === '실패' ? 'result-failure' :
                                rec.analysis_result === '불확실' ? 'result-uncertain' : 
                                rec.analysis_result === '미분석' ? 'result-unknown' : 'result-unknown';
            
            const callTypeLabel = rec.call_type === 'discovery' 
                ? '<span class="label label-discovery">패턴탐색</span>' 
                : '<span class="label label-unsubscribe">수신거부</span>';

            const autoLabel = rec.trigger === 'auto' ? '<span class="label label-auto">자동</span>' : '';

            let patternTypeBadge = '';
            if (rec.pattern_data) {
                if (rec.pattern_data.auto_supported === false) {
                    patternTypeBadge = '<span class="label label-unverified">확인 번호만 필요</span>';
                } else if (rec.pattern_data.pattern_type === 'id_only') {
                    patternTypeBadge = '<span class="label label-id-only">식별번호만 필요</span>';
                } else if (rec.pattern_data.pattern_type === 'confirm_only') {
                    patternTypeBadge = '<span class="label label-unverified">확인 번호만 필요</span>';
                }
            }
            const registrationBadge = rec.pattern_registered ? '<span class="label label-registered">패턴등록</span>' : '';

            let analysisDetailsHtml = '';
            let showAnalyzeButton = false;
            let showReanalyzeButton = false;
            const isConfirmOnly = rec.pattern_data && (rec.pattern_data.auto_supported === false || rec.pattern_data.pattern_type === 'confirm_only');
            let showRetryCallButton = false;
            if (rec.call_type === 'unsubscribe' && (rec.analysis_result === '실패' || rec.analysis_result === '불확실' || rec.analysis_result === '시도됨')) {
                showRetryCallButton = true;
            }
                    
            if (rec.analysis_result && rec.analysis_result !== '미분석') {
                const completedAt = rec.completed_at ? new Date(rec.completed_at).toLocaleString('ko-KR') : '';
                const confidenceText = rec.confidence ? ` (신뢰도: ${rec.confidence}%)` : '';
                
                // 패턴 탐색 결과인 경우 특별 처리
                if (rec.call_type === 'discovery' && rec.pattern_data) {
                    analysisDetailsHtml = `
                        <strong>패턴 분석 완료</strong>${confidenceText}${completedAt ? ` <span style="color:#666;">(${completedAt})</span>` : ''}
                        <p><strong>패턴명:</strong> ${rec.pattern_data.name}</p>
                        <p><strong>DTMF 타이밍:</strong> ${rec.pattern_data.dtmf_timing}초</p>
                        <p><strong>DTMF 패턴:</strong> ${rec.pattern_data.dtmf_pattern}</p>
                    `;
                } else {
                    // 일반 분석 결과
                    analysisDetailsHtml = `
                        <strong>분석 결과:</strong> ${rec.analysis_result}${confidenceText}${completedAt ? ` <span style="color:#666;">(${completedAt})</span>` : ''}
                        <p>${rec.analysis_text || ''}</p>
                    `;
                }
                
                if (rec.transcription) {
                    const transText = rec.transcription.trim() ? rec.transcription : '변환된 텍스트를 가져올 수 없습니다.';
                    analysisDetailsHtml += `
                        <div class="transcription-container">
                            <button class="btn btn-small btn-secondary toggle-transcription">전체 내용 보기</button>
                            <div class="transcription-text" style="display: none;">
                                <p><strong>변환된 텍스트:</strong></p>
                                <pre>${transText}</pre>
                            </div>
                            </div>
                        `;
                }
                showReanalyzeButton = true; // 분석 완료된 파일에 다시 분석 버튼 표시
            } else if (rec.call_type === 'discovery' && rec.pattern_registered) {
                // 패턴이 이미 등록된 탐색 녹음
                if (rec.pattern_data) {
                    const pat = rec.pattern_data;
                    analysisDetailsHtml = `
                        <strong>패턴 등록 완료</strong><br/>
                        <p><strong>패턴명:</strong> ${pat.name || '자동 생성 패턴'}</p>
                        <p><strong>DTMF 패턴:</strong> ${pat.dtmf_pattern}</p>
                        <p><strong>DTMF 타이밍:</strong> ${pat.dtmf_timing}초</p>
                        <p><strong>초기 대기:</strong> ${pat.initial_wait}초</p>
                        <p><strong>확인 DTMF:</strong> ${pat.confirmation_dtmf} (지연 ${pat.confirm_delay}s x ${pat.confirm_repeat}회)</p>
                    `;
                } else {
                    analysisDetailsHtml = '<strong>패턴 등록 완료</strong><br/>이미 자동 생성된 패턴이 등록되어 있습니다.';
                }
            } else {
                // 미분석 + 패턴 미등록 -> 결과 영역 숨김
                analysisDetailsHtml = '';
                showAnalyzeButton = true;
            }

            // 분석 결과 섹션 (없을 경우 display:none)
            const analysisResultSection = `
                <div class="analysis-result ${statusColor}" style="display: ${analysisDetailsHtml ? 'block' : 'none'};">
                    ${analysisDetailsHtml}
                </div>`;

            // auto-analysis 로직은 filename 으로 버튼을 찾으므로 data-file 은 순수 파일명만 사용
            const fileForAnalysis = rec.filename;

            item.innerHTML = `
                <div class="recording-header">
                                <div class="recording-info">
                                    <div class="recording-title">
                            📞 ${rec.title}
                                    </div>
                                    <div class="recording-datetime">
                            <span class="date-icon">📅</span> ${rec.datetime}
                                    </div>
                                </div>
                    <div class="recording-tags">${callTypeLabel} ${autoLabel} ${registrationBadge} ${patternTypeBadge}</div>
                                    </div>
                <audio controls preload="metadata" src="player.php?file=${encodeURIComponent(rec.filename)}&v=${rec.file_mtime}" style="width: 100%; margin-top: 10px;"></audio>
                ${analysisResultSection}
                ${showAnalyzeButton ? `
                <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small analyze-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic" viewBox="0 0 16 16">
                            <path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 4a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8.707 4a.5.5 0 0 0 0-.707Zm-3.5 1.65A.5.5 0 0 0 3.293 6L2 7.293a.5.5 0 1 0 .707.707L4 6.707a.5.5 0 0 0 0-.707l-.707-.707a.5.5 0 0 0-.707 0ZM10 8a2 2 0 1 0-4 0 2 2 0 0 0 4 0Z"/>
                            <path d="M6.25 10.5c.065.14.12.29.18.445l.08.18a.5.5 0 0 0 .868.036l.338-.676a.5.5 0 0 0-.16-.672l-.354-.354a.5.5 0 0 0-.85-.043l-.248.495Zm3.5 0c.065.14.12.29.18.445l.08.18a.5.5 0 0 0 .868.036l.338-.676a.5.5 0 0 0-.16-.672l-.354-.354a.5.5 0 0 0-.85-.043l-.248.495ZM1.625 13.5A.5.5 0 0 0 1 14h14a.5.5 0 0 0-.625-.5h-12.75Z"/>
                        </svg>
                        분석하기
                                    </button>
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small delete-btn">
                        🗑 삭제
                                    </button>
                                </div>
                ` : ''}
                ${showReanalyzeButton ? `
                <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small reanalyze-btn analyze-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                        ${rec.call_type === 'discovery' ? '패턴 다시 분석하기' : '다시 분석하기'}
                    </button>
                    ${showRetryCallButton ? `<button data-file="${fileForAnalysis}" data-phone="${rec.title}" data-id="${rec.identification_number || rec.id || ''}" data-notify="${rec.notification_phone || ''}" class="btn btn-small retry-call-btn" ${isConfirmOnly?'disabled title="자동 수신거부가 불가능합니다."':''}>${isConfirmOnly?'☎️ 직접 전화 필요':'📞 다시 시도하기'}</button>` : ''}
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small delete-btn">🗑 삭제</button>
                            </div>
                ` : ''}
            `;
            
            
            // 이벤트 리스너 추가 (이벤트 위임 대신 직접 추가)
            const transcriptionToggle = item.querySelector('.toggle-transcription');
            if (transcriptionToggle) {
                transcriptionToggle.addEventListener('click', function() {
                    const textDiv = item.querySelector('.transcription-text');
                    const isVisible = textDiv.style.display === 'block';
                    textDiv.style.display = isVisible ? 'none' : 'block';
                    this.textContent = isVisible ? '전체 내용 보기' : '숨기기';
                    // 파일명 기준으로 펼침 상태 저장/제거
                    if (!isVisible) {
                        openTranscriptions.add(rec.filename);
                    } else {
                        openTranscriptions.delete(rec.filename);
                    }
                    localStorage.setItem('openTranscriptions', JSON.stringify([...openTranscriptions]));
                });
            }
            // 목록 갱신 시 펼침 상태 복원
            if (openTranscriptions.has(rec.filename)) {
                const textDiv = item.querySelector('.transcription-text');
                if (textDiv) {
                    textDiv.style.display = 'block';
                    if (transcriptionToggle) transcriptionToggle.textContent = '숨기기';
                }
            }
            
            // 통화 진행 상태 즉시 트리거 (녹음중일 때)
            if (rec.analysis_result === '미분석' && !rec.ready_for_analysis && !item.querySelector('.call-progress')) {
                trackCallProgress(item, rec.filename);
            }
            
            const retryBtn = item.querySelector('.retry-call-btn');
            if (retryBtn && !retryBtn.disabled) {
                retryBtn.addEventListener('click', function(){
                    const phone = this.dataset.phone;
                    const idVal = this.dataset.id || '';
                    const notifyVal = this.dataset.notify || '';
                    if(!phone){ showToast('전화번호를 확인할 수 없습니다.',true); return; }
                    if (rec.pattern_data && rec.pattern_data.auto_supported === false) {
                        showToast('이 번호는 자동 수신거부가 불가능합니다. 안내에 따라 수동으로 진행해주세요.', true);
                        return;
                    }
                    // confirm 제거 – 바로 재시도 실행
                    const params = `phone=${encodeURIComponent(phone)}&id=${encodeURIComponent(idVal)}${notifyVal?`&notify=${encodeURIComponent(notifyVal)}`:''}`;
                    fetch('retry_call.php',{
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:params
                    })
                    .then(r=>r.text())
                    .then(txt=>{ const msg = txt.trim()?txt:'자동 수신거부가 불가능한 번호입니다.'; showToast(msg); getRecordings(); })
                    .catch(()=>showToast('다시 시도 요청 중 오류가 발생했습니다.',true));
                });
            }
            
            return item;
        }

        // 수동 분석 버튼 클릭 처리 함수
        function handleAnalysisClick(button) {
            const recordingFile = button.dataset.file;
            const callType = button.dataset.type || 'unsubscribe';
            console.log('Analyze button clicked, file:', recordingFile, 'type:', callType);
            console.log('Button dataset:', button.dataset);
            console.log('Button HTML:', button.outerHTML);
            
            if (!recordingFile) {
                showToast('분석할 파일 경로를 찾을 수 없습니다.', true);
                return;
            }

            // 버튼이 있는 recording-item 찾기
            const recordingItem = button.closest('.recording-item');
            
            // 버튼 상태 변경
            button.disabled = true;
            const originalContent = button.innerHTML;
            button.innerHTML = '<span class="spinner" style="width: 14px; height: 14px; margin-right: 5px;"></span> 분석 시작중...';

            // 전체 경로가 아닌 파일명만 전송
            const filename = recordingFile.includes('/') ? recordingFile.split('/').pop() : recordingFile;
            const fullPath = recordingFile.includes('/') ? recordingFile : '/var/spool/asterisk/monitor/' + recordingFile;

            console.log('Sending request with file:', fullPath);
            
            // call_type에 따라 다른 API 호출
            const apiUrl = callType === 'discovery' ? 'analyze_pattern_recording.php' : 'analyze_recording.php';

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'file=' + encodeURIComponent(fullPath)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Response body:', text);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Analysis response:', data);
                if (data.success && data.analysis_id) {
                    // 진행 상황 표시 UI 생성
                    const progressContainer = createProgressUI(recordingItem);
                    // 진행 중인 analysis_id를 추적
                    activeAnalysisMap.set(filename, data.analysis_id);
                    persistActiveAnalyses();
                    // call_type에 따라 다른 진행 상황 추적
                    if (callType === 'discovery') {
                        trackPatternAnalysisProgress(data.analysis_id, progressContainer, button, originalContent, data.phone_number, filename);
                    } else {
                        trackAnalysisProgress(data.analysis_id, progressContainer, button, originalContent);
                    }
                } else {
                    showToast('분석 시작 실패: ' + (data.message || '알 수 없는 오류'), true);
                    button.disabled = false;
                    button.innerHTML = originalContent;
                    autoAnalysisSet.delete(filename);
                    activeAnalysisMap.delete(filename);
                }
            })
            .catch(error => {
                showToast('분석 스크립트 실행 중 오류가 발생했습니다.', true);
                console.error('Fetch Error:', error);
                button.disabled = false;
                button.innerHTML = originalContent;
                autoAnalysisSet.delete(filename);
                activeAnalysisMap.delete(filename);
            });
        }

        // 진행 상황 UI 생성
        function createProgressUI(recordingItem) {
            // 기존 진행 상황 UI가 있으면 제거
            const existingProgress = recordingItem.querySelector('.analysis-progress');
            if (existingProgress) {
                existingProgress.remove();
            }

            const progressHTML = `
                <div class="analysis-progress" style="margin-top: 15px; padding: 15px; background: #f0f4f8; border-radius: 8px; border: 1px solid #d1d9e6;">
                    <div class="progress-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span class="progress-stage" style="font-weight: 600; color: #4a5568;">분석 준비중...</span>
                        <span class="progress-percentage" style="font-weight: 600; color: #667eea;">0%</span>
                    </div>
                    <div class="progress-bar" style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div class="progress-fill" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                    <div class="progress-message" style="margin-top: 8px; font-size: 13px; color: #718096;">대기중...</div>
                </div>
            `;

            recordingItem.insertAdjacentHTML('beforeend', progressHTML);
            return recordingItem.querySelector('.analysis-progress');
        }

        // 진행 상황 추적 (수신거부 분석용)
        function trackAnalysisProgress(analysisId, progressContainer, button, originalButtonContent) {
            const stageElement = progressContainer.querySelector('.progress-stage');
            const percentageElement = progressContainer.querySelector('.progress-percentage');
            const fillElement = progressContainer.querySelector('.progress-fill');
            const messageElement = progressContainer.querySelector('.progress-message');
            const recordingItem = progressContainer.closest('.recording-item');

            const stageNames = {
                'queued': '대기중',
                'starting': '시작중',
                'file_check': '파일 확인',
                'loading_model': '모델 로딩',
                'model_loaded': '모델 로드 완료',
                'transcribing': '음성 변환',
                'transcription_done': 'STT 완료',
                'analyzing_keywords': '키워드 분석',
                'analyzing': '텍스트 분석',
                'saving': '결과 저장',
                'completed': '완료',
                'error': '오류',
                'timeout': '시간 초과'
            };

            // 진행 상황 확인 함수
            const POLL_INTERVAL = 400; // ms – 더 짧은 주기로 폴링하여 빠른 단계 변화를 포착

            const checkProgress = () => {
                fetch(`get_analysis_progress.php?analysis_id=${analysisId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                            const stage = data.stage || 'unknown';
                            const percentage = data.percentage || 0;
                            const message = data.message || '';

                            // UI 업데이트
                            stageElement.textContent = stageNames[stage] || stage;
                            percentageElement.textContent = percentage + '%';
                            fillElement.style.width = percentage + '%';
                            messageElement.textContent = message;

                            if (data.completed || stage === 'completed') {
                                // 분석 완료
                                progressContainer.style.background = '#d1fae5';
                                progressContainer.style.borderColor = '#a7f3d0';
                                stageElement.style.color = '#065f46';
                                
                                setTimeout(() => {
                                    progressContainer.remove();
                                    button.disabled = false;
                                    button.innerHTML = originalButtonContent;
                                    showToast('분석이 완료되었습니다!');
                                    
                                    // 해당 녹음 항목만 업데이트
                                    updateSingleRecordingItem(recordingItem);
                                }, 2000);
                            } else if (stage === 'error' || stage === 'timeout') {
                                // 오류 발생
                                progressContainer.style.background = '#fee2e2';
                                progressContainer.style.borderColor = '#fecaca';
                                stageElement.style.color = '#991b1b';
                                
                                setTimeout(() => {
                                    progressContainer.remove();
                                    button.disabled = false;
                                    button.innerHTML = originalButtonContent;
                                }, 3000);
                } else {
                                // 계속 진행중 – 지정 주기 후 다시 확인
                                setTimeout(checkProgress, POLL_INTERVAL);
                            }
                        } else {
                            // API 오류
                            console.error('Progress check failed:', data);
                            progressContainer.remove();
                            button.disabled = false;
                            button.innerHTML = originalButtonContent;
                }
            })
            .catch(error => {
                        console.error('Progress check error:', error);
                        progressContainer.remove();
                        button.disabled = false;
                        button.innerHTML = originalButtonContent;
                    });
            };
            
            // 첫 번째 확인은 250ms 후에 시작 – 빠른 초기 단계 포착
            setTimeout(checkProgress, 250);
        }

        // 단일 녹음 항목 업데이트 함수
        function updateSingleRecordingItem(recordingItem) {
            // 오디오 요소에서 파일명 추출
            const audioElement = recordingItem.querySelector('audio');
            if (!audioElement) return;
            
            const src = audioElement.getAttribute('src');
            const match = src.match(/file=([^&]+)/);
            if (!match) return;
            
            const filename = decodeURIComponent(match[1]);
            
            // 서버에서 최신 데이터 가져오기
            fetch('get_recordings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.recordings) {
                        // 해당 파일의 최신 정보 찾기
                        const updatedRec = data.recordings.find(rec => rec.filename === filename);
                        if (updatedRec) {
                            // 새로운 항목으로 교체
                            const newItem = createRecordingItem(updatedRec);
                            recordingItem.replaceWith(newItem);
                            
                            // 애니메이션 효과
                            newItem.style.animation = 'fadeIn 0.5s ease-in';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating recording item:', error);
            });
        }

        // 패턴 분석 진행 상황 추적
        function trackPatternAnalysisProgress(analysisId, progressContainer, button, originalButtonContent, phoneNumber, filename) {
            const stageElement = progressContainer.querySelector('.progress-stage');
            const percentageElement = progressContainer.querySelector('.progress-percentage');
            const fillElement = progressContainer.querySelector('.progress-fill');
            const messageElement = progressContainer.querySelector('.progress-message');
            const recordingItem = progressContainer.closest('.recording-item');

            const stageNames = {
                'queued': '대기중',
                'starting': '시작중',
                'loading_model': '모델 로딩',
                'model_loaded': '모델 로드 완료',
                'transcribing': '음성 변환',
                'transcribed': '음성 변환 완료',
                'analyzing_keywords': '키워드 분석',
                'analyzing': '텍스트 분석',
                'saving': '결과 저장',
                'completed': '완료',
                'error': '오류',
                'timeout': '시간 초과'
            };

            // 폴링 주기 (ms)
            const POLL_INTERVAL = 800;

            // 진행 상황 확인 함수
            const checkProgress = () => {
                fetch(`get_pattern_analysis_progress.php?analysis_id=${analysisId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && !data.prevent_refresh) {
                            const stage = data.stage || 'unknown';
                            const percentage = data.percentage || 0;
                            const message = data.message || '';

                            // UI 업데이트
                            stageElement.textContent = stageNames[stage] || stage;
                            percentageElement.textContent = percentage + '%';
                            fillElement.style.width = percentage + '%';
                            messageElement.textContent = message;
                            
                            if (data.completed || stage === 'completed') {
                                // 분석 완료
                                progressContainer.style.background = '#d1fae5';
                                progressContainer.style.borderColor = '#a7f3d0';
                                stageElement.style.color = '#065f46';
                                
                                let successMessage = '패턴 분석이 완료되었습니다!';
                                successMessage += ` ${phoneNumber} 번호의 패턴이 저장되었습니다.`;
                                if (data.pattern_saved) {
                                    successMessage += ` ${phoneNumber} 번호의 패턴이 저장되었습니다.`;
                                }
                                if (filename && activeAnalysisMap.has(filename)) {
                                    activeAnalysisMap.delete(filename);
                                    persistActiveAnalyses();
                                }
                                
                                setTimeout(() => {
                                    progressContainer.remove();
                                    if (button) {
                                        button.disabled = false;
                                        button.innerHTML = originalButtonContent;
                                    }
                                    showToast(successMessage);
                                    
                                    // 패턴 분석 결과 표시
                                    if (data.result) {
                                        displayPatternAnalysisResult(recordingItem, data.result);
                                    }
                                    // 패턴 저장에 따른 태그 갱신
                                    updateSingleRecordingItem(recordingItem);
                                }, 2000);
                            } else if (stage === 'error' || stage === 'timeout') {
                                // 오류 발생
                                progressContainer.style.background = '#fee2e2';
                                progressContainer.style.borderColor = '#fecaca';
                                stageElement.style.color = '#991b1b';
                            
                            setTimeout(() => {
                                    progressContainer.remove();
                                    if (button) {
                                        button.disabled = false;
                                        button.innerHTML = originalButtonContent;
                                    }
                            }, 3000);
                            } else {
                                // 계속 진행중 – 지정 주기 후 다시 확인
                                setTimeout(checkProgress, POLL_INTERVAL);
                            }
                        } else {
                            // 아직 progress 파일이 생성되지 않았거나 서버가 준비 중
                            stageElement.textContent = '대기중';
                            messageElement.textContent = '서버 준비중...';
                            setTimeout(checkProgress, 1500); // 재시도
                         }
                    })
                    .catch(error => {
                        console.error('Progress check error:', error);
                        progressContainer.remove();
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = originalButtonContent;
                        }
                    });
            };
            
            // 첫 번째 확인은 500ms 후에 시작
            setTimeout(checkProgress, 500);
        }

        // 패턴 분석 결과 표시
        function displayPatternAnalysisResult(recordingItem, result) {
            const analysisResultDiv = recordingItem.querySelector('.analysis-result');
            if (!analysisResultDiv) return;
            
            const pattern = result.pattern;
            const confidence = result.confidence || 0;
            
            analysisResultDiv.className = 'analysis-result result-success';
            analysisResultDiv.style.display = 'block';
            analysisResultDiv.innerHTML = `
                <strong>패턴 분석 완료</strong> (신뢰도: ${confidence}%)
                <p><strong>패턴명:</strong> ${pattern.name}</p>
                <p><strong>DTMF 타이밍:</strong> ${pattern.dtmf_timing}초</p>
                <p><strong>DTMF 패턴:</strong> ${pattern.dtmf_pattern}</p>
                ${result.transcription ? `
                <div class="transcription-container">
                    <button class="btn btn-small btn-secondary toggle-transcription">전체 내용 보기</button>
                    <div class="transcription-text" style="display: none;">
                        <p><strong>변환된 텍스트:</strong></p>
                        <pre>${result.transcription}</pre>
                </div>
                    </div>
                ` : ''}
            `;

            // 토글 버튼에 이벤트 리스너 추가
            const transcriptionToggle = analysisResultDiv.querySelector('.toggle-transcription');
            if (transcriptionToggle) {
                transcriptionToggle.addEventListener('click', function() {
                    const textDiv = analysisResultDiv.querySelector('.transcription-text');
                    const isVisible = textDiv.style.display === 'block';
                    textDiv.style.display = isVisible ? 'none' : 'block';
                    this.textContent = isVisible ? '전체 내용 보기' : '숨기기';
                });
            }

            // 버튼 영역 업데이트 - 다시 분석하기 버튼만 표시
            const analyzeBtn = recordingItem.querySelector('.analyze-btn');
            const fileForAnalysis = analyzeBtn ? analyzeBtn.dataset.file : '';
            const buttonContainer = analyzeBtn ? analyzeBtn.parentElement : null;
            
            if (buttonContainer) {
                buttonContainer.innerHTML = `
                    <button data-file="${fileForAnalysis}" data-type="discovery" class="btn btn-small reanalyze-btn analyze-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                        패턴 다시 분석하기
                    </button>
                `;
            }
            
            // 분석 완료 후 전체 목록 새로고침하여 결과가 유지되도록 함
            setTimeout(() => {
                getRecordings();
            }, 1000);
        }

        // 수동 분석 버튼 클릭 처리 - 이벤트 위임 수정
        recordingsList.addEventListener('click', function(event) {
            // 삭제 버튼 처리
            const delBtn = event.target.closest('.delete-btn');
            if (delBtn && !delBtn.disabled) {
                event.preventDefault();
                handleDeleteClick(delBtn);
                return;
        }

            // 분석(재분석) 버튼 처리
            const analyzeBtn = event.target.closest('.analyze-btn');
            if (analyzeBtn && !analyzeBtn.disabled) {
                event.preventDefault();
                handleAnalysisClick(analyzeBtn);
            }
        });

        // 오디오 플레이어 로드 시 시간 초기화 (버그 수정)
        recordingsList.addEventListener('loadedmetadata', function(e) {
            if (e.target.tagName === 'AUDIO') {
                e.target.currentTime = 0;
                // 시간 표시 포맷 수정
                updateAudioTimeDisplay(e.target);
            }
        }, true);

        // 오디오 시간 업데이트 이벤트
        recordingsList.addEventListener('timeupdate', function(e) {
            if (e.target.tagName === 'AUDIO') {
                updateAudioTimeDisplay(e.target);
            }
        }, true);

        // 오디오 시간 표시 업데이트 함수
        function updateAudioTimeDisplay(audio) {
            // 브라우저의 기본 컨트롤을 사용하므로 별도 처리 불필요
            // 하지만 NaN 문제를 방지하기 위한 체크 추가
            if (isNaN(audio.duration)) {
                audio.load(); // 오디오 다시 로드
            }
        }

        // 시간 포맷팅 함수
        function formatTime(seconds) {
            if (isNaN(seconds) || seconds === Infinity) return '0:00';
            const minutes = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }

        // 토스트 알림 함수
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast-notification ' + (isError ? 'error' : 'success');
            toast.style.display = 'block';
                            
                            setTimeout(() => {
                toast.style.display = 'none';
                            }, 3000);
        }

        // 새로고침 버튼 이벤트 리스너
        refreshBtn.addEventListener('click', function() {
            getRecordings();
        });

        // 삭제 버튼 클릭 처리 함수
        function handleDeleteClick(button) {
            const recordingFile = button.dataset.file;
            const callType = button.dataset.type || 'unsubscribe';
            if (!recordingFile) return;

            if (!confirm('정말 이 녹음과 분석 결과를 삭제하시겠습니까?')) {
                return;
            }

            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '삭제중...';

            fetch('delete_recording.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'file=' + encodeURIComponent(recordingFile) + '&type=' + encodeURIComponent(callType)
            })
                .then(response => response.json())
                .then(data => {
                if (data.success) {
                    showToast('삭제되었습니다.');
                    const item = button.closest('.recording-item');
                    if (item) item.remove();
                } else {
                    showToast('삭제 실패: ' + (data.errors ? data.errors.join(', ') : '알 수 없는 오류'), true);
                    button.disabled = false;
                    button.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                console.error('Delete error:', error);
                showToast('삭제 중 오류가 발생했습니다.', true);
                button.disabled = false;
                button.innerHTML = originalContent;
                });
        }

        function createCallProgressUI(recordingItem) {
            const html = `
            <div class="call-progress" style="margin-top:10px;padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="call-status" style="color:#0369a1;font-weight:600;">통화 연결중...</span>
                    <span class="call-duration" style="color:#0369a1;font-weight:600;">0s</span>
                </div>
                <div class="progress-bar" style="background:#e0f2fe;height:6px;border-radius:4px;margin-top:8px;overflow:hidden;">
                    <div class="progress-fill" style="background:#0ea5e9;width:0;height:100%;transition:width 0.3s;"></div>
                </div>
                <div class="call-log"></div>
            </div>`;
            recordingItem.insertAdjacentHTML('beforeend', html);
            return recordingItem.querySelector('.call-progress');
        }

        function trackCallProgress(recordingItem, filename) {
            let progressEl = recordingItem.querySelector('.call-progress');
            if (!progressEl) {
                progressEl = createCallProgressUI(recordingItem);
            }

            // 로그 메시지를 친절한 한국어로 변환하는 헬퍼
            function translateCallLog(msg){
                if(!msg) return '';
                msg = msg.trim();
                if(msg.startsWith('RECORDING_START')) return '녹음 시작';
                if(msg.startsWith('RECORDING_END'))   return '녹음 종료';
                if(msg.startsWith('SENDING FIRST DTMF'))  return '식별번호 전송 중';
                if(msg.startsWith('SENDING SECOND DTMF')) return '확인 DTMF 전송 중';
                if(msg.startsWith('DTMF_CONFIRMED'))      return 'DTMF 확인됨';
                if(msg.includes('STT'))                   return '음성 인식 중';
                if(msg.includes('TRANSCRIBE')||msg.includes('TRANSCRIPTION')) return '음성 텍스트 변환 중';
                if(msg.includes('ANALYSIS'))              return '분석 중';
                if(msg.includes('TRIGGER'))               return '분석 트리거';
                if(msg.includes('WAITING') || msg.includes('IVR')) return '음성 안내 대기 중';
                if(msg.startsWith('CALL_FINISHED')||msg.startsWith('HANGUP')) return '통화 종료';
                if(msg.startsWith('FIRST_DTMF_SENT'))  return '식별번호 전송 완료';
                if(msg.startsWith('SECOND_DTMF_SENT')) return '확인 DTMF 전송 완료';
                if(msg.startsWith('UNSUB_success'))     return '수신거부 성공';
                if(msg.startsWith('UNSUB_failed'))      return '수신거부 실패';
                if(msg.startsWith('STT_START'))         return '음성 인식 시작';
                if(msg.startsWith('STT_DONE'))          return '음성 인식 완료';
                return msg; // 기본: 원본 유지
            }

            const statusEl = progressEl.querySelector('.call-status');
            const durEl = progressEl.querySelector('.call-duration');
            const fillEl = progressEl.querySelector('.progress-fill');
            const logEl  = progressEl.querySelector('.call-log');

            const poll = () => {
                fetch(`get_call_progress.php?file=${encodeURIComponent(filename)}`)
                    .then(r=>r.json())
                    .then(data=>{
                        if(!data.exists){
                            statusEl.textContent='녹음 대기중...';
                            setTimeout(poll,2000);
                            return;
                        }
                        durEl.textContent=`${data.duration_est}s`;
                        const percent=Math.min((data.duration_est/40)*100,99);
                        fillEl.style.width=percent+'%';
                        // 최신 call_progress 로그(여러 줄)로 상태 및 로그 영역 업데이트
                        (function(){
                            const m = filename.match(/-ID_([A-Za-z0-9]+)/);
                            if(!m) return;
                            fetch(`get_call_detail.php?id=${m[1]}&lines=20`)
                            .then(r=>r.json())
                            .then(d=>{
                                if(d.success && d.lines && d.lines.length){
                                    // 상태(마지막 줄) 업데이트
                                    const lastRaw = d.lines[d.lines.length-1];
                                    const lastMsg = lastRaw.substring(lastRaw.indexOf(']')+2);
                                    statusEl.textContent = translateCallLog(lastMsg);
                                    // 전체 로그 표시
                                    if(logEl){
                                        const text = d.lines.map(l=>l.substring(l.indexOf(']')+2)).join('\n');
                                        logEl.textContent = text;
                                        logEl.scrollTop = logEl.scrollHeight;
                                    }
                                }
                            }).catch(()=>{});
                        })();
                        if(data.finished){
                            statusEl.textContent='통화 종료';
                            fillEl.style.width='100%';
                            setTimeout(()=>{
                                progressEl.remove();
                                autoAnalysisSet.delete(filename); // 자동 분석 트리거를 위해 추가
                                getRecordings();
                            },3000);
                        }else{
                            setTimeout(poll,2000);
                        }
                    })
                    .catch(()=>setTimeout(poll,3000));
            };
            poll();
        }

        function updateProgressDisplay(progressData) {
            const progressBar = document.getElementById('analysisProgress');
            const progressText = document.getElementById('progressText');
            const progressMessage = document.getElementById('progressMessage');
            
            if (!progressBar || !progressText || !progressMessage) return;
            
            // 진행률 업데이트
            progressBar.style.width = progressData.percentage + '%';
            progressText.textContent = progressData.percentage + '%';
            
            // 진행 상태 메시지 업데이트
            progressMessage.textContent = progressData.message;
            
            // 단계별 진행상황 표시
            if (progressData.steps) {
                const stepsContainer = document.getElementById('analysisSteps');
                if (stepsContainer) {
                    let stepsHtml = '';
                    for (const [step, progress] of Object.entries(progressData.steps)) {
                        const stepName = {
                            'audio_processing': '오디오 처리',
                            'pattern_detection': '패턴 감지',
                            'pattern_analysis': '패턴 분석',
                            'saving': '결과 저장'
                        }[step] || step;
                        
                        stepsHtml += `
                            <div class="step-progress">
                                <div class="step-name">${stepName}</div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: ${progress}%" 
                                         aria-valuenow="${progress}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        ${progress}%
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    stepsContainer.innerHTML = stepsHtml;
                }
            }
            
            // 분석이 완료되면 프로그레스 바 숨기기
            if (progressData.completed) {
                setTimeout(() => {
                    const progressContainer = document.getElementById('progressContainer');
                    if (progressContainer) {
                        progressContainer.style.display = 'none';
                    }
                }, 2000);
            }
        }

        // 진행상황 체크 함수
        function checkPatternAnalysisProgress(analysisId) {
            if (!analysisId) {
                console.error('No analysis ID provided');
                return;
            }
            
            console.log('Checking progress for analysis:', analysisId);
            
            // 진행상황 컨테이너 표시
            const progressContainer = document.getElementById('progressContainer');
            if (progressContainer) {
                progressContainer.style.display = 'block';
            }
            
            // 진행상황 체크
            fetch(`get_pattern_analysis_progress.php?analysis_id=${analysisId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Progress data:', data);
                    
                    if (data.success) {
                        updateProgressDisplay(data);
                        
                        // 분석이 완료되지 않았으면 계속 체크
                        if (!data.completed) {
                            setTimeout(() => checkPatternAnalysisProgress(analysisId), 1000);
                        } else {
                            // 분석이 완료되면 3초 후에 페이지 새로고침
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        }
                    } else {
                        console.error('Progress check failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Progress check error:', error);
                });
        }

        // 진행상황 표시 업데이트
        function updateProgressDisplay(progressData) {
            console.log('Updating progress display:', progressData);
            
            const progressBar = document.getElementById('analysisProgress');
            const progressText = document.getElementById('progressText');
            const progressMessage = document.getElementById('progressMessage');
            
            if (!progressBar || !progressText || !progressMessage) {
                console.error('Progress display elements not found');
                return;
            }
            
            // 진행률 업데이트
            progressBar.style.width = progressData.percentage + '%';
            progressBar.setAttribute('aria-valuenow', progressData.percentage);
            progressText.textContent = progressData.percentage + '%';
            
            // 진행 상태 메시지 업데이트
            progressMessage.textContent = progressData.message;
            
            // 단계별 진행상황 표시
            if (progressData.steps) {
                const stepsContainer = document.getElementById('analysisSteps');
                if (stepsContainer) {
                    let stepsHtml = '';
                    for (const [step, progress] of Object.entries(progressData.steps)) {
                        const stepName = {
                            'audio_processing': '오디오 처리',
                            'pattern_detection': '패턴 감지',
                            'pattern_analysis': '패턴 분석',
                            'saving': '결과 저장'
                        }[step] || step;
                        
                        stepsHtml += `
                            <div class="step-progress" style="margin-bottom: 10px;">
                                <div class="step-name" style="margin-bottom: 5px;">${stepName}</div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: ${progress}%" 
                                         aria-valuenow="${progress}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        ${progress}%
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    stepsContainer.innerHTML = stepsHtml;
                }
            }
        }

        // 페이지 로드 시 진행상황 체크 시작
        document.addEventListener('DOMContentLoaded', function() {
            // 로그인 여부에 따라 녹음 목록 로드
            if (window.IS_LOGGED) {
                getRecordings();
            }
            
            // 인증 관련 이벤트 리스너 설정
            setupVerificationFlow();
        });

        // 패턴 분석 시작 함수
        function startPatternAnalysis(recordingFile) {
            console.log('Starting pattern analysis for file:', recordingFile);
            
            const formData = new FormData();
            formData.append('file', recordingFile);
            
            fetch('analyze_pattern_recording.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Analysis start response:', data);
                
                if (data.success) {
                    // 분석 ID를 URL에 추가하고 진행상황 체크 시작
                    const url = new URL(window.location.href);
                    url.searchParams.set('analysis_id', data.analysis_id);
                    window.history.pushState({}, '', url);
                    
                    checkPatternAnalysisProgress(data.analysis_id);
                } else {
                    console.error('Analysis start failed:', data.message);
                    alert('패턴 분석 시작 실패: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Analysis start error:', error);
                alert('패턴 분석 시작 중 오류가 발생했습니다.');
            });
        }

        // 펼침 상태 관리용 Set (localStorage 활용)
        const openTranscriptions = new Set(JSON.parse(localStorage.getItem('openTranscriptions') || '[]'));

        // 인증 플로우 설정
        function setupVerificationFlow() {
            const spamContent = document.getElementById('spamContent');
            const notificationPhone = document.getElementById('notificationPhone');
            const verificationSection = document.getElementById('verificationSection');
            const verificationCode = document.getElementById('verificationCode');
            const verifyMsg = document.getElementById('verifyMsg');
            const spamForm = document.getElementById('spamForm');
            
            let verificationCodeSent = false;
            let countdownTimer = null;
            
            
            // 인증번호 발송
            function sendVerificationCode(phoneNumber = null) {
                if (verificationCodeSent) return;
                
                const phone = phoneNumber || notificationPhone.value.trim();
                if (!phone) return;
                
                verifyMsg.className = 'verification-message verify-msg sending';
                verifyMsg.textContent = '인증번호를 발송하고 있습니다...';
                
                fetch('/api/send_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        verificationCodeSent = true;
                        verifyMsg.className = 'verification-message verify-msg success';
                        verifyMsg.textContent = '인증번호가 발송되었습니다. (유효시간: 10분)';
                        startCountdown(600); // 10분
                        verificationCode.focus();
                    } else {
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = data.message || '인증번호 발송에 실패했습니다.';
                    }
                })
                .catch(error => {
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '오류가 발생했습니다: ' + error.message;
                });
            }
            
            // 카운트다운 타이머
            function startCountdown(seconds) {
                const countdownElement = document.getElementById('verifyCountdown');
                let remaining = seconds;
                
                countdownTimer = setInterval(() => {
                    const minutes = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
                    
                    if (remaining <= 0) {
                        clearInterval(countdownTimer);
                        countdownElement.textContent = '시간 만료';
                        verificationCodeSent = false;
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = '인증번호가 만료되었습니다. 다시 시도해주세요.';
                    }
                    remaining--;
                }, 1000);
            }
            
            // 인증번호 확인
            function verifyCode() {
                const code = verificationCode.value.trim();
                const phone = notificationPhone.value.trim();
                
                if (!code || !phone) {
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '인증번호를 입력해주세요.';
                    return;
                }
                
                verifyMsg.className = 'verification-message verify-msg checking';
                verifyMsg.textContent = '인증번호를 확인하고 있습니다...';
                
                fetch('/api/verify_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone, code: code })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 인증 성공 - 로그인 상태로 변경
                        window.IS_LOGGED = true;
                        window.CUR_PHONE = phone;
                        
                        verifyMsg.className = 'verification-message verify-msg success';
                        verifyMsg.textContent = '인증이 완료되었습니다!';
                        
                        // 인증 섹션 숨기기
                        setTimeout(() => {
                            verificationSection.style.display = 'none';
                        }, 2000);
                        
                        // 녹음 목록 새로고침
                        getRecordings();
                        
                        // 카운트다운 타이머 정리
                        if (countdownTimer) {
                            clearInterval(countdownTimer);
                        }
                        
                        // 자동으로 메인 폼 제출 (인증 완료 후)
                        setTimeout(() => {
                            verifyMsg.textContent = '인증 완료! 수신거부 처리를 시작합니다...';
                            // 메인 폼 제출
                            const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                            spamForm.dispatchEvent(submitEvent);
                        }, 1000);
                    } else {
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = data.message || '인증번호가 올바르지 않습니다.';
                    }
                })
                .catch(error => {
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '오류가 발생했습니다: ' + error.message;
                });
            }
            
            // 이벤트 리스너 등록
            verificationCode.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    verifyCode();
                }
            });
            
            // 폼 제출 시 자동 인증 플로우
            spamForm.addEventListener('submit', function(e) {
                if (!window.IS_LOGGED) {
                    e.preventDefault();
                    
                    // 알림받을 연락처가 입력되어 있는지 확인
                    const notificationPhone = document.getElementById('notificationPhone').value.trim();
                    if (!notificationPhone) {
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = '알림받을 연락처를 먼저 입력해주세요.';
                        return false;
                    }
                    
                    // 이미 인증번호가 전송되었고 입력된 경우 바로 인증 시도
                    if (verificationCodeSent && verificationCode.value.trim()) {
                        verifyCode();
                        return false;
                    }
                    
                    // 인증번호가 아직 전송되지 않았으면 자동으로 전송
                    if (!verificationCodeSent) {
                        verifyMsg.className = 'verification-message verify-msg info';
                        verifyMsg.textContent = '인증번호를 전송하고 있습니다...';
                        
                        // 자동으로 인증번호 전송
                        sendVerificationCode(notificationPhone);
                        return false;
                    }
                    
                    // 인증번호가 전송되었지만 입력되지 않은 경우
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '전송된 인증번호를 입력해주세요.';
                    verificationSection.style.display = 'block';
                    verificationCode.focus();
                    return false;
                }
            });
        }

    // 페이지 언로드 시 진행 중인 분석 저장
    window.addEventListener('beforeunload', function() {
        if (typeof persistActiveAnalyses === 'function') {
            persistActiveAnalyses();
        }
    });

    // 디버그용 전역 함수 (개발 환경에서만 사용)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.debugRecordings = function() {
            fetch('get_recordings.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Current recordings:', data);
                    if (data.recordings) {
                        console.log('Ready for analysis:', data.recordings.filter(r => r.ready_for_analysis));
                        console.log('In progress:', data.recordings.filter(r => r.analysis_result === '미분석'));
                    }
                });
        };
        
        window.debugActiveAnalyses = function() {
            console.log('Active analyses:', [...activeAnalysisMap]);
            console.log('Auto analysis set:', [...autoAnalysisSet]);
        };
    }
    </script>
</body>
</html>
