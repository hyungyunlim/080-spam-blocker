<?php
require_once __DIR__ . '/auth.php';

// 디버그 정보 수집
$debugInfo = debug_session_info();

header('Content-Type: application/json');
echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>