<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>080 패턴 관리자</title>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .add-form { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
        input[type="text"], input[type="number"] { width: 200px; margin: 5px; padding: 5px; }
        button { padding: 8px 15px; margin: 5px; cursor: pointer; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>080 패턴 관리자</h1>
    
    <?php
    $patternsFile = __DIR__ . '/patterns.json';
    $message = '';
    
    // 패턴 추가/수정 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $patterns = json_decode(file_get_contents($patternsFile), true);
        
        if ($action === 'add') {
            $number = $_POST['number'] ?? '';
            $patterns['patterns'][$number] = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'initial_wait' => intval($_POST['initial_wait'] ?? 3),
                'dtmf_timing' => intval($_POST['dtmf_timing'] ?? 6),
                'dtmf_pattern' => $_POST['dtmf_pattern'] ?? '{ID}#',
                'confirmation_wait' => intval($_POST['confirmation_wait'] ?? 5),
                'confirmation_dtmf' => $_POST['confirmation_dtmf'] ?? '1',
                'total_duration' => intval($_POST['total_duration'] ?? 30),
                'notes' => $_POST['notes'] ?? ''
            ];
            
            if (file_put_contents($patternsFile, json_encode($patterns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $message = '<p class="success">패턴이 추가되었습니다!</p>';
            }
        }
        
        if ($action === 'delete') {
            $number = $_POST['number'] ?? '';
            unset($patterns['patterns'][$number]);
            file_put_contents($patternsFile, json_encode($patterns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = '<p class="success">패턴이 삭제되었습니다!</p>';
        }
    }
    
    // 현재 패턴 로드
    $patterns = json_decode(file_get_contents($patternsFile), true);
    ?>
    
    <?php echo $message; ?>
    
    <h2>현재 등록된 패턴</h2>
    <table>
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
            <th>액션</th>
        </tr>
        <?php foreach ($patterns['patterns'] as $number => $pattern): ?>
        <tr>
            <td><?php echo htmlspecialchars($number); ?></td>
            <td><?php echo htmlspecialchars($pattern['name']); ?></td>
            <td><?php echo htmlspecialchars($pattern['description'] ?? ''); ?></td>
            <td><?php echo $pattern['initial_wait']; ?>초</td>
            <td><?php echo $pattern['dtmf_timing']; ?>초</td>
            <td><?php echo htmlspecialchars($pattern['dtmf_pattern']); ?></td>
            <td><?php echo $pattern['confirmation_wait']; ?>초</td>
            <td><?php echo htmlspecialchars($pattern['confirmation_dtmf']); ?></td>
            <td><?php echo $pattern['total_duration']; ?>초</td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="number" value="<?php echo $number; ?>">
                    <button type="submit" onclick="return confirm('정말 삭제하시겠습니까?')">삭제</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <div class="add-form">
        <h2>새 패턴 추가</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            
            <label>080 번호: <input type="text" name="number" placeholder="0801234567" required></label><br>
            <label>이름: <input type="text" name="name" placeholder="회사명 패턴" required></label><br>
            <label>설명: <input type="text" name="description" placeholder="패턴 설명"></label><br>
            
            <h3>타이밍 설정 (초)</h3>
            <label>초기 대기: <input type="number" name="initial_wait" value="3" min="0" max="10"></label><br>
            <label>DTMF 타이밍: <input type="number" name="dtmf_timing" value="6" min="0" max="20"></label><br>
            <label>확인 대기: <input type="number" name="confirmation_wait" value="5" min="0" max="15"></label><br>
            <label>총 녹음시간: <input type="number" name="total_duration" value="30" min="10" max="60"></label><br>
            
            <h3>DTMF 설정</h3>
            <label>DTMF 패턴: <input type="text" name="dtmf_pattern" value="{ID}#" placeholder="{ID}# 또는 1,2,3"></label><br>
            <label>확인 DTMF: <input type="text" name="confirmation_dtmf" value="1" placeholder="1"></label><br>
            
            <label>메모: <input type="text" name="notes" placeholder="추가 메모"></label><br>
            
            <button type="submit">패턴 추가</button>
        </form>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 8px;">
        <h3>💡 사용 팁</h3>
        <ul>
            <li><strong>{ID}</strong>: 광고 문자에서 추출한 6자리 식별번호로 자동 치환됩니다</li>
            <li><strong>DTMF 타이밍</strong>: 통화 시작 후 첫 번째 DTMF를 보낼 시점 (초)</li>
            <li><strong>확인 DTMF</strong>: 식별번호 입력 후 확인을 위해 누를 번호 (보통 1)</li>
            <li>새로운 080 번호는 먼저 <strong>default</strong> 패턴으로 테스트 후 조정하세요</li>
        </ul>
    </div>
</body>
</html> 