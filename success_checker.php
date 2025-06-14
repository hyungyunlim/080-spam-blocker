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
$data = [];

file_put_contents($logFile, date('Y-m-d H:i:s') . " [{$callId}] STT_START\n", FILE_APPEND);

$analysisDir = __DIR__ . '/analysis_results';
if (!is_dir($analysisDir)) {
    @mkdir($analysisDir, 0775, true);
}

$python = '/usr/bin/python3';
$analyzer = __DIR__ . '/simple_analyzer.py';
$cmd = sprintf('%s %s --file %s --output_dir %s --model small 2>/dev/null',
    escapeshellcmd($python),
    escapeshellarg($analyzer),
    escapeshellarg($wav),
    escapeshellarg($analysisDir)
);

// 실행 (동기) – 녹음 길이가 이미 30초 내외라 STT time 10~15s 예상
exec($cmd);

$base = pathinfo($wav, PATHINFO_FILENAME);
$jsonFile = $analysisDir . '/analysis_' . $base . '.json';

$success = false;
if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);
    if ($data && isset($data['analysis']['status'])) {
        $status = $data['analysis']['status'];
        if ($status === 'success') {
            $success = true;
        } else {
            $success = false;
        }
        // transcription 내용도 로그 목적을 위해 재사용할 수 있음
        $txt = $data['transcription'] ?? '';
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

// === SMS 알림 전송 ===
try {
    // 알림 연락처, 080번호, 식별번호 추출
    $notifyVal = trim(exec("/usr/sbin/asterisk -rx \"database get CallFile {$callId} notification_phone\""));
    $notifyPhone = preg_replace('/[^0-9]/','', str_replace('Value:','',$notifyVal));
    $idVal = trim(exec("/usr/sbin/asterisk -rx \"database get CallFile {$callId} identification_number\""));
    $identNumber = preg_replace('/[^0-9]/','', str_replace('Value:','',$idVal));

    if($notifyPhone !== '' && $phoneNumber !== ''){
        require_once __DIR__ . '/sms_sender.php';
        $smsSender = new SMSSender();
        $confidence = isset($data['analysis']['confidence']) ? (int)$data['analysis']['confidence'] : 0;
        $recFileName = basename($wav);
        $smsSender->sendAnalysisCompleteNotification($notifyPhone, $phoneNumber, $identNumber, $status, $confidence, $recFileName);
    }
} catch(Throwable $e){
    file_put_contents($logFile, date('Y-m-d H:i:s')." [{$callId}] SMS_ERROR " . $e->getMessage() . "\n", FILE_APPEND);
}

exit(0); 