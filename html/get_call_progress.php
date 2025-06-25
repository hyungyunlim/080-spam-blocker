<?php
/**
 * 녹음 진행 상황 확인 API
 * GET file=FILENAME (basename only)
 * Returns JSON {exists:bool, size:int(bytes), duration_est:float(seconds), finished:bool}
 */
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing file parameter']);
    exit;
}

$filename = basename($_GET['file']);
$path = '/var/spool/asterisk/monitor/' . $filename;

if (!file_exists($path)) {
    echo json_encode(['exists' => false]);
    exit;
}

$size = filesize($path);
$mtime = filemtime($path);

// 8kHz 16-bit mono PCM -> 16KB per second (approx). For wav header 44 bytes, ignore.
$durationEst = round($size / 16000, 1);

// 완료 판단: 최근 4초간 파일이 갱신되지 않았고, 최소 5초(≈80KB) 이상 녹음되었으면 완료로 간주
$finished = ((time() - $mtime) > 4) && ($size >= 80 * 1024);

echo json_encode([
    'exists' => true,
    'size' => $size,
    'duration_est' => $durationEst,
    'finished' => $finished,
    'mtime' => $mtime
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?> 