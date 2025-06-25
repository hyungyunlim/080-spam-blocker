<?php
/**
 * 비동기 음성 분석 API with Progress Tracking
 * - 음성 분석을 백그라운드에서 실행하고 진행 상황을 실시간으로 추적
 * - Server-Sent Events로 클라이언트에게 진행 상황 전송
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 진행 상황 추적을 위한 디렉토리
$progressDir = '/var/www/html/progress/';
$resultDir = '/var/www/html/analysis_results/';

// 디렉토리 생성
if (!is_dir($progressDir)) {
    mkdir($progressDir, 0755, true);
}
if (!is_dir($resultDir)) {
    mkdir($resultDir, 0755, true);
}

function generateJobId($filename) {
    return md5($filename . time());
}

function updateProgress($jobId, $stage, $progress, $message = '') {
    global $progressDir;
    
    $progressData = [
        'job_id' => $jobId,
        'stage' => $stage,
        'progress' => $progress,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'updated_at' => time()
    ];
    
    file_put_contents($progressDir . $jobId . '.json', json_encode($progressData, JSON_UNESCAPED_UNICODE));
}

function startAnalysis($filename, $jobId) {
    global $progressDir, $resultDir;
    
    $recordingPath = '/var/spool/asterisk/monitor/' . $filename;
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $resultFile = $resultDir . $baseName . '_analysis.json';
    $progressFile = $progressDir . $jobId . '.json';
    
    updateProgress($jobId, 'starting', 0, '분석 준비 중...');
    
    // 녹음 파일 존재 확인
    if (!file_exists($recordingPath)) {
        updateProgress($jobId, 'error', 0, '녹음 파일을 찾을 수 없습니다: ' . $filename);
        return false;
    }
    
    updateProgress($jobId, 'file_check', 10, '파일 확인 완료');
    
    // Python 스크립트 경로 – 통합된 runner 사용
    $scriptPath = '/var/www/html/simple_analyzer_runner.py';
    
    // 백그라운드에서 Python 스크립트 실행
    $command = sprintf(
        'python3 %s %s %s small --progress_file %s > /dev/null 2>&1 &',
        escapeshellcmd($scriptPath),
        escapeshellarg($recordingPath),
        escapeshellarg($resultFile),
        escapeshellarg($progressFile)
    );
    
    updateProgress($jobId, 'processing', 15, 'STT 분석 시작...');
    shell_exec($command);
    
    return true;
}

// POST 요청: 새로운 분석 시작
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode([
            'success' => false,
            'error' => '파일명이 제공되지 않았습니다.'
        ]);
        exit;
    }
    
    $jobId = generateJobId($filename);
    
    if (startAnalysis($filename, $jobId)) {
        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'filename' => $filename,
            'message' => '분석이 시작되었습니다.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '분석 시작에 실패했습니다.'
        ]);
    }
    exit;
}

// GET 요청: 진행 상황 조회
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jobId = $_GET['job_id'] ?? '';
    $list = $_GET['list'] ?? '';
    
    // 진행 중인 작업 목록
    if ($list === 'true') {
        $progressFiles = glob($progressDir . '*.json');
        $jobs = [];
        
        foreach ($progressFiles as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && (time() - $data['updated_at']) < 3600) { // 1시간 이내
                $jobs[] = $data;
            }
        }
        
        echo json_encode([
            'success' => true,
            'jobs' => $jobs
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 특정 작업 진행 상황
    if (!empty($jobId)) {
        $progressFile = $progressDir . $jobId . '.json';
        
        if (file_exists($progressFile)) {
            $data = json_decode(file_get_contents($progressFile), true);
            echo json_encode([
                'success' => true,
                'progress' => $data
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'error' => '작업을 찾을 수 없습니다.'
            ]);
        }
        exit;
    }
}

// 사용법 안내
echo json_encode([
    'success' => false,
    'error' => '잘못된 요청',
    'usage' => [
        'POST' => 'filename 파라미터로 비동기 분석 시작 (job_id 반환)',
        'GET ?job_id=xxx' => '특정 작업 진행 상황 조회',
        'GET ?list=true' => '진행 중인 모든 작업 목록'
    ]
], JSON_UNESCAPED_UNICODE);
?>
