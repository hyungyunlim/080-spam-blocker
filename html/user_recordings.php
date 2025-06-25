<?php
require_once __DIR__.'/auth.php';
require_login();
header('Content-Type: application/json');

$uid=(int)$_SESSION['user_id'];
$db=new SQLite3(__DIR__.'/spam.db');
// gather recording_file names for user where file exists
$res=$db->query("SELECT recording_file FROM unsubscribe_calls WHERE user_id={$uid} AND recording_file IS NOT NULL ORDER BY id DESC");
$files=[];
while($row=$res->fetchArray(SQLITE3_ASSOC)){
    $fname=$row['recording_file'];
    $path='/var/spool/asterisk/monitor/'.$fname;
    if(!is_file($path)) continue;
    $files[] = parse_recording_filename_user($fname);
}

echo json_encode($files, JSON_UNESCAPED_UNICODE);

// helper similar to get_recordings.php but lightweight
function parse_recording_filename_user($filename){
    $info=[
        'filename'=>$filename,
        'title'=>'',
        'timestamp'=>0,
        'datetime'=>'',
        'path'=>'/var/spool/asterisk/monitor/'.$filename
    ];
    if(preg_match('/^(\d{8}-\d{6})/', $filename, $m)){
        $dt=DateTime::createFromFormat('Ymd-His',$m[1]);
        if($dt){
            $info['timestamp']=$dt->getTimestamp();
            $info['datetime']=$dt->format('Y-m-d H:i:s');
        }
    } else {
        $full='/var/spool/asterisk/monitor/'.$filename;
        if(file_exists($full)){
            $info['timestamp']=filemtime($full);
            $info['datetime']=date('Y-m-d H:i:s',$info['timestamp']);
        }
    }
    // 전화번호 추출 for title
    if(preg_match('/TO_(\d+)/', $filename, $m)){
        $info['title']=$m[1];
    }
    $info['file_size']=file_exists($info['path'])?filesize($info['path']):0;
    return $info;
}
?> 