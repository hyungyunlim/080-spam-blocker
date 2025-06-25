<?php
require_once __DIR__.'/auth.php';
$db=new SQLite3(__DIR__.'/spam.db');
$logged=is_logged_in();
$err='';$msg='';
if($logged){
  $uid=(int)$_SESSION['user_id'];
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['embed_action']??'')==='unsub'){
      $p080=preg_replace('/[^0-9]/','',$_POST['phone080']??'');
      $ident=preg_replace('/[^0-9]/','',$_POST['ident']??'');
      if($p080==''||$ident==''){$err='번호와 식별번호를 입력하세요.';}else{
         $cmd="php process_v2.php --phone={$p080} --id={$ident} --notification=".$_SESSION['phone']." --auto > /dev/null 2>&1 &";
         exec($cmd);
         $msg='요청을 전송했습니다.';
      }
  }
  $rows=$db->query("SELECT * FROM unsubscribe_calls WHERE user_id={$uid} ORDER BY id DESC LIMIT 50");
}
?>
<?php if(!$logged): ?>
  <div class="card"><div class="card-body" style="text-align:center">
    <a class="btn" href="login.php">🔑 로그인하여 개인 대시보드 보기</a>
  </div></div>
<?php else: ?>
  <div class="card"><div class="card-header">👋 내 대시보드
     <span style="margin-left:auto"></span><a href="logout.php" class="btn btn-small btn-secondary">로그아웃</a></div>
     <div class="card-body">
       <?php if($err) echo '<p style="color:red">'.$err.'</p>'; if($msg) echo '<p style="color:green">'.$msg.'</p>'; ?>
       <form method="post" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap">
         <input type="hidden" name="embed_action" value="unsub">
         <input name="phone080" placeholder="080번호" maxlength="11" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:6px" required>
         <input name="ident" placeholder="식별번호" maxlength="8" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:6px" required>
         <button class="btn btn-small" type="submit">요청 보내기</button>
       </form>
       <h4 style="margin-bottom:10px">최근 요청 내역</h4>
       <?php $any=false; while($r=$rows->fetchArray(SQLITE3_ASSOC)){ if(!$any){echo '<table><tr><th>ID</th><th>080</th><th>식별</th><th>상태</th><th>신뢰도</th></tr>'; $any=true;} $cls='status-pending'; switch($r['status']){case 'success':$cls='status-success';break;case 'failed':$cls='status-failed';break;case 'uncertain':$cls='status-uncertain';break;} echo '<tr><td>'.$r['id'].'</td><td>'.$r['phone080'].'</td><td>'.$r['identification'].'</td><td class="'.$cls.'">'.$r['status'].'</td><td>'.($r['confidence']??'').'</td></tr>'; } if(!$any) echo '<p>아직 요청 내역이 없습니다.</p>'; else echo '</table>'; ?>
     </div></div>
<?php endif; ?> 