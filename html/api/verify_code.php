<?php
require_once __DIR__.'/../auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// Additional headers for mobile browser compatibility
header('X-Accel-Expires: 0');
header('Vary: *');
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
   // Log the pending call for debugging
   $logMessage = sprintf(
       "[%s] Processing pending SMS: phone=%s, id=%s, notify=%s\n",
       date('Y-m-d H:i:s'),
       $row['phone080'],
       $row['identification'],
       $phone
   );
   file_put_contents('/tmp/call_process.log', $logMessage, FILE_APPEND);
   
   // Use CLI-specific script to avoid session conflicts
   $cmd = sprintf(
       'cd %s && php process_cli.php --phone=%s --id=%s --notification=%s --auto >> /tmp/call_process.log 2>&1 &',
       escapeshellarg(__DIR__ . '/..'),
       escapeshellarg($row['phone080']),
       escapeshellarg($row['identification']),
       escapeshellarg($phone)
   );
   
   // Log the command being executed
   file_put_contents('/tmp/call_process.log', "[" . date('Y-m-d H:i:s') . "] Executing: $cmd\n", FILE_APPEND);
   
   // Execute the background process
   exec($cmd, $output, $returnCode);
   
   // Log execution result
   file_put_contents('/tmp/call_process.log', "[" . date('Y-m-d H:i:s') . "] Command return code: $returnCode\n", FILE_APPEND);
   
   // Small delay to let the background process initialize
   usleep(200000); // 0.2 second
   
   $db->exec("UPDATE sms_incoming SET processed=1 WHERE id={$row['id']}");
}
// Set session data and regenerate ID for security
$_SESSION['user_id']=$user['id'];
$_SESSION['phone']=$phone;

// Force session save before regenerating
session_write_close();

// Start new session and regenerate ID for security (prevents session fixation)
session_start();
$_SESSION['user_id']=$user['id'];
$_SESSION['phone']=$phone;

// Update last access for user
update_last_access($phone);

// Force final session save
session_write_close();

// Add small delay to ensure all operations complete
usleep(500000); // 0.5 second delay

echo json_encode([
    'success'=>true,
    'redirect'=>true,
    'user_phone'=>$phone,
    'logged_in'=>true
]);
?>