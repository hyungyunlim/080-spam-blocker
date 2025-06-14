<?php
require_once __DIR__.'/auth.php';
$db=new SQLite3(__DIR__.'/spam.db');
$phone=$_SESSION['login_phone']??'';
if($phone==''){header('Location: login.php');exit;}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $code=$_POST['code']??'';
 $phoneClean=preg_replace('/[^0-9]/','',$phone);
 $user=$db->querySingle("SELECT id FROM users WHERE phone='{$phoneClean}'",true);
 if($user){
   $vc=$db->querySingle("SELECT id,expires_at FROM verification_codes WHERE user_id={$user['id']} AND code='{$code}' AND used=0",true);
   if($vc && time() <= $vc['expires_at']){
      $db->exec("UPDATE users SET verified=1,verified_at=datetime('now') WHERE id={$user['id']}");
      $db->exec("UPDATE verification_codes SET used=1 WHERE id={$vc['id']}");

      // process pending sms_incoming
      $res=$db->query("SELECT id, phone080, identification FROM sms_incoming WHERE user_id={$user['id']} AND processed=0");
      while($row=$res->fetchArray(SQLITE3_ASSOC)){
          $cmd='php '. __DIR__.'/process_v2.php --phone='.$row['phone080'].' --id='.$row['identification'].' --notification='.$phoneClean.' --auto > /dev/null 2>&1 &';
          exec($cmd);
          $db->exec("UPDATE sms_incoming SET processed=1 WHERE id={$row['id']}");
      }

      unset($_SESSION['login_phone']);
      $_SESSION['user_id']=$user['id'];
      $_SESSION['phone']=$phoneClean;
      session_write_close();
      require_once __DIR__.'/auth.php';
      set_flash('휴대폰 인증이 완료되었습니다.');
      require __DIR__.'/verify_success.php';
      exit;
   } else { $err='인증번호가 올바르지 않거나 만료되었습니다.'; }
 }
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>인증번호 확인</title><link rel="stylesheet" href="style.css"></head><body>
<h3><?php echo htmlspecialchars($phone); ?> 로 전송된 인증번호 입력</h3>
<?php if($err) echo '<p style="color:red">'.$err.'</p>'; ?>
<form method="post"><input name="code" maxlength="6" required><button type="submit">확인</button></form>
</body></html> 