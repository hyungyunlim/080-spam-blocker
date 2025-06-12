<?php
/**
 * analyze_pattern_recording.php
 * 패턴 탐색(discovery) 녹음 파일을 분석하기 위한 비동기 API
 * - Whisper + DTMF 패턴 분석 Python 스크립트를 백그라운드로 실행
 * - 진행 상황을 progress/pattern_<id>.json 파일로 기록 (Python 스크립트도 계속 업데이트할 수 있음)
 * - 완료 결과는 pattern_discovery/<id>.json 으로 저장되며 get_pattern_analysis_progress.php 가 poll링
 */

header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------------------
// 1. 입력 검증
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['file']) || empty($_POST['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing file parameter.']);
    exit;
}

$recordingFile = $_POST['file'];

// 디렉터리 트래버설 방지
if (strpos($recordingFile, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file path.']);
    exit;
}

// 녹음 파일이 상대 경로(파일명)로 들어오면 절대경로로 보정
if (strpos($recordingFile, '/') === false) {
    $recordingFile = '/var/spool/asterisk/monitor/' . $recordingFile;
}

if (!file_exists($recordingFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Recording file not found: ' . basename($recordingFile)]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. 분석 ID 및 경로 설정
// -----------------------------------------------------------------------------
$analysisId   = 'pattern_' . uniqid() . '_' . time();
$progressDir  = __DIR__ . '/progress/';
$resultDir    = __DIR__ . '/pattern_discovery/';

if (!is_dir($progressDir)) {
    @mkdir($progressDir, 0777, true);
}
if (!is_dir($resultDir)) {
    @mkdir($resultDir, 0777, true);
}

// 디렉토리가 이미 존재하지만 웹서버가 쓰기 불가할 수 있으므로 퍼미션 보강
@chmod($progressDir, 0777);
@chmod($resultDir, 0777);

$progressFile = $progressDir . $analysisId . '.json';
$resultFile   = $resultDir   . $analysisId . '.json';

// 전화번호 추출 (파일명 내 discovery-<phone>)
$phoneNumber = '';
if (preg_match('/discovery-(\d+)/', basename($recordingFile), $m)) {
    $phoneNumber = $m[1];
}

// -----------------------------------------------------------------------------
// 3. 초기 progress 파일 생성
// -----------------------------------------------------------------------------
file_put_contents($progressFile, json_encode([
    'stage'       => 'queued',
    'percentage'  => 0,
    'message'     => '분석 대기중...',
    'analysis_id' => $analysisId,
    'updated_at'  => time()
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// -----------------------------------------------------------------------------
// 4. Python 스크립트 실행 (백그라운드)
// -----------------------------------------------------------------------------
$pythonPath  = '/usr/bin/python3';
if (!file_exists($pythonPath)) {
    $pythonPath = 'python3';
}

// 새 래퍼 스크립트 (실시간 progress 지원) 사용
$scriptPath  = __DIR__ . '/pattern_analyzer_runner.py';

// 래퍼가 없으면 기존 스크립트 사용 (progress 미지원)
if (!file_exists($scriptPath)) {
    $scriptPath = '/home/linux/080-spam-blocker/advanced_pattern_analyzer.py';
}

$cmd = sprintf(
    '%s %s %s %s %s --progress_file %s > %s 2>&1 &',
    escapeshellcmd($pythonPath),                    // python3
    escapeshellarg($scriptPath),                    // script path
    escapeshellarg($recordingFile),                 // arg1: audio file
    escapeshellarg($resultFile),                    // arg2: output json
    escapeshellarg($phoneNumber ?: 'unknown'),      // arg3: phone number
    escapeshellarg($progressFile),                  // progress json
    escapeshellarg(__DIR__ . '/logs/pattern_analyzer_' . date('Y-m-d') . '.log')
);

// 디버그 로그 저장
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0775, true);
}
error_log('[analyze_pattern_recording] CMD: ' . $cmd);

shell_exec($cmd);

// progress-file 자체는 분석기가 사용하지 않지만, poll 간 갭을 줄이기 위해 잠정적으로 5초 후 'starting' 단계로 업데이트
register_shutdown_function(function() use ($progressFile){
    if (file_exists($progressFile)) {
        $data = json_decode(file_get_contents($progressFile), true);
        if ($data) {
            $data['stage'] = 'starting';
            $data['message'] = 'Python 스크립트 실행 중...';
            $data['updated_at'] = time();
            file_put_contents($progressFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
});

// -----------------------------------------------------------------------------
// 5. 응답 반환
// -----------------------------------------------------------------------------

echo json_encode([
    'success'      => true,
    'analysis_id'  => $analysisId,
    'phone_number' => $phoneNumber,
    'message'      => '패턴 분석이 시작되었습니다.'
]);
?> 