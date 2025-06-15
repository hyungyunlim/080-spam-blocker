<?php
require_once __DIR__ . '/auth.php';

// 인증 확인
if (!is_logged_in()) {
    header("HTTP/1.1 401 Unauthorized");
    echo "Authentication required.";
    exit;
}

// 디버깅을 위한 로그
error_log("Player.php accessed with file: " . ($_GET['file'] ?? 'none'));

$recording_dir = '/var/spool/asterisk/monitor/';

// GET 파라미터로 파일명 받기
$filename = $_GET['file'] ?? '';

// 보안 검사: 파일명 형식 및 경로 탐색 방지
if (empty($filename)) {
    header("HTTP/1.1 400 Bad Request");
    echo "No filename provided.";
    exit;
}

if (strpos($filename, '..') !== false) {
    header("HTTP/1.1 400 Bad Request");
    echo "Path traversal detected.";
    exit;
}

// 더 포용적인 파일명 패턴 (숫자, 문자, 하이픈, 언더스코어, 점만 허용)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.wav$/i', $filename)) {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid filename format: " . htmlspecialchars($filename);
    exit;
}

$full_path = $recording_dir . $filename;

// 파일이 실제로 존재하고, .wav 파일인지 확인
if (file_exists($full_path) && pathinfo($full_path, PATHINFO_EXTENSION) == 'wav') {
    // 브라우저에 오디오 파일임을 알리는 헤더 전송
    header('Content-Type: audio/wav');
    header('Content-Length: ' . filesize($full_path));
    header('Accept-Ranges: bytes');
    
    // 다운로드 시 올바른 파일명 설정
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    // 파일 내용 출력 (스트리밍)
    readfile($full_path);
    exit;
} else {
    // 파일이 없으면 404 에러 전송
    header("HTTP/1.1 404 Not Found");
    error_log("Player.php: File not found: " . $full_path . " (exists: " . (file_exists($full_path) ? 'yes' : 'no') . ")");
    echo "File not found.";
    exit;
}
?>
