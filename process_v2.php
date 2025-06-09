<?php
// PHP 오류를 화면에 모두 표시
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$spamMessage = $_POST['spam_message'] ?? '';
$manualDtmf = $_POST['dtmf_sequence'] ?? '';
$manualPhone = $_POST['phone_number'] ?? '';

if (empty($spamMessage)) {
    die("오류: 광고 문자 내용이 비어있습니다.");
}

// 080 번호 추출
preg_match('/080-?\d{3,4}-?\d{4}/', $spamMessage, $matches);
if (empty($matches)) {
    die("오류: 문자 내용에서 080으로 시작하는 번호를 찾을 수 없습니다.");
}
$phoneNumber = str_replace('-', '', $matches[0]);

// 식별번호 추출 (6자리 숫자 또는 수동 입력 전화번호 사용)
preg_match('/\b\d{6}\b/', $spamMessage, $idMatches);
$identificationNumber = $idMatches[0] ?? '';

// 식별번호가 없고 수동으로 전화번호를 입력했으면 사용
$phoneToUse = '';
if (empty($identificationNumber) && !empty($manualPhone)) {
    // 전화번호에서 하이픈만 제거, 010은 그대로 유지
    $phoneToUse = preg_replace('/[^0-9]/', '', $manualPhone);
    // 한국 휴대폰 번호 형식 확인 (010으로 시작하는 11자리)
    if (strlen($phoneToUse) == 11 && substr($phoneToUse, 0, 3) == '010') {
        // 010은 그대로 유지
        $identificationNumber = $phoneToUse;
    } else {
        // 잘못된 형식인 경우 그대로 사용
        $identificationNumber = $phoneToUse;
    }
    // phoneToUse는 그대로 유지하여 {Phone} 변수에서 사용
}

// 패턴 데이터베이스 로드
$patternsFile = __DIR__ . '/patterns.json';
$patterns = [];
if (file_exists($patternsFile)) {
    $patterns = json_decode(file_get_contents($patternsFile), true)['patterns'] ?? [];
}

// 해당 번호의 패턴 찾기 (없으면 default 사용)
$pattern = $patterns[$phoneNumber] ?? $patterns['default'] ?? [
    'initial_wait' => 3,
    'dtmf_timing' => 6,
    'dtmf_pattern' => '{ID}#',
    'confirmation_wait' => 5,
    'confirmation_dtmf' => '1',
    'total_duration' => 30
];

// 수동 입력이 있으면 그것을 사용, 없으면 패턴 사용
if (!empty($manualDtmf)) {
    $dtmfToSend = preg_replace('/[,\s]/', '', $manualDtmf);
} else {
    // 패턴에서 변수 치환 ({ID}, {Phone} 지원)
    $dtmfToSend = $pattern['dtmf_pattern'];
    $dtmfToSend = str_replace('{ID}', $identificationNumber, $dtmfToSend);
    $dtmfToSend = str_replace('{Phone}', $phoneToUse ?: $identificationNumber, $dtmfToSend);
    $dtmfToSend .= $pattern['confirmation_dtmf'];
}

echo "추출된 080번호: " . $phoneNumber . "\n";
echo "식별번호: " . $identificationNumber . (!empty($phoneToUse) ? " (수동 입력된 전화번호)" : "") . "\n";
echo "사용된 패턴: " . ($pattern['name'] ?? 'Unknown') . "\n";
echo "DTMF 시퀀스: " . $dtmfToSend . "\n\n";

// AstDB에 변수 저장 (패턴 정보 포함)
$uniqueId = uniqid();
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf {$dtmfToSend}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} recnum {$phoneNumber}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} pattern " . json_encode($pattern) . "\"");
echo "AstDB에 변수 저장 완료: ID={$uniqueId}\n";

// Call File 내용 생성
$callFileContent = "Channel: quectel/quectel0/{$phoneNumber}\n";
$callFileContent .= "Context: callfile-handler\n";
$callFileContent .= "Extension: s\n";
$callFileContent .= "Priority: 1\n";
$callFileContent .= "Set: CALLFILE_ID={$uniqueId}\n";
$callFileContent .= "Set: INITIAL_WAIT={$pattern['initial_wait']}\n";
$callFileContent .= "Set: DTMF_TIMING={$pattern['dtmf_timing']}\n";
$callFileContent .= "Set: CONFIRM_WAIT={$pattern['confirmation_wait']}\n";
$callFileContent .= "Set: TOTAL_DURATION={$pattern['total_duration']}\n";

// Call File 생성 및 이동
$tempFile = tempnam(sys_get_temp_dir(), 'call_');
file_put_contents($tempFile, $callFileContent);
chown($tempFile, 'asterisk');
chgrp($tempFile, 'asterisk');
$spoolDir = '/var/spool/asterisk/outgoing/';
$finalFile = $spoolDir . basename($tempFile);
if (rename($tempFile, $finalFile)) {
    echo "성공: Call File이 생성되었습니다. Asterisk가 곧 전화를 걸 것입니다.";
    
    // 패턴 학습 모드 안내
    echo "\n\n💡 팁: 이 번호가 처음이거나 패턴이 맞지 않으면, 녹음을 들어보고 patterns.json을 업데이트하세요!";
} else {
    echo "오류: Call File을 생성하지 못했습니다.";
}
?> 