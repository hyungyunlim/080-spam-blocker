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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 30px;
        }

        .pattern-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .pattern-table th,
        .pattern-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .pattern-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pattern-table tr:hover {
            background: #f7fafc;
        }

        .pattern-table td {
            font-size: 0.95rem;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-secondary {
            background: #718096;
        }

        .btn-secondary:hover {
            box-shadow: 0 5px 15px rgba(113, 128, 150, 0.4);
        }

        .btn-danger {
            background: #e53e3e;
        }

        .btn-danger:hover {
            box-shadow: 0 5px 15px rgba(229, 62, 62, 0.4);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .help-text {
            font-size: 0.9rem;
            color: #718096;
            margin-top: 5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: fadeIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #764ba2;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-auto {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-unverified {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        @keyframes fadeIn {
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
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
            }
        }

        .new-pattern {
            animation: fadeIn 0.5s ease, pulse 2s ease-in-out;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .pattern-table {
                font-size: 0.85rem;
            }
            
            .pattern-table th,
            .pattern-table td {
                padding: 8px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        .label {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            margin: 2px;
        }
        .auto-generated {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .needs-verification {
            background-color: #fff3e0;
            color: #f57c00;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">
            ← 메인으로 돌아가기
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
                            'notes' => '새로운 번호는 이 패턴으로 시작'
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
                            'notes' => $_POST['notes'] ?? ($existingPattern['notes'] ?? '')
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
                                <th>이름</th>
                                <th>설명</th>
                                <th>초기대기</th>
                                <th>DTMF타이밍</th>
                                <th>DTMF패턴</th>
                                <th>확인대기</th>
                                <th>확인DTMF</th>
                                <th>총시간</th>
                                <th>지연</th>
                                <th>반복</th>
                                <th>상태</th>
                                <th>액션</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patterns['patterns'] as $number => $pattern): ?>
                            <tr data-number="<?php echo htmlspecialchars($number); ?>" 
                                data-created="<?php echo $pattern['created_at'] ?? ''; ?>">
                                <td><strong><?php echo htmlspecialchars($number); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($pattern['name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($pattern['description'] ?? ''); ?></td>
                                <td><?php echo $pattern['initial_wait']; ?>초</td>
                                <td><?php echo $pattern['dtmf_timing']; ?>초</td>
                                <td><code><?php echo htmlspecialchars($pattern['dtmf_pattern']); ?></code></td>
                                <td><?php echo $pattern['confirmation_wait']; ?>초</td>
                                <td><?php echo htmlspecialchars($pattern['confirmation_dtmf']); ?></td>
                                <td><?php echo $pattern['total_duration']; ?>초</td>
                                <td><?php echo $pattern['confirm_delay'] ?? 2; ?>초</td>
                                <td><?php echo $pattern['confirm_repeat'] ?? 3; ?>회</td>
                                <td>
                                    <?php if (isset($pattern['needs_verification']) && $pattern['needs_verification']): ?>
                                        <span class="label needs-verification">검증 필요</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-small btn-secondary" onclick="editPattern('<?php echo $number; ?>')">수정</button>
                                        <?php if ($number !== 'default'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="number" value="<?php echo $number; ?>">
                                            <button type="submit" class="btn btn-small btn-danger" 
                                                    onclick="return confirm('정말 삭제하시겠습니까?')">삭제</button>
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
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="form-number">080 번호</label>
                                <input type="text" name="number" id="form-number" placeholder="0801234567" required>
                                <div class="help-text">하이픈(-) 없이 숫자만 입력</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-name">패턴 이름</label>
                                <input type="text" name="name" id="form-name" placeholder="회사명 패턴" required>
                                <div class="help-text">식별하기 쉬운 이름</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-description">설명</label>
                            <input type="text" name="description" id="form-description" placeholder="패턴에 대한 설명">
                        </div>
                        
                        <h3 style="margin: 30px 0 20px; color: #4a5568;">⏱️ 타이밍 설정 (초)</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="form-initial-wait">초기 대기</label>
                                <input type="number" name="initial_wait" id="form-initial-wait" value="3" min="0" max="10">
                                <div class="help-text">통화 연결 후 대기 시간</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-dtmf-timing">DTMF 타이밍</label>
                                <input type="number" name="dtmf_timing" id="form-dtmf-timing" value="6" min="0" max="20">
                                <div class="help-text">첫 DTMF 입력 시점</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-confirmation-wait">확인 대기</label>
                                <input type="number" name="confirmation_wait" id="form-confirmation-wait" value="2" min="0" max="15">
                                <div class="help-text">확인 버튼 입력 전 대기</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-total-duration">총 녹음시간</label>
                                <input type="number" name="total_duration" id="form-total-duration" value="30" min="10" max="60">
                                <div class="help-text">전체 통화 녹음 시간</div>
                            </div>
                            <div class="form-group">
                                <label for="form-confirm-delay">확인 지연</label>
                                <input type="number" name="confirm_delay" id="form-confirm-delay" value="2" min="0" max="10">
                                <div class="help-text">반복 확인 DTMF 간격(초)</div>
                            </div>
                            <div class="form-group">
                                <label for="form-confirm-repeat">반복 횟수</label>
                                <input type="number" name="confirm_repeat" id="form-confirm-repeat" value="3" min="1" max="5">
                                <div class="help-text">확인 DTMF 전송 횟수</div>
                            </div>
                        </div>
                        
                        <h3 style="margin: 30px 0 20px; color: #4a5568;">📞 DTMF 설정</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="form-dtmf-pattern">DTMF 패턴</label>
                                <input type="text" name="dtmf_pattern" id="form-dtmf-pattern" value="{ID}#" placeholder="{ID}# 또는 1,2,3">
                                <div class="help-text">{ID}는 식별번호로 치환됩니다</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="form-confirmation-dtmf">확인 DTMF</label>
                                <input type="text" name="confirmation_dtmf" id="form-confirmation-dtmf" value="1" placeholder="1">
                                <div class="help-text">확인을 위해 누를 번호</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="form-notes">메모</label>
                            <textarea name="notes" id="form-notes" rows="3" placeholder="추가 메모나 특이사항"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button type="submit" class="btn" id="submit-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
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
                <div class="card-header">
                    💡 사용 팁
                </div>
                <div class="card-body">
                    <ul style="line-height: 1.8; color: #4a5568;">
                        <li><strong>{ID}</strong>: 광고 문자에서 추출한 식별번호로 자동 치환됩니다</li>
                        <li><strong>{Phone}</strong>: 사용자 전화번호로 치환됩니다 (필요한 경우)</li>
                        <li><strong>DTMF 타이밍</strong>: 통화 시작 후 첫 번째 DTMF를 보낼 시점 (초)</li>
                        <li><strong>확인 DTMF</strong>: 식별번호 입력 후 확인을 위해 누를 번호 (보통 1)</li>
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
            
            function editPattern(number) {
                const pattern = patterns[number];
                if (!pattern) return;
                
                // 폼 제목과 액션 변경
                document.getElementById('form-header').textContent = '✏️ 패턴 수정';
                document.getElementById('form-action').value = 'edit';
                document.getElementById('submit-btn').innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg> 패턴 수정';
                document.getElementById('cancel-btn').style.display = 'inline-flex';
                
                // 폼 필드 채우기
                document.getElementById('form-number').value = number;
                document.getElementById('form-name').value = pattern.name || '';
                document.getElementById('form-description').value = pattern.description || '';
                document.getElementById('form-initial-wait').value = pattern.initial_wait || 3;
                document.getElementById('form-dtmf-timing').value = pattern.dtmf_timing || 6;
                document.getElementById('form-confirmation-wait').value = pattern.confirmation_wait || 2;
                document.getElementById('form-total-duration').value = pattern.total_duration || 30;
                document.getElementById('form-confirm-delay').value = pattern.confirm_delay || 2;
                document.getElementById('form-confirm-repeat').value = pattern.confirm_repeat || 3;
                document.getElementById('form-dtmf-pattern').value = pattern.dtmf_pattern || '{ID}#';
                document.getElementById('form-confirmation-dtmf').value = pattern.confirmation_dtmf || '1';
                document.getElementById('form-notes').value = pattern.notes || '';
                
                // 번호 필드를 읽기 전용으로 설정
                document.getElementById('form-number').readOnly = true;
                
                // 폼으로 스크롤
                document.getElementById('pattern-form').scrollIntoView({ behavior: 'smooth' });
            }
            
            function cancelEdit() {
                // 폼 초기화
                document.getElementById('form-header').textContent = '➕ 새 패턴 추가';
                document.getElementById('form-action').value = 'add';
                document.getElementById('submit-btn').innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg> 패턴 추가';
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
                                const newRow = createPatternRow(number, pattern);
                                tbody.insertBefore(newRow, tbody.firstChild);
                                newRow.classList.add('new-pattern');
                                
                                // 알림 표시
                                showNotification(`새 패턴이 추가되었습니다: ${number}`);
                            }
                        });
                        
                        lastCheckTime = new Date().toISOString();
                    })
                    .catch(error => console.error('패턴 확인 오류:', error));
            }
            
            // 패턴 행 생성
            function createPatternRow(number, pattern) {
                const tr = document.createElement('tr');
                tr.setAttribute('data-number', number);
                tr.setAttribute('data-created', pattern.created_at || '');
                
                const autoGenBadge = pattern.auto_generated ? '<span class="badge badge-auto">자동</span>' : '';
                const verifiedBadge = pattern.needs_verification ? 
                    '<span class="badge badge-unverified">미검증</span>' : 
                    '<span class="badge badge-verified">검증됨</span>';
                const typeBadge = (pattern.pattern_type === 'confirm_only') ? '<span class="badge badge-unverified">Confirm-Only</span>' :
                                  (pattern.pattern_type === 'id_only') ? '<span class="badge badge-verified">ID-Only</span>' : '';
                const supportedBadge = (pattern.auto_supported === false) ? '<span class="badge badge-unverified">수동</span>' : '';
                
                tr.innerHTML = `
                    <td><strong>${number}</strong></td>
                    <td>
                        ${pattern.name} ${autoGenBadge} ${typeBadge} ${supportedBadge}
                    </td>
                `;
                
                return tr;
            }
            
            // 알림 표시
            function showNotification(message) {
                const notification = document.createElement('div');
                notification.className = 'alert alert-success';
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '1000';
                notification.textContent = '🎉 ' + message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
            
            // 10초마다 새 패턴 확인
            setInterval(checkForNewPatterns, 10000);

            // 패턴 삭제 처리
            function deletePattern(number) {
                if (!confirm('정말 삭제하시겠습니까?')) {
                    return;
                }
                
                // AJAX로 삭제 요청
                fetch('delete_pattern.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'number=' + encodeURIComponent(number)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 성공 메시지 표시
                        showMessage(data.message, 'success');
                        
                        // 해당 행 제거
                        const row = document.querySelector(`tr[data-number="${number}"]`);
                        if (row) {
                            row.remove();
                        }
                    } else {
                        // 오류 메시지 표시
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('패턴 삭제 중 오류가 발생했습니다.', 'error');
                });
            }

            // 메시지 표시 함수
            function showMessage(message, type = 'info') {
                const messageDiv = document.createElement('div');
                messageDiv.className = `alert alert-${type}`;
                messageDiv.textContent = message;
                
                const container = document.querySelector('.container');
                container.insertBefore(messageDiv, container.firstChild);
                
                // 3초 후 메시지 제거
                setTimeout(() => {
                    messageDiv.remove();
                }, 3000);
            }

            // 패턴 분석 진행 상태 확인
            function checkPatternAnalysisProgress() {
                fetch('get_pattern_analysis_progress.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 진행 상태 표시 업데이트
                            updateProgressDisplay(data);
                            
                            // 분석이 완료되지 않았고 리프레시 방지가 필요한 경우
                            if (!data.completed && data.prevent_refresh) {
                                // 1초 후 다시 확인
                                setTimeout(checkPatternAnalysisProgress, 1000);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error checking progress:', error);
                    });
            }

            // 진행 상태 표시 업데이트
            function updateProgressDisplay(data) {
                const progressBar = document.getElementById('analysis-progress');
                const progressMessage = document.getElementById('analysis-message');
                
                if (progressBar && progressMessage) {
                    progressBar.style.width = data.percentage + '%';
                    progressMessage.textContent = data.message;
                    
                    if (data.completed) {
                        progressBar.classList.add('completed');
                    }
                }
            }

            // 페이지 로드 시 진행 상태 확인 시작
            document.addEventListener('DOMContentLoaded', function() {
                checkPatternAnalysisProgress();
            });
        </script>
    </div>
</body>
</html>
<?php
        } // CLI 체크 닫는 괄호
?>