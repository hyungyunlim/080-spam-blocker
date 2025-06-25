<?php
// api/recent_activity.php – Returns paginated recent activity for admin infinite scroll
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$limit  = 30;
try {
    $db = new SQLite3(__DIR__ . '/../spam.db');
    $stmt = $db->prepare("SELECT u.phone, uc.created_at, '080' || uc.phone080 as activity, uc.phone080, uc.identification, uc.pattern_source, CASE WHEN uc.status IN ('success','completed') THEN 'success' ELSE 'failed' END as status, (SELECT si.raw_text FROM sms_incoming si WHERE si.phone080 = uc.phone080 ORDER BY si.received_at DESC LIMIT 1) as spam_content FROM unsubscribe_calls uc JOIN users u ON uc.user_id = u.id ORDER BY uc.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $items = [];
    while($row = $result->fetchArray(SQLITE3_ASSOC)){
        $items[] = $row;
    }
    echo json_encode(['success'=>true,'items'=>$items]);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?> 