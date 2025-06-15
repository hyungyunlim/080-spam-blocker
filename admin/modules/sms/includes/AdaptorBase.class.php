<?php
namespace FreePBX\modules\Sms;
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
#[\AllowDynamicProperties]
abstract class AdaptorBase {
	private $objSmsplus = false;
	public function __construct() {
		$this->db = \FreePBX::Database();
		$this->FreePBX = \FreePBX::create();
		if(!class_exists(\Emojione\Emojione::class)) {
			include __DIR__."/Emojione.class.php";
		}
		$this->emoji = new \Emojione\Client(new \Emojione\Ruleset());
		if ($this->FreePBX->Modules->checkStatus('smsplus')) {
			$this->objSmsplus = $this->FreePBX->Smsplus->getObject();
		}
	}

	/**
	 * Insert message media into the database
	 * @param  integer $to    The DID the message was sent to
	 * @param  integer $from  The DID the message was from
	 * @param  string $cnam  The CNAME of the message
	 * @param  string $message The message body
	 * @param  array  $files Array of file names to process
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for NOW())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id if there is one
	 * @return integer        The inserted message ID
	 */
	public function sendMedia($to,$from,$cnam,$message=null,$files=[],$time=null,$adaptor=null,$emid=null,$chatId='') {
		$id = self::sendMessage($to,$from,$cnam,$message,$time,$adaptor,$emid,$chatId);
		foreach($files as $file){
			if(file_exists($file)) {
				$data = file_get_contents($file);
				try {
					$sql = "INSERT INTO sms_media (`mid`, `name`, `raw`) VALUES (?, ?, ?)";
					$sth = $this->db->prepare($sql);
					$sth->execute([$id, basename((string) $file), $data]);
					// sending data to webhook when media is sent
					$lid = $this->db->lastInsertId();
					$acpProtocol = "http";
					$acpPort =  "";
					if ($this->FreePBX->Modules->checkStatus('sysadmin')) {
						$acpnetDetails = $this->FreePBX->Sysadmin->getAllNetworkInfo();
						if (isset($acpnetDetails['protocols']['acp']) && !empty($acpnetDetails['protocols']['acp'])) {
							$acpProtocol = $acpnetDetails['protocols']['acp']['protocol'];
							$acpPort = $acpnetDetails['protocols']['acp']['port'];
						}
					}
					$ampWebAddress = $this->FreePBX->Config->get_conf_setting('AMPWEBADDRESS');
					$ampWebAddress = isset($ampWebAddress) && !empty($ampWebAddress) ? rtrim((string) $ampWebAddress, '/') : "AMPWEBADDRESS";
					$apiUrl = $acpProtocol . "://" . $ampWebAddress . (!empty($acpPort) ? ":$acpPort" : "") . "/admin/api/api/rest/sms/media/" . $lid;
					$message = ['mediaUrl' => $apiUrl];
					$this->FreePBX->sms->sendDataToWebHook($to, $from, $adaptor, date("r", $time ? strtotime($time) : time()), $message, 'out', 'send', 'Event on sending media');
				} catch (\Exception $e) {
					throw new \Exception('Unable to Insert Message Media into DB '.$e->getMessage());
				}
				unlink($file);
			}
		}
		return $id;
	}

	/**
	 * Insert a sent message into the database
	 * @param  integer $to      The DID the message was sent to
	 * @param  integer $from    The DID the message was from
	 * @param  string $cnam    The CNAME of the message
	 * @param  string $message The message
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for NOW())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id if there is one
	 * @return integer          The ID of the row that was inserted
	 */
	public function sendMessage($to,$from,$cnam,$message,$time=null,$adaptor=null,$emid=null,$chatId='') {
		$sql = "SELECT id FROM sms_dids WHERE `did` = :did";
		$sth = $this->db->prepare($sql);
		$sth->execute([":did" => $from]);
		$didid = $sth->fetchColumn();

		$threadid = $this->determineThreadID($to,$from,'out');
		$sql = "INSERT INTO sms_messages (`from`, `to`, `cnam`, `direction`, `tx_rx_datetime`, `body`, `adaptor`, `emid`, `threadid`, `didid`,`timestamp`) VALUES (?, ?, ?, 'out', from_unixtime(?), ?, ?, ?, ?, ?, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$message = $this->emoji->toShort($message);
			$time = !empty($time) ? $time : time();
			$message = !is_null($message) ? $message : "";
			$sth->execute([$from, $to, $cnam, $time, $message, $adaptor, $emid, $threadid, $didid, $time]);
			$id = $this->db->lastInsertId();
			$this->FreePBX->Hooks->processHooks($id,$to,$from,$cnam,$message,$time,$adaptor,$emid,$threadid,$didid);
			$this->FreePBX->astman->UserEvent("sms-outbound",["id" => $id, "to" => $to, "from" => $from, "cnam" => $cnam, "message" => json_encode($message, JSON_THROW_ON_ERROR), "time" => $time, "adaptor" => $adaptor, "emid" => !is_null($emid) ? $emid : 'sms-'.uniqid(), "threadid" => $threadid, "didid" => $didid, "chat_id" => $chatId]);

			// sending data to webhook when message is sent
			if (!empty($message)) {
				$this->FreePBX->sms->sendDataToWebHook($to, $from, $adaptor, date("r", strtotime($time)), $message, 'out', 'send', 'Event on sending message');
			}

			return $id;
		} catch (\Exception $e) {
			throw new \Exception('Unable to Insert Message into DB '.$e->getMessage());
		}
	}

	/**
	 * Insert a received message into the database
	 * @param  integer $to      The DID the message was sent to
	 * @param  integer $from    The DID the message was from
	 * @param  string $cnam    The CNAME of the message
	 * @param  string $message The message
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for NOW())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id if there is one
	 * @return integer          The ID of the row that was inserted
	 */
	public function getMessage($to,$from,$cnam,$message,$time=null,$adaptor=null,$emid=null) {
		$db_table = 'sms_messages';
		$sql = "SELECT id FROM sms_dids WHERE `did` = :did";
		$sth = $this->db->prepare($sql);
		$sth->execute([":did" => $to]);
		$didid = $sth->fetchColumn();
		if ($this->objSmsplus) {
			$getDBTableSmsBlock = $this->objSmsplus->getDBTableSmsBlock($from);
			$db_table = (!empty($getDBTableSmsBlock['status'])) ? $getDBTableSmsBlock['db_table'] : 'sms_messages';
		}
		$threadid = $this->determineThreadID($to,$from,'in');
		$sql = "INSERT INTO $db_table (`from`, `to`, `cnam`, `direction`, `tx_rx_datetime`, `body`, `adaptor`, `emid`, `threadid`, `didid`, `timestamp`) VALUES (?, ?, ?, 'in', from_unixtime(?), ?, ?, ?, ?, ?, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$message = $this->emoji->toShort($message);
			$time = !empty($time) ? $time : time();
			$sth->execute([$from, $to, $cnam, $time, $message, $adaptor, $emid, $threadid, $didid, $time]);
			$id = $this->db->lastInsertId();
			$this->FreePBX->Hooks->processHooks($id,$to,$from,$cnam,$message,$time,$adaptor,$emid,$threadid, $didid);

			// sending data to webhook when message is received
			if (!empty($message)) {
				$this->FreePBX->sms->sendDataToWebHook($to, $from, $adaptor, date("r", strtotime($time)), $message, 'in', 'receive', 'Event on receiving message');
			}

			return $id;
		} catch (\Exception $e) {
			throw new \Exception('Unable to Insert Message into DB'.$e->getMessage());
		}
	}

	public function updateMessageByEMID($emid,$message,$adaptor=null) {
		$sql = "SELECT id FROM sms_messages WHERE `adaptor` = :adaptor AND `emid` = :emid";
		$sth = $this->db->prepare($sql);
		$sth->execute([":adaptor" => $adaptor, ":emid" => $emid]);
		$res = $sth->fetch(\PDO::FETCH_ASSOC);
		if(empty($res)) {
			throw new \Exception("Invalid EMID");
		}

		$sql = "UPDATE sms_messages SET `body` = :body WHERE `emid` = :emid AND `adaptor` = :adaptor";
		$sth = $this->db->prepare($sql);
		$sth->execute([":body" => $message, ":adaptor" => $adaptor, ":emid" => $emid]);
		return $res['id'];
	}

	/**
	 * Add Media into the database for sent or received messages
	 * @param integer $msgid The message ID from getMessage or sendMessage
	 * @param string $name  The filename
	 * @param string $data  The raw data from the file
	 */
	public function addMedia($msgid, $name, $data) {
		$sql = "INSERT INTO sms_media (`mid`, `name`, `raw`) VALUES (?, ?, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$sth->execute([$msgid, $name, $data]);
			// sending data to webhook when media is received
			$id = $this->db->lastInsertId();
			$messageData = $this->FreePBX->sms->getMessageByID($msgid);
			if ($messageData) {
				$acpProtocol = "http";
				$acpPort =  "";
				if ($this->FreePBX->Modules->checkStatus('sysadmin')) {
					$acpnetDetails = $this->FreePBX->Sysadmin->getAllNetworkInfo();
					if (isset($acpnetDetails['protocols']['acp']) && !empty($acpnetDetails['protocols']['acp'])) {
						$acpProtocol = $acpnetDetails['protocols']['acp']['protocol'];
						$acpPort = $acpnetDetails['protocols']['acp']['port'];
					}
				}
				$ampWebAddress = $this->FreePBX->Config->get_conf_setting('AMPWEBADDRESS');
				$ampWebAddress = isset($ampWebAddress) && !empty($ampWebAddress) ? rtrim((string) $ampWebAddress, '/') : "AMPWEBADDRESS";
				$apiUrl = $acpProtocol . "://" . $ampWebAddress . (!empty($acpPort) ? ":$acpPort" : "") . "/admin/api/api/rest/sms/media/" . $id;
				$message = ['mediaUrl' => $apiUrl];
				$this->FreePBX->sms->sendDataToWebHook($messageData['to'], $messageData['from'], $messageData['adaptor'], date("r", strtotime((string) $messageData['tx_rx_datetime'])), $message, 'in', 'recieve', 'Event on recieving media');
			}
		} catch (\Exception) {
			throw new \Exception('Unable to Insert Media into DB');
		}
	}

	/**
	 * Hooks for adaptor classes to modify dialplan
	 */
	public function dialPlanHooks(&$ext, $engine, $priority) {}

	/**
	 * Determine the thread ID
	 * @method determineThreadID
	 * @param  string            $to        Who the message was to
	 * @param  string            $from      Who the message was from
	 * @param  string            $direction The direction of the message
	 * @return string                       The resulting threadID
	 */
	private function determineThreadID($to,$from,$direction) {
		if($direction == 'in') {
			$local = $to;
			$remote = $from;
		} else {
			$local = $from;
			$remote = $to;
		}
		$threadid = sha1($local.$remote);
		return $threadid;
	}

	/**
	 * Generate the sms-inbound asterisk User Event
	 * @method emitSmsInboundUserEvt
	 * @param  string $msgid     id of message in sms_messages table
	 * @param  string $to        Who the message was to
	 * @param  string $from      Who the message was from
	 * @param  string $cnam		 The CNAME of the message
	 * @param  string $message   The message body
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for time())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id if there is one
	 */
	public function emitSmsInboundUserEvt($msgid, $to, $from, $cnam, $message, $time, $adaptor, $emid) {
		$sql = "SELECT id FROM sms_dids WHERE `did` = :did";
		$sth = $this->db->prepare($sql);
		$sth->execute([":did" => $to]);
		$didId = $sth->fetchColumn();

		$threadId = $this->determineThreadID($to, $from, 'in');

		if (empty($time)) {
			$time = time();
		}

		$this->FreePBX->astman->UserEvent("sms-inbound",["id" => $msgid, "to" => $to, "from" => $from, "cnam" => $cnam, "message" => json_encode($message, JSON_THROW_ON_ERROR), "time" => $time, "adaptor" => $adaptor, "emid" => $emid, "threadid" => $threadId, "didid" => $didId]);
	}
}
