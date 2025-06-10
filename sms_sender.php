<?php
/**
 * SMS 전송 유틸리티
 * Asterisk Quectel 모듈을 통한 SMS 전송 기능
 */

class SMSSender {
    private $quectelCommand = "quectel sms quectel0";
    private $config;
    
    public function __construct() {
        $configFile = __DIR__ . '/sms_config.php';
        if (file_exists($configFile)) {
            $this->config = include $configFile;
        } else {
            $this->config = ['message_mode' => 'short', 'single_sms_max_length' => 140];
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
        $maxLength = 280; // 300바이트 안전 마진 고려
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
            
            if ($byteLength > 1000) {
                $result['message'] = 'Message too long (max 1000 bytes)';
                return $result;
            }
            
            if (empty($normalizedPhone)) {
                $result['message'] = 'Invalid phone number';
                return $result;
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
        // 설정에 따라 메시지 모드 선택
        $messageMode = $this->config['message_mode'] ?? 'short';
        
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
            'success' => '✅성공',
            'failed' => '❌실패', 
            'uncertain' => '⚠️불명',
            'attempted' => '🔄시도'
        ];
        
        $statusEmoji = $statusText[$analysisResult] ?? '❓';
        $serverIP = '192.168.1.254';
        
        // 간결한 메시지 (140바이트 이내로 제한)
        $message = "[080분석] {$statusEmoji}\n";
        $message .= "{$targetNumber}\n";
        $message .= "ID:{$identificationNumber} ({$confidence}%)\n";
        $message .= "{$serverIP}/spam/player.php?file=" . urlencode($recordingFile);
        
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
}
?> 