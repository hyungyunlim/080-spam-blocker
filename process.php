<?php
// PHP 오류를 화면에 모두 표시
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', 1);
$spamMessage = $_POST['spam_message'] ?? ''; $dtmfSequence = $_POST['dtmf_sequence'] ?? '';
if (empty($spamMessage)) { die("오류: 광고 문자 내용이 비어있습니다."); }
preg_match('/080-?\d{3,4}-?\d{4}/', $spamMessage, $matches);
if (empty($matches)) { die("오류: 문자 내용에서 080으로 시작하는 번호를 찾을 수 없습니다."); }
$phoneNumber = str_replace('-', '', $matches[0]); $dtmfToSend = preg_replace('/[,\s]/', '', $dtmfSequence);
echo "추출된 번호: " . $phoneNumber . "\n"; echo "입력된 DTMF 시퀀스: " . $dtmfToSend . "\n\n";

// --- AstDB에 변수 저장 (가장 확실한 방법) ---
$uniqueId = uniqid(); // 각 통화를 구별할 고유 ID
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf {$dtmfToSend}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} recnum {$phoneNumber}\"");
echo "AstDB에 변수 저장 완료: ID={$uniqueId}\n";

// --- Call File 내용 생성 ---
$callFileContent = "Channel: quectel/quectel0/{$phoneNumber}\n";
$callFileContent .= "Context: callfile-handler\n"; // 이제 이 컨텍스트는 통화가 연결된 '후에' 실행됨
$callFileContent .= "Extension: s\n";
$callFileContent .= "Priority: 1\n";
$callFileContent .= "Set: CALLFILE_ID={$uniqueId}\n"; // 생성한 고유 ID를 채널 변수로 전달

// --- Call File 생성 및 이동 ---
$tempFile = tempnam(sys_get_temp_dir(), 'call_');
file_put_contents($tempFile, $callFileContent);
chown($tempFile, 'asterisk'); chgrp($tempFile, 'asterisk');
$spoolDir = '/var/spool/asterisk/outgoing/';
$finalFile = $spoolDir . basename($tempFile);
if (rename($tempFile, $finalFile)) {
    echo "성공: Call File이 생성되었습니다. Asterisk가 곧 전화를 걸 것입니다.";
} else {
    echo "오류: Call File을 생성하지 못했습니다.";
}
?>
