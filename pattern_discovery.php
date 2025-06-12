<?php
// sudo nano pattern_discovery.php

if (!class_exists('PatternManager')) {
    if (file_exists(__DIR__ . '/pattern_manager.php')) {
        require_once __DIR__ . '/pattern_manager.php';
    } else if (file_exists(__DIR__ . '/pattern_manager_class.php')) { // legacy name
        require_once __DIR__ . '/pattern_manager_class.php';
    } else {
        class PatternManager {
            private $patternsFile;
            public function __construct($path) { $this->patternsFile = $path; }
            public function getPatterns() { 
                if (!file_exists($this->patternsFile)) return ['patterns' => ['default' => []]];
                $data = json_decode(file_get_contents($this->patternsFile), true);
                return $data ?: ['patterns' => ['default' => []]];
            }
            public function savePatterns($data) { 
                return file_put_contents($this->patternsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }
}

if (!class_exists('CallProcessor')) {
    require_once __DIR__ . '/process_v2.php';
}
if (!class_exists('SMSSender')) {
    require_once __DIR__ . '/sms_sender.php';
}

class PatternDiscovery
{
    private $patternManager;
    private $logFile;
    private $discoveryDir = '/var/www/html/spam/pattern_discovery/';
    private $pythonScript = '/home/linux/080-spam-blocker/advanced_pattern_analyzer.py';
    
    public function __construct()
    {
        $this->logFile = __DIR__ . '/logs/pattern_discovery.log';
        $this->patternManager = new PatternManager(__DIR__ . '/patterns.json');
        
        if (!is_dir(__DIR__ . '/logs')) {
            if (@mkdir(__DIR__ . '/logs', 0775, true)) {
                @chown(__DIR__ . '/logs', 'asterisk');
                @chgrp(__DIR__ . '/logs', 'asterisk');
            }
        }
        if (!is_dir($this->discoveryDir)) {
            if (@mkdir($this->discoveryDir, 0775, true)) {
                @chown($this->discoveryDir, 'asterisk');
                @chgrp($this->discoveryDir, 'asterisk');
            }
        }
        $this->log("--- PatternDiscovery Initialized ---");
        }
        
    private function log($message)
    {
        @file_put_contents($this->logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
            }

    public function startDiscovery($phoneNumber, $notificationPhone)
    {
        $this->log("Attempting to start discovery for phone: {$phoneNumber}");
        $uniqueId = uniqid('discovery_');

        // 상태: 시작됨
        exec("/usr/sbin/asterisk -rx \"database put Discovery/{$uniqueId}/status starting\"");
        exec("/usr/sbin/asterisk -rx \"database put Discovery {$uniqueId}/phone {$phoneNumber}\"");
        exec("/usr/sbin/asterisk -rx \"database put Discovery {$uniqueId}/notify {$notificationPhone}\"");
        $this->log("Stored discovery info in AstDB with ID: {$uniqueId}");

        $callFileContent = "Channel: quectel/quectel0/{$phoneNumber}\n";
        $callFileContent .= "Context: pattern-discovery\n";
        $callFileContent .= "Extension: s\n";
        $callFileContent .= "MaxRetries: 1\n";
        $callFileContent .= "RetryTime: 60\n";
        $callFileContent .= "WaitTime: 25\n";
        $callFileContent .= "Priority: 1\n";
        $callFileContent .= "Set: DISCOVERY_ID={$uniqueId}\n";

        // 상태: 전화 거는 중
        exec("/usr/sbin/asterisk -rx \"database put Discovery/{$uniqueId}/status calling\"");

        $tempDir = __DIR__ . '/tmp_calls/';
        if (!is_dir($tempDir)) { @mkdir($tempDir, 0775, true); }
        
        $tempFile = tempnam($tempDir, 'discovery_call_');
        if ($tempFile === false) {
            $this->log("Error: Failed to create temp file in {$tempDir}.");
            return "Error: 임시 파일을 생성하지 못했습니다.";
        }

        file_put_contents($tempFile, $callFileContent);
        $this->log("Temporary discovery Call File created at: {$tempFile}");

        @chown($tempFile, 'asterisk');
        @chgrp($tempFile, 'asterisk');

        $spoolDir = '/var/spool/asterisk/outgoing/';
        $finalFile = $spoolDir . basename($tempFile);

        if (rename($tempFile, $finalFile)) {
            $this->log("Discovery Call File moved to spool: {$finalFile}");
            return "패턴 학습을 위한 전화를 시작합니다.";
        } else {
            $this->log("Error: Failed to move Call File to spool directory.");
            @unlink($tempFile);
            return "Error: 패턴 학습 전화를 시작하지 못했습니다.";
        }
    }

    public function analyzeRecording($discoveryId)
    {
        $this->log("--- Starting Analysis for Discovery ID: {$discoveryId} ---");
        
        // 상태: 분석 중
        exec("/usr/sbin/asterisk -rx \"database put Discovery/{$discoveryId}/status analyzing\"");

        $phoneNumberVal = exec("/usr/sbin/asterisk -rx \"database get Discovery {$discoveryId}/phone\"");
        $phoneNumber = trim(str_replace("Value: ", "", $phoneNumberVal));

        if (empty($phoneNumber)) {
            $this->log("Error: Could not retrieve phone number from AstDB for ID: {$discoveryId}. Raw value: '{$phoneNumberVal}'");
            return;
        }
        $this->log("Retrieved from DB - Phone: {$phoneNumber}");

        $recordingDir = '/var/spool/asterisk/monitor/';
        $searchPattern = "{$recordingDir}*-discovery-{$phoneNumber}.wav";
        $files = glob($searchPattern);
        if (empty($files)) {
            $this->log("Error: No recording file found matching pattern: {$searchPattern}");
            return;
        }
        usort($files, function($a, $b) { return filemtime($b) <=> filemtime($a); });
        $recordingFile = $files[0];
        $this->log("Found recording file: {$recordingFile}");

        // Python 분석 스크립트 실행
        $resultFile = $this->discoveryDir . pathinfo($recordingFile, PATHINFO_FILENAME) . '_pattern.json';
        $command = sprintf(
            'python3 %s %s %s %s 2>&1',
            escapeshellarg($this->pythonScript),
            escapeshellarg($recordingFile),
            escapeshellarg($resultFile),
            escapeshellarg($phoneNumber)
        );
        
        $this->log("Executing analysis command: {$command}");
        $output = shell_exec($command);
        $this->log("Python script output: " . trim($output));
        
        // 분석 결과 확인 및 처리
        if (file_exists($resultFile)) {
            $analysisResult = json_decode(file_get_contents($resultFile), true);
            if ($analysisResult && !empty($analysisResult['success'])) {
                $this->log("Analysis successful. Saving new pattern.");
                $this->createPatternFromAnalysis($phoneNumber, $analysisResult['pattern'], $discoveryId);
            } else {
                $this->log("Error: Analysis script reported failure. Result: " . json_encode($analysisResult));
            }
        } else {
            $this->log("Error: Analysis result file not found at {$resultFile}.");
    }
    }

    private function createPatternFromAnalysis($phoneNumber, $patternDetails, $discoveryId) {
        $patterns = $this->patternManager->getPatterns();
        
        $newPattern = array_merge(
            $patterns['patterns']['default'] ?? [],
            [
                'name'              => $patternDetails['name'] ?? "자동 감지 패턴",
                'description'       => $patternDetails['description'] ?? "자동으로 감지된 패턴",
                'dtmf_pattern'      => $patternDetails['dtmf_pattern'] ?? '{ID}#',
                'initial_wait'      => $patternDetails['initial_wait'] ?? ($patterns['patterns']['default']['initial_wait'] ?? 3),
                'dtmf_timing'       => $patternDetails['dtmf_timing'] ?? ($patterns['patterns']['default']['dtmf_timing'] ?? 6),
                'confirmation_wait' => $patternDetails['confirmation_wait'] ?? ($patterns['patterns']['default']['confirmation_wait'] ?? 2),
                'confirmation_dtmf' => $patternDetails['confirmation_dtmf'] ?? ($patterns['patterns']['default']['confirmation_dtmf'] ?? '1'),
                'total_duration'    => $patternDetails['total_duration'] ?? ($patterns['patterns']['default']['total_duration'] ?? 30),
                'confirm_delay'     => $patternDetails['confirm_delay'] ?? ($patterns['patterns']['default']['confirm_delay'] ?? 2),
                'confirm_repeat'    => $patternDetails['confirm_repeat'] ?? ($patterns['patterns']['default']['confirm_repeat'] ?? 3),
                'notes'             => "자동 분석됨 - " . date('Y-m-d H:i:s'),
                'auto_generated'    => true,
                'needs_verification' => true,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ]
        );
        
        $patterns['patterns'][$phoneNumber] = $newPattern;
        
        if ($this->patternManager->savePatterns($patterns)) {
            $this->log("Successfully saved new pattern for {$phoneNumber}.");
            // 학습 성공 후 자동 재시도 및 알림
            $this->initiateUnsubscribeAndNotify($phoneNumber, $discoveryId);
        } else {
            $this->log("Error: Failed to save new pattern for {$phoneNumber}.");
        }
    }

    private function initiateUnsubscribeAndNotify($targetPhoneNumber, $discoveryId) {
        $this->log("Initiating auto-unsubscribe for {$targetPhoneNumber} after successful learning.");

        // 상태: 완료됨
        exec("/usr/sbin/asterisk -rx \"database put Discovery/{$discoveryId}/status completed\"");

        // 1. 알림 받을 전화번호 조회
        $notifyPhoneVal = exec("/usr/sbin/asterisk -rx \"database get Discovery {$discoveryId}/notify\"");
        $notifyPhoneNumber = trim(str_replace("Value: ", "", $notifyPhoneVal));

        if (empty($notifyPhoneNumber)) {
            $this->log("Could not find notification phone number for discovery ID {$discoveryId}. Skipping notification and unsubscribe call.");
            return;
}

        // 2. 수신거부 재시도
        $callProcessor = new CallProcessor();
        // 실제 ID가 없으므로 '000000'을 테스트용으로 사용
        $unsubscribeResult = $callProcessor->makeCall('000000', $targetPhoneNumber, $notifyPhoneNumber); 
        $this->log("Auto-unsubscribe call initiated. Result: {$unsubscribeResult}");

        // 3. SMS 알림 발송
        $smsSender = new SMSSender();
        $message = "[080 패턴 학습 완료]\n\n" .
                   "▶ 대상: {$targetPhoneNumber}\n" .
                   "▶ 결과: 패턴 학습 성공\n\n" .
                   "학습된 패턴으로 수신거부를 자동으로 재시도합니다. 최종 결과는 별도 SMS로 발송됩니다.";

        $smsResult = $smsSender->sendSMS($notifyPhoneNumber, $message);
        if ($smsResult['success']) {
            $this->log("Successfully sent pattern learning completion notification SMS to {$notifyPhoneNumber}.");
        } else {
            $this->log("Failed to send SMS notification. Reason: {$smsResult['message']}");
        }
        
        // 작업 완료 후 AstDB 정리
        exec("/usr/sbin/asterisk -rx \"database del Discovery {$discoveryId}/phone\"");
        exec("/usr/sbin/asterisk -rx \"database del Discovery {$discoveryId}/notify\"");
        exec("/usr/sbin/asterisk -rx \"database del Discovery {$discoveryId}/status\"");
        $this->log("Cleaned up AstDB for ID: {$discoveryId}");
    }
}

if (php_sapi_name() == 'cli' && isset($argv[1]) && $argv[1] == 'analyze' && isset($argv[2])) {
    require_once __DIR__ . '/pattern_discovery.php';
    $discovery = new PatternDiscovery();
    $discovery->analyzeRecording($argv[2]);
    }