<?php
require_once __DIR__.'/../auth.php';
require_once __DIR__.'/../sms_sender.php';
header('Content-Type: application/json');
$input=json_decode(file_get_contents('php://input'),true);
$phone=preg_replace('/[^0-9]/','',$input['phone']??'');
if($phone===''){echo json_encode(['success'=>false,'message'=>'phone required']);exit;}
$db=new SQLite3(__DIR__.'/../spam.db');
$db->exec("INSERT OR IGNORE INTO users(phone) VALUES('{$phone}')");
$row=$db->querySingle("SELECT id FROM users WHERE phone='{$phone}'",true);
$code=str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
$exp=time()+600;
$db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$row['id']},'{$code}',{$exp})");
$result=(new SMSSender())->sendVerificationCode($phone,$code);
$response=['success'=>$result['success'],'message'=>$result['message']??''];
if($result['success']){$response['expires_at']=$exp;}
else{$response['debug']=$result['debug']??null;}
echo json_encode($response,JSON_UNESCAPED_UNICODE);
?> 