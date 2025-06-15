<?php
/**
 * sms_fetcher.php
 * -----------------------------------------------------------------------------
 * Quectel EC25 모뎀(SIM) 내부에 저장된 문자(SMS) 목록을 읽어와서
 * 각 메시지를 기존 sms_auto_processor.php 로 전달한다.
 *  - /dev/ttyUSB2 AT 포트 사용 (필요 시 환경 변수 SMS_TTY 로 재정의)
 *  - AT+CMGF=1  (TEXT 모드)
 *  - AT+CPMS="SM","SM","SM"
 *  - AT+CMGL="ALL"  로 목록 수신
 *  - 각 항목은 2줄(+CMGL:, 본문) 혹은 그 이상의 multi-line 일 수 있음
 *  - 처리 후 AT+CMGD=<idx>,0  로 개별 삭제
 *
 * 사용 예:
 *   php sms_fetcher.php            # 한 번만 실행
 *   php sms_fetcher.php --loop     # 데몬(60초 주기)
 * -----------------------------------------------------------------------------
 */
$opt = getopt('', ['loop']);
$loop = isset($opt['loop']);
$candidatePorts = ['/dev/ttyUSB3','/dev/ttyUSB2','/dev/ttyUSB1'];
$portEnv = getenv('SMS_TTY');
$port = $portEnv ?: $candidatePorts[0]; // 초기값

// --port=/dev/ttyXXX CLI 로도 지정 가능
$cliOpt = getopt('', ['port:']);
if(isset($cliOpt['port']) && $cliOpt['port']!==''){
    $port = $cliOpt['port'];
}

$interval = 60; // loop 간격

function openPort($dev){
    $fp = @fopen($dev, 'r+');
    if(!$fp) throw new RuntimeException("Cannot open serial port {$dev}");
    stream_set_blocking($fp, false);
    stream_set_timeout($fp, 2);
    return $fp;
}

// 포트를 asterisk 등 다른 프로세스가 사용 중인지 확인
function isPortBusy($dev): bool {
    $out = [];
    exec('lsof -F -n '.escapeshellarg($dev).' 2>/dev/null', $out);
    foreach($out as $line){
        if($line !== '') return true; // 출력이 있으면 점유 중
    }
    return false;
}

function sendAT($fp, $cmd, $waitMs=300){
    fwrite($fp, $cmd."\r");
    usleep($waitMs*1000);
    $resp = '';
    $start = microtime(true);
    $timeout = 4.0; // 최대 4초 읽기 (OK 나 ERROR 만나면 더 빨리 종료)
    while(microtime(true) - $start < $timeout){
        $buf = fread($fp, 4096);
        if($buf!==false && $buf!=='') $resp .= $buf;
        if(strpos($resp, "OK") !== false || strpos($resp, "ERROR") !== false) break;
        usleep(20000);
    }
    return $resp;
}

function processSmsBatch($fp){
    // 시작 관리자 로드
    require_once __DIR__ . '/startup_manager.php';
    $startupManager = new StartupManager();
    
    // 모뎀 준비 – TEXT 모드 & SIM 스토리지 선택
    sendAT($fp, 'AT');
    sendAT($fp, 'AT+CMGF=1');
    sendAT($fp, 'AT+CPMS="SM","SM","SM"');

    // 전체 목록 요청
    $raw = sendAT($fp, 'AT+CMGL="ALL"', 800);
    if(stripos($raw, '+CMGL:') === false) return 0;

    // +CMGL: idx,"STO <status>","<sender>",,"YYYY/MM/DD,HH:MM:SS+TZ"
    $msgs = preg_split('/\r?\n/', trim($raw));
    $pending = [];
    $skipped = 0;
    
    for($i=0; $i<count($msgs); $i++){
        if(strpos($msgs[$i], '+CMGL:') === 0){
            // 전체 +CMGL 라인 파싱 (타임스탬프 포함)
            if(!preg_match('/\+CMGL: (\d+),"[^"]+","([^"]*)",[^,]*,"([^"]*)"/', $msgs[$i], $m)) continue;
            $idx = (int)$m[1];
            $sender = $m[2];
            $timestamp = $m[3] ?? '';
            $body  = $msgs[$i+1] ?? '';
            
            // 타임스탬프 검증 - 안전한 메시지인지 확인
            if (!empty($timestamp)) {
                $msgTime = $startupManager->parseSmsTimestamp($timestamp);
                if (!$startupManager->isMessageSafeToProcess($msgTime)) {
                    // 재시작 전 메시지는 건너뜀
                    sendAT($fp, 'AT+CMGD='.$idx.',0'); // 삭제만 하고 처리하지 않음
                    $skipped++;
                    continue;
                }
            }
            
            // === UCS2(UTF-16BE) 헥스 문자열일 경우 디코딩 ===
            if(preg_match('/^[0-9A-F]{4,}$/i', $body) && strlen($body)%4==0){
                $bin = @pack('H*', $body);
                if($bin!==false){
                    $decoded = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
                    if($decoded!==false && trim($decoded)!==''){
                        $body = $decoded;
                    }
                }
            }
            $pending[] = [$idx, $sender, $body];
        }
    }
    
    // 스킵된 메시지 로그
    if ($skipped > 0) {
        $logMsg = date('Y-m-d H:i:s') . " [sms_fetcher] Skipped {$skipped} old messages (before startup threshold)\n";
        file_put_contents(__DIR__ . '/logs/sms_fetcher.log', $logMsg, FILE_APPEND);
    }

    foreach($pending as $p){
        [$idx, $sender, $body] = $p;
        $b64 = base64_encode($body);
        // 기존 자동 처리기로 전달 (백그라운드)
        $cmd = 'php '.__DIR__.'/sms_auto_processor.php --caller='.escapeshellarg($sender).' --msg_base64='.escapeshellarg($b64).' > /dev/null 2>&1 &';
        exec($cmd);
        // 삭제
        sendAT($fp, 'AT+CMGD='.$idx.',0');
    }
    return count($pending);
}

function runOnce(){
    global $port, $candidatePorts;
    // 후보 포트 리스트 중 사용 가능하고 busy 가 아닌 첫 포트 선택
    $selected = null;
    if(!isPortBusy($port) && file_exists($port)){
        $selected = $port;
    } else {
        foreach($candidatePorts as $p){
            if($p === $port) continue;
            if(file_exists($p) && !isPortBusy($p)){
                $selected = $p; break;
            }
        }
    }
    if($selected === null){
        // 포트가 모두 점유 중이면 skip
        return 0;
    }
    try{
        $fp = openPort($selected);
        $cnt = processSmsBatch($fp);
        fclose($fp);
        return $cnt;
    }catch(Throwable $e){
        error_log('[sms_fetcher] '.$e->getMessage());
        return 0;
    }
}

if($loop){
    echo "[sms_fetcher] loop mode – polling SIM every {$interval}s\n";
    while(true){
        $n = runOnce();
        if($n>0) echo date('Y-m-d H:i:s')." Fetched {$n} SMS\n";
        sleep($interval);
    }
} else {
    $n = runOnce();
    echo "Fetched {$n} pending SMS\n";
}
?> 