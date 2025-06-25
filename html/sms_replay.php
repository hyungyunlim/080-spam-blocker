<?php
/**
 * sms_replay.php  –  과거 SMS 로그(sms.txt)를 재처리하여 자동 수신거부 통화를 트리거한다.
 *
 * 사용 예시:
 *   php sms_replay.php                 # 전체 재생
 *   php sms_replay.php --since="2025-06-13"   # 해당 날짜 이후만
 *   php sms_replay.php --dry-run       # 실제 호출 대신 명령만 출력
 *
 * 옵션
 *   --file=path      sms.txt 경로 (기본: /var/log/asterisk/sms.txt)
 *   --since=date     YYYY-MM-DD 또는 strtotime 파싱 가능한 문자열
 *   --dry-run        처리 대신 실행 명령만 출력
 */

$options = getopt('', [
    'file::',
    'since::',
    'dry-run'
]);

$logFile = $options['file'] ?? '/var/log/asterisk/sms.txt';
$sinceFilter = null;
if (isset($options['since'])) {
    $sinceFilter = strtotime($options['since']);
    if ($sinceFilter === false) {
        fwrite(STDERR, "--since 인자 날짜 파싱 실패: {$options['since']}\n");
        exit(1);
    }
}
$dryRun = isset($options['dry-run']);

if (!file_exists($logFile)) {
    fwrite(STDERR, "sms.txt 파일을 찾을 수 없습니다: {$logFile}\n");
    exit(1);
}

$fh = fopen($logFile, 'r');
if (!$fh) {
    fwrite(STDERR, "sms.txt 파일을 열 수 없습니다.\n");
    exit(1);
}

$messages = [];
$current = null;
$bodyLines = [];

while (($line = fgets($fh)) !== false) {
    if (preg_match('/^SMS From ([0-9]+) on (.+)$/', trim($line), $m)) {
        // 이전 메시지 저장
        if ($current !== null) {
            $current['body'] = trim(implode("", $bodyLines));
            $messages[] = $current;
        }
        $bodyLines = [];
        $current = [
            'caller' => $m[1],
            'timestamp' => strtotime($m[2]),
            'body' => ''
        ];
        continue;
    }
    $bodyLines[] = $line;
}
// 마지막 메시지 추가
if ($current !== null) {
    $current['body'] = trim(implode("", $bodyLines));
    $messages[] = $current;
}

fclose($fh);

$processed = 0;
foreach ($messages as $msg) {
    if ($sinceFilter && $msg['timestamp'] && $msg['timestamp'] < $sinceFilter) {
        continue; // 필터보다 이전
    }
    if ($msg['body'] === '') continue; // 빈 본문

    // sms_auto_processor 가 080 번호를 찾지 못하면 자체적으로 skip
    $base64 = base64_encode($msg['body']);
    $cmd = 'php ' . __DIR__ . '/sms_auto_processor.php --caller=' . escapeshellarg($msg['caller']) . ' --msg_base64=' . escapeshellarg($base64) . ' > /dev/null 2>&1 &';

    if ($dryRun) {
        echo "DRY: {$cmd}\n";
    } else {
        exec($cmd);
    }
    $processed++;
}

echo "총 {$processed}건 처리 완료" . ($dryRun ? " (dry-run)" : "") . "\n";
?> 