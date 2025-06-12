<?php
// 080 수신거부 – 통화 단계별 로그 조회 API
// GET 방식: ?id=<callfile_id>&lines=30
// id : 고유 CALLFILE_ID (uniqid) – 필수
// lines : 반환할 최대 라인 수(기본 50)

header('Content-Type: application/json; charset=utf-8');

$callId = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', $_GET['id']) : '';
if ($callId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing id parameter']);
    exit;
}

$maxLines = isset($_GET['lines']) ? intval($_GET['lines']) : 50;
if ($maxLines <= 0) $maxLines = 50;

$logPath = "/var/log/asterisk/call_progress/{$callId}.log";
if (!file_exists($logPath)) {
    echo json_encode(['success' => false, 'error' => 'log not found']);
    exit;
}

$lines = file($logPath, FILE_IGNORE_NEW_LINES);
$total = count($lines);
$start = max(0, $total - $maxLines);
$recent = array_slice($lines, $start);

echo json_encode([
    'success' => true,
    'total_lines' => $total,
    'returned' => count($recent),
    'lines' => $recent
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?> 