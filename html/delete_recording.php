<?php
// delete_recording.php
// 080 수신거부 자동화 시스템 – 녹음 및 분석 결과 삭제 엔드포인트
// POST file=<path or filename>&type=<unsubscribe|discovery>

header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------------------
// 1. 기본 검증
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method']]);
    exit;
}

$recordingParam = $_POST['file'] ?? '';
$callType       = $_POST['type'] ?? 'unsubscribe';
$errors         = [];
$operationOk    = true;

if (empty($recordingParam)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing file parameter']]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. 경로 처리 및 보안 검사
// -----------------------------------------------------------------------------
$recordingDir = '/var/spool/asterisk/monitor/';

// 인자로 전체 경로가 전달될 수도 있고, 파일명만 전달될 수도 있음
if (strpos($recordingParam, '/') === false) {
    // 파일명만 넘어온 경우 디렉토리를 붙인다
    $recordingPath = $recordingDir . $recordingParam;
} else {
    $recordingPath = $recordingParam;
}

$recordingFilename = basename($recordingPath); // 디렉토리 제거, 최종 파일명

// 디렉터리 트래버설 방지
if (strpos($recordingFilename, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Invalid file path']]);
    exit;
}

// -----------------------------------------------------------------------------
// 3. 녹음 파일 삭제
// -----------------------------------------------------------------------------
$recordingFullPath = $recordingDir . $recordingFilename;
if (file_exists($recordingFullPath)) {
    if (!@unlink($recordingFullPath)) {
        $operationOk = false;
        $errors[]    = '녹음 파일 삭제 실패: ' . $recordingFilename;
    }
}

// -----------------------------------------------------------------------------
// 4. 분석 결과 및 진행 상황 파일 삭제
// -----------------------------------------------------------------------------
$baseName     = pathinfo($recordingFilename, PATHINFO_FILENAME);
$analysisDir  = __DIR__ . '/analysis_results/';
$progressDir  = __DIR__ . '/progress/';
$patternDir   = __DIR__ . '/pattern_discovery/';

// (1) 분석 결과 파일
$possibleAnalysis = [
    $analysisDir . 'analysis_' . $baseName . '.json',
    $analysisDir . $baseName . '_analysis.json',
];
foreach ($possibleAnalysis as $file) {
    if (file_exists($file) && !@unlink($file)) {
        $operationOk = false;
        $errors[]    = '분석 결과 삭제 실패: ' . basename($file);
    }
}

// (2) 진행 상황 파일 – analysis_ 또는 pattern_ 접두사 사용
$progressFiles = glob($progressDir . '*');
if ($progressFiles) {
    foreach ($progressFiles as $pf) {
        // 파일 내용(analysis_id)와 baseName이 직접적으로 연결되지는 않지만,
        // 파일명 안에 baseName이 포함되는 경우에 한해 삭제 시도
        if (strpos($pf, $baseName) !== false) {
            if (!@unlink($pf)) {
                $operationOk = false;
                $errors[]    = '진행 상황 파일 삭제 실패: ' . basename($pf);
            }
        }
    }
}

// (3) 패턴 분석 관련 파일 (discovery 전용)
if ($callType === 'discovery') {
    // 패턴 결과 파일 – 두 가지 네이밍 케이스 지원
    $patternFiles = array_merge(
        glob($patternDir . $baseName . '_pattern.json'),
        glob($patternDir . '*_' . $baseName . '.json'),
        glob($patternDir . 'pattern_*' . $baseName . '*.json')
    );
    foreach ($patternFiles as $pf) {
        if (file_exists($pf) && !@unlink($pf)) {
            $operationOk = false;
            $errors[]    = '패턴 결과 삭제 실패: ' . basename($pf);
        }
    }
}

// -----------------------------------------------------------------------------
// 5. 응답 반환
// -----------------------------------------------------------------------------
if (!$operationOk) {
    http_response_code(500);
}

echo json_encode([
    'success' => $operationOk,
    'errors'  => $errors
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?> 