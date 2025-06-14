<?php
require_once __DIR__.'/auth.php';
require_login();
$db=new SQLite3(__DIR__.'/spam.db');
$uid=(int)$_SESSION['user_id'];
// handle manual send
$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $p080=preg_replace('/[^0-9]/','',$_POST['phone080']??'');
  $ident=preg_replace('/[^0-9]/','',$_POST['ident']??'');
  if($p080==''||$ident==''){$err='번호와 식별번호를 입력하세요.';}else{
     $cmd="php process_v2.php --phone={$p080} --id={$ident} --notification=".$_SESSION['phone']." --auto > /dev/null 2>&1 &";
     exec($cmd);
     $msg='요청을 전송했습니다.';
  }
}
$rows=$db->query("SELECT * FROM unsubscribe_calls WHERE user_id={$uid} ORDER BY id DESC LIMIT 50");
$flash=get_flash();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>대시보드</title><link rel="stylesheet" href="style.css"></head><body>
<?php if($flash) echo '<p style="color:green">'.htmlspecialchars($flash).'</p>'; ?>
<h2>안녕하세요, <?php echo htmlspecialchars($_SESSION['phone']); ?></h2>
<a href="logout.php">로그아웃</a>
<h3>새 080 수신거부 요청</h3>
<?php if($err) echo '<p style="color:red">'.$err.'</p>'; if($msg) echo '<p style="color:green">'.$msg.'</p>'; ?>
<form method="post">
  080 번호: <input name="phone080" required maxlength="11"> 식별번호: <input name="ident" required maxlength="8">
  <button type="submit">요청 보내기</button>
</form>
<h3>최근 요청 내역</h3>
<table border="1"><tr><th>ID</th><th>080</th><th>식별</th><th>상태</th><th>신뢰도</th></tr>
<?php
$any=false;
while($r=$rows->fetchArray(SQLITE3_ASSOC)){
  if(!$any){echo '<table border="1"><tr><th>ID</th><th>080</th><th>식별</th><th>상태</th><th>신뢰도</th></tr>'; $any=true;}
  $cls='status-pending';
  switch($r['status']){
     case 'success':$cls='status-success';break;
     case 'failed':$cls='status-failed';break;
     case 'uncertain':$cls='status-uncertain';break;
  }
  echo '<tr><td>'.$r['id'].'</td><td>'.$r['phone080'].'</td><td>'.$r['identification'].'</td><td class="'.$cls.'">'.$r['status'].'</td><td>'.($r['confidence']??'').'</td></tr>';
}
if(!$any) echo '<p>아직 요청 내역이 없습니다.</p>'; else echo '</table>';
?>
</body></html> 