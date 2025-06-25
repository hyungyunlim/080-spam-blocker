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
// Also keep code in session for API verification fallback
$_SESSION['api_verify_phone']   = $phone;
$_SESSION['api_verify_code']    = $code;
$_SESSION['api_verify_expires'] = $exp;
// Log the SMS sending attempt
$logEntry = sprintf(
    "[%s] SMS Send Attempt: phone=%s, code=%s, user_id=%s\n",
    date('Y-m-d H:i:s'),
    $phone,
    $code,
    $row['id']
);
file_put_contents('/tmp/sms_debug.log', $logEntry, FILE_APPEND);

$result=(new SMSSender())->sendVerificationCode($phone,$code);

// Log the result with full debug info
$resultLogEntry = sprintf(
    "[%s] SMS Send Result: success=%s, message=%s, debug=%s\n",
    date('Y-m-d H:i:s'),
    $result['success'] ? 'true' : 'false',
    $result['message'] ?? 'no message',
    json_encode($result['debug'] ?? 'no debug info', JSON_UNESCAPED_UNICODE)
);
file_put_contents('/tmp/sms_debug.log', $resultLogEntry, FILE_APPEND);

// Prepare response with debug info for troubleshooting
$response = [
    'success' => $result['success'],
    'message' => $result['message'] ?? ''
];
if ($result['success']) {
    $response['expires_at'] = $exp;
    $response['debug'] = [
        'phone'   => $phone,
        'user_id' => $row['id'],
        'sms_debug' => $result['debug'] ?? 'no sms debug'
    ];
} else {
    $response['debug'] = $result['debug'] ?? null;
}
echo json_encode($response,JSON_UNESCAPED_UNICODE);
?> 