<?php
// 보안 헤더 및 콘텐츠 타입 설정
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function parse_recording_filename($filename) {
    $info = [
        'filename'      => $filename,
        'title'         => 'N/A',
        'call_type'     => 'unknown',
        'timestamp'     => 0,
        'id'            => 'N/A',
        'datetime'      => 'N/A',
        'path'          => '/var/spool/asterisk/monitor/' . $filename
    ];

    $timestamp_str = '';
    $phone_number = '';

    // 패턴 1: 일반 수신거부 (예: 20250610-134303-FROM_SYSTEM-TO_0808895050.wav)
    if (preg_match('/(\d{8}-\d{6}).*?TO_(\d+)/i', $filename, $matches)) {
        $info['call_type']      = 'unsubscribe';
        $timestamp_str          = $matches[1];
        $phone_number   = $matches[2];
        if (preg_match('/-ID_(\d+)/i', $filename, $id_match)) {
            $info['id'] = $id_match[1];
        }
    }
    // 패턴 2: 패턴 학습 (예: 20250611-000841-discovery-0808895050.wav)
    elseif (preg_match('/(\d{8}-\d{6}).*?discovery-([0-9]+)/i', $filename, $matches)) {
        $info['call_type']      = 'discovery';
        $timestamp_str          = $matches[1];
        $phone_number   = $matches[2];
    }
    
    // 타임스탬프 파싱
    if (!empty($timestamp_str) && ($dt = DateTime::createFromFormat('Ymd-His', $timestamp_str))) {
        $info['timestamp'] = $dt->getTimestamp();
        $info['datetime'] = $dt->format('Y-m-d H:i:s');
    } else {
        // 파일명에서 파싱 실패 시, 파일 수정시간을 대체로 사용
        $filepath = '/var/spool/asterisk/monitor/' . $filename;
        if (file_exists($filepath)) {
            $info['timestamp'] = filemtime($filepath);
            $info['datetime'] = date('Y-m-d H:i:s', $info['timestamp']);
        }
    }

    // 항상 파일 mtime 포함 (재생 캐시 무력화용)
    $filepath = '/var/spool/asterisk/monitor/' . $filename;
    $info['file_mtime'] = file_exists($filepath) ? filemtime($filepath) : $info['timestamp'];
    $info['file_size']  = file_exists($filepath) ? filesize($filepath) : 0;

    $info['title'] = $phone_number ?: '알 수 없는 번호';
    if ($info['id'] !== 'N/A') {
        $info['title'] .= ' (ID: ' . $info['id'] . ')';
    }


    return $info;
}

/**
 * 주어진 녹음 파일에 대한 분석 결과를 찾아 반환합니다.
 * @param string $recording_filename 녹음 파일명
 * @param string $call_type 통화 유형 (discovery 또는 unsubscribe)
 * @return array 분석 결과 (status, text)
 */
function get_analysis_result($recording_filename, $call_type = 'unsubscribe', $recordTimestamp = null) {
    require_once __DIR__ . '/pattern_manager.php';

    $base_filename = pathinfo($recording_filename, PATHINFO_FILENAME);

    // 기록 시각이 없으면 파일명에서 파싱
    if ($recordTimestamp === null) {
        if (preg_match('/^(\d{8}-\d{6})/', $recording_filename, $mt)) {
            $dt = DateTime::createFromFormat('Ymd-His', $mt[1]);
            if ($dt) $recordTimestamp = $dt->getTimestamp();
        }
    }

    // 전화번호 추출 (discovery 파일명 기반)
    $phone_in_filename = null;
    if ($call_type === 'discovery' && preg_match('/discovery-([0-9]+)/i', $recording_filename, $m)) {
        $phone_in_filename = $m[1];
    }

    // 패턴 매니저에서 자동 등록 여부 확인
    $patternManager = new PatternManager();
    $patternsData = $patternManager->getPatterns();
    $pattern_registered_auto = false;
    if ($phone_in_filename && isset($patternsData['patterns'][$phone_in_filename])) {
        $pat = $patternsData['patterns'][$phone_in_filename];
        $pattern_registered_auto = isset($pat['auto_generated']) && $pat['auto_generated'];
    }

    // 패턴 탐색 녹음인 경우 pattern_discovery 디렉토리에서 찾기
    if ($call_type === 'discovery') {
        $pattern_dir = __DIR__ . '/pattern_discovery/';

        // 1) 파일 기반 매칭 (same base filename + _pattern.json)
        $base = pathinfo($recording_filename, PATHINFO_FILENAME);
        $file_specific = $pattern_dir . $base . '_pattern.json';
        if (file_exists($file_specific)) {
            $data = json_decode(file_get_contents($file_specific), true);
            if ($data && isset($data['success']) && $data['success']) {
                // 분석 시점이 녹음 생성보다 충분히 이후인지 확인 (5초 기준)
                $analysisTs = isset($data['analysis_time']) ? to_unix_timestamp($data['analysis_time']) : 0;
                if ($recordTimestamp && $analysisTs && $analysisTs <= ($recordTimestamp + 5)) {
                    // 아직 해당 녹음에 대한 분석이 아니므로 무시
                } else {
                    return [
                        'analysis_result' => '성공',
                        'analysis_text' => '패턴 분석 완료 - ' . ($data['pattern']['name'] ?? '패턴 생성됨'),
                        'confidence' => $data['confidence'] ?? null,
                        'transcription' => $data['transcription'] ?? null,
                        'pattern_data' => $data['pattern'] ?? null,
                        'completed_at' => $data['analysis_time'] ?? null,
                        'pattern_registered' => $pattern_registered_auto
                    ];
                }
            }
        }

        // 2) 기존 전화번호 기반 fallback (이전 로직)
        if (is_dir($pattern_dir)) {
            $files = glob($pattern_dir . '*.json');
            $latestData = null;
            $latestTime = 0;
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE || !isset($data['success']) || !$data['success']) continue;
                if (!isset($data['phone_number'])) continue;
                if (preg_match('/discovery-(\d+)/', $recording_filename, $matches)) {
                    $phone_in_filename = $matches[1];
                    if ($data['phone_number'] === $phone_in_filename) {
                        $ts = isset($data['analysis_time']) ? to_unix_timestamp($data['analysis_time']) : filemtime($file);
                        if ($ts > $latestTime) {
                            $latestTime = $ts;
                            $latestData = $data;
                        }
                    }
                }
            }
            if ($latestData) {
                $analysisTs = isset($latestData['analysis_time']) ? to_unix_timestamp($latestData['analysis_time']) : 0;
                if ($recordTimestamp && $analysisTs && $analysisTs <= ($recordTimestamp + 5)) {
                    // 분석 시점이 이전이므로 무시
                } else {
                    return [
                        'analysis_result' => '성공',
                        'analysis_text' => '패턴 분석 완료 - ' . ($latestData['pattern']['name'] ?? '패턴 생성됨'),
                        'confidence' => $latestData['confidence'] ?? null,
                        'transcription' => $latestData['transcription'] ?? null,
                        'pattern_data' => $latestData['pattern'] ?? null,
                        'completed_at' => $latestData['analysis_time'] ?? null,
                        'pattern_registered' => $pattern_registered_auto
                    ];
                }
            }
        }
    }
    
    // 일반 분석 결과 확인 (기존 코드)
    $analysis_dir = __DIR__ . '/analysis_results/';

    // 두 가지 파일명 패턴을 모두 확인
    $possible_filenames = [
        'analysis_' . $base_filename . '.json', // "analysis_FILENAME.json"
        $base_filename . '_analysis.json'      // "FILENAME_analysis.json"
    ];

    $analysis_filepath = null;
    foreach ($possible_filenames as $filename) {
        if (file_exists($analysis_dir . $filename)) {
            $analysis_filepath = $analysis_dir . $filename;
            break;
        }
    }

    if ($analysis_filepath) {
        $content = file_get_contents($analysis_filepath);
        $data = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['analysis'])) {
            // completed_at validation
            $analysisTs = isset($data['timestamp']) ? to_unix_timestamp($data['timestamp']) : 0;
            if ($recordTimestamp && $analysisTs && $analysisTs <= ($recordTimestamp + 5)) {
                // treat as un-analyzed
            } else {
                $status = $data['analysis']['status'] ?? '결과 없음';
                // "success", "failed", "unknown" 등을 한글로 변환
                switch ($status) {
                    case 'success':
                        $status_ko = '성공';
                        break;
                    case 'failed':
                        $status_ko = '실패';
                        break;
                    case 'unknown':
                        $status_ko = '불확실';
                        break;
                    case 'attempted':
                        $status_ko = '시도됨';
                        break;
                    default:
                        $status_ko = $status; // 그 외 다른 상태값은 그대로 사용
                }

                return [
                    'analysis_result' => $status_ko,
                    'analysis_text' => $data['analysis']['reason'] ?? '분석 내용이 없습니다.',
                    'confidence' => $data['analysis']['confidence'] ?? null,
                    'transcription' => $data['transcription'] ?? null,
                    'completed_at' => $data['timestamp'] ?? null,
                    'pattern_registered' => $pattern_registered_auto
                ];
            }
        }
    }
    
    return [
        'analysis_result' => '미분석',
        'analysis_text' => '아직 분석되지 않았습니다.',
        'confidence' => null,
        'transcription' => null,
        'pattern_registered' => $pattern_registered_auto
    ];
}

$recording_dir = '/var/spool/asterisk/monitor/';
$recordings = [];
$lastUpdated = 0; // 최근 수정 시각 추적

// 디렉토리가 존재하는지 확인
if (is_dir($recording_dir)) {
    if ($dh = opendir($recording_dir)) {
        while (($file = readdir($dh)) !== false) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'wav') {
                $recording_info = parse_recording_filename($file);
                // 기존 분석 결과 확인 및 추가 (파일 생성 시점 고려)
                $analysis_data = get_analysis_result($file, $recording_info['call_type'], $recording_info['timestamp']);
                $recording_info = array_merge($recording_info, $analysis_data);
                
                // 최신 수정 시각 계산
                if ($recording_info['timestamp'] > $lastUpdated) {
                    $lastUpdated = $recording_info['timestamp'];
                }
                if ($recording_info['file_mtime'] > $lastUpdated) {
                    $lastUpdated = $recording_info['file_mtime'];
                }
                // 분석 완료 시각도 비교해 최신값 반영
                if (!empty($analysis_data['completed_at'])) {
                    $analysisTs = to_unix_timestamp($analysis_data['completed_at']);

                    if ($analysisTs && $analysisTs > $lastUpdated) {
                        $lastUpdated = $analysisTs;
                    }
                }

                // 자동 분석 필요 여부 (현재 미분석이며 파일 수정 후 10초 경과 및 파일 크기 150KB 이상)
                $inactive = (time() - $recording_info['file_mtime']) > 4; // 최근 4초간 변동 없음
                $longEnough = $recording_info['file_size'] >= 80 * 1024;   // 약 5초 이상 분량
                $recording_info['ready_for_analysis'] = (
                    ($recording_info['analysis_result'] === '미분석') &&
                    $inactive &&
                    $longEnough
                );

                // 상태 변화가 발생할 경우(분석 준비 완료 또는 분석 결과 생성) -> 업데이트 타임스탬프를 현재로 갱신하여 프론트가 DOM을 새로 그리도록 함
                if ($recording_info['ready_for_analysis'] || ($recording_info['analysis_result'] !== '미분석' && $analysis_data['analysis_result'] !== '미분석')) {
                    $lastUpdated = time();
                }

                // 최종 배열에 추가 (ready flag 포함)
                $recordings[] = $recording_info;
            }
        }
        closedir($dh);
    }
}

// 패턴 파일 변경 시점도 변경 감지에 포함
$patternsFile = __DIR__ . '/patterns.json';
if (file_exists($patternsFile)) {
    $patMtime = filemtime($patternsFile);
    if ($patMtime > $lastUpdated) {
        $lastUpdated = $patMtime;
    }
}

// pattern_discovery 결과 디렉토리 내 최신 분석도 확인
$discoveryDir = __DIR__ . '/pattern_discovery/';
if (is_dir($discoveryDir)) {
    $latestJson = array_reduce(glob($discoveryDir . '*.json'), function($carry, $file) {
        $mtime = filemtime($file);
        return ($mtime > $carry) ? $mtime : $carry;
    }, 0);
    if ($latestJson > $lastUpdated) {
        $lastUpdated = $latestJson;
    }
}

// 최신 파일이 위로 오도록 정렬 (타임스탬프 기준)
usort($recordings, function($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});

// 응답 데이터 구조 정리
$response = [
    'success' => true,
    'updated' => $lastUpdated,
    'recordings' => $recordings
];

// JSON 형태로 출력
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * 문자열 또는 숫자 형태의 시간을 안전하게 UNIX 타임스탬프로 변환
 * @param mixed $time
 * @return int|false
 */
function to_unix_timestamp($time) {
    if (empty($time)) return false;
    if (is_numeric($time)) return (int)$time;

    // ISO8601 with optional microseconds (e.g., 2025-06-11T00:08:41 or 2025-06-11T00:08:41.123456)
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?$/', $time)) {
        try {
            $dt = new DateTime($time);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            // fall through
        }
    }

    // 일반적인 'Y-m-d H:i:s' 형태 등은 strtotime 시도
    $ts = strtotime($time);
    return $ts ?: false;
}
?>