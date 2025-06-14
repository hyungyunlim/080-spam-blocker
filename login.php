<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/sms_sender.php';

$db = new SQLite3(__DIR__.'/spam.db');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $phone = preg_replace('/[^0-9]/','', $_POST['phone'] ?? '');
    if($phone==='') $err='전화번호를 입력하세요.'; else {
        // ensure user row
        $db->exec("INSERT OR IGNORE INTO users(phone) VALUES('{$phone}')");
        $row = $db->querySingle("SELECT id,verified FROM users WHERE phone='{$phone}'", true);
        // always send code (login each time)
        $code = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
        $exp  = time()+600;
        $db->exec("INSERT INTO verification_codes(user_id,code,expires_at) VALUES({$row['id']},'{$code}',{$exp})");
        (new SMSSender())->sendVerificationCode($phone,$code);
        $_SESSION['login_phone']=$phone;
        session_write_close();
        header('Location: verify.php');
        exit;
    }
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>로그인</title><link rel="stylesheet" href="style.css"></head><body>
<h2>휴대폰 인증 로그인</h2>
<?php if(isset($err)) echo '<p style="color:red">'.$err.'</p>'; ?>
<form method="post">
    <label>전화번호(010…): <input name="phone" required></label>
    <button type="submit">인증번호 받기</button>
</form>
</body></html> 