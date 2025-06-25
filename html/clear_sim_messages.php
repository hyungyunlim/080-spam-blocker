<?php
/**
 * clear_sim_messages.php
 * 
 * SIM 카드에 저장된 모든 SMS 메시지를 AT 명령으로 강제 삭제
 * Asterisk 재시작 시 오래된 메시지가 재처리되는 것을 방지
 */

// CLI 실행만 허용
if (php_sapi_name() !== 'cli') {
    exit("This script must be run from command line\n");
}

$force = in_array('--force', $argv);
$verbose = in_array('--verbose', $argv);

if (!$force) {
    echo "This will delete ALL SMS messages from SIM card.\n";
    echo "Use --force to confirm, --verbose for detailed output\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting SIM message cleanup\n";

// 후보 포트들
$candidatePorts = ['/dev/ttyUSB3', '/dev/ttyUSB2', '/dev/ttyUSB1', '/dev/ttyUSB0'];
$success = false;

foreach ($candidatePorts as $port) {
    if (!file_exists($port)) {
        if ($verbose) echo "Port {$port} does not exist\n";
        continue;
    }
    
    // 포트 사용 중 여부 확인
    $out = [];
    exec('lsof -F -n ' . escapeshellarg($port) . ' 2>/dev/null', $out);
    if (!empty($out)) {
        if ($verbose) echo "Port {$port} is busy\n";
        continue;
    }
    
    if ($verbose) echo "Trying port {$port}...\n";
    
    try {
        $fp = @fopen($port, 'r+');
        if (!$fp) {
            if ($verbose) echo "Failed to open {$port}\n";
            continue;
        }
        
        stream_set_blocking($fp, false);
        stream_set_timeout($fp, 3);
        
        // AT 명령 전송 함수
        $sendAT = function($cmd, $timeout = 3) use ($fp, $verbose) {
            if ($verbose) echo ">> {$cmd}\n";
            fwrite($fp, $cmd . "\r");
            usleep(300000); // 300ms 대기
            
            $resp = '';
            $start = microtime(true);
            while (microtime(true) - $start < $timeout) {
                $buf = fread($fp, 4096);
                if ($buf !== false && $buf !== '') $resp .= $buf;
                if (strpos($resp, "OK") !== false || strpos($resp, "ERROR") !== false) break;
                usleep(50000); // 50ms
            }
            
            if ($verbose) echo "<< " . trim($resp) . "\n";
            return $resp;
        };
        
        // 모뎀 초기화 및 상태 확인
        $resp1 = $sendAT('AT');
        if (strpos($resp1, 'OK') === false) {
            if ($verbose) echo "AT command failed on {$port}\n";
            fclose($fp);
            continue;
        }
        
        // TEXT 모드 설정
        $sendAT('AT+CMGF=1');
        
        // SIM 스토리지 설정
        $sendAT('AT+CPMS="SM","SM","SM"');
        
        // 메시지 개수 확인
        $statusResp = $sendAT('AT+CPMS?');
        if (preg_match('/\+CPMS: "SM",(\d+),(\d+)/', $statusResp, $m)) {
            $used = (int)$m[1];
            $total = (int)$m[2];
            echo "SIM storage: {$used}/{$total} messages\n";
            
            if ($used == 0) {
                echo "No messages to delete\n";
                fclose($fp);
                $success = true;
                break;
            }
        }
        
        // 방법 1: 일괄 삭제 시도 (Quectel 전용)
        echo "Attempting bulk delete...\n";
        $deleteResp = $sendAT('AT+QMGDA="DEL ALL"', 10);
        
        if (strpos($deleteResp, 'OK') !== false) {
            echo "✅ Bulk delete successful via {$port}\n";
            $success = true;
        } else {
            echo "⚠️  Bulk delete failed, trying individual delete...\n";
            
            // 방법 2: 개별 삭제
            $listResp = $sendAT('AT+CMGL="ALL"', 10);
            if (strpos($listResp, '+CMGL:') !== false) {
                $lines = explode("\n", $listResp);
                $deleted = 0;
                
                foreach ($lines as $line) {
                    if (preg_match('/\+CMGL: (\d+),/', $line, $m)) {
                        $idx = (int)$m[1];
                        $delResp = $sendAT("AT+CMGD={$idx},0");
                        if (strpos($delResp, 'OK') !== false) {
                            $deleted++;
                            if ($verbose) echo "Deleted message {$idx}\n";
                        }
                    }
                }
                
                echo "✅ Individual delete: {$deleted} messages removed via {$port}\n";
                $success = true;
            } else {
                echo "❌ Could not list messages on {$port}\n";
            }
        }
        
        fclose($fp);
        
        if ($success) break;
        
    } catch (Exception $e) {
        echo "❌ Error on {$port}: " . $e->getMessage() . "\n";
    }
}

if ($success) {
    echo "[" . date('Y-m-d H:i:s') . "] ✅ SIM message cleanup completed successfully\n";
    
    // 성공 시 startup manager에 기록
    if (file_exists(__DIR__ . '/startup_manager.php')) {
        require_once __DIR__ . '/startup_manager.php';
        $sm = new StartupManager();
        $sm->markStartup('sim_cleared');
    }
    
    exit(0);
} else {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ SIM message cleanup failed on all ports\n";
    exit(1);
}
?>