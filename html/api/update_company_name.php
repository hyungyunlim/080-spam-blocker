<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

// 1. 로그인 확인
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.']);
    exit;
}

// 2. 요청 방식 확인 (POST만 허용)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '잘못된 요청 방식입니다.']);
    exit;
}

// 3. 파라미터 확인
$call_id = $_POST['call_id'] ?? null;
// company_names는 JSON 문자열 배열로 전달될 것으로 예상
$company_names_json = $_POST['company_names'] ?? '[]'; 
$company_names = json_decode($company_names_json, true);

if (empty($call_id) || !is_numeric($call_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '유효하지 않은 통화 ID입니다.']);
    exit;
}

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '잘못된 회사명 데이터 형식입니다.']);
    exit;
}

// 4. 데이터베이스 업데이트
try {
    $db = new SQLite3(__DIR__ . '/../spam.db');
    
    // 저장할 최종 JSON 문자열
    $final_json = json_encode($company_names, JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("UPDATE unsubscribe_calls SET company_name = :company_name WHERE id = :call_id");
    $stmt->bindValue(':company_name', $final_json, SQLITE3_TEXT);
    $stmt->bindValue(':call_id', $call_id, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    
    if ($result) {
        // 변경된 행이 있는지 확인
        if ($db->changes() > 0) {
            echo json_encode(['success' => true, 'message' => '회사명이 성공적으로 업데이트되었습니다.']);
        } else {
            // ID에 해당하는 레코드가 없을 수 있음
            echo json_encode(['success' => false, 'error' => '해당 통화 기록을 찾을 수 없습니다.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '데이터베이스 업데이트에 실패했습니다.']);
    }
    
    $db->close();
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("회사명 업데이트 오류: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '서버 오류가 발생했습니다.']);
}
