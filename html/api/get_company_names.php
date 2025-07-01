<?php
require_once __DIR__ . '/../PatternManager.php';

header('Content-Type: application/json');

$number = $_GET['number'] ?? null;

if (empty($number)) {
    echo json_encode(['success' => false, 'error' => '전화번호를 입력해주세요.']);
    exit;
}

// PatternManager.php 파일이 클래스만 로드하도록 래퍼(pattern_manager.php)를 사용
require_once __DIR__ . '/../pattern_manager.php';

$pm = new PatternManager();
$names = $pm->getCompanyNamesByNumber($number);

echo json_encode(['success' => true, 'names' => $names]);
