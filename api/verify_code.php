<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json');
$input=json_decode(file_get_contents('php://input'),true);
$phone=preg_replace('/[^0-9]/','',$input['phone']??'');
$code=preg_replace('/[^0-9]/','',$input['code']??'');
if($phone===''||$code===''){
    echo json_encode(['success'=>false,'message'=>'phone/code required']);exit;
}
$db=new SQLite3(__DIR__.'/../spam.db');
$user=$db->querySingle("SELECT id FROM users WHERE phone='{$phone}'",true);
if(!$user){echo json_encode(['success'=>false,'message'=>'user not found']);exit;}
$vc=$db->querySingle("SELECT id,expires_at FROM verification_codes WHERE user_id={$user['id']} AND code='{$code}' AND used=0 ORDER BY id DESC LIMIT 1",true);
if(!$vc){echo json_encode(['success'=>false,'message'=>'invalid code']);exit;}
if(time()>$vc['expires_at']){echo json_encode(['success'=>false,'message'=>'code expired']);exit;}
$db->exec("UPDATE users SET verified=1,verified_at=datetime('now') WHERE id={$user['id']}");
$db->exec("UPDATE verification_codes SET used=1 WHERE id={$vc['id']}");
$res=$db->query("SELECT id, phone080, identification FROM sms_incoming WHERE user_id={$user['id']} AND processed=0");
while($row=$res->fetchArray(SQLITE3_ASSOC)){
   $cmd='php '. __DIR__.'/../process_v2.php --phone='.$row['phone080'].' --id='.$row['identification'].' --notification='.$phone.' --auto > /dev/null 2>&1 &';
   exec($cmd);
   $db->exec("UPDATE sms_incoming SET processed=1 WHERE id={$row['id']}");
}
$_SESSION['user_id']=$user['id'];
$_SESSION['phone']=$phone;

echo json_encode(['success'=>true]);
?> 