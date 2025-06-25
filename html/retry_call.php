<?php
// API: retry_call.php – initiate new unsubscribe call for given 080 number
header('Content-Type: text/plain; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){
    http_response_code(405);
    exit("Method not allowed");
}
$phone = isset($_POST['phone']) ? preg_replace('/[^0-9]/','',$_POST['phone']) : '';
$ident = isset($_POST['id']) ? preg_replace('/[^0-9]/','',$_POST['id']) : '';
$notify = isset($_POST['notify']) ? preg_replace('/[^0-9]/','',$_POST['notify']) : '';
if($phone===''){
    http_response_code(400);
    exit("전화번호를 확인할 수 없습니다.");
}

// NEW: 보조 검색 – id 생략 시 최근 DB 기록에서 자동 보완
if($ident===''){
    try{
        $db = new SQLite3(__DIR__.'/notifications.db');
        $row = $db->querySingle("SELECT identification FROM sms_incoming WHERE phone080='{$phone}' AND identification!='' ORDER BY id DESC LIMIT 1");
        if($row){ $ident = preg_replace('/[^0-9]/','', $row); }
    }catch(Throwable $e){ /* ignore */ }
}

// id 여전히 비어 있으면 오류 반환
if($ident===''){
    http_response_code(400);
    exit("식별번호(ID)를 찾을 수 없습니다. 다시 시도하시려면 ID를 입력해주세요.");
}

// 가짜 스팸 문자 내용을 만들어 process_v2.php 재사용
$_POST = [
    'spam_content' => 'AUTO_CALL '.$phone,
    'notification_phone' => $notify,
    'phone_number'      => $ident,
];
ob_start();
include __DIR__.'/process_v2.php';
$output = ob_get_clean();
echo $output;
?> 