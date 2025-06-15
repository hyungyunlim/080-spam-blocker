<?php
require_once "sms_sender.php";
$sender = new SMSSender();
$result = $sender->sendVerificationCode("01012345678", "123456");
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
