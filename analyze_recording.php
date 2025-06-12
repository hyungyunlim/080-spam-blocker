<?php
/**
 * 080 수신거부 녹음 파일 분석 API
 * - Python 분석 스크립트를 백그라운드에서 실행
 * - 실시간 진행 상황 추적 지원
 */

header('Content-Type: application/json; charset=utf-8');

// 에러 로깅 설정
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/analyze_recording_error.log');
error_reporting(E_ALL);

// 로그 디렉토리 생성
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0775, true);
}

// POST 요청인지, file 파라미터가 있는지 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['file']) || empty($_POST['file'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid or missing file parameter.']));
}

$recordingFile = $_POST['file'];

// 보안 검사: 파일 경로 조작 방지
if (strpos($recordingFile, '..') !== false) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid file path.']));
}

// 파일 존재 여부 확인
if (!is_file($recordingFile)) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Recording file not found: ' . basename($recordingFile)]));
}

// 분석 결과를 저장할 디렉토리
$analysisDir = __DIR__ . '/analysis_results';
if (!is_dir($analysisDir)) {
    if (!@mkdir($analysisDir, 0775, true)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to create analysis directory.']));
    }
}

// 진행 상황을 저장할 디렉토리
$progressDir = __DIR__ . '/progress';
if (!is_dir($progressDir)) {
    if (!@mkdir($progressDir, 0775, true)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to create progress directory.']));
    }
}

// Python 스크립트 경로 확인
$analyzerScript = __DIR__ . '/simple_analyzer.py';
if (!file_exists($analyzerScript)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Analysis script not found.']));
}

// Python 실행 파일 확인
$pythonPath = '/usr/bin/python3';
if (!file_exists($pythonPath)) {
    // 대체 경로 시도
    $pythonPath = 'python3';
}

// 분석 ID 생성 (진행 상황 추적용)
$analysisId = 'analysis_' . uniqid() . '_' . time();
$progressFile = $progressDir . '/' . $analysisId . '.json';

// 초기 진행 상황 파일 생성
file_put_contents($progressFile, json_encode([
    'stage' => 'queued',
    'percentage' => 0,
    'message' => '분석 대기중...',
    'timestamp' => date('c'),
    'updated_at' => time()
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$modelSize = 'small'; // small 모델 사용

// Python 스크립트 실행 명령어 구성
$command = sprintf(
    '%s %s --file %s --output_dir %s --model %s --progress_file %s > %s 2>&1 &',
    escapeshellcmd($pythonPath),
    escapeshellarg($analyzerScript),
    escapeshellarg($recordingFile),
    escapeshellarg($analysisDir),
    escapeshellarg($modelSize),
    escapeshellarg($progressFile),
    escapeshellarg(__DIR__ . '/logs/analyzer_' . date('Y-m-d') . '.log')
);

// 디버그 로그
error_log("Executing command: " . $command);

// 명령어 실행
$output = shell_exec($command);
$lastLine = exec($command, $fullOutput, $returnCode);

// 실행 결과 로깅
error_log("Command return code: " . $returnCode);
error_log("Command output: " . implode("\n", $fullOutput));

// 응답 반환
echo json_encode([
    'success' => true,
    'message' => '분석이 시작되었습니다.',
    'analysis_id' => $analysisId,
    'debug' => [
        'command' => $command,
        'return_code' => $returnCode,
        'file' => basename($recordingFile)
    ]
]);
?> 