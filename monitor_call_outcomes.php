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

    // confirm-only 패턴(auto_supported:false)는 자동 재시도 대상에서 제외
    if ($hasSpecific && isset($patterns[$dialed]['auto_supported']) && $patterns[$dialed]['auto_supported'] === false) {
        continue; // skip further processing
    }

    // lock check (prevent duplicate while analysis pending)
    $lockFile = __DIR__ . '/pattern_discovery_active/' . $dialed . '.lock';
    if (file_exists($lockFile) && (time() - filemtime($lockFile) < 1800)) {
        // discovery or analysis in progress; wait
        continue;
    }

    //  추가 검사: 최근 녹음 분석 결과가 "success" 인 경우, 재학습/재통화 건너뜀
    $recordingDir = '/var/spool/asterisk/monitor/';
    $filesRec = glob($recordingDir . '*-TO_' . $dialed . '.wav');
    usort($filesRec, function($a,$b){return filemtime($b) <=> filemtime($a);} );
    if($filesRec){
        $latestRec = $filesRec[0];
        $base = pathinfo($latestRec, PATHINFO_FILENAME);
        $analysisDir = __DIR__ . '/analysis_results/';
        $candidates = [
            $analysisDir . 'analysis_' . $base . '.json',
            $analysisDir . $base . '_analysis.json'
        ];
        foreach($candidates as $cand){
            if(file_exists($cand)){
                $data = json_decode(file_get_contents($cand),true);
                if(isset($data['analysis']['status']) && $data['analysis']['status']==='success'){
                    // 이미 성공 처리됨
                    continue 2; // skip foreach($files) loop continue outer
                }
            }
        }
    }

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