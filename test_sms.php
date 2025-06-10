<?php
require_once __DIR__ . '/sms_sender.php';

$result = null;
$error = null;
$showLogs = isset($_GET['logs']) && $_GET['logs'] === '1';
$sentNotification = isset($_GET['sent']) && $_GET['sent'] === '1';

// SMS 전송 기록 조회
$smsSender = new SMSSender();
$recentLogs = $smsSender->getRecentSMSLogs(20); // 최근 20개 기록

if ($_POST) {
    try {
        $phoneNumber = $_POST['phone_number'] ?? '';
        $message = $_POST['message'] ?? '';
        $selectedMode = $_POST['selected_mode'] ?? 'single';
        
        if (empty($phoneNumber) || empty($message)) {
            $error = "전화번호와 메시지를 모두 입력해주세요.";
        } else {
            
            // 모드별 메시지 길이 검증
            $messageLength = $smsSender->calculateByteLength($message);
            $maxLength = ($selectedMode === 'single') ? 140 : 300;
            
            if ($messageLength > $maxLength) {
                $error = "메시지가 {$maxLength}바이트를 초과합니다. (현재: {$messageLength}바이트)";
            } else {
                $result = $smsSender->sendSMS($phoneNumber, $message);
                $result['mode'] = $selectedMode;
                $result['max_length'] = $maxLength;
                $smsSender->logSMS($result, 'test_message_' . $selectedMode, $message);
                
                // SMS 전송 후 로그 페이지로 리다이렉트 (성공/실패 무관)
                if ($result) {
                    $redirectUrl = '?logs=1&sent=1&status=' . ($result['success'] ? 'success' : 'failed');
                    header("Location: " . $redirectUrl);
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        $error = "오류 발생: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS 전송하기</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Apple Color Emoji', 'Segoe UI Emoji', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            max-width: 580px;
            width: 100%;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .header p {
            font-size: 15px;
            opacity: 0.9;
        }

        .content {
            padding: 35px;
        }

        .info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 14px;
            line-height: 1.7;
        }

        .info strong {
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #495057;
            font-size: 15px;
        }

        /* 라디오 버튼 그룹 개선 */
        .radio-group {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .radio-option:last-child {
            margin-bottom: 0;
        }

        .radio-option:hover {
            background: #f8f9fa;
            border-color: #667eea;
            transform: translateX(4px);
        }

        .radio-option input[type="radio"] {
            margin: 0 12px 0 0;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .radio-option.selected {
            background: #e3f2fd;
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }

        .radio-text {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            white-space: nowrap;
        }

        input[type="tel"], input[type="text"], textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        input[type="tel"]:focus, input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            max-height: 300px;
            font-family: inherit;
            line-height: 1.5;
        }

        .byte-counter {
            font-size: 12px;
            color: #6c757d;
            text-align: right;
            margin-top: 8px;
            font-weight: 500;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            display: inline-block;
            float: right;
        }

        .byte-counter.warning {
            color: #fd7e14;
            background: #fff3cd;
        }

        .byte-counter.error {
            color: #dc3545;
            background: #f8d7da;
            font-weight: 600;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            background: #adb5bd;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-sample {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-sample:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .btn-reset {
            background: #6c757d !important;
        }

        .btn-reset:hover {
            background: #5a6268 !important;
        }

        .result {
            margin-top: 25px;
            padding: 20px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.6;
            position: relative;
            transition: all 0.3s ease;
        }

        #sentNotification {
            animation: slideInDown 0.3s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .back-link a:hover {
            color: #764ba2;
            transform: translateX(-3px);
        }

        /* 디버깅 정보 스타일 */
        details {
            margin-top: 15px;
        }

        details summary {
            cursor: pointer;
            font-weight: 600;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            transition: background 0.2s ease;
        }

        details summary:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        details div {
            margin-top: 12px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.8) !important;
            padding: 12px !important;
            border-radius: 8px !important;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
        }

        /* 전송 기록 스타일 */
        .logs-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }

        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .view-mode-toggle {
            display: flex;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }

        .view-mode-btn {
            background: transparent;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            color: #6c757d;
        }

        .view-mode-btn.active {
            background: #667eea;
            color: white;
        }

        .view-mode-btn:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .view-mode-btn.active:hover {
            background: #5a6fd8;
        }

        .logs-container {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .log-item {
            background: white;
            margin: 8px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            transition: all 0.2s ease;
        }

        .log-item:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .log-item.log-failed {
            border-left-color: #dc3545;
        }

        /* 간단 보기 모드 */
        .simple-view .log-item {
            padding: 10px 15px;
            margin: 4px 8px;
        }

        .simple-view .log-original-message,
        .simple-view .log-debug {
            display: none;
        }

        .simple-view .log-details {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 5px;
        }

        .simple-view .log-result-message {
            font-size: 12px;
            padding: 6px 8px;
            margin-bottom: 0;
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .log-status {
            font-size: 14px;
        }

        .log-time {
            font-size: 12px;
            color: #6c757d;
            font-weight: normal;
        }

        .log-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #495057;
        }

        .log-original-message, .log-result-message {
            font-size: 13px;
            color: #495057;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .log-original-message {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 3px solid #2196f3;
        }

        .message-content {
            margin-top: 5px;
        }

        .message-text {
            white-space: pre-wrap;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .message-text:empty::before {
            content: "(빈 메시지)";
            color: #6c757d;
            font-style: italic;
        }

        .btn-expand {
            background: #007bff;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 8px;
            transition: all 0.2s ease;
        }

        .btn-expand:hover {
            background: #0056b3;
        }

        .log-debug {
            margin-top: 8px;
            font-size: 12px;
        }

        .log-debug summary {
            cursor: pointer;
            color: #6c757d;
            font-weight: 500;
            padding: 5px 8px;
            background: #e9ecef;
            border-radius: 4px;
            transition: background 0.2s ease;
        }

        .log-debug summary:hover {
            background: #dee2e6;
        }

        .debug-content {
            margin-top: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #6c757d;
        }

        .debug-content div {
            margin-bottom: 5px;
        }

        .debug-content code {
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 11px;
            word-break: break-all;
        }

        .no-logs {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .btn-secondary {
            display: inline-block;
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        /* 반응형 디자인 */
        @media (max-width: 640px) {
            .container {
                margin: 10px;
                border-radius: 12px;
            }
            
            .header {
                padding: 25px 20px;
            }
            
            .content {
                padding: 25px 20px;
            }
            
            .radio-text {
                font-size: 13px;
            }

            .log-details {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 SMS 전송하기</h1>
            <p>SMS 전송 및 테스트 기능</p>
        </div>

        <div class="content">
            <div class="info">
                <strong>📋 테스트 안내</strong>
                • 실제 SMS가 전송되므로 신중하게 테스트하세요<br>
                • <span id="maxLengthInfo">메시지는 최대 140바이트까지 가능합니다 (단일 SMS)</span><br>
                • 한글은 2바이트로 계산됩니다<br>
                • 줄바꿈(엔터키)이 지원됩니다
            </div>

            <form method="post">
                <div class="form-group">
                    <label>SMS 전송 모드 선택</label>
                    <div class="radio-group">
                        <div class="radio-option <?= ($_POST['selected_mode'] ?? 'single') === 'single' ? 'selected' : '' ?>">
                            <input type="radio" name="selected_mode" value="single" id="singleMode" 
                                   <?= ($_POST['selected_mode'] ?? 'single') === 'single' ? 'checked' : '' ?>>
                            <span class="radio-text">단일 SMS (140 바이트, 분할 없음)</span>
                        </div>
                        <div class="radio-option <?= ($_POST['selected_mode'] ?? '') === 'long' ? 'selected' : '' ?>">
                            <input type="radio" name="selected_mode" value="long" id="longMode" 
                                   <?= ($_POST['selected_mode'] ?? '') === 'long' ? 'checked' : '' ?>>
                            <span class="radio-text">긴 메시지 (300 바이트, 분할 가능)</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone_number">수신자 전화번호 *</label>
                    <input type="tel" id="phone_number" name="phone_number" 
                           value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>"
                           placeholder="01012345678" required>
                </div>

                <div class="form-group">
                    <label for="message">메시지 내용 *</label>
                    <textarea id="message" name="message" 
                              placeholder="테스트 메시지를 입력하세요...&#10;줄바꿈도 지원됩니다!" 
                              required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="sampleBtn" class="btn-sample">📝 샘플 메시지</button>
                            <button type="button" id="resetBtn" class="btn-sample btn-reset">🔄 초기화</button>
                        </div>
                        <div class="byte-counter" id="byteCounter">0 / 140 bytes</div>
                    </div>
                </div>

                <button type="submit" class="btn">📤 SMS 전송</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <?php if (!$showLogs): ?>
                    <a href="?logs=1" class="btn-secondary">📋 전송 기록 보기 (<?= count($recentLogs) ?>)</a>
                <?php else: ?>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <a href="?" class="btn-secondary">📤 새 SMS 전송</a>
                        <a href="?logs=1" class="btn-secondary">🔄 로그 새로고침</a>
                        <a href="index.php" class="btn-secondary">🏠 메인으로</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php if ($result): ?>
            <div class="result <?= $result['success'] ? 'success' : 'error' ?>">
                <?php if ($result['success']): ?>
                    <strong>✅ 전송 성공!</strong><br>
                    수신자: <?= htmlspecialchars($result['phone']) ?><br>
                    메시지 길이: <?= $result['bytes'] ?> bytes<br>
                    전송 모드: <?= ($result['mode'] ?? 'unknown') === 'single' ? '단일 SMS' : '긴 메시지 (분할 가능)' ?><br>
                    상태: <?= htmlspecialchars($result['message']) ?><br>
                    
                    <?php if (isset($result['debug'])): ?>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; font-weight: bold;">🔍 디버깅 정보</summary>
                            <div style="margin-top: 8px; font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px;">
                                <strong>처리된 메시지:</strong><br>
                                <?= htmlspecialchars($result['debug']['processed_message']) ?><br><br>
                                <strong>명령어:</strong><br>
                                <code><?= htmlspecialchars($result['debug']['command']) ?></code><br><br>
                                <strong>원본 길이:</strong> <?= $result['debug']['original_message_length'] ?> bytes<br>
                                <strong>처리된 길이:</strong> <?= $result['debug']['processed_message_length'] ?> bytes<br>
                                <strong>반환 코드:</strong> <?= $result['debug']['return_code'] ?><br>
                                <strong>출력:</strong> <?= htmlspecialchars(implode(' ', $result['debug']['output'])) ?>
                            </div>
                        </details>
                    <?php endif; ?>
                <?php else: ?>
                    <strong>❌ 전송 실패</strong><br>
                    오류: <?= htmlspecialchars($result['message']) ?><br>
                    <?php if ($result['phone']): ?>
                        정규화된 번호: <?= htmlspecialchars($result['phone']) ?><br>
                    <?php endif; ?>
                    <?php if ($result['bytes']): ?>
                        메시지 길이: <?= $result['bytes'] ?> bytes<br>
                    <?php endif; ?>
                    
                    <?php if (isset($result['debug'])): ?>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; font-weight: bold;">🔍 디버깅 정보</summary>
                            <div style="margin-top: 8px; font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px;">
                                <strong>처리된 메시지:</strong><br>
                                <?= htmlspecialchars($result['debug']['processed_message'] ?? '') ?><br><br>
                                <strong>명령어:</strong><br>
                                <code><?= htmlspecialchars($result['debug']['command'] ?? '') ?></code><br><br>
                                <strong>원본 길이:</strong> <?= $result['debug']['original_message_length'] ?? 0 ?> bytes<br>
                                <strong>처리된 길이:</strong> <?= $result['debug']['processed_message_length'] ?? 0 ?> bytes<br>
                                <strong>반환 코드:</strong> <?= $result['debug']['return_code'] ?? 'N/A' ?><br>
                                <strong>출력:</strong> <?= htmlspecialchars(implode(' ', $result['debug']['output'] ?? [])) ?>
                            </div>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($error): ?>
            <div class="result error">
                <strong>❌ 오류</strong><br>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($sentNotification): ?>
            <div class="result <?= $_GET['status'] === 'success' ? 'success' : 'error' ?>" id="sentNotification">
                <?php if ($_GET['status'] === 'success'): ?>
                    <strong>✅ SMS 전송 완료!</strong><br>
                    메시지가 성공적으로 전송되었습니다. 아래에서 전송 기록을 확인하세요.
                <?php else: ?>
                    <strong>❌ SMS 전송 실패</strong><br>
                    전송에 실패했습니다. 아래 로그에서 자세한 내용을 확인하세요.
                <?php endif; ?>
                <button type="button" onclick="hideSentNotification()" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer; color: inherit;">×</button>
            </div>
        <?php endif; ?>

        <?php if ($showLogs): ?>
            <div class="logs-section">
                <div class="logs-header">
                    <h3 style="margin: 0; color: #495057;">📋 최근 전송 기록</h3>
                    <div class="view-mode-toggle">
                        <button type="button" class="view-mode-btn active" id="detailedViewBtn" onclick="switchViewMode('detailed')">
                            📄 상세 보기
                        </button>
                        <button type="button" class="view-mode-btn" id="simpleViewBtn" onclick="switchViewMode('simple')">
                            📝 간단 보기
                        </button>
                    </div>
                </div>
                
                <?php if (empty($recentLogs)): ?>
                    <div class="no-logs">
                        <p>아직 전송 기록이 없습니다.</p>
                    </div>
                <?php else: ?>
                    <div class="logs-container" id="logsContainer">
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-item <?= $log['success'] ? 'log-success' : 'log-failed' ?>">
                                <div class="log-header">
                                    <span class="log-status"><?= $log['success'] ? '✅ 성공' : '❌ 실패' ?></span>
                                    <span class="log-time"><?= htmlspecialchars($log['timestamp']) ?></span>
                                </div>
                                <div class="log-details">
                                    <div><strong>수신자:</strong> <?= htmlspecialchars($log['phone']) ?></div>
                                    <div><strong>타입:</strong> <?= htmlspecialchars($log['type']) ?></div>
                                    <div><strong>크기:</strong> <?= $log['bytes'] ?> bytes</div>
                                </div>
                                
                                <?php if (!empty($log['original_message'])): ?>
                                    <div class="log-original-message">
                                        <strong>📝 전송 메시지:</strong>
                                        <div class="message-content">
                                            <?php 
                                            $msg = htmlspecialchars(trim($log['original_message']));
                                            $isLong = strlen($msg) > 100;
                                            $displayMsg = $isLong ? substr($msg, 0, 100) . '...' : $msg;
                                            $displayMsg = nl2br($displayMsg);
                                            ?>
                                            <span class="message-text <?= $isLong ? 'message-truncated' : '' ?>" 
                                                  data-full-message="<?= $msg ?>">
                                                <?= $displayMsg ?>
                                            </span>
                                            <?php if ($isLong): ?>
                                                <button type="button" class="btn-expand" onclick="toggleMessage(this)">
                                                    <span class="expand-text">전체 보기</span>
                                                    <span class="collapse-text" style="display: none;">접기</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="log-result-message">
                                    <strong>📤 전송 결과:</strong> <?= htmlspecialchars($log['result_message'] ?? $log['message'] ?? '') ?>
                                </div>
                                
                                <?php if (!empty($log['debug'])): ?>
                                    <details class="log-debug">
                                        <summary>🔍 디버그 정보</summary>
                                        <div class="debug-content">
                                            <?php if (isset($log['debug']['command'])): ?>
                                                <div><strong>명령어:</strong> <code><?= htmlspecialchars($log['debug']['command']) ?></code></div>
                                            <?php endif; ?>
                                            <?php if (isset($log['debug']['return_code'])): ?>
                                                <div><strong>반환 코드:</strong> <?= $log['debug']['return_code'] ?></div>
                                            <?php endif; ?>
                                            <?php if (isset($log['debug']['output']) && is_array($log['debug']['output'])): ?>
                                                <div><strong>출력:</strong> <?= htmlspecialchars(implode(' ', $log['debug']['output'])) ?></div>
                                            <?php endif; ?>
                                            <?php if (isset($log['debug']['processed_message'])): ?>
                                                <div><strong>처리된 메시지:</strong> <?= htmlspecialchars($log['debug']['processed_message']) ?></div>
                                            <?php endif; ?>
                                            <?php if (isset($log['debug']['processed_message_length'])): ?>
                                                <div><strong>처리된 길이:</strong> <?= $log['debug']['processed_message_length'] ?> bytes</div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

            <div class="back-link">
                <a href="index.php">← 메인 시스템으로 돌아가기</a>
            </div>
        </div>
    </div>

    <script>
        function calculateByteLength(text) {
            let byteLength = 0;
            for (let i = 0; i < text.length; i++) {
                const charCode = text.charCodeAt(i);
                if (charCode <= 0x7F) {
                    byteLength += 1;
                } else {
                    byteLength += 2;
                }
            }
            return byteLength;
        }

        function getCurrentMaxLength() {
            const singleMode = document.getElementById('singleMode');
            return singleMode && singleMode.checked ? 140 : 300;
        }

        function updateByteCount() {
            const messageElement = document.getElementById('message');
            const message = messageElement ? messageElement.value : '';
            const byteLength = calculateByteLength(message);
            const counter = document.getElementById('byteCounter');
            const maxLength = getCurrentMaxLength();
            
            if (!counter) return;
            
            counter.textContent = `${byteLength} / ${maxLength} bytes`;
            
            // 기존 클래스 제거
            counter.classList.remove('warning', 'error');
            
            if (byteLength > maxLength) {
                counter.classList.add('error');
                messageElement.style.borderColor = '#dc3545';
            } else if (byteLength > maxLength * 0.8) {
                counter.classList.add('warning');
                messageElement.style.borderColor = '#fd7e14';
            } else {
                messageElement.style.borderColor = '#e1e5e9';
            }
            
            // 전송 버튼 활성화/비활성화
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                if (byteLength > maxLength) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                } else {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        }

        function updateModeInfo() {
            const singleMode = document.getElementById('singleMode');
            const longMode = document.getElementById('longMode');
            const maxLengthInfo = document.getElementById('maxLengthInfo');
            
            // 라디오 옵션 시각적 상태 업데이트
            const radioOptions = document.querySelectorAll('.radio-option');
            radioOptions.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                if (radio && radio.checked) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });
            
            // 최대 길이 정보 업데이트
            if (singleMode && singleMode.checked) {
                maxLengthInfo.textContent = '메시지는 최대 140바이트까지 가능합니다 (단일 SMS)';
            } else {
                maxLengthInfo.textContent = '메시지는 최대 300바이트까지 가능합니다 (분할 가능)';
            }
            
            updateByteCount();
        }

        // DOM이 로드된 후 이벤트 리스너 설정
        document.addEventListener('DOMContentLoaded', function() {
            const messageElement = document.getElementById('message');
            const singleMode = document.getElementById('singleMode');
            const longMode = document.getElementById('longMode');
            
            // 모드 변경 이벤트 리스너
            if (singleMode && longMode) {
                singleMode.addEventListener('change', updateModeInfo);
                longMode.addEventListener('change', updateModeInfo);
                updateModeInfo(); // 초기 설정
            }
            
            // 라디오 옵션 클릭 이벤트 (전체 영역 클릭)
            const radioOptions = document.querySelectorAll('.radio-option');
            radioOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio && !radio.checked) {
                        radio.checked = true;
                        updateModeInfo();
                    }
                });
            });
            
            // 샘플 메시지 버튼 이벤트
            const sampleBtn = document.getElementById('sampleBtn');
            if (sampleBtn) {
                sampleBtn.addEventListener('click', function() {
                    const messageElement = document.getElementById('message');
                    if (messageElement) {
                        const sampleMessage = "[080스팸차단] 분석완료\n발신: 0215551234\n결과: 스팸(95%)\n녹음: http://192.168.1.254/audio/20241201-140532.wav\n의심되는 내용 발견시 신고하세요.";
                        messageElement.value = sampleMessage;
                        updateByteCount();
                        
                        // 긴 메시지 모드로 자동 전환
                        const longMode = document.getElementById('longMode');
                        if (longMode) {
                            longMode.checked = true;
                            updateModeInfo();
                        }
                    }
                });
            }
            
            // 초기화 버튼 이벤트
            const resetBtn = document.getElementById('resetBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (confirm('모든 입력 내용을 초기화하시겠습니까?')) {
                        // 폼 필드 초기화
                        const phoneElement = document.getElementById('phone_number');
                        const messageElement = document.getElementById('message');
                        const singleMode = document.getElementById('singleMode');
                        
                        if (phoneElement) phoneElement.value = '';
                        if (messageElement) messageElement.value = '';
                        if (singleMode) {
                            singleMode.checked = true;
                            updateModeInfo();
                        }
                        
                        updateByteCount();
                        
                        // 포커스를 전화번호 필드로
                        if (phoneElement) phoneElement.focus();
                    }
                });
            }
            
            if (messageElement) {
                // 초기 바이트 카운트 업데이트
                updateByteCount();
                
                // 모든 입력 이벤트에 리스너 추가
                messageElement.addEventListener('input', updateByteCount);
                messageElement.addEventListener('keyup', updateByteCount);
                messageElement.addEventListener('paste', function() {
                    // 붙여넣기 후 약간의 지연을 두고 업데이트
                    setTimeout(updateByteCount, 10);
                });
                messageElement.addEventListener('cut', function() {
                    // 잘라내기 후 약간의 지연을 두고 업데이트
                    setTimeout(updateByteCount, 10);
                });
                
                // 포커스 이벤트에도 업데이트 (다른 곳에서 값이 변경될 수 있으므로)
                messageElement.addEventListener('focus', updateByteCount);
                messageElement.addEventListener('blur', updateByteCount);
            }
            
            // 폼 제출 시 최종 검증
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const message = document.getElementById('message').value;
                    const byteLength = calculateByteLength(message);
                    const maxLength = getCurrentMaxLength();
                    
                    if (byteLength > maxLength) {
                        e.preventDefault();
                        alert(`메시지가 ${maxLength}바이트를 초과합니다. 내용을 줄여주세요.`);
                        return false;
                    }
                    
                    if (message.trim() === '') {
                        e.preventDefault();
                        alert('메시지 내용을 입력해주세요.');
                        return false;
                    }
                });
            }
        });
        
        // 페이지가 완전히 로드된 후에도 한 번 더 업데이트
        window.addEventListener('load', updateByteCount);
        
        // 메시지 확장/축소 기능
        function toggleMessage(button) {
            const messageText = button.parentElement.querySelector('.message-text');
            const expandText = button.querySelector('.expand-text');
            const collapseText = button.querySelector('.collapse-text');
            const fullMessage = messageText.getAttribute('data-full-message');
            
            if (messageText.classList.contains('message-truncated')) {
                // 확장
                messageText.innerHTML = fullMessage.replace(/\n/g, '<br>');
                messageText.classList.remove('message-truncated');
                expandText.style.display = 'none';
                collapseText.style.display = 'inline';
            } else {
                // 축소
                const truncated = fullMessage.length > 100 ? fullMessage.substring(0, 100) + '...' : fullMessage;
                messageText.innerHTML = truncated.replace(/\n/g, '<br>');
                messageText.classList.add('message-truncated');
                expandText.style.display = 'inline';
                collapseText.style.display = 'none';
            }
        }
        
        // 보기 모드 전환 기능
        function switchViewMode(mode) {
            const logsContainer = document.getElementById('logsContainer');
            const detailedBtn = document.getElementById('detailedViewBtn');
            const simpleBtn = document.getElementById('simpleViewBtn');
            
            if (mode === 'simple') {
                logsContainer.classList.add('simple-view');
                detailedBtn.classList.remove('active');
                simpleBtn.classList.add('active');
                
                // 로컬 스토리지에 설정 저장
                localStorage.setItem('sms_view_mode', 'simple');
            } else {
                logsContainer.classList.remove('simple-view');
                detailedBtn.classList.add('active');
                simpleBtn.classList.remove('active');
                
                // 로컬 스토리지에 설정 저장
                localStorage.setItem('sms_view_mode', 'detailed');
            }
        }
        
        // 페이지 로드 시 저장된 보기 모드 복원
        document.addEventListener('DOMContentLoaded', function() {
            const savedMode = localStorage.getItem('sms_view_mode') || 'detailed';
            if (document.getElementById('logsContainer')) {
                switchViewMode(savedMode);
            }
            
            // 전송 완료 알림 자동 사라짐 (5초 후)
            const sentNotification = document.getElementById('sentNotification');
            if (sentNotification) {
                setTimeout(function() {
                    hideSentNotification();
                }, 5000);
            }
        });
        
        // 전송 완료 알림 숨기기
        function hideSentNotification() {
            const notification = document.getElementById('sentNotification');
            if (notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 300);
            }
        }
    </script>
</body>
</html> 