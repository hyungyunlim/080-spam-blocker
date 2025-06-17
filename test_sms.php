<?php
require_once "sms_sender.php";
$sender = new SMSSender();
$result = $sender->sendSMS("01099998888", "test message");
echo json_encode($result, JSON_PRETTY_PRINT);
?>
