<?php
/**
 * asterisk_startup_hook.php
 * 
 * Asterisk 재시작 시 호출되는 훅 스크립트
 * - 재시작 감지 및 마킹
 * - 큐 정리
 * - SIM 메시지 정리 (선택적)
 */

// CLI 실행만 허용
if (php_sapi_name() !== 'cli') {
    exit("This script must be run from command line\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Asterisk Startup Hook Started\n";

require_once __DIR__ . '/startup_manager.php';
$startupManager = new StartupManager();

// 1. 재시작 마킹
$state = $startupManager->markStartup('asterisk_hook');
echo "Startup marked: safe threshold = " . date('Y-m-d H:i:s', $state['safe_message_threshold']) . "\n";

// 2. 큐 및 임시 파일 정리
$cleaned = $startupManager->cleanupOnStartup();
echo "Cleanup completed: {$cleaned} files removed\n";

// 3. SIM 메시지 강제 정리 (옵션)
$clearSim = $argv[1] ?? '';
if ($clearSim === '--clear-sim') {
    echo "Attempting to clear SIM messages...\n";
    
    // AT 명령으로 SIM 메시지 전체 삭제 시도
    $candidatePorts = ['/dev/ttyUSB3', '/dev/ttyUSB2', '/dev/ttyUSB1'];
    
    foreach ($candidatePorts as $port) {
        if (!file_exists($port)) continue;
        
        // 포트가 사용 중인지 확인
        $out = [];
        exec('lsof -F -n ' . escapeshellarg($port) . ' 2>/dev/null', $out);
        if (!empty($out)) continue; // 사용 중이면 건너뜀
        
        try {
            $fp = @fopen($port, 'r+');
            if (!$fp) continue;
            
            stream_set_blocking($fp, false);
            stream_set_timeout($fp, 2);
            
            // AT 명령 전송 함수
            $sendAT = function($cmd) use ($fp) {
                fwrite($fp, $cmd . "\r");
                usleep(300000); // 300ms 대기
                $resp = '';
                $start = microtime(true);
                while (microtime(true) - $start < 3.0) {
                    $buf = fread($fp, 4096);
                    if ($buf !== false && $buf !== '') $resp .= $buf;
                    if (strpos($resp, "OK") !== false || strpos($resp, "ERROR") !== false) break;
                    usleep(20000);
                }
                return $resp;
            };
            
            // SIM 메시지 일괄 삭제 시도
            $sendAT('AT');
            $sendAT('AT+CMGF=1');
            $deleteResp = $sendAT('AT+QMGDA="DEL ALL"'); // 모든 메시지 삭제
            
            fclose($fp);
            
            if (strpos($deleteResp, 'OK') !== false) {
                echo "SIM messages cleared successfully via {$port}\n";
                break;
            } else {
                echo "SIM clear failed on {$port}: {$deleteResp}\n";
            }
            
        } catch (Exception $e) {
            echo "Error accessing {$port}: " . $e->getMessage() . "\n";
        }
    }
}

// 4. 상태 리포트
$diag = $startupManager->getDiagnostics();
echo "\n=== Startup State ===\n";
echo "Startup Time: " . $diag['startup_time'] . "\n";
echo "Safe Threshold: " . $diag['safe_threshold'] . "\n";
echo "Recent Startup: " . ($diag['is_recent_startup'] ? 'Yes' : 'No') . "\n";

echo "[" . date('Y-m-d H:i:s') . "] Asterisk Startup Hook Completed\n";
?>