<?php
/**
 * 패턴 분석 진행 상황 확인 및 결과 저장 API
 */

header('Content-Type: application/json; charset=utf-8');

// GET 파라미터 확인
if (!isset($_GET['analysis_id']) || empty($_GET['analysis_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing analysis_id parameter.']));
}

$analysisId = $_GET['analysis_id'];

// 보안 검사
if (!preg_match('/^pattern_[a-zA-Z0-9_]+$/', $analysisId)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid analysis_id format.']));
}

$progressFile = __DIR__ . '/progress/' . $analysisId . '.json';
$resultFile = __DIR__ . '/pattern_discovery/' . $analysisId . '.json';

// 결과 파일이 있는지 먼저 확인 (분석 완료)
if (file_exists($resultFile)) {
    // 결과 파일 읽기
    $resultData = json_decode(file_get_contents($resultFile), true);
    
    if ($resultData && $resultData['success']) {
        // pattern_manager.php에 패턴 저장
        require_once __DIR__ . '/pattern_manager.php';
        
        $patternManager = new PatternManager();
        
        // 디버그 로깅
        error_log("Pattern analysis completed for phone: " . $resultData['phone_number']);
        error_log("Analysis result: " . json_encode($resultData, JSON_UNESCAPED_UNICODE));
        
        // 패턴 데이터 준비
        $patternData = [
            'name' => $resultData['pattern']['name'] ?? '자동 생성 패턴',
            'description' => $resultData['pattern']['description'] ?? '패턴 분석을 통해 자동 생성된 패턴',
            'initial_wait' => $resultData['pattern']['initial_wait'] ?? 3,
            'dtmf_timing' => $resultData['pattern']['dtmf_timing'] ?? 6,
            'dtmf_pattern' => $resultData['pattern']['dtmf_pattern'] ?? '{ID}#',
            'confirmation_wait' => $resultData['pattern']['confirmation_wait'] ?? 2,
            'confirmation_dtmf' => $resultData['pattern']['confirmation_dtmf'] ?? '1',
            'total_duration' => $resultData['pattern']['total_duration'] ?? 30,
            'confirm_delay' => $resultData['pattern']['confirm_delay'] ?? 2,
            'confirm_repeat' => $resultData['pattern']['confirm_repeat'] ?? 3,
            'pattern_type' => $resultData['pattern']['pattern_type'] ?? 'two_step',
            'auto_supported' => $resultData['pattern']['auto_supported'] ?? true,
            'confidence' => $resultData['confidence'] ?? 0,
            'transcription' => $resultData['transcription'] ?? '',
            'analysis_time' => $resultData['analysis_time'] ?? date('Y-m-d H:i:s'),
            'auto_generated' => true,
            'registered_via' => 'auto',
            'needs_verification' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'label' => '자동 생성'  // 라벨 추가
        ];
        
        // 패턴 저장
        try {
            error_log("Attempting to save pattern for phone: " . $resultData['phone_number']);
            $saveResult = $patternManager->updatePattern($resultData['phone_number'], $patternData);
            error_log("Pattern save result: " . ($saveResult ? "success" : "failed"));
            
            // 진행 상황 파일 및 결과 파일 처리 (루프 방지)
            @unlink($progressFile);
            // 동일 결과가 반복 저장되지 않도록 결과 파일을 .done 확장자로 변경
            $archived = $resultFile . '.done';
            @rename($resultFile, $archived);
            
            echo json_encode([
                'success' => true,
                'completed' => true,
                'stage' => 'completed',
                'percentage' => 100,
                'message' => '패턴 분석이 완료되었습니다.',
                'result' => $resultData,
                'pattern_saved' => $saveResult,
                'pattern_data' => $patternData  // 디버깅을 위해 패턴 데이터도 포함
            ]);
            exit;
        } catch (Exception $e) {
            error_log("Pattern save error: " . $e->getMessage());
            error_log("Pattern data: " . json_encode($patternData, JSON_UNESCAPED_UNICODE));
            echo json_encode([
                'success' => false,
                'completed' => true,
                'message' => '패턴 저장 중 오류가 발생했습니다: ' . $e->getMessage(),
                'pattern_data' => $patternData  // 디버깅을 위해 패턴 데이터도 포함
            ]);
            exit;
        }
    } else {
        error_log("Invalid or failed analysis result: " . json_encode($resultData, JSON_UNESCAPED_UNICODE));
    }
}

// 진행 상황 파일이 존재하는지 확인
if (file_exists($progressFile)) {
    $progressData = json_decode(file_get_contents($progressFile), true);
    
    if ($progressData) {
        // 디버그 로깅
        error_log("Progress check for analysis_id: " . $analysisId);
        error_log("Progress data: " . json_encode($progressData, JSON_UNESCAPED_UNICODE));
        
        // 분석이 진행 중인 경우
        if (isset($progressData['stage']) && in_array($progressData['stage'], ['analyzing', 'queued'])) {
            // 진행률 계산
            $totalProgress = 0;
            if (isset($progressData['steps'])) {
                $stepWeights = [
                    'audio_processing' => 30,
                    'pattern_detection' => 30,
                    'pattern_analysis' => 30,
                    'saving' => 10
                ];
                
                foreach ($progressData['steps'] as $step => $progress) {
                    $totalProgress += ($progress * $stepWeights[$step] / 100);
                }
            }
            
            $progressData['percentage'] = min(99, round($totalProgress));
            
            // 진행 상태 메시지 업데이트
            if ($progressData['percentage'] < 30) {
                $progressData['message'] = '오디오 처리 중...';
            } elseif ($progressData['percentage'] < 60) {
                $progressData['message'] = '패턴 감지 중...';
            } elseif ($progressData['percentage'] < 90) {
                $progressData['message'] = '패턴 분석 중...';
            } else {
                $progressData['message'] = '결과 저장 중...';
            }
            
            error_log("Updated progress: " . json_encode($progressData, JSON_UNESCAPED_UNICODE));
            $progressData['success'] = true;
            echo json_encode($progressData);
            exit;
        }
        $progressData['success'] = true;
        echo json_encode($progressData);
        exit;
    } else {
        error_log("Invalid progress data in file: " . $progressFile);
    }
} else {
    error_log("Progress file not found: " . $progressFile);
}

// 진행 상황 파일이 없으면 완료된 것으로 간주
echo json_encode([
    'success' => true,
    'completed' => true,
    'stage' => 'completed',
    'percentage' => 100,
    'message' => '분석이 완료되었습니다.'
]);
?> 