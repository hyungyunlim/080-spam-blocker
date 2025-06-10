<?php
/**
 * 080 수신거부 녹음 파일 분석 API
 * - 녹음 파일을 Python 음성 분석기로 전달
 * - STT 결과와 수신거부 성공 여부를 JSON으로 반환
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/recording_info_extractor.php';

/**
 * 분석 완료 시 SMS 알림 전송
 */
function sendAnalysisCompleteSMS($filename, $analysisData) {
    try {
        $extractor = new RecordingInfoExtractor();
        $info = $extractor->extractAllInfo($filename);
        
        // 필요한 정보가 모두 있는지 확인
        if (empty($info['notification_phone']) || empty($info['target_number'])) {
            error_log("SMS notification skipped for {$filename}: missing notification info");
            return;
        }
        
        $analysisResult = $analysisData['analysis']['status'] ?? 'uncertain';
        $confidence = $analysisData['analysis']['confidence'] ?? 0;
        
        // SMS 전송 스크립트 실행
        $command = "/usr/bin/php /var/www/html/spam/send_analysis_sms.php " .
                   escapeshellarg($info['notification_phone']) . " " .
                   escapeshellarg($info['target_number']) . " " .
                   escapeshellarg($info['identification_number'] ?? 'Unknown') . " " .
                   escapeshellarg($analysisResult) . " " .
                   escapeshellarg($confidence) . " " .
                   escapeshellarg($filename) . " > /dev/null 2>&1 &";
        
        exec($command);
        
        error_log("SMS notification sent for {$filename} to {$info['notification_phone']}");
        
    } catch (Exception $e) {
        error_log("Error sending SMS notification for {$filename}: " . $e->getMessage());
    }
}

function analyzeRecording($filename) {
    $recordingPath = '/var/spool/asterisk/monitor/' . $filename;
    $pythonScript = '/home/linux/080-spam-blocker/voice_analyzer.py';
    $pythonEnv = '/home/linux/080-spam-blocker/venv/bin/python';
    $outputDir = '/var/www/html/spam/analysis_results/';
    
    // 출력 디렉토리 생성
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // 녹음 파일 존재 확인
    if (!file_exists($recordingPath)) {
        return [
            'success' => false,
            'error' => '녹음 파일을 찾을 수 없습니다: ' . $filename
        ];
    }
    
    // 결과 파일 경로
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $resultFile = $outputDir . $baseName . '_analysis.json';
    
    // Python 스크립트 직접 실행 
    $scriptPath = '/home/linux/080-spam-blocker/simple_analyzer.py';
    $command = 'python3 ' . escapeshellarg($scriptPath) . ' ' . 
               escapeshellarg($recordingPath) . ' ' . 
               escapeshellarg($resultFile) . ' small 2>&1';
    
    $output = shell_exec($command);
    
    // 결과 파일 읽기
    if (file_exists($resultFile)) {
        $analysisData = json_decode(file_get_contents($resultFile), true);
        
        // 분석 완료 시 SMS 알림 전송
        sendAnalysisCompleteSMS($filename, $analysisData);
        
        return [
            'success' => true,
            'filename' => $filename,
            'analysis' => $analysisData,
            'output' => $output
        ];
    } else {
        return [
            'success' => false,
            'error' => '분석 실패',
            'output' => $output,
            'command' => $command
        ];
    }
}

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode([
            'success' => false,
            'error' => '파일명이 제공되지 않았습니다.'
        ]);
        exit;
    }
    
    $result = analyzeRecording($filename);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// GET 요청 처리 (기존 분석 결과 조회)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filename = $_GET['filename'] ?? '';
    $list = $_GET['list'] ?? '';
    
    // 분석 결과 목록 조회
    if ($list === 'true') {
        $analysisDir = '/var/www/html/spam/analysis_results/';
        $results = [];
        
        if (is_dir($analysisDir)) {
            $files = glob($analysisDir . '*_analysis.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $results[] = [
                        'filename' => basename($data['file_path']),
                        'timestamp' => $data['timestamp'],
                        'status' => $data['analysis']['status'],
                        'confidence' => $data['analysis']['confidence']
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // 특정 파일 분석 결과 조회
    if (!empty($filename)) {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $resultFile = '/var/www/html/spam/analysis_results/' . $baseName . '_analysis.json';
        
        if (file_exists($resultFile)) {
            $data = json_decode(file_get_contents($resultFile), true);
            echo json_encode([
                'success' => true,
                'analysis' => $data
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                'success' => false,
                'error' => '분석 결과를 찾을 수 없습니다.'
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
        'POST' => 'filename 파라미터와 함께 POST 요청으로 분석 시작',
        'GET' => 'filename 파라미터로 분석 결과 조회, list=true로 전체 목록 조회'
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?> 