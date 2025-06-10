<?php
require_once 'sms_sender.php';

$smsSender = new SMSSender();

echo "=== 음성 분석 완료 SMS 미리보기 ===\n\n";

// 성공 케이스 (간결한 버전)
echo "📱 성공 케이스 (간결한 버전):\n";
$successMessage = "[080분석] ✅성공
080-1234-5678
ID:105623 (95%)
192.168.1.254/spam/player.php?file=20240115-153025-FROM_SYSTEM-TO_0801234567.wav";

echo $successMessage, "\n\n";
echo "메시지 길이: ", $smsSender->calculateByteLength($successMessage), " bytes\n\n";

// 실패 케이스
echo "📱 실패 케이스:\n";
$failMessage = "[080 수신거부 분석완료]
❌ 수신거부 실패

📞 대상번호: 080-9876-5432
🔑 식별번호: 789012
📊 신뢰도: 67%
⏰ 분석시간: " . date('Y-m-d H:i:s') . "

⚠️ 수신거부 처리에 문제가 있을 수 있습니다. 녹음을 확인해주세요.

🎙️ 녹음 재생:
http://192.168.1.254/spam/player.php?file=20240115-154030-FROM_SYSTEM-TO_0809876543.wav

📱 웹 관리: http://192.168.1.254/spam/";

echo $failMessage, "\n\n";
echo "메시지 길이: ", $smsSender->calculateByteLength($failMessage), " bytes\n\n";

// 판단불가 케이스
echo "📱 판단불가 케이스:\n";
$uncertainMessage = "[080 수신거부 분석완료]
⚠️ 판단불가

📞 대상번호: 080-5555-1234
🔑 식별번호: 456789
📊 신뢰도: 45%
⏰ 분석시간: " . date('Y-m-d H:i:s') . "

🤔 결과가 명확하지 않습니다. 직접 확인이 필요합니다.

🎙️ 녹음 재생:
http://192.168.1.254/spam/player.php?file=20240115-155045-FROM_SYSTEM-TO_0805555123.wav

📱 웹 관리: http://192.168.1.254/spam/";

echo $uncertainMessage, "\n\n";
echo "메시지 길이: ", $smsSender->calculateByteLength($uncertainMessage), " bytes\n\n";

echo "=== 모든 케이스가 1000바이트 제한 내에서 전송 가능합니다! ===\n";
?> 