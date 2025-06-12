<?php
// monitor_call_outcomes.php
// Cron-friendly script: parses /var/log/asterisk/call_progress/*.log that are older than 2 min,
// decides success/failure, and if failed + pattern was only default, launches PatternDiscovery.

require_once __DIR__ . '/pattern_discovery.php';

$logDir = '/var/log/asterisk/call_progress';
$files  = glob($logDir . '/*.log');
if (!$files) {
    exit; // nothing to do
}

foreach ($files as $file) {
    if (filemtime($file) > time() - 120) continue; // wait at least 2 min
    if (file_exists($file . '.done')) continue;     // already handled

    $content = file_get_contents($file);
    $callId  = basename($file, '.log');

    $success = false;
    if (strpos($content, 'Recording completed') !== false && strpos($content, 'Completed') !== false) {
        // naive heuristic for success
        $success = true;
    }

    // extract dialed 080
    $dialed = null;
    if (preg_match('/TO_(\d{10,11})/', $content, $m)) {
        $dialed = $m[1];
    }

    // mark as processed
    touch($file . '.done');

    if ($success || !$dialed) continue;

    // 실패: 패턴 존재 여부 확인
    $patternsFile = __DIR__ . '/patterns.json';
    $patternsData = file_exists($patternsFile) ? json_decode(file_get_contents($patternsFile), true) : [];
    $patterns     = $patternsData['patterns'] ?? [];

    $hasSpecific = isset($patterns[$dialed]) && $patterns[$dialed]['name'] !== '기본값';

    if (!$hasSpecific) {
        // 1) 패턴 디스커버리 시작
        $discovery = new PatternDiscovery();
        $discovery->startDiscovery($dialed, '');

        // 2) 90초 후 새 패턴으로 재시도 자동 호출 (백그라운드)
        $notif = '01000000000';
        $cmd = "(sleep 90; php " . __DIR__ . "/process_v2.php --auto --phone={$dialed} --notification={$notif} >> /var/log/asterisk/retry.log 2>&1) &";
        exec($cmd);
    }
} 