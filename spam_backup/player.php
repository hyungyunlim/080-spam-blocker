<?php
$recording_dir = '/var/spool/asterisk/monitor/';

// GET 파라미터로 파일명 받기
$filename = $_GET['file'] ?? '';

// 보안 검사: 파일명에 '..'가 포함되어 상위 디렉토리로 이동하는 것을 방지
if (empty($filename) || strpos($filename, '..') !== false) {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid filename.";
    exit;
}

$full_path = $recording_dir . $filename;

// 파일이 실제로 존재하고, .wav 파일인지 확인
if (file_exists($full_path) && pathinfo($full_path, PATHINFO_EXTENSION) == 'wav') {
    // 브라우저에 오디오 파일임을 알리는 헤더 전송
    header('Content-Type: audio/wav');
    header('Content-Length: ' . filesize($full_path));
    header('Accept-Ranges: bytes');
    
    // 파일 내용 출력 (스트리밍)
    readfile($full_path);
    exit;
} else {
    // 파일이 없으면 404 에러 전송
    header("HTTP/1.1 404 Not Found");
    echo "File not found.";
    exit;
}
?>
