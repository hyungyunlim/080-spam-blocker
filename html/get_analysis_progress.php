<?php
/**
 * get_analysis_progress.php
 * 수신거부(일반) 음성 분석 진행 상황을 반환합니다.
 * Expect: GET analysis_id=<analysis_...>
 * Returns JSON: {success, stage, percentage, message, completed, [steps]}
 */

header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------------------
// 1. 파라미터 검증
// -----------------------------------------------------------------------------
if (!isset($_GET['analysis_id']) || empty($_GET['analysis_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing analysis_id parameter.']);
    exit;
}

$analysisId = $_GET['analysis_id'];

if (!preg_match('/^analysis_[a-zA-Z0-9_]+$/', $analysisId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid analysis_id format.']);
    exit;
}

$progressFile = __DIR__ . '/progress/' . $analysisId . '.json';

// 단계별 가중치 (총합 100)
$stepWeights = [
    'starting'       => 5,
    'loading_model'  => 15,
    'transcribing'   => 40,
    'analyzing'      => 25,
    'saving'         => 10,
    'completed'      => 5
];

// -----------------------------------------------------------------------------
// 2. 진행 파일 존재 여부 확인
// -----------------------------------------------------------------------------
if (file_exists($progressFile)) {
    $data = json_decode(file_get_contents($progressFile), true);
    if (!$data) {
        // 파일이 쓰는 중이어서 JSON이 완성되지 않았을 가능성 – 잠시 후 재시도하도록 응답
        echo json_encode([
            'success' => true,
            'stage' => 'queued',
            'percentage' => 0,
            'message' => 'Progress file not ready',
            'prevent_refresh' => true
        ]);
        exit;
    }

    $stage = $data['stage'] ?? 'unknown';
    $percentage = $data['percentage'] ?? 0;
    $message = $data['message'] ?? '';

    // steps 필드가 없으면 stage 기반으로 rough percentage 계산
    if (!isset($data['steps'])) {
        // stage 인덱스 순회로 누적 비율 계산
        $cumulative = 0;
        foreach ($stepWeights as $k => $w) {
            if ($k === $stage) {
                $percentage = max($percentage, $cumulative);
                break;
            }
            $cumulative += $w;
        }
    }

    echo json_encode([
        'success'    => true,
        'stage'      => $stage,
        'percentage' => $percentage,
        'message'    => $message,
        'updated_at' => $data['updated_at'] ?? time(),
        'completed'  => ($stage === 'completed')
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 3. progress 파일이 없으면 완료로 간주
// -----------------------------------------------------------------------------
// 분석 결과 파일 유무에 따라 성공/실패 판단은 클라이언트에서 get_recordings 로 재조회하며 처리.

echo json_encode([
    'success'    => true,
    'stage'      => 'completed',
    'percentage' => 100,
    'message'    => '분석이 완료되었습니다.',
    'completed'  => true
]);
?> 