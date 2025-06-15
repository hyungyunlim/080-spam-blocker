<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
$database = FreePBX::create()->Database;

$sth = $database->prepare("SELECT * FROM sms_messages WHERE threadid = ''");
$sth->execute();
$messages = $sth->fetchAll(PDO::FETCH_ASSOC);
$threads = [];
foreach($messages as $message) {
	if($message['direction'] == 'in') {
		$local = $message['to'];
		$remote = $message['from'];
	} else {
		$local = $message['from'];
		$remote = $message['to'];
	}
	$threadid = sha1($local.$remote);
	$message['threadid'] = $threadid;
	$threads[$threadid][] = $message;
	$sth = $database->prepare("UPDATE sms_messages SET threadid = :threadid WHERE id = :id");
	$sth->execute(["threadid" => $threadid, "id" => $message['id']]);
}

$sth = $database->prepare("SELECT DISTINCT did FROM sms_dids");
$sth->execute();
$dids = $sth->fetchAll();
if(empty($dids)) {
	$sth = $database->prepare("SELECT DISTINCT did FROM sms_routing");
	$sth->execute();
	$dids = $sth->fetchAll(PDO::FETCH_ASSOC);
	$routing = [];
	foreach($dids as $did) {
		$sth = $database->prepare("INSERT INTO sms_dids (`did`) VALUES (:did)");
		$sth->execute([":did" => $did['did']]);
		$id = $database->lastInsertId();
		$routing[$did['did']] = $id;
	}

	$sth = $database->prepare("SELECT * FROM sms_messages WHERE didid = ''");
	$sth->execute();
	$messages = $sth->fetchAll(PDO::FETCH_ASSOC);
	foreach($messages as $message) {
		if($message['direction'] == 'in') {
			$local = $message['to'];
			$remote = $message['from'];
		} else {
			$local = $message['from'];
			$remote = $message['to'];
		}
		$sth = $database->prepare("UPDATE sms_messages SET didid = :didid WHERE id = :id");
		$sth->execute([":didid" => $routing[$local], ":id" => $message['id']]);
	}

	$sth = $database->prepare("SELECT * FROM sms_routing WHERE didid = ''");
	$sth->execute();
	$routes = $sth->fetchAll(PDO::FETCH_ASSOC);
	foreach($routes as $route) {
		$sth = $database->prepare("UPDATE sms_routing SET didid = :didid WHERE did = :did");
		$sth->execute([":didid" => $routing[$route['did']], ":did" => $route['did']]);
	}

	$database->query("UPDATE sms_messages SET `timestamp` = UNIX_TIMESTAMP(tx_rx_datetime)");
}

$sth = $database->prepare("SELECT * FROM sms_messages WHERE emid IS NULL");
$sth->execute();
$nullemid = $sth->fetchAll();
foreach($nullemid as $message) {
	$sth = $database->prepare("UPDATE sms_messages SET emid = :emid WHERE id = :id");
	$sth->execute([":emid" => 'sms-'.uniqid(), ":id" => $message['id']]);
}
$sth = $database->prepare("ALTER TABLE sms_messages MODIFY COLUMN body VARCHAR(1600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$sth->execute();
