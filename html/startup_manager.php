<?php
/**
 * startup_manager.php
 * 
 * Asterisk/시스템 재시작 감지 및 상태 관리
 * - 재시작 시점 기록
 * - 큐 정리
 * - 안전한 메시지 처리 시작점 설정
 */

class StartupManager {
    private $stateFile = '/tmp/asterisk_startup_state.json';
    private $maxStartupAge = 600; // 10분: 재시작 후 이 시간 이전 메시지는 무시
    
    public function __construct() {
        $this->ensureStateFile();
    }
    
    /**
     * 상태 파일 초기화/로드
     */
    private function ensureStateFile() {
        if (!file_exists($this->stateFile)) {
            $this->markStartup('initial');
        }
    }
    
    /**
     * 재시작 마킹 (init, restart, reload 등)
     */
    public function markStartup($type = 'restart') {
        $state = [
            'startup_time' => time(),
            'startup_type' => $type,
            'safe_message_threshold' => time() - $this->maxStartupAge,
            'last_update' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT));
        
        // 퍼미션: Apache(www-data)와 Asterisk 모두 쓰기 가능하도록 0666
        @chmod($this->stateFile, 0666);
        
        // 로그 기록
        $logMsg = date('Y-m-d H:i:s') . " [StartupManager] Startup marked: {$type}, safe threshold: " . date('Y-m-d H:i:s', $state['safe_message_threshold']) . "\n";
        file_put_contents(__DIR__ . '/logs/startup.log', $logMsg, FILE_APPEND);
        
        return $state;
    }
    
    /**
     * 현재 상태 조회
     */
    public function getState() {
        if (!file_exists($this->stateFile)) {
            return $this->markStartup('missing_state');
        }
        
        $content = file_get_contents($this->stateFile);
        return json_decode($content, true) ?: $this->markStartup('corrupted_state');
    }
    
    /**
     * 메시지가 안전한 처리 대상인지 확인
     * @param int $messageTimestamp 메시지의 타임스탬프
     * @return bool
     */
    public function isMessageSafeToProcess($messageTimestamp) {
        $state = $this->getState();
        $threshold = $state['safe_message_threshold'] ?? 0;
        
        // 메시지가 안전 임계점보다 나중에 온 것이면 처리 허용
        return $messageTimestamp >= $threshold;
    }
    
    /**
     * 재시작 후 경과 시간 (초)
     */
    public function getSecondsSinceStartup() {
        $state = $this->getState();
        return time() - ($state['startup_time'] ?? time());
    }
    
    /**
     * 재시작 직후 인지 확인 (10분 이내)
     */
    public function isRecentStartup() {
        return $this->getSecondsSinceStartup() < $this->maxStartupAge;
    }
    
    /**
     * Call Queue 정리 (재시작 시)
     */
    public function cleanupOnStartup() {
        $queueDir = __DIR__ . '/call_queue/';
        $spoolDir = '/var/spool/asterisk/outgoing/';
        $tmpDir = '/tmp/';
        
        $cleaned = 0;
        
        // 0. 스풀 디렉토리의 잔여 call 파일들 정리 (재시작 시 즉시 발신 방지)
        if (is_dir($spoolDir)) {
            foreach (glob($spoolDir . '/*.call') as $file) {
                if (unlink($file)) $cleaned++;
            }
        }
        
        // 1. 큐 디렉토리의 오래된 call 파일들 정리
        if (is_dir($queueDir)) {
            foreach (glob($queueDir . '/*.call') as $file) {
                if (unlink($file)) $cleaned++;
            }
        }
        
        // 2. 임시 SMS 락 파일들 정리 (10분 이상 된 것만)
        foreach (glob($tmpDir . 'smslock_*') as $lockFile) {
            $age = time() - filemtime($lockFile);
            if ($age > 600) { // 10분 = 600초
                if (@unlink($lockFile)) {
                    $cleaned++;
                } else {
                    // 권한 문제 시 강제 삭제 시도
                    exec('rm -f ' . escapeshellarg($lockFile) . ' 2>/dev/null');
                    if (!file_exists($lockFile)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        // 3. 진행 상태 파일들 정리
        foreach (glob($tmpDir . 'call_queue_*') as $stateFile) {
            if (unlink($stateFile)) $cleaned++;
        }
        
        $logMsg = date('Y-m-d H:i:s') . " [StartupManager] Cleanup completed: {$cleaned} files removed\n";
        file_put_contents(__DIR__ . '/logs/startup.log', $logMsg, FILE_APPEND);
        
        return $cleaned;
    }
    
    /**
     * SMS 메시지의 수신 시간 파싱 (AT+CMGL 형식)
     * @param string $timestamp "YYYY/MM/DD,HH:MM:SS+TZ" 형식
     * @return int Unix timestamp
     */
    public function parseSmsTimestamp($timestamp) {
        // 2025/06/15,10:30:45+32 형식 파싱
        if (preg_match('/(\d{4})\/(\d{2})\/(\d{2}),(\d{2}):(\d{2}):(\d{2})/', $timestamp, $m)) {
            return mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
        }
        
        // 파싱 실패 시 현재 시간 반환 (안전을 위해)
        return time();
    }
    
    /**
     * 시스템 상태 진단
     */
    public function getDiagnostics() {
        $state = $this->getState();
        $now = time();
        
        return [
            'startup_time' => date('Y-m-d H:i:s', $state['startup_time'] ?? 0),
            'seconds_since_startup' => $this->getSecondsSinceStartup(),
            'is_recent_startup' => $this->isRecentStartup(),
            'safe_threshold' => date('Y-m-d H:i:s', $state['safe_message_threshold'] ?? 0),
            'current_time' => date('Y-m-d H:i:s', $now),
            'state_file_exists' => file_exists($this->stateFile),
            'startup_type' => $state['startup_type'] ?? 'unknown'
        ];
    }
}

// CLI 사용을 위한 스크립트 부분
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'status';
    $manager = new StartupManager();
    
    switch ($action) {
        case 'mark':
            $type = $argv[2] ?? 'manual';
            $state = $manager->markStartup($type);
            echo "Startup marked: {$type}\n";
            echo "Safe threshold: " . date('Y-m-d H:i:s', $state['safe_message_threshold']) . "\n";
            break;
            
        case 'cleanup':
            $cleaned = $manager->cleanupOnStartup();
            echo "Cleanup completed: {$cleaned} files removed\n";
            break;
            
        case 'status':
        default:
            $diag = $manager->getDiagnostics();
            echo "=== Startup Manager Status ===\n";
            foreach ($diag as $key => $value) {
                echo sprintf("%-20s: %s\n", $key, is_bool($value) ? ($value ? 'Yes' : 'No') : $value);
            }
            break;
    }
}
?>