<?php
// verification_handler.php – verify phone by code and process pending SMS

header('Content-Type: application/json; charset=utf-8');

$phone = preg_replace('/[^0-9]/','', $_GET['phone'] ?? '');
$code  = $_GET['code'] ?? '';
if($phone==='' || $code===''){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing phone or code']);
    exit;
}

$dbPath = __DIR__ . '/spam.db';
$db = new SQLite3($dbPath);

// ensure schema
$schemaFile = __DIR__ . '/schema.sql';
if(file_exists($schemaFile)) $db->exec(file_get_contents($schemaFile));

$usr = $db->querySingle("SELECT id,verified FROM users WHERE phone='{$phone}'", true);
if(!$usr){
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'User not found']);
    exit;
}

$uid = $usr['id'];
$now = time();
$vc = $db->querySingle("SELECT id,expires_at,used FROM verification_codes WHERE user_id={$uid} AND code='{$code}'", true);
if(!$vc){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid code']);
    exit;
}
if($vc['used']){
    echo json_encode(['success'=>false,'message'=>'Code already used']);
    exit;
}
if($now > $vc['expires_at']){
    echo json_encode(['success'=>false,'message'=>'Code expired']);
    exit;
}

// mark verified
$db->exec("UPDATE users SET verified=1, verified_at=datetime('now') WHERE id={$uid}");
$db->exec("UPDATE verification_codes SET used=1 WHERE id={$vc['id']}");

// Process pending SMS for this user
$res = $db->query("SELECT id, phone080, identification FROM sms_incoming WHERE user_id={$uid} AND processed=0");
$cnt=0;
while($row=$res->fetchArray(SQLITE3_ASSOC)){
    $cmd = 'php '. __DIR__ . '/process_v2.php --phone='.$row['phone080'].' --id='.$row['identification'].' --notification='.$phone.' --auto > /dev/null 2>&1 &';
    exec($cmd);
    $db->exec("UPDATE sms_incoming SET processed=1 WHERE id={$row['id']}");
    $cnt++;
}

echo json_encode(['success'=>true,'verified'=>true,'processed_sms'=>$cnt]);
?> 