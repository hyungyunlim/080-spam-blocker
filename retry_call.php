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