<?php
/**
 * SMS 전송 유틸리티
 * Asterisk Quectel 모듈을 통한 SMS 전송 기능
 */

class SMSSender {
    private $quectelCommand = "quectel sms quectel0";
    private $config;
    private $db;
    
    public function __construct() {
        $configFile = __DIR__ . '/sms_config.php';
        if (file_exists($configFile)) {
            $this->config = include $configFile;
        } else {
            $this->config = ['message_mode' => 'short', 'single_sms_max_length' => 300];
        }
        
        // 데이터베이스 연결 초기화
        try {
            $this->db = new SQLite3(__DIR__ . '/spam.db');
            // 스키마 적용
            $schemaFile = __DIR__ . '/schema.sql';
            if (file_exists($schemaFile)) {
                $this->db->exec(file_get_contents($schemaFile));
            }
        } catch (Exception $e) {
            error_log('SMSSender DB initialization failed: ' . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * 사용자 알림 설정 조회
     * @param string $phoneNumber 전화번호
     * @return array 알림 설정
     */
    private function getUserNotificationSettings($phoneNumber) {
        if (!$this->db) {
            return $this->getDefaultNotificationSettings();
        }
        
        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // 사용자 ID 조회
            $userStmt = $this->db->prepare('SELECT id FROM users WHERE phone = :phone');
            $userStmt->bindValue(':phone', $cleanPhone, SQLITE3_TEXT);
            $userResult = $userStmt->execute();
            $user = $userResult->fetchArray(SQLITE3_ASSOC);
            
            if (!$user) {
                return $this->getDefaultNotificationSettings();
            }
            
            // 알림 설정 조회
            $settingsStmt = $this->db->prepare('SELECT * FROM user_notification_settings WHERE user_id = :user_id');
            $settingsStmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
            $settingsResult = $settingsStmt->execute();
            $settings = $settingsResult->fetchArray(SQLITE3_ASSOC);
            
            if ($settings) {
                return [
                    'notify_on_start' => (bool)$settings['notify_on_start'],
                    'notify_on_success' => (bool)$settings['notify_on_success'],
                    'notify_on_failure' => (bool)$settings['notify_on_failure'],
                    'notify_on_retry' => (bool)$settings['notify_on_retry'],
                    'notification_mode' => $settings['notification_mode']
                ];
            } else {
                // 설정이 없으면 기본값으로 생성
                $this->createDefaultUserSettings($user['id']);
                return $this->getDefaultNotificationSettings();
            }
            
        } catch (Exception $e) {
            error_log('Failed to get user notification settings: ' . $e->getMessage());
            return $this->getDefaultNotificationSettings();
        }
    }
    
    /**
     * 기본 알림 설정 반환
     * @return array 기본 알림 설정
     */
    private function getDefaultNotificationSettings() {
        return [
            'notify_on_start' => true,
            'notify_on_success' => true,
            'notify_on_failure' => true,
            'notify_on_retry' => true,
            'notification_mode' => 'short'
        ];
    }
    
    /**
     * 사용자에게 기본 알림 설정 생성
     * @param int $userId 사용자 ID
     */
    private function createDefaultUserSettings($userId) {
        if (!$this->db) return;
        
        try {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO user_notification_settings (user_id) VALUES (:user_id)');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->execute();
        } catch (Exception $e) {
            error_log('Failed to create default user settings: ' . $e->getMessage());
        }
    }
    
    /**
     * 전화번호 정규화
     * @param string $phoneNumber 입력된 전화번호
     * @return string 정규화된 전화번호
     */
    public function normalizePhoneNumber($phoneNumber) {
        // 숫자만 추출
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // 한국 휴대폰 번호 형식 확인 및 정규화
        if (strlen($cleaned) == 11 && substr($cleaned, 0, 3) == '010') {
            return $cleaned; // 010xxxxxxxx 형태
        } elseif (strlen($cleaned) == 10 && substr($cleaned, 0, 2) == '01') {
            return '0' . $cleaned; // 01xxxxxxxx -> 010xxxxxxxx
        }
        
        return $cleaned;
    }
    
    /**
     * 메시지 길이 계산 (바이트 단위)
     * @param string $message 메시지 내용
     * @return int 바이트 길이
     */
    public function calculateByteLength($message) {
        return strlen(mb_convert_encoding($message, 'UTF-8', 'UTF-8'));
    }
    
    /**
     * SMS 전송을 위한 안전한 메시지 준비
     * @param string $message 원본 메시지
     * @return string 안전하게 처리된 메시지
     */
    private function prepareSafeMessage($message) {
        // 1. 다양한 줄바꿈 형태를 통일 (줄바꿈 보존)
        $safeMessage = str_replace(["\r\n", "\r"], "\n", $message);
        
        // 2. 연속된 공백을 하나로 줄임 (줄바꿈은 제외)
        $safeMessage = preg_replace('/[ \t]+/', ' ', $safeMessage);
        
        // 3. 연속된 줄바꿈을 최대 2개로 제한
        $safeMessage = preg_replace('/\n{3,}/', "\n\n", $safeMessage);
        
        // 4. 앞뒤 공백 제거
        $safeMessage = trim($safeMessage);
        
        // 5. 특수문자 처리 (SMS에 문제가 될 수 있는 문자들)
        $safeMessage = str_replace(['`', '"', "'", '\\'], '', $safeMessage);
        
        // 6. 길이 재확인 및 자르기 (300바이트 제한 준수)
        $maxAllowed = $this->config['single_sms_max_length'] ?? 300;
        $maxLength = $maxAllowed - 10; // 10바이트 여유 마진
        if ($this->calculateByteLength($safeMessage) > $maxLength) {
            $safeMessage = mb_substr($safeMessage, 0, 120) . '...';
        }
        
        return $safeMessage;
    }
    
    /**
     * 출력에서 오류 감지
     * @param string $output Asterisk 출력
     * @return bool 오류가 있으면 true
     */
    private function hasError($output) {
        $errorPatterns = [
            'error',
            'failed',
            'invalid',
            'not found',
            'timeout'
        ];
        
        $lowerOutput = strtolower($output);
        foreach ($errorPatterns as $pattern) {
            if (strpos($lowerOutput, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * SMS 전송
     * @param string $phoneNumber 수신자 전화번호
     * @param string $message 메시지 내용
     * @return array 전송 결과
     */
    public function sendSMS($phoneNumber, $message) {
        $result = [
            'success' => false,
            'message' => '',
            'phone' => '',
            'bytes' => 0
        ];
        
        try {
            // 전화번호 정규화
            $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
            $result['phone'] = $normalizedPhone;
            
            // 메시지 길이 확인
            $byteLength = $this->calculateByteLength($message);
            $result['bytes'] = $byteLength;
            
            $maxAllowed = $this->config['single_sms_max_length'] ?? 300;
            // auto multipart support
            if ($byteLength > $maxAllowed) {
                $parts = [];
                $chunkMax = $maxAllowed - 10; // allow prefix like (1/3)
                $offset = 0;
                $msgLen = mb_strlen($message, 'UTF-8');
                while ($offset < $msgLen) {
                    $chunk = mb_substr($message, $offset, $chunkMax, 'UTF-8');
                    // ensure byte length <= chunkMax
                    while ($this->calculateByteLength($chunk) > $chunkMax) {
                        $chunk = mb_substr($chunk, 0, -1, 'UTF-8');
                    }
                    $parts[] = $chunk;
                    $offset += mb_strlen($chunk, 'UTF-8');
                }
                $total = count($parts);
                $successAll = true;
                foreach ($parts as $idx=>$ptxt) {
                    $prefix = "(".($idx+1)."/{$total}) ";
                    $sendText = $prefix.$ptxt;
                    $subRes = $this->sendSMS($phoneNumber, $sendText); // recursion safe because shorter now
                    $successAll = $successAll && $subRes['success'];
                }
                $result['success'] = $successAll;
                $result['message'] = $successAll ? 'Multipart SMS sent' : 'One or more parts failed';
                return $result;
            }
            
            if (empty($normalizedPhone)) {
                $result['message'] = 'Invalid phone number';
                return $result;
            }
            
            // 모뎀 사용 상태 체크 (통화 중인지 확인)
            $statusCommand = "/usr/sbin/asterisk -rx " . escapeshellarg("quectel show device state quectel0");
            $statusOutput = [];
            exec($statusCommand, $statusOutput, $statusCode);
            $statusText = implode(' ', $statusOutput);
            
            // 모뎀이 통화 중이면 대기
            if (strpos($statusText, 'call') !== false || strpos($statusText, 'busy') !== false) {
                $result['message'] = 'Modem is busy - SMS queued for later';
                $result['debug'] = 'Modem status: ' . $statusText;
                // 짧은 대기 후 재시도
                sleep(2);
            }
            
            // 메시지를 안전하게 처리 (특수문자 및 한글 대응)
            $safeMessage = $this->prepareSafeMessage($message);
            
            // SMS 전송 명령어 구성 (더 안전한 방식)
            $command = "/usr/sbin/asterisk -rx " . escapeshellarg("{$this->quectelCommand} {$normalizedPhone} {$safeMessage}");
            
            // 명령어 실행
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            // 출력 메시지 분석
            $outputText = implode(' ', $output);
            
            // 성공 조건 개선 (queued도 성공으로 간주)
            if ($returnCode === 0 && !$this->hasError($outputText)) {
                $result['success'] = true;
                $result['message'] = 'SMS sent successfully';
                if (strpos($outputText, 'queued') !== false) {
                    $result['message'] = 'SMS queued for sending';
                }
            } else {
                $result['message'] = 'Failed to send SMS: ' . $outputText;
            }
            
            // 디버깅 정보 추가
            $result['debug'] = [
                'command' => $command,
                'return_code' => $returnCode,
                'output' => $output,
                'original_message_length' => $this->calculateByteLength($message),
                'processed_message_length' => $this->calculateByteLength($safeMessage),
                'processed_message' => $safeMessage
            ];
            
        } catch (Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 080 수신거부 처리 시작 알림 SMS 전송
     * @param string $phoneNumber 알림 받을 전화번호
     * @param string $targetNumber 수신거부 신청할 080번호
     * @param string $identificationNumber 사용할 식별번호
     * @return array 전송 결과
     */
    public function sendProcessStartNotification($phoneNumber, $targetNumber, $identificationNumber) {
        $settings = $this->getUserNotificationSettings($phoneNumber);
        
        if (!$settings['notify_on_start']) {
            return ['success' => true, 'message' => 'Notification disabled by user', 'phone' => $phoneNumber, 'bytes' => 0];
        }
        
        $message = "[080 수신거부 자동화]\n";
        $message .= "🔄 처리 시작\n\n";
        $message .= "대상번호: {$targetNumber}\n";
        $message .= "식별번호: {$identificationNumber}\n";
        $message .= "시작시간: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "처리가 완료되면 결과를 알려드립니다.";
        
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * 080 수신거부 완료 알림 SMS 전송
     * @param string $phoneNumber 알림 받을 전화번호
     * @param string $targetNumber 수신거부 신청한 080번호
     * @param string $identificationNumber 사용된 식별번호
     * @param string $status 처리 결과 (success/failed)
     * @return array 전송 결과
     */
    public function sendUnsubscribeNotification($phoneNumber, $targetNumber, $identificationNumber, $status = 'completed') {
        $statusText = [
            'success' => '✅ 성공',
            'completed' => '✅ 완료', 
            'failed' => '❌ 실패',
            'error' => '⚠️ 오류'
        ];
        
        $statusEmoji = $statusText[$status] ?? '📋 처리됨';
        
        $message = "[080 수신거부 자동화]\n";
        $message .= "{$statusEmoji}\n\n";
        $message .= "대상번호: {$targetNumber}\n";
        $message .= "식별번호: {$identificationNumber}\n";
        $message .= "처리시간: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "녹음파일을 확인하여 수신거부가 정상 처리되었는지 확인해주세요.";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * 음성 분석 완료 알림 SMS 전송 (간결한 버전)
     * @param string $phoneNumber 알림 받을 전화번호
     * @param string $targetNumber 수신거부 신청한 080번호
     * @param string $identificationNumber 사용된 식별번호
     * @param string $analysisResult 분석 결과 (success/failed/uncertain)
     * @param int $confidence 신뢰도 (0-100)
     * @param string $recordingFile 녹음 파일명
     * @return array 전송 결과
     */
    public function sendAnalysisCompleteNotification($phoneNumber, $targetNumber, $identificationNumber, $analysisResult, $confidence, $recordingFile) {
        $settings = $this->getUserNotificationSettings($phoneNumber);
        
        // 알림 설정 확인
        if ($analysisResult === 'success' && !$settings['notify_on_success']) {
            return ['success' => true, 'message' => 'Success notification disabled by user', 'phone' => $phoneNumber, 'bytes' => 0];
        }
        if ($analysisResult === 'failed' && !$settings['notify_on_failure']) {
            return ['success' => true, 'message' => 'Failure notification disabled by user', 'phone' => $phoneNumber, 'bytes' => 0];
        }
        
        // 사용자 설정에 따라 메시지 모드 선택
        $messageMode = $settings['notification_mode'] ?? 'short';
        
        if ($messageMode === 'detailed') {
            return $this->sendAnalysisCompleteNotificationDetailed($phoneNumber, $targetNumber, $identificationNumber, $analysisResult, $confidence, $recordingFile);
        } else {
            return $this->sendAnalysisCompleteNotificationShort($phoneNumber, $targetNumber, $identificationNumber, $analysisResult, $confidence, $recordingFile);
        }
    }
    
    /**
     * 간결한 분석 완료 알림 (단일 SMS)
     */
    private function sendAnalysisCompleteNotificationShort($phoneNumber, $targetNumber, $identificationNumber, $analysisResult, $confidence, $recordingFile) {
        $statusText = [
            'success' => '✅ 수신거부 성공',
            'failed' => '❌ 수신거부 실패', 
            'uncertain' => '⚠️ 결과 불분명',
            'attempted' => '🔄 처리 완료'
        ];
        
        $statusEmoji = $statusText[$analysisResult] ?? '❓ 알 수 없음';
        $serverIP = '192.168.1.254';
        
        // 개선된 메시지 (더 명확한 정보 제공)
        $message = "[080 수신거부 완료]\n";
        $message .= "{$statusEmoji}\n\n";
        $message .= "📞 {$targetNumber}\n";
        $message .= "🔑 ID: {$identificationNumber}\n";
        $message .= "📊 신뢰도: {$confidence}%\n";
        $message .= "⏰ " . date('Y-m-d H:i:s') . "\n\n";
        
        // 결과별 추가 안내
        if ($analysisResult === 'success') {
            $message .= "🎉 수신거부가 성공적으로 처리되었습니다!\n";
        } elseif ($analysisResult === 'failed') {
            $message .= "⚠️ 수신거부 처리에 문제가 있을 수 있습니다.\n";
        } elseif ($analysisResult === 'uncertain') {
            $message .= "🤔 결과 확인이 필요합니다.\n";
        }
        
        $message .= "\n🎙️ 녹음 확인: http://{$serverIP}/spam/player.php?file=" . urlencode($recordingFile);
        
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * 실패 시 재시도 알림 SMS 전송
     * @param string $phoneNumber 알림 받을 전화번호
     * @param string $targetNumber 수신거부 신청할 080번호
     * @param string $identificationNumber 사용할 식별번호
     * @param int $retryCount 재시도 횟수
     * @param string $reason 실패 사유
     * @return array 전송 결과
     */
    public function sendRetryNotification($phoneNumber, $targetNumber, $identificationNumber, $retryCount, $reason = '') {
        $settings = $this->getUserNotificationSettings($phoneNumber);
        
        if (!$settings['notify_on_retry']) {
            return ['success' => true, 'message' => 'Retry notification disabled by user', 'phone' => $phoneNumber, 'bytes' => 0];
        }
        
        $message = "[080 수신거부 재시도]\n";
        $message .= "🔄 {$retryCount}차 재시도 중\n\n";
        $message .= "📞 {$targetNumber}\n";
        $message .= "🔑 ID: {$identificationNumber}\n";
        
        if (!empty($reason)) {
            $message .= "📝 사유: {$reason}\n";
        }
        
        $message .= "⏰ " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "⚠️ 이전 시도가 실패하여 다시 시도합니다.\n";
        $message .= "결과는 처리 완료 후 알려드립니다.";
        
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * 최종 실패 알림 SMS 전송 (모든 재시도 실패 시)
     * @param string $phoneNumber 알림 받을 전화번호
     * @param string $targetNumber 수신거부 신청한 080번호
     * @param string $identificationNumber 사용된 식별번호
     * @param int $totalAttempts 총 시도 횟수
     * @return array 전송 결과
     */
    public function sendFinalFailureNotification($phoneNumber, $targetNumber, $identificationNumber, $totalAttempts) {
        $settings = $this->getUserNotificationSettings($phoneNumber);
        
        if (!$settings['notify_on_failure']) {
            return ['success' => true, 'message' => 'Final failure notification disabled by user', 'phone' => $phoneNumber, 'bytes' => 0];
        }
        
        $message = "[080 수신거부 최종 실패]\n";
        $message .= "❌ 처리 불가\n\n";
        $message .= "📞 {$targetNumber}\n";
        $message .= "🔑 ID: {$identificationNumber}\n";
        $message .= "🔄 총 시도: {$totalAttempts}회\n";
        $message .= "⏰ " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "⚠️ 모든 시도가 실패했습니다.\n";
        $message .= "수동으로 직접 수신거부 요청하시거나\n";
        $message .= "웹 관리자에서 패턴을 확인해주세요.\n\n";
        $message .= "🌐 관리자: http://192.168.1.254/spam/";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * 음성 분석 완료 알림 SMS 전송 (상세한 버전 - 분할 전송용)
     */
    public function sendAnalysisCompleteNotificationDetailed($phoneNumber, $targetNumber, $identificationNumber, $analysisResult, $confidence, $recordingFile) {
        $statusText = [
            'success' => '✅ 수신거부 성공',
            'failed' => '❌ 수신거부 실패', 
            'uncertain' => '⚠️ 판단불가',
            'attempted' => '🔄 시도함'
        ];
        
        $statusEmoji = $statusText[$analysisResult] ?? '❓ 알 수 없음';
        $serverIP = '192.168.1.254';
        $audioUrl = "http://{$serverIP}/spam/player.php?file=" . urlencode($recordingFile);
        
        $message = "[080 수신거부 분석완료]\n";
        $message .= "{$statusEmoji}\n\n";
        $message .= "📞 대상번호: {$targetNumber}\n";
        $message .= "🔑 식별번호: {$identificationNumber}\n";
        $message .= "📊 신뢰도: {$confidence}%\n";
        $message .= "⏰ 분석시간: " . date('Y-m-d H:i:s') . "\n\n";
        
        // 결과에 따른 추가 안내
        if ($analysisResult === 'success') {
            $message .= "🎉 수신거부가 정상적으로 처리된 것으로 분석되었습니다.\n\n";
        } elseif ($analysisResult === 'failed') {
            $message .= "⚠️ 수신거부 처리에 문제가 있을 수 있습니다. 녹음을 확인해주세요.\n\n";
        } elseif ($analysisResult === 'uncertain') {
            $message .= "🤔 결과가 명확하지 않습니다. 직접 확인이 필요합니다.\n\n";
        }
        
        $message .= "🎙️ 녹음 재생:\n{$audioUrl}\n\n";
        $message .= "📱 웹 관리: http://{$serverIP}/spam/";
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * 로그 기록 (상세 정보 포함)
     * @param array $result SMS 전송 결과
     * @param string $type 로그 타입
     * @param string $originalMessage 원본 메시지 내용 (선택사항)
     */
    public function logSMS($result, $type = 'notification', $originalMessage = null) {
        // ---- guard: ensure $result is array ----
        if (!is_array($result)) {
            $result = [
                'success' => false,
                'message' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'phone'   => '',
                'bytes'   => 0,
                'debug'   => null
            ];
        }
        
        $logFile = __DIR__ . '/logs/sms_notifications.log';
        $jsonLogFile = __DIR__ . '/logs/sms_detailed.json';
        $timestamp = date('Y-m-d H:i:s');
        $status = $result['success'] ? 'SUCCESS' : 'FAILED';
        
        // 로그 디렉토리 확인 및 생성
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 기존 텍스트 로그 (호환성 유지)
        $logEntry = "[{$timestamp}] [{$type}] [{$status}] Phone: {$result['phone']}, ";
        $logEntry .= "Bytes: {$result['bytes']}, Message: {$result['message']}\n";
        
        // 상세 JSON 로그
        $detailedLog = [
            'timestamp' => $timestamp,
            'type' => $type,
            'status' => $status,
            'success' => $result['success'],
            'phone' => $result['phone'],
            'bytes' => $result['bytes'],
            'result_message' => $result['message'],
            'original_message' => $originalMessage,
            'debug' => $result['debug'] ?? null
        ];
        
        // 로그 파일에 기록
        try {
            // 기존 텍스트 로그
            $logResult = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            if ($logResult === false) {
                error_log("Failed to write to text log file: $logFile");
            }
            
            // 상세 JSON 로그
            $jsonEntry = json_encode($detailedLog, JSON_UNESCAPED_UNICODE) . "\n";
            $jsonResult = file_put_contents($jsonLogFile, $jsonEntry, FILE_APPEND | LOCK_EX);
            if ($jsonResult === false) {
                error_log("Failed to write to JSON log file: $jsonLogFile");
            }
            
            // 디버깅을 위한 상태 확인
            if (php_sapi_name() === 'cli') {
                echo "Log written - Text: " . ($logResult !== false ? 'OK' : 'FAILED') . 
                     ", JSON: " . ($jsonResult !== false ? 'OK' : 'FAILED') . "\n";
            }
            
        } catch (Exception $e) {
            error_log("SMS log write failed: " . $e->getMessage());
            // CLI에서 실행 중이면 에러 출력
            if (php_sapi_name() === 'cli') {
                echo "Log write error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * 최근 SMS 전송 기록 조회 (상세 정보 포함)
     * @param int $limit 조회할 기록 수
     * @return array SMS 전송 기록들
     */
    public function getRecentSMSLogs($limit = 10) {
        $jsonLogFile = __DIR__ . '/logs/sms_detailed.json';
        $textLogFile = __DIR__ . '/logs/sms_notifications.log';
        $logs = [];
        
        try {
            // JSON 로그 파일이 있으면 우선 사용 (더 상세한 정보)
            if (file_exists($jsonLogFile)) {
                $lines = file($jsonLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    $lines = array_reverse($lines); // 최신 순으로
                    $count = 0;
                    
                    foreach ($lines as $line) {
                        if ($count >= $limit) break;
                        
                        $logData = json_decode($line, true);
                        if ($logData) {
                            $logs[] = $logData;
                            $count++;
                        }
                    }
                    
                    return $logs;
                }
            }
            
            // JSON 로그가 없으면 기존 텍스트 로그 사용
            if (file_exists($textLogFile)) {
                $lines = file($textLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    $lines = array_reverse($lines);
                    $count = 0;
                    
                    foreach ($lines as $line) {
                        if ($count >= $limit) break;
                        
                        // 로그 라인 파싱: [timestamp] [type] [status] Phone: xxx, Bytes: xxx, Message: xxx
                        if (preg_match('/\[(.*?)\] \[(.*?)\] \[(.*?)\] Phone: (.*?), Bytes: (\d+), Message: (.*)/', $line, $matches)) {
                            $logs[] = [
                                'timestamp' => $matches[1],
                                'type' => $matches[2],
                                'status' => $matches[3],
                                'phone' => $matches[4],
                                'bytes' => (int)$matches[5],
                                'result_message' => $matches[6],
                                'success' => $matches[3] === 'SUCCESS',
                                'original_message' => null,
                                'debug' => null
                            ];
                            $count++;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("SMS log read failed: " . $e->getMessage());
        }
        
        return $logs;
    }

    public function sendVerificationCode($phoneNumber, $code){
        $msg = "[080 차단 서비스]\n인증번호: {$code}\n10분 내 입력하세요.";
        
        // 인증 SMS는 중요하므로 최대 3회 재시도
        $maxRetries = 3;
        $lastResult = null;
        
        for ($i = 0; $i < $maxRetries; $i++) {
            $result = $this->sendSMS($phoneNumber, $msg);
            
            if ($result['success']) {
                if ($i > 0) {
                    $result['message'] .= " (재시도 {$i}회 후 성공)";
                }
                return $result;
            }
            
            $lastResult = $result;
            
            // 마지막 시도가 아니면 잠시 대기
            if ($i < $maxRetries - 1) {
                sleep(5); // 5초 대기 후 재시도
            }
        }
        
        // 모든 재시도 실패
        $lastResult['message'] = "SMS 전송 실패 ({$maxRetries}회 재시도): " . $lastResult['message'];
        return $lastResult;
    }
}
?> 