<?php
require_once __DIR__.'/auth.php';

// 로그인 체크
if (!is_logged_in()) {
    header('Location: login.php?redirect=notification_settings.php');
    exit;
}

$userPhone = current_user_phone();

// 데이터베이스 연결
try {
    $db = new SQLite3(__DIR__ . '/spam.db');
    // 스키마 적용
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $db->exec(file_get_contents($schemaFile));
    }
} catch (Exception $e) {
    die('데이터베이스 연결 실패: ' . $e->getMessage());
}

// 사용자 정보 조회
$userStmt = $db->prepare('SELECT id FROM users WHERE phone = :phone');
$userStmt->bindValue(':phone', $userPhone, SQLITE3_TEXT);
$userResult = $userStmt->execute();
$user = $userResult->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    die('사용자 정보를 찾을 수 없습니다.');
}

$userId = $user['id'];

// 설정 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifyOnStart = isset($_POST['notify_on_start']) ? 1 : 0;
    $notifyOnSuccess = isset($_POST['notify_on_success']) ? 1 : 0;
    $notifyOnFailure = isset($_POST['notify_on_failure']) ? 1 : 0;
    $notifyOnRetry = isset($_POST['notify_on_retry']) ? 1 : 0;
    $notificationMode = $_POST['notification_mode'] ?? 'short';
    
    try {
        // 기존 설정이 있는지 확인
        $checkStmt = $db->prepare('SELECT id FROM user_notification_settings WHERE user_id = :user_id');
        $checkStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $existing = $checkResult->fetchArray(SQLITE3_ASSOC);
        
        if ($existing) {
            // 업데이트
            $updateStmt = $db->prepare('
                UPDATE user_notification_settings 
                SET notify_on_start = :start,
                    notify_on_success = :success,
                    notify_on_failure = :failure,
                    notify_on_retry = :retry,
                    notification_mode = :mode,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ');
            $updateStmt->bindValue(':start', $notifyOnStart, SQLITE3_INTEGER);
            $updateStmt->bindValue(':success', $notifyOnSuccess, SQLITE3_INTEGER);
            $updateStmt->bindValue(':failure', $notifyOnFailure, SQLITE3_INTEGER);
            $updateStmt->bindValue(':retry', $notifyOnRetry, SQLITE3_INTEGER);
            $updateStmt->bindValue(':mode', $notificationMode, SQLITE3_TEXT);
            $updateStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $updateStmt->execute();
        } else {
            // 새로 생성
            $insertStmt = $db->prepare('
                INSERT INTO user_notification_settings 
                (user_id, notify_on_start, notify_on_success, notify_on_failure, notify_on_retry, notification_mode)
                VALUES (:user_id, :start, :success, :failure, :retry, :mode)
            ');
            $insertStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $insertStmt->bindValue(':start', $notifyOnStart, SQLITE3_INTEGER);
            $insertStmt->bindValue(':success', $notifyOnSuccess, SQLITE3_INTEGER);
            $insertStmt->bindValue(':failure', $notifyOnFailure, SQLITE3_INTEGER);
            $insertStmt->bindValue(':retry', $notifyOnRetry, SQLITE3_INTEGER);
            $insertStmt->bindValue(':mode', $notificationMode, SQLITE3_TEXT);
            $insertStmt->execute();
        }
        
        $successMessage = '알림 설정이 저장되었습니다.';
    } catch (Exception $e) {
        $errorMessage = '설정 저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

// 현재 설정 조회
$settingsStmt = $db->prepare('SELECT * FROM user_notification_settings WHERE user_id = :user_id');
$settingsStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$settingsResult = $settingsStmt->execute();
$settings = $settingsResult->fetchArray(SQLITE3_ASSOC);

// 기본값 설정
if (!$settings) {
    $settings = [
        'notify_on_start' => 1,
        'notify_on_success' => 1,
        'notify_on_failure' => 1,
        'notify_on_retry' => 1,
        'notification_mode' => 'short'
    ];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>알림 설정 - 080 수신거부 자동화</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-label {
            flex: 1;
        }
        
        .setting-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .setting-description {
            font-size: 14px;
            color: #6b7280;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input[type="checkbox"] {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .radio-option input[type="radio"] {
            margin: 0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: #5a67d8;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #a7f3d0;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fca5a5;
        }
        
        @media (max-width: 768px) {
            .setting-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .toggle-switch {
                align-self: flex-end;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 알림 설정</h1>
            <p>080 수신거부 처리 알림을 원하는 대로 설정하세요</p>
        </div>

        <div class="card">
            <div class="card-header">
                <a href="index.php" class="back-link">
                    ← 메인으로 돌아가기
                </a>
                
                <div style="text-align: center;">
                    <strong>📱 <?php echo htmlspecialchars($userPhone); ?></strong>님의 알림 설정
                </div>
            </div>
            
            <div class="card-body">
                <?php if (isset($successMessage)): ?>
                <div class="success-message">
                    ✅ <?php echo $successMessage; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                <div class="error-message">
                    ❌ <?php echo $errorMessage; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">🔄 처리 시작 알림</div>
                            <div class="setting-description">수신거부 처리가 시작될 때 알림을 받습니다</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notify_on_start" <?php echo $settings['notify_on_start'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">✅ 성공 알림</div>
                            <div class="setting-description">수신거부가 성공적으로 처리된 경우 알림을 받습니다</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notify_on_success" <?php echo $settings['notify_on_success'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">❌ 실패 알림</div>
                            <div class="setting-description">수신거부 처리가 실패한 경우 알림을 받습니다</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notify_on_failure" <?php echo $settings['notify_on_failure'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">🔄 재시도 알림</div>
                            <div class="setting-description">실패 후 재시도할 때 알림을 받습니다</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notify_on_retry" <?php echo $settings['notify_on_retry'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">📱 알림 상세도</div>
                            <div class="setting-description">알림 메시지의 상세 정도를 선택하세요</div>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="notification_mode" value="short" <?php echo $settings['notification_mode'] === 'short' ? 'checked' : ''; ?>>
                                    <span>간결한 알림 (권장)</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="notification_mode" value="detailed" <?php echo $settings['notification_mode'] === 'detailed' ? 'checked' : ''; ?>>
                                    <span>상세한 알림</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" class="btn">
                            💾 설정 저장하기
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin-bottom: 10px; color: #374151;">💡 알림 설정 안내</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #6b7280; font-size: 14px; line-height: 1.6;">
                        <li><strong>간결한 알림:</strong> 핵심 정보만 포함된 짧은 메시지 (1개 SMS)</li>
                        <li><strong>상세한 알림:</strong> 모든 정보가 포함된 긴 메시지 (여러 개 SMS로 분할 전송 가능)</li>
                        <li>모든 알림을 끄더라도 중요한 오류 알림은 계속 전송됩니다</li>
                        <li>설정은 즉시 적용되며 다음 처리부터 반영됩니다</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>