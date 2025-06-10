<?php
// 보안 헤더 및 콘텐츠 타입 설정
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$recording_dir = '/var/spool/asterisk/monitor/';
$files = [];

// 디렉토리가 존재하는지 확인
if (is_dir($recording_dir)) {
    // 디렉토리 핸들 열기
    if ($dh = opendir($recording_dir)) {
        // 디렉토리의 파일들을 반복해서 읽기
        while (($file = readdir($dh)) !== false) {
            // .wav 파일만 필터링
            if (pathinfo($file, PATHINFO_EXTENSION) == 'wav') {
                $files[] = $file;
            }
        }
        closedir($dh);
    }
}

// 최신 파일이 위로 오도록 정렬
rsort($files);

// JSON 형태로 출력
echo json_encode($files);
?>
