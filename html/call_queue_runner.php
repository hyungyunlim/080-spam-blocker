<?php
/**
 * call_queue_runner.php
 *
 * 큐 디렉토리에 쌓인 Asterisk Call File 을 하나씩 꺼내어
 * 모뎀(quectel) 채널이 사용 중이지 않을 때 /var/spool/asterisk/outgoing 으로 이동한다.
 *
 * 사용:
 *   php call_queue_runner.php          # 한 번 수행 (cron 에서 호출 권장)
 *   php call_queue_runner.php --loop   # while 루프(daemon) – 5초 간격
 */

$options = getopt('', ['loop', 'startup']);
$loopMode = isset($options['loop']);
$startupMode = isset($options['startup']);

// 재시작 관리자 초기화
require_once __DIR__ . '/startup_manager.php';
$startupManager = new StartupManager();

$queueDir = __DIR__ . '/call_queue/';
$spoolDir = '/var/spool/asterisk/outgoing/';

$minGapSec = 45; // 최소 간격(초) – 한 통 이동 후 45초 대기
$stateFile  = '/tmp/call_queue_last_move';

// --- NEW: TTL 정책 (반복 실행 시 중복 발신 방지) ----------------------------
// queue 폴더 파일  → 1시간 이상이면 폐기
// spool 폴더 파일 → 30분 이상이면 폐기(통화 실패·미완료 가능성)
$queueTtlSec = 3600;   // 1 h
$spoolTtlSec = 300;    // 5 min

// 시작 모드 처리
if ($startupMode) {
    echo "[call_queue_runner] Startup mode: cleaning up and marking restart\n";
    $startupManager->markStartup('asterisk_restart');
    $cleaned = $startupManager->cleanupOnStartup();
    echo "Cleaned up {$cleaned} files\n";
    exit(0);
}

function purgeOldCallfiles(string $dir, int $ttlSec): void {
    if (!is_dir($dir)) return;
    $now = time();
    foreach (glob($dir . '/*.call') as $f) {
        if ($now - filemtime($f) > $ttlSec) {
            @unlink($f);
            file_put_contents(__DIR__.'/logs/queue_runner.log', date('Y-m-d H:i:s')." Purged stale file: {$f}\n", FILE_APPEND);
        }
    }
}

// 최초 실행 시 1회, loop 모드에서는 매회 수행
purgeOldCallfiles($queueDir, $queueTtlSec);
purgeOldCallfiles($spoolDir, $spoolTtlSec);

if (!is_dir($queueDir)) {
    echo "Queue directory not found: {$queueDir}\n";
    exit(0);
}

function isModemIdle(): bool {
    // quectel 채널이 active 한지 확인 (concise 형식)
    $out = [];
    exec('/usr/sbin/asterisk -rx "core show channels concise" | grep quectel', $out);
    return count($out) === 0;
}

function processOnce($queueDir, $spoolDir): bool {
    global $stateFile, $minGapSec, $queueTtlSec;
    // 최근 이동 시점 확인 – 아직 대기 시간 이내면 이동 보류
    if (file_exists($stateFile) && (time() - filemtime($stateFile) < $minGapSec)) {
        return false;
    }
    // 대기 중인 .call 파일 중 가장 오래된 것 반환
    $files = glob($queueDir . '/*.call');
    if (!$files) return false;
    usort($files, function($a,$b){ return filemtime($a) <=> filemtime($b); });
    $next = $files[0];
    // TTL 검사 – 1h 초과 파일은 삭제 후 skip
    if (time() - filemtime($next) > $queueTtlSec) {
        @unlink($next);
        file_put_contents(__DIR__.'/logs/queue_runner.log', date('Y-m-d H:i:s')." Dropped expired queue file: {$next}\n", FILE_APPEND);
        return false;
    }
    if (!isModemIdle()) return false;
    $dest = $spoolDir . basename($next);
    if (rename($next, $dest)) {
        // ownership 을 asterisk 로 변경 (필요시)
        @chown($dest, 'asterisk');
        @chgrp($dest, 'asterisk');
        file_put_contents(__DIR__.'/logs/queue_runner.log', date('Y-m-d H:i:s')." Moved to spool: ".$dest."\n", FILE_APPEND);
        // 이동 성공 시 타임스탬프 기록하여 간격 보장
        @touch($stateFile);
        return true;
    }
    return false;
}

if ($loopMode) {
    echo "[call_queue_runner] daemon 모드 시작...\n";
    while(true){
        processOnce($queueDir, $spoolDir);
        sleep(5);
    }
} else {
    $moved = processOnce($queueDir, $spoolDir);
    echo $moved ? "1건 이동 완료\n" : "이동할 파일이 없거나 모뎀이 사용 중\n";
}
?> 