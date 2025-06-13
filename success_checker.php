<?php
// success_checker.php : determine if unsubscribe call succeeded using STT keyword match
// Usage: php success_checker.php --callfile=ID --record=/path/to.wav

require_once __DIR__.'/vendor/autoload.php'; // if fast-whisper installed via composer (optional)

$options = getopt('', [
    'callfile:',
    'record:'
]);

$callId = $options['callfile'] ?? '';
$wav    = $options['record']  ?? '';
$logDir = '/var/log/asterisk/call_progress';
if (!$callId || !file_exists($wav)) {
    exit(1);
}
$logFile = $logDir . "/{$callId}.log";

file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] STT_START\n", FILE_APPEND);

// --- simplistic approach: use whisper tiny via shell (require whisper installed) ---
$txt = '';
$WHISPER = '/usr/local/bin/whisper'; // absolute path so Asterisk System() can find it
$MODEL   = 'base';
$MODEL_DIR = '/opt/whisper_models';
$cmd = $WHISPER . ' ' . escapeshellarg($wav) . " --model {$MODEL} --model_dir {$MODEL_DIR} --language ko --fp16 False --output_format txt --output_dir /tmp 2>/dev/null";
exec($cmd);
$outFile = '/tmp/' . pathinfo($wav, PATHINFO_FILENAME) . '.txt';
if (file_exists($outFile)) {
    $txt = trim(file_get_contents($outFile));
    unlink($outFile);
}

$success = false;
if ($txt) {
    $successKeywords = ['수신거부', '완료', '더 이상'];
    $failKeywords    = ['다시 시도', '번호를 확인'];
    foreach ($successKeywords as $kw) {
        if (mb_stripos($txt, $kw) !== false) { $success = true; break; }
    }
    foreach ($failKeywords as $kw) {
        if (mb_stripos($txt, $kw) !== false) { $success = false; break; }
    }
}
$status = $success ? 'success' : 'failed';
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] UNSUB_{$status}\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] STT_DONE\n", FILE_APPEND);

// === 패턴 사용 통계 업데이트 ===
require_once __DIR__ . '/pattern_manager.php';
try {
    $pm = new PatternManager(__DIR__ . '/patterns.json');
    // Dialed 080 번호 AstDB 에서 가져오기
    $recVal = trim(exec("/usr/sbin/asterisk -rx \"database get CallFile {$callId} recnum\""));
    $phoneNumber = preg_replace('/[^0-9]/', '', str_replace('Value:','', $recVal));
    if ($phoneNumber !== '') {
        $pm->recordPatternUsage($phoneNumber, $success);
    }
} catch (Throwable $e) {
    // 로깅만, 실패해도 메인 로직에는 영향 없도록
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] PM_ERROR " . $e->getMessage() . "\n", FILE_APPEND);
}

exit(0); 