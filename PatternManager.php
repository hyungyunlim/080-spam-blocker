<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/pattern_manager.php';
    $pm = new PatternManager();
    $action = $_POST['action'];
    $number = preg_replace('/[^0-9]/','', $_POST['number'] ?? '');

    if ($action === 'add' || $action === 'edit') {
        // Assemble pattern data safely
        $pattern = [
            'name'              => $_POST['name'] ?? '',
            'description'       => $_POST['description'] ?? '',
            'initial_wait'      => (int)($_POST['initial_wait'] ?? 3),
            'dtmf_timing'       => (int)($_POST['dtmf_timing'] ?? 6),
            'dtmf_pattern'      => trim($_POST['dtmf_pattern'] ?? '{ID}#'),
            'confirmation_wait' => (int)($_POST['confirmation_wait'] ?? 2),
            'confirmation_dtmf' => trim($_POST['confirmation_dtmf'] ?? '1'),
            'total_duration'    => (int)($_POST['total_duration'] ?? 30),
            'confirm_delay'     => (int)($_POST['confirm_delay'] ?? 2),
            'confirm_repeat'    => (int)($_POST['confirm_repeat'] ?? 3),
            'pattern_type'      => $_POST['pattern_type'] ?? 'standard',
            'auto_supported'    => isset($_POST['auto_supported']) ? (bool)$_POST['auto_supported'] : true,
            'notes'             => $_POST['notes'] ?? ''
        ];
        try {
            $pm->updatePattern($number, $pattern);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">패턴 저장 실패: '.htmlspecialchars($e->getMessage()).'</div>';
        }
    } elseif ($action === 'delete') {
        try {
            $pm->deletePattern($number);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">삭제 실패: '.htmlspecialchars($e->getMessage()).'</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>패턴 매니저 - 080 수신거부 자동화</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #8b80f9 0%, #6a5acd 50%, #9370db 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px;
            text-align: center;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }

        .header h1 {
            font-size: 3rem;
            background: linear-gradient(135deg, #8b80f9 0%, #6a5acd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .header p {
            color: #64748b;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 28px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #8b80f9 0%, #6a5acd 100%);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #8b80f9 0%, #6a5acd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #64748b;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-header {
            background: linear-gradient(135deg, #8b80f9 0%, #6a5acd 100%);
            color: white;
            padding: 24px 32px;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 32px;
        }

        /* 패턴 테이블 개선 */
        .pattern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-top: 24px;
        }

        .pattern-table thead tr {
            background: #f8fafc;
            border-radius: 12px;
        }

        .pattern-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: none;
        }

        .pattern-table th:first-child {
            border-radius: 12px 0 0 12px;
        }

        .pattern-table th:last-child {
            border-radius: 0 12px 12px 0;
        }

        .pattern-table tbody tr {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            position: relative;
        }

        .pattern-table tbody tr:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .pattern-table td {
            padding: 20px;
            font-size: 0.95rem;
            border: none;
            vertical-align: middle;
        }

        .pattern-table td:first-child {
            border-radius: 12px 0 0 12px;
        }

        .pattern-table td:last-child {
            border-radius: 0 12px 12px 0;
        }

        /* 버튼 스타일 개선 */
        .btn {
            background: linear-gradient(135deg, #8b80f9 0%, #6a5acd 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.2);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .btn:hover::before {
            transform: translateX(0);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 128, 249, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.875rem;
        }

        .btn-secondary {
            background: #64748b;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .btn-outline {
            background: transparent;
            color: #8b80f9;
            border: 2px solid #8b80f9;
        }

        .btn-outline:hover {
            background: #8b80f9;
            color: white;
        }

        /* 폼 스타일 개선 */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #334155;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
            background: #f8fafc;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8b80f9;
            box-shadow: 0 0 0 4px rgba(139, 128, 249, 0.1);
            background: white;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }

        /* DTMF 패턴 빌더 개선 */
        .dtmf-builder {
            background: rgba(248, 250, 252, 0.8);
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
            border: 2px dashed rgba(139, 128, 249, 0.2);
        }

        .dtmf-builder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .dtmf-builder-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dtmf-timeline {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .dtmf-steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 80px;
            position: relative;
        }
        
        .dtmf-steps:empty::after {
            content: '위의 "단계 추가" 버튼을 클릭하여 DTMF 시퀀스를 구성하세요';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #94a3b8;
            font-size: 0.95rem;
            text-align: center;
            pointer-events: none;
        }

        .dtmf-step {
            display: grid;
            grid-template-columns: 60px 1fr 150px 50px;
            gap: 12px;
            align-items: center;
            padding: 16px;
            background: white;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            cursor: move;
        }

        .dtmf-step:hover {
            border-color: #8b80f9;
            box-shadow: 0 4px 12px rgba(139, 128, 249, 0.1);
        }
        
        /* 드래그 오버 라인(위/아래) */
        .dtmf-step.drag-over-top::before,
        .dtmf-step.drag-over-bottom::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            height: 4px;
            background: #6a5acd;
            border-radius: 2px;
        }

        .dtmf-step.drag-over-top::before {
            top: -2px;
        }

        .dtmf-step.drag-over-bottom::after {
            bottom: -2px;
        }

        .dtmf-step-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8b80f9 0%, #6a5acd 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            position: relative;
            cursor: grab;
        }
        
        .dtmf-step-number::before {
            content: '⋮⋮';
            position: absolute;
            left: -20px;
            color: #94a3b8;
            font-size: 1.2rem;
            letter-spacing: -4px;
        }
        
        .dtmf-step:active {
            cursor: grabbing;
        }

        .dtmf-step input {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .dtmf-step input:focus {
            border-color: #8b80f9;
            box-shadow: 0 0 0 3px rgba(139, 128, 249, 0.1);
            outline: none;
        }

        /* 패턴 프리뷰 */
        .pattern-preview {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 1.1rem;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pattern-preview-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .pattern-preview-value {
            color: #10b981;
            font-weight: 700;
        }

        /* 템플릿 선택기 */
        .template-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .template-card {
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .template-card:hover {
            border-color: #8b80f9;
            box-shadow: 0 4px 12px rgba(139, 128, 249, 0.1);
        }

        .template-card.selected {
            border-color: #8b80f9;
            background: rgba(139, 128, 249, 0.05);
        }

        .template-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .template-name {
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }

        .template-desc {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* 레이블 개선 */
        .label {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 4px;
        }

        .label-auto {
            background: #dbeafe;
            color: #1e40af;
        }

        .label-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .label-unverified {
            background: #fee2e2;
            color: #991b1b;
        }

        .label-confirm-only {
            background: #fef3c7;
            color: #92400e;
        }

        .label-id-only {
            background: #ede9fe;
            color: #6d28d9;
        }

        .label-manual {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* 알림 스타일 개선 */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        /* 액션 버튼 그룹 */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        /* 팁 카드 개선 */
        .tips-card {
            background: linear-gradient(135deg, rgba(139, 128, 249, 0.05) 0%, rgba(106, 90, 205, 0.05) 100%);
            border: 2px solid rgba(139, 128, 249, 0.2);
            border-radius: 16px;
            padding: 24px;
        }

        .tips-card h3 {
            color: #8b80f9;
            margin-bottom: 16px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tips-card ul {
            list-style: none;
            line-height: 2;
        }

        .tips-card li {
            padding-left: 28px;
            position: relative;
            color: #475569;
        }

        .tips-card li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #8b80f9;
            font-weight: 700;
        }

        /* 백 링크 개선 */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 24px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .back-link:hover {
            background: rgba(255,255,255,0.25);
            transform: translateX(-4px);
            border-color: rgba(255,255,255,0.2);
        }

        /* 애니메이션 */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(139, 128, 249, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(139, 128, 249, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(139, 128, 249, 0);
            }
        }

        .new-pattern {
            animation: slideIn 0.5s ease, pulse 2s ease-in-out;
        }

        /* 반응형 디자인 */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 32px 24px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 24px;
            }
            
            .dtmf-step {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .pattern-table {
                font-size: 0.85rem;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* 드래그 시 자리 표시(placeholder) */
        .dtmf-placeholder {
            height: 68px; /* dtmf-step padding 포함 평균 높이 */
            border: 3px dashed #6a5acd;
            border-radius: 12px;
            background: rgba(106, 90, 205, 0.08);
            margin: 4px 0;
            transition: all 0.15s ease;
        }
        .pattern-table tbody tr { background: rgba(255,255,255,0.95); }
        .dtmf-builder { background: #ffffff; }
 
        /* 항상 라이트 테마로 강제 */
        .pattern-table tbody tr,
        .pattern-table tbody td {
            background: #ffffff !important;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            메인으로 돌아가기
        </a>

        <div class="header">
            <h1>🧠 패턴 매니저</h1>
            <p>080 번호별 DTMF 패턴을 관리하고 최적화합니다</p>
        </div>

        <?php
        /**
         * 080번호 패턴 관리자
         * - 패턴 로드/저장
         * - 패턴 검증 및 업데이트
         * - 패턴 통계 및 관리
         */

        class PatternManager {
            private $patternsFile;
            
            public function __construct($patternsFile = null) {
                $this->patternsFile = $patternsFile ?? __DIR__ . '/patterns.json';
            }
            
            /**
             * 패턴 데이터 로드
             */
            public function getPatterns() {
                if (!file_exists($this->patternsFile)) {
                    return $this->getDefaultPatterns();
                }
                
                $data = json_decode(file_get_contents($this->patternsFile), true);
                if (!$data || !isset($data['patterns'])) {
                    return $this->getDefaultPatterns();
                }
                
                return $data;
            }
            
            /**
             * 기본 패턴 구조 반환
             */
            private function getDefaultPatterns() {
                return [
                    'patterns' => [
                        'default' => [
                            'name' => '기본값',
                            'description' => '알려지지 않은 번호용 기본 설정',
                            'initial_wait' => 3,
                            'dtmf_timing' => 6,
                            'dtmf_pattern' => '{ID}#',
                            'confirmation_wait' => 2,
                            'confirmation_dtmf' => '1',
                            'total_duration' => 30,
                            'confirm_delay' => 2,
                            'confirm_repeat' => 3,
                            'notes' => '새로운 번호는 이 패턴으로 시작',
                            'pattern_type' => 'standard'
                        ]
                    ],
                    'variables' => [
                        '{ID}' => '광고 문자에서 추출한 식별번호',
                        '{Phone}' => '휴대폰 번호'
                    ]
                ];
            }
            
            /**
             * 패턴 데이터 저장
             */
            public function savePatterns($patterns) {
                try {
                    $json = json_encode($patterns, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    if ($json === false) {
                        throw new Exception('JSON 인코딩 실패');
                    }
                    
                    // 백업 생성
                    if (file_exists($this->patternsFile)) {
                        copy($this->patternsFile, $this->patternsFile . '.backup.' . date('YmdHis'));
                    }
                    
                    // 임시 파일에 쓰고 원자적 이동
                    $tempFile = $this->patternsFile . '.tmp';
                    if (file_put_contents($tempFile, $json) === false) {
                        throw new Exception('임시 파일 쓰기 실패');
                    }
                    
                    if (!rename($tempFile, $this->patternsFile)) {
                        throw new Exception('파일 이동 실패');
                    }
                    
                    return true;
                    
                } catch (Exception $e) {
                    // 임시 파일 정리
                    if (isset($tempFile) && file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                    throw $e;
                }
            }
            
            /**
             * 특정 번호의 패턴 가져오기
             */
            public function getPattern($phoneNumber) {
                $patterns = $this->getPatterns();
                return $patterns['patterns'][$phoneNumber] ?? $patterns['patterns']['default'] ?? null;
            }
            
            /**
             * 패턴 추가 또는 업데이트
             */
            public function updatePattern($phoneNumber, $patternData) {
                $patterns = $this->getPatterns();
                
                // 업데이트 시간 추가
                $patternData['updated_at'] = date('Y-m-d H:i:s');
                
                // 기존 패턴이 있으면 일부 정보 보존
                if (isset($patterns['patterns'][$phoneNumber])) {
                    $existing = $patterns['patterns'][$phoneNumber];
                    $patternData['created_at'] = $existing['created_at'] ?? date('Y-m-d H:i:s');
                    $patternData['usage_count'] = $existing['usage_count'] ?? 0;
                    $patternData['last_used'] = $existing['last_used'] ?? null;
                } else {
                    $patternData['created_at'] = date('Y-m-d H:i:s');
                    $patternData['usage_count'] = 0;
                }
                
                $patterns['patterns'][$phoneNumber] = $patternData;
                
                return $this->savePatterns($patterns);
            }
            
            /**
             * 패턴 사용 기록 업데이트
             */
            public function recordPatternUsage($phoneNumber, $success = null) {
                $patterns = $this->getPatterns();
                
                if (!isset($patterns['patterns'][$phoneNumber])) {
                    return false;
                }
                
                $pattern = &$patterns['patterns'][$phoneNumber];
                $pattern['usage_count'] = ($pattern['usage_count'] ?? 0) + 1;
                $pattern['last_used'] = date('Y-m-d H:i:s');
                
                if ($success !== null) {
                    if (!isset($pattern['success_stats'])) {
                        $pattern['success_stats'] = ['success' => 0, 'failed' => 0];
                    }
                    
                    if ($success) {
                        $pattern['success_stats']['success']++;
                        // 성공하면 검증 완료로 표시
                        if (isset($pattern['needs_verification'])) {
                            $pattern['needs_verification'] = false;
                            $pattern['verified_at'] = date('Y-m-d H:i:s');
                        }
                    } else {
                        $pattern['success_stats']['failed']++;
                    }
                }
                
                return $this->savePatterns($patterns);
            }
            
            /**
             * 패턴 삭제
             */
            public function deletePattern($phoneNumber) {
                if ($phoneNumber === 'default') {
                    throw new Exception('기본 패턴은 삭제할 수 없습니다');
                }
                
                $patterns = $this->getPatterns();
                
                if (!isset($patterns['patterns'][$phoneNumber])) {
                    return false;
                }
                
                unset($patterns['patterns'][$phoneNumber]);
                
                return $this->savePatterns($patterns);
            }
            
            /**
             * 패턴 검증 상태 업데이트
             */
            public function verifyPattern($phoneNumber, $verified = true) {
                $patterns = $this->getPatterns();
                
                if (!isset($patterns['patterns'][$phoneNumber])) {
                    return false;
                }
                
                $pattern = &$patterns['patterns'][$phoneNumber];
                $pattern['needs_verification'] = !$verified;
                
                if ($verified) {
                    $pattern['verified_at'] = date('Y-m-d H:i:s');
                }
                
                return $this->savePatterns($patterns);
            }
            
            /**
             * 패턴 통계 가져오기
             */
            public function getPatternStats() {
                $patterns = $this->getPatterns();
                $stats = [
                    'total_patterns' => 0,
                    'auto_generated' => 0,
                    'verified' => 0,
                    'needs_verification' => 0,
                    'most_used' => null,
                    'recent_patterns' => []
                ];
                
                foreach ($patterns['patterns'] as $phone => $pattern) {
                    if ($phone === 'default') continue;
                    
                    $stats['total_patterns']++;
                    
                    if (isset($pattern['auto_generated']) && $pattern['auto_generated']) {
                        $stats['auto_generated']++;
                    }
                    
                    if (isset($pattern['needs_verification']) && $pattern['needs_verification']) {
                        $stats['needs_verification']++;
                    } else {
                        $stats['verified']++;
                    }
                    
                    // 가장 많이 사용된 패턴
                    $usageCount = $pattern['usage_count'] ?? 0;
                    if (!$stats['most_used'] || $usageCount > $stats['most_used']['usage_count']) {
                        $stats['most_used'] = array_merge($pattern, ['phone' => $phone]);
                    }
                    
                    // 최근 생성된 패턴
                    if (isset($pattern['created_at'])) {
                        $stats['recent_patterns'][] = [
                            'phone' => $phone,
                            'name' => $pattern['name'],
                            'created_at' => $pattern['created_at'],
                            'auto_generated' => $pattern['auto_generated'] ?? false
                        ];
                    }
                }
                
                // 최근 패턴 정렬
                usort($stats['recent_patterns'], function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                $stats['recent_patterns'] = array_slice($stats['recent_patterns'], 0, 5);
                
                return $stats;
            }
            
            /**
             * 패턴 내보내기 (백업용)
             */
            public function exportPatterns($includeStats = false) {
                $patterns = $this->getPatterns();
                
                if (!$includeStats) {
                    // 통계 정보 제거
                    foreach ($patterns['patterns'] as &$pattern) {
                        unset($pattern['usage_count'], $pattern['last_used'], 
                              $pattern['success_stats'], $pattern['created_at'], 
                              $pattern['updated_at'], $pattern['verified_at']);
                    }
                }
                
                return $patterns;
            }
            
            /**
             * 패턴 가져오기 (복원용)
             */
            public function importPatterns($importData, $merge = true) {
                if (!is_array($importData) || !isset($importData['patterns'])) {
                    throw new Exception('잘못된 패턴 데이터 형식');
                }
                
                if ($merge) {
                    $existingPatterns = $this->getPatterns();
                    $importData['patterns'] = array_merge($existingPatterns['patterns'], $importData['patterns']);
                }
                
                return $this->savePatterns($importData);
            }
        }

        // CLI 실행이 아닌 경우에만 웹 인터페이스 실행
        if (php_sapi_name() !== 'cli') {
            $manager = new PatternManager();
            $message = '';
            
            // 패턴 추가/수정 처리
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                
                try {
                    if ($action === 'add' || $action === 'edit') {
                        $number = $_POST['number'] ?? '';
                        // 기존 패턴 값 보존을 위해 먼저 기존 데이터를 불러옵니다
                        $existingPattern = $manager->getPattern($number);

                        $patternData = array_merge($existingPattern ?? [], [
                            'name' => $_POST['name'] ?? '',
                            'description' => $_POST['description'] ?? '',
                            'initial_wait' => intval($_POST['initial_wait'] ?? 3),
                            'dtmf_timing' => intval($_POST['dtmf_timing'] ?? 6),
                            'dtmf_pattern' => $_POST['dtmf_pattern'] ?? '{ID}#',
                            'confirmation_wait' => intval($_POST['confirmation_wait'] ?? 2),
                            'confirmation_dtmf' => $_POST['confirmation_dtmf'] ?? '1',
                            'total_duration' => intval($_POST['total_duration'] ?? 30),
                            'confirm_delay' => intval($_POST['confirm_delay'] ?? ($existingPattern['confirm_delay'] ?? 2)),
                            'confirm_repeat' => intval($_POST['confirm_repeat'] ?? ($existingPattern['confirm_repeat'] ?? 3)),
                            'notes' => $_POST['notes'] ?? ($existingPattern['notes'] ?? ''),
                            'pattern_type' => $_POST['pattern_type'] ?? 'standard'
                        ]);
                        
                        $manager->updatePattern($number, $patternData);
                        $message = $action === 'add' ? 
                            '<div class="alert alert-success">✅ 패턴이 추가되었습니다!</div>' : 
                            '<div class="alert alert-success">✅ 패턴이 수정되었습니다!</div>';
                    }
                    
                    if ($action === 'delete') {
                        $number = $_POST['number'] ?? '';
                        $manager->deletePattern($number);
                        $message = '<div class="alert alert-success">✅ 패턴이 삭제되었습니다!</div>';
                    }
                } catch (Exception $e) {
                    $message = '<div class="alert alert-error">❌ 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            
            // 현재 패턴 및 통계 로드
            $patterns = $manager->getPatterns();
            $stats = $manager->getPatternStats();
            ?>
            
            <?php echo $message; ?>
            
            <!-- 통계 카드 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_patterns']; ?></div>
                    <div class="stat-label">전체 패턴</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['auto_generated']; ?></div>
                    <div class="stat-label">자동 생성</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['verified']; ?></div>
                    <div class="stat-label">검증 완료</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['needs_verification']; ?></div>
                    <div class="stat-label">검증 필요</div>
                </div>
            </div>
            
            <!-- 패턴 목록 -->
            <div class="card">
                <div class="card-header">
                    📋 등록된 패턴 목록
                    <button class="btn btn-small" onclick="checkForNewPatterns()">
                        🔄 새로고침
                    </button>
                </div>
                <div class="card-body">
                    <table class="pattern-table" id="patternTable">
                        <thead>
                            <tr>
                                <th>080 번호</th>
                                <th>패턴 정보</th>
                                <th>DTMF 패턴</th>
                                <th>타이밍</th>
                                <th>상태</th>
                                <th>액션</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patterns['patterns'] as $number => $pattern): ?>
                            <tr data-number="<?php echo htmlspecialchars($number); ?>" 
                                data-created="<?php echo $pattern['created_at'] ?? ''; ?>">
                                <td>
                                    <div style="font-weight: 700; font-size: 1rem; color: #1e293b;">
                                        <?php echo htmlspecialchars($number); ?>
                                    </div>
                                    <?php if (isset($pattern['usage_count']) && $pattern['usage_count'] > 0): ?>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
                                        사용: <?php echo $pattern['usage_count']; ?>회
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">
                                        <?php echo htmlspecialchars($pattern['name']); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #64748b; margin-top: 4px;">
                                        <?php echo htmlspecialchars($pattern['description'] ?? ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($pattern['dtmf_pattern']); ?>
                                    </code>
                                </td>
                                <td>
                                    <div style="font-size: 0.875rem;">
                                        <div>초기: <?php echo $pattern['initial_wait']; ?>초</div>
                                        <div>DTMF: <?php echo $pattern['dtmf_timing']; ?>초</div>
                                        <div>확인: <?php echo $pattern['confirmation_wait']; ?>초</div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (isset($pattern['auto_generated']) && $pattern['auto_generated']): ?>
                                        <span class="label label-auto">자동</span>
                                    <?php endif; ?>
                                    <?php if (isset($pattern['needs_verification']) && $pattern['needs_verification']): ?>
                                        <span class="label label-unverified">검증필요</span>
                                    <?php else: ?>
                                        <span class="label label-verified">검증됨</span>
                                    <?php endif; ?>
                                    <?php if (isset($pattern['pattern_type'])): ?>
                                        <?php if ($pattern['pattern_type'] === 'confirm_only'): ?>
                                            <span class="label label-confirm-only">확인전용</span>
                                        <?php elseif ($pattern['pattern_type'] === 'id_only'): ?>
                                            <span class="label label-id-only">식별번호만 필요</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (isset($pattern['auto_supported']) && $pattern['auto_supported'] === false): ?>
                                        <span class="label label-manual">수동</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-small btn-secondary" onclick="editPattern('<?php echo $number; ?>')">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                            </svg>
                                            수정
                                        </button>
                                        <?php if ($number !== 'default'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="number" value="<?php echo $number; ?>">
                                            <button type="submit" class="btn btn-small btn-danger" 
                                                    onclick="return confirm('정말 삭제하시겠습니까?')">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                    <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                                </svg>
                                                삭제
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 패턴 추가/수정 폼 -->
            <div class="card">
                <div class="card-header" id="form-header">
                    ➕ 새 패턴 추가
                </div>
                <div class="card-body">
                    <form method="post" id="pattern-form">
                        <input type="hidden" name="action" value="add" id="form-action">
                        
                        <!-- 패턴 템플릿 선택 -->
                        <h3 style="margin-bottom: 16px; color: #334155; font-size: 1.1rem;">📋 패턴 템플릿 선택</h3>
                        <div class="template-selector" id="template-selector">
                            <div class="template-card selected" data-template="standard">
                                <div class="template-icon">🎯</div>
                                <div class="template-name">표준 패턴</div>
                                <div class="template-desc">ID 입력 + 확인</div>
                            </div>
                            <div class="template-card" data-template="id_only">
                                <div class="template-icon">🆔</div>
                                <div class="template-name">ID 전용</div>
                                <div class="template-desc">ID만 입력</div>
                            </div>
                            <div class="template-card" data-template="confirm_only">
                                <div class="template-icon">✅</div>
                                <div class="template-name">확인 전용</div>
                                <div class="template-desc">확인만 필요</div>
                            </div>
                            <div class="template-card" data-template="complex">
                                <div class="template-icon">🔧</div>
                                <div class="template-name">복잡한 패턴</div>
                                <div class="template-desc">다단계 입력</div>
                            </div>
                        </div>
                        <input type="hidden" name="pattern_type" id="pattern-type" value="standard">
                        
                        <div class="form-row" style="margin-top: 32px;">
                            <div class="form-group">
                                <label for="form-number">080 번호</label>
                                <input type="text" name="number" id="form-number" placeholder="0801234567" required>
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">하이픈(-) 없이 숫자만 입력</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-name">패턴 이름</label>
                                <input type="text" name="name" id="form-name" placeholder="회사명 패턴" required>
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">식별하기 쉬운 이름</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-description">설명</label>
                            <input type="text" name="description" id="form-description" placeholder="패턴에 대한 설명">
                        </div>
                        
                        <h3 style="margin: 32px 0 20px; color: #334155;">⏱️ 타이밍 설정</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="form-initial-wait">초기 대기 (초)</label>
                                <input type="number" name="initial_wait" id="form-initial-wait" value="3" min="0">
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">통화 연결 후 대기 시간</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-dtmf-timing">DTMF 타이밍 (초)</label>
                                <input type="number" name="dtmf_timing" id="form-dtmf-timing" value="6" min="0" max="20">
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">첫 DTMF 입력 시점</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-confirmation-wait">확인 대기 (초)</label>
                                <input type="number" name="confirmation_wait" id="form-confirmation-wait" value="2" min="0" max="15">
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">확인 버튼 입력 전 대기</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <!-- 총 녹음시간 필드를 행의 마지막으로 이동 -->
                            
                            <div class="form-group">
                                <label for="form-confirm-delay">확인 지연 (초)</label>
                                <input type="number" name="confirm_delay" id="form-confirm-delay" value="2" min="0" max="10">
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">반복 확인 DTMF 간격</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-confirm-repeat">반복 횟수</label>
                                <input type="number" name="confirm_repeat" id="form-confirm-repeat" value="3" min="1" max="5">
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">확인 DTMF 전송 횟수</div>
                            </div>

                            <div class="form-group">
                                <label for="form-total-duration">총 녹음시간 (초)</label>
                                <input type="number" name="total_duration" id="form-total-duration" value="30" min="10" max="60">
                                <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">전체 통화 녹음 시간</div>
                            </div>
                        </div>
                        
                        <h3 style="margin: 32px 0 20px; color: #334155;">📞 DTMF 패턴 설정</h3>
                        
                            <div class="form-group">
                                <label for="form-dtmf-pattern">DTMF 패턴</label>
                            <input type="text" name="dtmf_pattern" id="form-dtmf-pattern" value="{ID}#" placeholder="DTMF 시퀀스 직접 입력 가능" style="background:#ffffff;">
                            <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">직접 입력하거나 아래 빌더를 사용하세요</div>
                            </div>
                            
                        <!-- DTMF 빌더 -->
                        <div class="dtmf-builder">
                            <div class="dtmf-builder-header">
                                <div class="dtmf-builder-title">
                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    DTMF 시퀀스 빌더
                                </div>
                                <button type="button" class="btn btn-small btn-success" onclick="addStep()">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                    </svg>
                                    단계 추가
                                </button>
                            </div>
                            
                            <div class="dtmf-timeline">
                                <div class="dtmf-steps" id="dtmf-steps"></div>
                            </div>
                            
                            <div class="pattern-preview">
                                <span class="pattern-preview-label">패턴 미리보기:</span>
                                <span class="pattern-preview-value" id="pattern-preview">{ID}#</span>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 24px;">
                                <label for="form-confirmation-dtmf">확인 DTMF</label>
                                <input type="text" name="confirmation_dtmf" id="form-confirmation-dtmf" value="1" placeholder="1">
                            <div class="help-text" style="font-size: 0.875rem; color: #64748b; margin-top: 6px;">확인을 위해 누를 번호</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-notes">메모</label>
                            <textarea name="notes" id="form-notes" rows="3" placeholder="추가 메모나 특이사항"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 12px; margin-top: 32px;">
                            <button type="submit" class="btn" id="submit-btn">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                                </svg>
                                패턴 추가
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancel-btn" onclick="cancelEdit()" style="display:none;">
                                취소
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 사용 팁 -->
            <div class="card">
                <div class="card-body tips-card">
                    <h3>
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        사용 팁
                    </h3>
                    <ul>
                        <li><strong>{ID}</strong>: 광고 문자에서 추출한 식별번호로 자동 치환됩니다</li>
                        <li><strong>{Phone}</strong>: 사용자 전화번호로 치환됩니다 (필요한 경우)</li>
                        <li><strong>DTMF 빌더</strong>: 복잡한 패턴도 시각적으로 쉽게 구성할 수 있습니다</li>
                        <li><strong>템플릿</strong>: 자주 사용하는 패턴 유형을 빠르게 적용할 수 있습니다</li>
                        <li>새로운 080 번호는 먼저 <strong>default</strong> 패턴으로 테스트 후 조정하세요</li>
                        <li>패턴 분석이 완료되면 자동으로 이 목록에 추가됩니다</li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
            // 패턴 데이터를 JavaScript에서 사용할 수 있도록 변환
            const patterns = <?php echo json_encode($patterns['patterns']); ?>;
            let lastCheckTime = new Date().toISOString();
            let stepCounter = 0;
            let isProgrammaticUpdate = false; // 양방향 동기화 루프 방지
            
            // 템플릿 선택
            document.querySelectorAll('.template-card').forEach(card => {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    const template = this.dataset.template;
                    document.getElementById('pattern-type').value = template;
                    
                    // 템플릿에 따라 DTMF 빌더 초기화
                    clearDTMFBuilder();
                    switch(template) {
                        case 'standard':
                            addStep(0, '{ID}#');
                            document.getElementById('form-confirmation-dtmf').value = '1';
                            showNotification('표준 패턴: ID 입력 후 확인 버튼');
                            break;
                        case 'id_only':
                            addStep(0, '{ID}#');
                            document.getElementById('form-confirmation-dtmf').value = '';
                            showNotification('ID 전용: 식별번호만 입력');
                            break;
                        case 'confirm_only':
                            addStep(0, '1');
                            document.getElementById('form-confirmation-dtmf').value = '';
                            showNotification('확인 전용: 확인 버튼만 필요');
                            break;
                        case 'complex':
                            addStep(0, '1');
                            addStep(1, '{ID}#');
                            addStep(0.5, '9');
                            document.getElementById('form-confirmation-dtmf').value = '1';
                            showNotification('복잡한 패턴: 다단계 입력 설정');
                            break;
                    }

                    // confirm_only 패턴은 자동 수신거부 미지원
                    if (template === 'confirm_only') {
                        document.getElementById('auto_supported').checked = false;
                    }
                });
            });
            
            // DTMF 빌더 함수들
            function clearDTMFBuilder() {
                document.getElementById('dtmf-steps').innerHTML = '';
                stepCounter = 0;
                updatePatternPreview();
            }
            
            function addStep(delay = 0, digits = '') {
                stepCounter++;
                const stepsContainer = document.getElementById('dtmf-steps');
                
                const stepDiv = document.createElement('div');
                stepDiv.className = 'dtmf-step';
                stepDiv.dataset.stepId = stepCounter;
                stepDiv.draggable = true;
                
                stepDiv.innerHTML = `
                    <div class="dtmf-step-number">${stepCounter}</div>
                    <input type="number" class="step-delay" min="0" step="0.5" value="${delay}" 
                           placeholder="지연(초)" onchange="updatePatternPreview()">
                    <input type="text" class="step-digits" value="${digits}" 
                           placeholder="DTMF 입력 (예: 1234#)" onchange="updatePatternPreview()">
                    <button type="button" class="btn btn-small btn-danger" onclick="removeStep(${stepCounter})">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                        </svg>
                    </button>
                `;
                
                // 드래그 이벤트 추가
                stepDiv.addEventListener('dragstart', handleDragStart);
                stepDiv.addEventListener('dragend', handleDragEnd);
                stepDiv.addEventListener('dragover', handleDragOver);
                stepDiv.addEventListener('drop', handleDrop);
                stepDiv.addEventListener('dragenter', handleDragEnter);
                stepDiv.addEventListener('dragleave', handleDragLeave);
                
                stepsContainer.appendChild(stepDiv);
                updatePatternPreview();
                renumberSteps();
            }
            
            // 드래그앤드롭 관련 함수들
            let draggedElement = null;
            let placeholderDiv = null;
            
            function handleDragStart(e) {
                draggedElement = this;
                this.style.opacity = '0.6';
                this.style.transform = 'scale(0.98)';
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);

                // placeholder 생성
                placeholderDiv = document.createElement('div');
                placeholderDiv.className = 'dtmf-placeholder';
            }
            
            function handleDragEnd(e) {
                this.style.opacity = '';
                this.style.transform = '';

                if (placeholderDiv && placeholderDiv.parentNode) {
                    placeholderDiv.parentNode.removeChild(placeholderDiv);
                }
                const steps = document.querySelectorAll('.dtmf-step');
                steps.forEach(step => {
                    step.classList.remove('drag-over-top', 'drag-over-bottom');
                });
            }
            
            function handleDragEnter(e) {
                // 필요없음, 실제 라인은 dragover에서 처리
            }
            
            function handleDragLeave(e) {
                this.classList.remove('drag-over-top', 'drag-over-bottom');
            }
            
            function handleDragOver(e) {
                if (e.preventDefault()) e.preventDefault();

                const bounding = this.getBoundingClientRect();
                const offset = e.clientY - bounding.top;

                const container = document.getElementById('dtmf-steps');
                if (!placeholderDiv.parentNode) {
                    container.insertBefore(placeholderDiv, this);
                }

                if (offset < bounding.height / 2) {
                    container.insertBefore(placeholderDiv, this);
                } else {
                    container.insertBefore(placeholderDiv, this.nextSibling);
                }

                e.dataTransfer.dropEffect = 'move';
                return false;
            }
            
            function handleDrop(e) {
                e.preventDefault();
                if (e.stopPropagation) {
                    e.stopPropagation();
                }
                
                if (placeholderDiv && placeholderDiv.parentNode) {
                    // 애니메이션: 현재 위치와 placeholder 위치 차이를 계산 후 슬라이드
                    const placeholderRect = placeholderDiv.getBoundingClientRect();
                    const draggedRect = draggedElement.getBoundingClientRect();
                    const deltaY = placeholderRect.top - draggedRect.top;

                    // DOM 이동 전에 transform 기준 좌표 유지하기 위해 fixed transform 적용
                    draggedElement.style.transition = 'none';
                    draggedElement.style.transform = `translateY(${deltaY}px)`;

                    // 실제 DOM 위치 교체
                    placeholderDiv.parentNode.replaceChild(draggedElement, placeholderDiv);

                    // 다음 프레임에 원래 위치로 돌아오며 애니메이션
                    requestAnimationFrame(() => {
                        draggedElement.style.transition = 'transform 200ms ease';
                        draggedElement.style.transform = '';

                        // transition 끝나면 인라인 스타일 정리
                        const cleanup = () => {
                            draggedElement.style.transition = '';
                            draggedElement.removeEventListener('transitionend', cleanup);
                        };
                        draggedElement.addEventListener('transitionend', cleanup);
                    });
                }

                renumberSteps();
                updatePatternPreview();

                return false;
            }
            
            function removeStep(stepId) {
                const step = document.querySelector(`[data-step-id="${stepId}"]`);
                if (step) {
                    step.remove();
                    renumberSteps();
                    updatePatternPreview();
                }
            }
            
            function renumberSteps() {
                const steps = document.querySelectorAll('.dtmf-step');
                steps.forEach((step, index) => {
                    step.querySelector('.dtmf-step-number').textContent = index + 1;
                });
            }
            
            function updatePatternPreview() {
                const steps = document.querySelectorAll('.dtmf-step');
                const segments = [];

                steps.forEach(step => {
                    const delay = parseFloat(step.querySelector('.step-delay').value) || 0;
                    const digits = step.querySelector('.step-digits').value.trim();

                    if (!digits) return; // 입력이 없는 단계는 무시

                    // 0.5초 단위로 w 생성 (delay*2)
                    const wCount = Math.round(delay * 2);
                    const segment = 'w'.repeat(wCount) + digits;
                    segments.push(segment);
                });

                const pattern = segments.length ? segments.join(',') : '{ID}#';
                isProgrammaticUpdate = true;
                document.getElementById('form-dtmf-pattern').value = pattern;
                isProgrammaticUpdate = false;
                document.getElementById('pattern-preview').textContent = pattern;
            }
            
            // 패턴 편집
            function editPattern(number) {
                const pattern = patterns[number];
                if (!pattern) return;
                
                // 폼 제목과 액션 변경
                document.getElementById('form-header').textContent = '✏️ 패턴 수정';
                document.getElementById('form-action').value = 'edit';
                document.getElementById('submit-btn').innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg> 패턴 수정';
                document.getElementById('cancel-btn').style.display = 'inline-flex';
                
                // 폼 필드 채우기
                const setField = (id, val, def) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.value = (val ?? def);
                };

                document.getElementById('form-number').value = number;
                setField('form-name', pattern.name, '');
                setField('form-description', pattern.description, '');
                setField('form-initial-wait', pattern.initial_wait, 3);
                setField('form-dtmf-timing', pattern.dtmf_timing, 6);
                setField('form-confirmation-wait', pattern.confirmation_wait, 2);
                setField('form-total-duration', pattern.total_duration, 30);
                setField('form-confirm-delay', pattern.confirm_delay, 2);
                setField('form-confirm-repeat', pattern.confirm_repeat, 3);
                setField('form-dtmf-pattern', pattern.dtmf_pattern, '{ID}#');
                setField('form-confirmation-dtmf', pattern.confirmation_dtmf, '1');
                setField('form-notes', pattern.notes, '');
                
                // 패턴 타입 선택
                const patternType = pattern.pattern_type || 'standard';
                document.getElementById('pattern-type').value = patternType;
                document.querySelectorAll('.template-card').forEach(card => {
                    card.classList.toggle('selected', card.dataset.template === patternType);
                });
                
                // DTMF 빌더 채우기
                clearDTMFBuilder();
                parseDTMFPattern(pattern.dtmf_pattern || '{ID}#');
                
                // 번호 필드를 읽기 전용으로 설정
                document.getElementById('form-number').readOnly = true;
                
                // 폼으로 스크롤
                document.getElementById('pattern-form').scrollIntoView({ behavior: 'smooth' });
            }
            
            function parseDTMFPattern(patternStr) {
                if (!patternStr) {
                    addStep(0, '{ID}#');
                    return;
                }

                const parts = patternStr.split(',');
                let pendingDelay = 0;

                parts.forEach(part => {
                    let segment = part;

                    // 시작의 w 갯수로 지연 계산
                    const wMatch = segment.match(/^(w+)/);
                    if (wMatch) {
                        pendingDelay += wMatch[1].length * 0.5; // 누적
                        segment = segment.substring(wMatch[1].length);
                    }

                    if (segment) {
                        // 지연+digits 단계
                        addStep(pendingDelay, segment);
                        pendingDelay = 0;
                    } else if (pendingDelay > 0) {
                        // 지연만 존재하는 단계
                        addStep(pendingDelay, '');
                        pendingDelay = 0;
                    }
                });

                // 문자열 끝이 w 로 끝나 지연만 남은 경우 처리
                if (pendingDelay > 0) {
                    addStep(pendingDelay, '');
                }
            }
            
            function cancelEdit() {
                // 폼 초기화
                document.getElementById('form-header').textContent = '➕ 새 패턴 추가';
                document.getElementById('form-action').value = 'add';
                document.getElementById('submit-btn').innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg> 패턴 추가';
                document.getElementById('cancel-btn').style.display = 'none';
                
                // 필드 초기화
                document.getElementById('pattern-form').reset();
                document.getElementById('form-number').readOnly = false;
                
                // 기본값 복원
                document.getElementById('form-initial-wait').value = 3;
                document.getElementById('form-dtmf-timing').value = 6;
                document.getElementById('form-confirmation-wait').value = 2;
                document.getElementById('form-total-duration').value = 30;
                document.getElementById('form-confirm-delay').value = 2;
                document.getElementById('form-confirm-repeat').value = 3;
                document.getElementById('form-dtmf-pattern').value = '{ID}#';
                document.getElementById('form-confirmation-dtmf').value = '1';
                
                // 템플릿 초기화
                document.querySelectorAll('.template-card').forEach(card => {
                    card.classList.toggle('selected', card.dataset.template === 'standard');
                });
                
                // DTMF 빌더 초기화
                clearDTMFBuilder();
                addStep(0, '{ID}#');
            }
            
            // 새로운 패턴 확인 (실시간 업데이트)
            function checkForNewPatterns() {
                fetch('patterns.json?t=' + Date.now())
                    .then(response => response.json())
                    .then(data => {
                        const currentPatterns = data.patterns;
                        const tbody = document.querySelector('#patternTable tbody');
                        
                        // 새로운 패턴 확인
                        Object.keys(currentPatterns).forEach(number => {
                            const existingRow = tbody.querySelector(`tr[data-number="${number}"]`);
                            const pattern = currentPatterns[number];
                            
                            if (!existingRow && pattern.created_at > lastCheckTime) {
                                // 새 패턴 추가
                                location.reload(); // 간단히 페이지 리로드
                                showNotification(`새 패턴이 추가되었습니다: ${number}`);
                            }
                        });
                        
                        lastCheckTime = new Date().toISOString();
                    })
                    .catch(error => console.error('패턴 확인 오류:', error));
            }
            
            // 알림 표시
            function showNotification(message) {
                const notification = document.createElement('div');
                notification.className = 'alert alert-success';
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '1000';
                notification.innerHTML = `
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    ${message}
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
            
            // 페이지 로드 시 초기화
            document.addEventListener('DOMContentLoaded', function() {
                // 기본 패턴 추가
                if (document.getElementById('dtmf-steps').children.length === 0) {
                    addStep(0, '{ID}#');
            }
            
            // 10초마다 새 패턴 확인
            setInterval(checkForNewPatterns, 10000);
            });

            // ---- 컨테이너 레벨 드롭 처리 ----
            const stepsContainerEl = document.getElementById('dtmf-steps');

            stepsContainerEl.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            stepsContainerEl.addEventListener('drop', function(e) {
                e.preventDefault();
                if (placeholderDiv && draggedElement) {
                    if (placeholderDiv.parentNode) {
                        placeholderDiv.parentNode.replaceChild(draggedElement, placeholderDiv);
                        renumberSteps();
                        updatePatternPreview();
                    }
                }
            });

            // === dtmf_pattern 직접 입력 ↔ 빌더 동기화 (지연 적용) ===
            const dtmfInput = document.getElementById('form-dtmf-pattern');
            let inputDebounce;

            dtmfInput.addEventListener('input', function () {
                if (isProgrammaticUpdate) return;
                document.getElementById('pattern-preview').textContent = this.value || '{ID}#';

                // 400ms 후 빌더 동기화 (디바운스)
                clearTimeout(inputDebounce);
                inputDebounce = setTimeout(() => {
                    if (isProgrammaticUpdate) return;
                    syncBuilderWithText(this.value);
                }, 400);
            });

            dtmfInput.addEventListener('blur', function () {
                if (isProgrammaticUpdate) return;
                syncBuilderWithText(this.value);
            });

            function syncBuilderWithText(str) {
                isProgrammaticUpdate = true;
                const original = str;
                clearDTMFBuilder();
                parseDTMFPattern(str);
                // updatePatternPreview 가 입력 값을 재작성했을 수 있으므로 원문 복원
                dtmfInput.value = original;
                isProgrammaticUpdate = false;
                document.getElementById('pattern-preview').textContent = str || '{ID}#';
            }

            // === 총 녹음시간 자동 계산 ===
            function recalcTotalDuration() {
                const initial = parseFloat(document.getElementById('form-initial-wait').value) || 0;
                const dtmf = parseFloat(document.getElementById('form-dtmf-timing').value) || 0;
                const confirmWait = parseFloat(document.getElementById('form-confirmation-wait').value) || 0;
                const confirmDelay = parseFloat(document.getElementById('form-confirm-delay').value) || 0;
                const confirmRepeat = parseInt(document.getElementById('form-confirm-repeat').value) || 0;

                // 총합 + 5초 버퍼
                const total = Math.round(initial + dtmf + confirmWait + (confirmDelay * confirmRepeat) + 5);

                const totalInput = document.getElementById('form-total-duration');
                totalInput.value = total;
            }

            // 타이밍 관련 필드가 변경될 때 자동 갱신
            ['form-initial-wait', 'form-dtmf-timing', 'form-confirmation-wait', 'form-confirm-delay', 'form-confirm-repeat']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.addEventListener('input', recalcTotalDuration);
                    }
                });

            // 페이지 로드 시 최초 계산
            document.addEventListener('DOMContentLoaded', recalcTotalDuration);
        </script>
    </div>
</body>
</html>
<?php
        } // CLI 체크 닫는 괄호
?>