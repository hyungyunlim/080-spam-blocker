<?php
/**
 * remote_analyzer_client.php
 * M1 맥미니에서 STT 분석을 수행하기 위한 클라이언트
 * 라즈베리파이에서 녹음 파일을 맥미니로 전송하고 결과를 받아옴
 */

class RemoteAnalyzerClient {
    private $macMiniIP = '192.168.1.92'; // M1 맥미니 IP
    private $macMiniPort = 8080;
    private $timeout = 120; // 2분 타임아웃
    
    public function __construct($ip = null, $port = null) {
        if ($ip) $this->macMiniIP = $ip;
        if ($port) $this->macMiniPort = $port;
    }
    
    /**
     * 맥미니 연결 상태 확인
     */
    public function checkConnection() {
        $url = "http://{$this->macMiniIP}:{$this->macMiniPort}/health";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    /**
     * 녹음 파일을 맥미니로 전송하여 분석
     */
    public function analyzeAudio($audioFilePath, $callId) {
        if (!file_exists($audioFilePath)) {
            throw new Exception("Audio file not found: $audioFilePath");
        }
        
        if (!$this->checkConnection()) {
            throw new Exception("Cannot connect to Mac Mini at {$this->macMiniIP}:{$this->macMiniPort}");
        }
        
        $url = "http://{$this->macMiniIP}:{$this->macMiniPort}/analyze";
        
        $postData = [
            'call_id' => $callId,
            'audio_file' => new CURLFile($audioFilePath, 'audio/wav', basename($audioFilePath))
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: RaspberryPi-STT-Client/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP error: $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("Invalid JSON response: $response");
        }
        
        return $result;
    }
    
    /**
     * 분석 진행 상황 확인
     */
    public function getAnalysisProgress($callId) {
        $url = "http://{$this->macMiniIP}:{$this->macMiniPort}/progress/$callId";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
}

/**
 * 원격 분석 실행 함수 (기존 success_checker.php에서 호출)
 */
function performRemoteAnalysis($audioFile, $callId) {
    $logFile = "/var/log/asterisk/call_progress/{$callId}.log";
    
    try {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] REMOTE_STT_START\n", FILE_APPEND);
        
        $client = new RemoteAnalyzerClient();
        
        // 분석 요청 시작
        $startResponse = $client->analyzeAudio($audioFile, $callId);
        if (!$startResponse || $startResponse['status'] !== 'processing') {
            throw new Exception('Failed to start remote analysis');
        }
        
        // 분석 완료까지 대기 (최대 60초)
        $maxWait = 60;
        $waited = 0;
        $result = null;
        
        while ($waited < $maxWait) {
            sleep(2);
            $waited += 2;
            
            $progress = $client->getAnalysisProgress($callId);
            if ($progress) {
                // M1 분석 진행 상황을 상세 로깅
                $progressStatus = $progress['status'] ?? 'unknown';
                $progressPercent = $progress['progress'] ?? 0;
                $progressMessage = $progress['message'] ?? '';
                
                file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] M1_PROGRESS status={$progressStatus} progress={$progressPercent}% msg={$progressMessage}\n", FILE_APPEND);
                
                if ($progressStatus === 'completed') {
                    $result = $progress['result'];
                    break;
                }
                if ($progressStatus === 'error') {
                    throw new Exception('Remote analysis error: ' . ($progressMessage ?: 'Unknown error'));
                }
            } else {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] M1_PROGRESS no_response (waited={$waited}s)\n", FILE_APPEND);
            }
        }
        
        if (!$result) {
            throw new Exception('Remote analysis timeout after ' . $maxWait . ' seconds');
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] REMOTE_STT_SUCCESS\n", FILE_APPEND);
        
        // 결과를 기존 형식으로 변환
        $analysisResult = [
            'file_path' => $audioFile,
            'timestamp' => date('c'),
            'transcription' => $result['transcription'] ?? '',
            'analysis' => [
                'status' => $result['status'] ?? 'failed',
                'confidence' => $result['confidence'] ?? 30,
                'reason' => $result['reason'] ?? 'Remote analysis result'
            ],
            'pattern_hint' => $result['pattern_hint'] ?? null,
            'file_size' => filesize($audioFile),
            'processing_time' => $result['processing_time'] ?? 0,
            'remote_analysis' => true
        ];
        
        // 결과 저장
        $analysisDir = __DIR__ . '/analysis_results';
        if (!is_dir($analysisDir)) {
            mkdir($analysisDir, 0775, true);
        }
        
        $base = pathinfo($audioFile, PATHINFO_FILENAME);
        $jsonFile = $analysisDir . '/analysis_' . $base . '.json';
        file_put_contents($jsonFile, json_encode($analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $analysisResult;
        
    } catch (Exception $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] REMOTE_STT_ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        
        // 로컬 분석으로 폴백
        file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] FALLBACK_TO_LOCAL\n", FILE_APPEND);
        return null; // 로컬 분석을 계속 진행하도록 함
    }
}
?>