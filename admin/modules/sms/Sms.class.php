<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
namespace FreePBX\modules;

#[\AllowDynamicProperties]
class Sms implements \BMO {
	private $objSmsplus;
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		if(!class_exists(\Emojione\Emojione::class)) {
			include(__DIR__."/includes/Emojione.class.php");
		}
		$this->objSmsplus = false;
		if ($this->FreePBX->Modules->checkStatus('smsplus')) {
			$this->objSmsplus = $this->FreePBX->Smsplus->getObject();
		}
	}

	public function install() {
		if ($this->FreePBX->Modules->checkStatus("sysadmin")) {
			touch("/var/spool/asterisk/incron/sms_web_hook.logrotate");
		}
	}
	public function uninstall() {

	}

	public function doConfigPageInit($page){
	}

	public function myDialplanHooks()
	{
		return true;
	}

	public function ajaxRequest($req, &$setting)
 {
     return match ($req) {
         'add', 'edit', 'del', 'bulkdelete', 'getJSON' => true,
         default => false,
     };
 }

	public function ajaxHandler()
	{

		$request = $_REQUEST;
		if (!empty($_REQUEST['id']) && $_REQUEST['command'] == 'add') {
			$_REQUEST['command'] = 'edit';
		}
		switch ($_REQUEST['command']) {
			case 'add':
				if(isset($_REQUEST['id']) && $_REQUEST['id'] != '') {
					return $this->updateWebhook($request);
				}
				return $this->addWebhook($request);
				break;
			case 'edit':
				return $this->updateWebhook($request);
				break;
			case 'bulkdelete':
				$ids = $_REQUEST['ids'] ?? [];
				$ids = json_decode((string) $ids, true, 512, JSON_THROW_ON_ERROR);
				foreach ($ids as $id) {
					$this->deleteWebHook($id);
				}
				return ['status' => 'true', 'message' => _("API's Deleted")];
				break;
			case 'del':
				$ret = $this->deleteWebHook($request['id']);
				return ['status' => $ret];
				break;
			case 'getJSON':
				switch ($request['jdata']) {
					case 'grid':
						return $this->getAllWebhooks();
						break;
				}
				break;
		}
	}

	public function showPage()
	{
		$request = freepbxGetSanitizedRequest();
		$action = !empty($request['action']) ? $request['action'] : '';
		$html = '';

		switch ($action) {
			default:
				$settings = $this->getAllWebhooks();
				$html .= load_view(__DIR__ . '/views/index.php', ['settings' => $settings]);
				break;
		}

		return $html;
	}

	// CRUD Functions for Web hook webHook

	public function addWebhook($request)
	{
		$validation = $this->smsWebhookValidation($request);

		if ($validation['status'] == false) {
			return $validation;
		}

		// check dataToBeSentOn already exists 
		$dataToBeSentOn = $request['dataToBeSentOn'];
		$res = $this->getWebHookByDataToBeSent([$dataToBeSentOn]);
		if ((is_countable($res) ? count($res) : 0) > 0) {
			return ['status' => false, 'message' => sprintf(_("web hook already exists for data to be sent on %s sms events"), $dataToBeSentOn)];
		}

		$webHookBaseurl = $request['webHookBaseurl'];
		$enablewebHook = $request['enablewebHook'] ? 1 : 0;
		$dataToBeSentOn = $request['dataToBeSentOn'];
		$sql = "INSERT INTO sms_webhooks (`webhookUrl`, `enablewebHook`, `dataToBeSentOn`) VALUES (?,?,?)";
		$sth = $this->db->prepare($sql);
		$sth->execute([$webHookBaseurl, $enablewebHook, $dataToBeSentOn]);

		return ['status' => true];
	}

	public function updateWebhook($request)
	{
		$validation = $this->smsWebhookValidation($request);

		if ($validation['status'] == false) {
			return $validation;
		}

		// check dataToBeSentOn already exists 
		$dataToBeSentOn = trim((string) $request['dataToBeSentOn']);
		$webHookBaseurl = $request['webHookBaseurl'];
		$enablewebHook = $request['enablewebHook'] ? 1 : 0;

		$sql = "SELECT count(*) FROM sms_webhooks where id != ? and dataToBeSentOn = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$request['id'], $dataToBeSentOn]);
		$option = $sth->fetch(\PDO::FETCH_ASSOC);

		if ($option['count(*)']) {
			return ['status' => false, 'message' => sprintf(_("web hook already exists for data to be sent on %s sms events"), $dataToBeSentOn)];
		}

		$sql = "UPDATE sms_webhooks SET `webhookUrl` = :webhookUrl,  `enablewebHook` = :enablewebHook,  `dataToBeSentOn` = :dataToBeSentOn WHERE `id` = :id ";
		$sth = $this->db->prepare($sql);
		$sth->bindParam(':id', $request['id']);
		$sth->bindParam(':webhookUrl', $webHookBaseurl);
		$sth->bindParam(':enablewebHook', $enablewebHook);
		$sth->bindParam(':dataToBeSentOn', $dataToBeSentOn);
		$sth->execute();

		return ['status' => true];
	}

	public function smsWebhookValidation($request)
	{
		// removing space before and after url before validating 
		$request['webHookBaseurl'] = trim((string) $request['webHookBaseurl']);

		if (
			!isset($request['webHookBaseurl']) || empty($request['webHookBaseurl'])
		) {
			return ['status' => false, 'message' => _("web hook url is required")];
		}

		if (!isset($request['enablewebHook'])) {
			return ['status' => false, 'message' => _("enablewebHook is required")];
		}

		if (
			!isset($request['dataToBeSentOn']) || empty($request['dataToBeSentOn'])
		) {
			return ['status' => false, 'message' => _("dataToBeSentOn is required")];
		}

		if (!preg_match('#^(ht|f)tps?://#', $request['webHookBaseurl'])) {
			return ['status' => false, 'message' => _("Invalid webhook url")];
		}

		return ['status' => true];
	}

	public function getWebhookById($id)
	{
		$sql = "SELECT * FROM sms_webhooks WHERE `id` = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$id]);
		return $sth->fetch(\PDO::FETCH_ASSOC);
	}

	public function getAllWebhooks()
	{
		$sql = "SELECT * FROM sms_webhooks";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function deleteWebHook($id)
	{
		$sql = "DELETE FROM sms_webhooks WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$id]);
		return true;
	}

	public function getWebHookByDataToBeSent($dataToBeSentOn)
	{
		$options = "";
		foreach ($dataToBeSentOn as $k => $item) {
			if ($k > 0) {
				$options .= ",";
			}
			$options .= "'" . $item . "'";
		}
		$sql = "SELECT * FROM sms_webhooks WHERE `dataToBeSentOn` in ($options)";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$res = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $res;
	}

	public function addLog($driver = false, $type = false, $request = false, $data = false, $response = false)
	{
		if ($request) {
			$this->FreePBX->Logger->driverLogWrite(
				'sms_web_hook',
				$driver . ': HTTP ' . $type . ' Request URL=' . json_encode($request, JSON_THROW_ON_ERROR)
			);
		}
		if ($data) {
			$this->FreePBX->Logger->driverLogWrite(
				'sms_web_hook',
				$driver . ': HTTP ' . $type . ' Data=' . json_encode($data, JSON_THROW_ON_ERROR)
			);
		}
		if ($response) {
			$this->FreePBX->Logger->driverLogWrite(
				'sms_web_hook',
				$driver . ': HTTP ' . $type . ' Received Response=' . json_encode($response, JSON_THROW_ON_ERROR)
			);
		}
	}

	public function sendDataToWebHook($to, $from, $adaptor, $time, $message, $eventDirection, $dataToBeSentOn, $eventDesc){

		$data = ['to' => $to, 'from' => $from, "adaptor" => $adaptor, 'time' => $time, 'message' =>  $message, 'eventDirection' => $eventDirection];

		$webhookDetails = $this->getWebHookByDataToBeSent(['both', $dataToBeSentOn]);

		try {
			foreach ($webhookDetails as $hook) {
				if(!is_null($hook) && $hook != "" && $hook['enablewebHook']){
					$ret = \WpOrg\Requests\Requests::post($hook['webhookUrl'], ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
					$code = $ret->status_code;
					if($ret && $code == 200){
						$this->addLog('WebHook', 'Post', $hook['webhookUrl'], ['status' => true, 'message' => _('Data sent to web hook'), 'event' => $eventDesc], $code);
					}else{
						$this->addLog('WebHook', 'Post', $hook['webhookUrl'], ['status' => false, 'message' => _('Failed to send data to the configured web hook. Due to' . json_encode($ret))], $code);
					}
				}
			}
		} catch (\Exception $e) {
			$this->addLog('WebHook', 'Post', $hook['webhookUrl'], ['status' => false, 'message' => "Failed to send data to the configured web hook due to exception" . $e->getMessage()]);
		}
	}
	
	public function getMediaByName($name) {
		$sql = "SELECT * FROM sms_media WHERE `name` = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$name]);
		$media = $sth->fetch(\PDO::FETCH_ASSOC);
		if(!empty($media)) {
			return $media['raw'];
		}
	}

	public function getMediaByMediaID($id)
	{
		$sql = "SELECT * FROM sms_media WHERE `id` = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$id]);
		$res = $sth->fetch(\PDO::FETCH_ASSOC);
		return $res;
	}

	public function getMediaByID($id) {
		$sql = "SELECT * FROM sms_media WHERE `mid` = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$id]);
		$medias = $sth->fetchAll(\PDO::FETCH_ASSOC);
		$final = [];
		$smil = null;
		$files = [];
		foreach($medias as $media) {
			$ext = pathinfo((string) $media['name'],PATHINFO_EXTENSION);
			if($ext == "smil") {
				$smil = $media['raw'];
				continue;
			}
			if($ext == "xml" && preg_match('/^smil-/',(string) $media['name'])) {
				$smil = $media['raw'];
				continue;
			}
			$files[$media['name']] = $media['raw'];
		}
		if(!empty($smil)) {
			$xml = simplexml_load_string((string) $smil);
			foreach($xml->body->par as $parts) {
				foreach($parts as $type => $data) {
					foreach($data->attributes() as $a => $b) {
						if($a == 'src') {
							$name = (string)$b;
							$data = $files[$name] ?? '';
							$final[] = ['type' => $type, 'link' => $name, 'data' => $data];
						}
					}
				}
			}
		} else {
			foreach($files as $name => $data) {
				$ext = pathinfo($name,PATHINFO_EXTENSION);
				$type = match ($ext) {
        "png", "jpg", "jpeg", "gif", "tiff" => 'img',
        default => 'bin',
    };
				$final[] = ['type' => $type, 'link' => $name, 'data' => $data];
			}
		}
		return $final;
	}

	/**
	 * Delete DID from the Routing Table
	 * @param {int} $did           The DID
	 */

	public function delDIDRouting($did) {
		$did = strlen($did) == 10 ? '1'.$did : $did;
		$sql = "DELETE FROM sms_routing WHERE did = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($did));

		$sql = "DELETE FROM sms_dids WHERE `did` = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($did));
	}

	/**
	 * Add DID to the Routing Table
	 * @param {int} $did           The DID
	 * @param {array} $users=array() The assigned user(s)
	 * @param {string} $adaptor		The adaptor of the DID
	 */
	public function addDIDRouting($did,$users=[],$adaptor='Sipstation') {
		$did = strlen((string) $did) == 10 ? '1'.$did : $did;
		$sql = "DELETE FROM sms_routing WHERE did = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$did]);
		foreach($users as $user) {
			$this->insertDIDRouting($did,$user,$adaptor);
		}
	}

	/**
	 * Add User to the DID Routing Table
	 * @param {int} $user         The User Man User ID
	 * @param {array} $dids=array() Array of DIDs to add to said user
	 * @param {string} $adaptor		The adaptor of the DIDs
	 */
	public function addUserRouting($user,$dids=[],$adaptor='Sipstation') {
		$sql = "DELETE FROM sms_routing WHERE uid = ? AND adaptor = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$user, $adaptor]);
		foreach($dids as $did) {
			$this->insertDIDRouting($did,$user,$adaptor);
		}
	}

	private function insertDIDRouting($did,$user,$adaptor='Sipstation') {
		$did = strlen((string) $did) == 10 ? '1'.$did : $did;
		$sql = "SELECT * FROM sms_dids WHERE `did` = :did";
		$sth = $this->db->prepare($sql);
		$sth->execute([":did" => $did]);
		$row = $sth->fetch(\PDO::FETCH_ASSOC);
		if(empty($row)) {
			$sth = $this->db->prepare("INSERT INTO sms_dids (`did`) VALUES (:did)");
			$sth->execute([":did" => $did]);
			$id = $this->db->lastInsertId();
		} else {
			$id = $row['id'];
		}
		$sql = "INSERT INTO sms_routing (`did`, `uid`, `accepter`, `adaptor`, `didid`) VALUES (?,?,?,?,?)";
		$sth = $this->db->prepare($sql);
		$sth->execute([$did, $user, 'UCP', $adaptor, $id]);
	}

	/**
	 * Get Routing information from said DID
	 * @param {int} $did The DID
	 */
	public function getDIDRouting($did) {
		$did = strlen((string) $did) == 10 ? '1'.$did : $did;
		$sql = "SELECT * FROM sms_routing WHERE did = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$did]);
		$dids = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $dids;
	}

	/**
	 * Get Assigned user for said did
	 * @param {int} $did The DID
	 */
	public function getAssignedUsers($did) {
		$routing = $this->getDIDRouting($did);
		$final = [];
		foreach($routing as $r) {
			$final[] = $r['uid'];
		}
		return $final;
	}

	/**
	 * Get DIDs that are Assigned for this User
	 * @param {[type]} $user [description]
	 */
	public function getAssignedDIDs($user) {
		$routing = $this->getUserRouting($user);
		$final = [];
		foreach($routing as $r) {
			$final[] = $r['did'];
		}
		return $final;
	}

	/**
	 * Get all routing information for said user
	 * @param {int} $user The User ID
	 */
	public function getUserRouting($user) {
		$sql = "SELECT * FROM sms_routing WHERE uid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$user]);
		$user = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $user;
	}

	/**
	 * Get all messages less than said ID
	 * @param {int} $uid   The User ID
	 * @param {int} $id    The Message ID
	 * @param {int} $from  The DID from
	 * @param {int} $to    The DID to
	 * @param {int} $limit = 1 How many results to return
	 */
	public function getMessagesOlderThanID($uid,$id,$from,$to,$limit = 1) {
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r WHERE r.uid = ? AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND (m.from = r.did OR m.to = r.did) AND m.id < ? ORDER BY timestamp DESC LIMIT ".$limit;
		$sth = $this->db->prepare($sql);
		$sth->execute([$uid, $from, $to, $to, $from, $id]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get all messages less than said ID
	 * @param {int} $uid   The User ID
	 * @param {int} $id    The Message ID
	 * @param {int} $from  The DID from
	 * @param {int} $to    The DID to
	 * @param {int} $limit = 1 How many results to return
	 */
	public function getMessagesOlderThanEMID($uid,$emid,$from,$to,$limit = 1) {
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r INNER JOIN (SELECT `timestamp` FROM sms_messages WHERE emid  = ?) z WHERE r.uid = ? AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND (m.from = r.did OR m.to = r.did) AND z.timestamp >= m.timestamp AND m.emid != ? ORDER BY m.timestamp DESC LIMIT ".$limit;
		$sth = $this->db->prepare($sql);
		$sth->execute([$emid, $uid, $from, $to, $to, $from, $emid]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get all messages that have been marked as delivered
	 * @param {int} $uid   The User ID
	 * @param {int} $from  The DID from
	 * @param {int} $to    The DID to
	 * @param {int} $start =             0 The starting position
	 * @param {int} $limit =             1 How many results to return
	 */
	public function getAllDeliveredMessages($uid,$from,$to,$start = 0, $limit = 1) {
		$threadid = sha1($from.$to);
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r WHERE r.uid = ? AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND (m.from = r.did OR m.to = r.did) AND m.threadid = ? ORDER BY timestamp DESC LIMIT ".$start.",".$limit;
		$sth = $this->db->prepare($sql);
		$sth->execute([$uid, $from, $to, $to, $from, $threadid]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get all Undelivered Messages
	 * @param {int} $uid The User ID
	 */
	public function getAllUndeliveredMessages($uid) {
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r WHERE r.uid = ? AND (m.from = r.did OR m.to = r.did) AND direction = 'in' AND delivered = 0";
		$sth = $this->db->prepare($sql);
		$sth->execute([$uid]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get All Messages
	 * @param {int} $uid       The User ID
	 * @param {int} $from      The DID from
	 * @param {int} $to        The DID to
	 * @param {string} $search='' The search phrase to look for
	 */
	public function getAllMessages($uid,$from,$to,$search='') {
		$threadid = sha1($from.$to);
		if(empty($search)) {
			$sql = "SELECT m.*, timestamp as utime FROM sms_messages m, sms_routing r WHERE r.uid = ? AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND (m.from = r.did OR m.to = r.did) AND m.threadid = ?  ORDER BY timestamp DESC";
			$sth = $this->db->prepare($sql);
			$sth->execute([$uid, $from, $to, $to, $from, $threadid]);
		} else {
			$sql = "SELECT m.*, timestamp as utime FROM sms_messages m, sms_routing r WHERE r.uid = ? AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND (m.from = r.did OR m.to = r.did) AND m.threadid = ? AND body LIKE ? ORDER BY timestamp DESC";
			$sth = $this->db->prepare($sql);
			$sth->execute([$uid, $from, $to, $to, $from, $threadid, '%'.$search.'%']);
		}
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get All Messages after ID
	 * @param {int} $uid   The User ID
	 * @param {int} $from  The DID from
	 * @param {int} $to    The DID to
	 * @param {int} $msgId The message ID to check after
	 */
	public function getAllMessagesAfterEMID($uid,$from,$to,$msgId) {
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r INNER JOIN (SELECT `timestamp` FROM sms_messages WHERE emid  = ?) z WHERE r.uid = ? AND (m.from = r.did OR m.to = r.did) AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND z.timestamp <= m.timestamp AND m.emid != ? ORDER BY timestamp DESC";
		$sth = $this->db->prepare($sql);
		$sth->execute([$msgId, $uid, $from, $to, $to, $from, $msgId]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get All Messages after ID
	 * @param {int} $uid   The User ID
	 * @param {int} $from  The DID from
	 * @param {int} $to    The DID to
	 * @param {int} $msgId The message ID to check after
	 */
	public function getAllMessagesAfterID($uid,$from,$to,$msgId) {
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r WHERE r.uid = ? AND (m.from = r.did OR m.to = r.did) AND ((m.from = ? AND m.to = ?) OR (m.from = ? AND m.to = ?)) AND m.id > ? ORDER BY timestamp DESC";
		$sth = $this->db->prepare($sql);
		$sth->execute([$uid, $from, $to, $to, $from, $msgId]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get Messages since last time stamp
	 * @param {int} $uid  The User ID
	 * @param {int} $time Unix timestamp
	 */
	public function getMessagesSinceTime($uid,$time) {
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r WHERE r.uid = ? AND (m.from = r.did OR m.to = r.did) AND timestamp > ? ORDER BY timestamp DESC";
		$sth = $this->db->prepare($sql);
		$sth->execute([$uid, $time]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	/**
	 * Get a single message
	 * @method getMessageByID
	 * @param  {int}         $id The message ID
	 * @return {array}             Array of message information
	 */
	public function getMessageByID($id) {
		$sql = "SELECT * FROM sms_messages WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$id]);
		$message = $sth->fetch(\PDO::FETCH_ASSOC);
		return $message;
	}

	/**
	 * Get All messages in a thread
	 * @method getMessagesByThreadID
	 * @param  {string}                $id The thread ID
	 * @return {array}                    The messages as an array
	 */
	public function getMessagesByThreadID($id) {
		$sql = "SELECT * FROM sms_messages WHERE threadid = ? ORDER BY timestamp DESC";
		$sth = $this->db->prepare($sql);
		$sth->execute([$id]);
		$messages = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $messages;
	}

	private function getDIDIDByDID($did) {
		$sql = "SELECT id FROM sms_dids WHERE `did` = :did";
		$sth = $this->db->prepare($sql);
		$sth->execute([":did" => $did]);
		return $sth->fetchColumn();
	}

	public function getUserConversationsByDID($uid,$did,$search='',$order='asc',$orderby='timestamp',$start=0,$limit=null) {
		$conversations = [];
		$id = $this->getDIDIDByDID($did);
		if(empty($id)) {
			return ["total" => 0, "conversations" => []];
		}

		$didids = [$id];

		$out = $this->getConversationsByDIDIDs($didids,$search,$order,$orderby,$start,$limit);

		foreach($out['conversations'] as $i => $convo) {
			$out['conversations'][$i]['prettyto'] = $this->replaceDIDwithDisplay($uid,$convo['remotedid']);
			$out['conversations'][$i]['from'] = $convo['remotedid'];
			$out['conversations'][$i]['to'] = $convo['localdid'];
		}

		return ["total" => $out['total'], "conversations" => $out['conversations']];
	}

	public function getUserConversations($uid,$search='',$order='asc',$orderby='timestamp',$start=0,$limit=null) {
		$conversations = [];
		$dids = $this->getUserRouting($uid);
		$didids = [];
		foreach($dids as $did) {
			$didids[] = $did['didid'];
		}

		$out = $this->getConversationsByDIDIDs($didids,$search,$order,$orderby,$start,$limit);

		foreach($out['conversations'] as $i => $convo) {
			$out['conversations'][$i]['prettyto'] = $this->replaceDIDwithDisplay($uid,$convo['remotedid']);
			$out['conversations'][$i]['from'] = $convo['remotedid'];
			$out['conversations'][$i]['to'] = $convo['localdid'];
		}

		return ["total" => $out['total'], "conversations" => $out['conversations']];
	}

	public function getAllMessagesHistory($uid,$search='',$order='asc',$orderby='date',$start=0,$limit=null) {
		set_time_limit(0);
		$dids = $this->getAssignedDIDs($uid);
		$total = 0;
		$conversations = [];
		foreach($dids as $did) {
			$data = $this->getAllConversationHistoryByDID($uid,$did,$search,'desc','date',0,null);
			if($data['total'] == 0) {
				continue;
			}
			$total = $total + $data['total'];
			$conversations = array_merge($conversations, $data['conversations']);
		}
		usort($conversations, function($a, $b) use($orderby) {
			switch($orderby) {
				case 'timestamp':
					return $a['messages'][0]['timestamp'] <=> $b['messages'][0]['timestamp'];
    break;
				case 'localdid':
					return strcmp((string) $a['messages'][0]['localdid'], (string) $b['messages'][0]['localdid']);
				break;
				case 'remotedid':
					return strcmp((string) $a['messages'][0]['remotedid'], (string) $b['messages'][0]['remotedid']);
				break;
			}
		});
		$conversations = ($order == 'desc') ? array_reverse($conversations) : $conversations;
		$finalConversations = array_slice($conversations,$start,$limit);
		return ["total" => $total, "conversations" => $finalConversations];
	}

	public function getAllConversationHistoryByDID($uid,$did,$search='',$order='asc',$orderby='utime',$start=0,$limit=null) {
		$conversations = $this->getAllConversationsByDID($did,$search,$order);
		$total = is_countable($conversations) ? count($conversations) : 0;
		$conversations = array_slice($conversations, $start, $limit);
		$finalConversations = [];
		foreach($conversations as $remote => $convo) {
			$finalConversations[$remote]['messages'] = $convo;
			$finalConversations[$remote]['local'] = $convo[0]['localdid'];
			$finalConversations[$remote]['prettyto'] = $prettyto =  $this->replaceDIDwithDisplay($uid,$convo[0]['remotedid']);

			$finalConversations[$remote]['messages']= array_map(function($message) use($prettyto) {
				$message['prettyto'] = $prettyto;
				return $message;
			},$finalConversations[$remote]['messages']);
		}
		return ["total" => $total, "conversations" => $finalConversations];
	}

	public function getAllConversationsByDID($did,$search='',$order='asc') {
		$order = ($order == 'desc') ? 'desc' : 'asc';
		$didid = $this->getDIDIDByDID($did);
		if(!empty($search)) {
			$sql = "SELECT IF(`direction` = 'out', `to`, `from`) as rdid, m.*, IF(`direction` = 'out', `to`, `from`) as remotedid, :did as localdid FROM sms_messages m WHERE `didid` = :didid AND `body` LIKE :body ORDER BY `timestamp` ".$order;
		} else {
			$sql = "SELECT IF(`direction` = 'out', `to`, `from`) as rdid, m.*, IF(`direction` = 'out', `to`, `from`) as remotedid, :did as localdid FROM sms_messages m WHERE `didid` = :didid ORDER BY `timestamp` ".$order;
		}
		$sth = $this->db->prepare($sql);
		if(!empty($search)) {
			$sth->execute([":did" => $did, ":didid" => $didid, ":body" => '%'.$search.'%']);
		} else {
			$sth->execute([":did" => $did, ":didid" => $didid]);
		}
		$conversations = $sth->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
		return $conversations;
	}

	public function deleteConversationsByThreadID($uid, $threadID) {
		try {
			if ($this->objSmsplus) {
				$this->objSmsplus->storeDeletedMessages($threadID);
			}
			$this->sendSMSDeletedEvent("", "", $threadID);
			$sql = "DELETE a FROM `sms_messages` a, `sms_routing` b WHERE a.`threadid` = :threadid AND b.uid = :uid";
			$sth = $this->db->prepare($sql);
			$sth->execute([":threadid" => $threadID, ":uid" => $uid]);
			return true;
		} catch(\Exception) {
			return false;
		}
	}

	public function getConversationsByDIDIDs($didids,$search='',$order='asc',$orderby='timestamp',$start=0,$limit=null) {
		$order = ($order == 'desc') ? 'desc' : 'asc';

		switch($orderby) {
			case 'localdid':
			case 'remotedid':
			case 'timestamp':
			break;
			default:
				$orderby = 'timestamp';
		}

		$dididQuery = [];
		$i = 0;
		foreach($didids as $id) {
			$dididQuery[':didid'.$i] = $id;
			$i++;
		}

		if(!empty($search)) {
			$sql = "SELECT SQL_CALC_FOUND_ROWS m.threadid, IF(`direction` = 'out', `to`, `from`) as remotedid, IF(`direction` = 'in', `to`, `from`) as localdid, MAX(`timestamp`) as `timestamp` FROM sms_messages m WHERE `didid` IN (".implode(",",array_keys($dididQuery)).") AND `body` LIKE :body GROUP BY threadid ORDER BY `$orderby` $order LIMIT $start,$limit";
		} else {
			$sql = "SELECT SQL_CALC_FOUND_ROWS m.threadid, IF(`direction` = 'out', `to`, `from`) as remotedid, IF(`direction` = 'in', `to`, `from`) as localdid, MAX(`timestamp`) as `timestamp` FROM sms_messages m WHERE `didid` IN (".implode(",",array_keys($dididQuery)).") GROUP BY threadid ORDER BY `$orderby` $order LIMIT $start,$limit";
		}

		$sth = $this->db->prepare($sql);

		$params = $dididQuery;
		if(!empty($search)) {
			$params = [...$params, ":body" => '%'.$search.'%'];
		}
		$sth->execute($params);
		$conversations = $sth->fetchAll(\PDO::FETCH_ASSOC);

		$sql = "SELECT FOUND_ROWS() as count";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$total = $sth->fetch(\PDO::FETCH_ASSOC);

		return ["conversations" => $conversations, "total" => $total['count']];
	}
	
	public function deleteConversations($uid, $did1, $did2, $threadid = '') {
		if ($threadid == '') {
			try {
				if ($this->objSmsplus) {
					$this->objSmsplus->storeDeletedMessages('', $did1, $did2);
				}
				$this->sendSMSDeletedEvent($did1, $did2, $threadid);
				$sql = "DELETE a FROM `sms_messages` a, `sms_routing` b WHERE ((a.`from` = :did1 AND a.`to` = :did2) OR (a.`from` = :did2 OR a.`to` = :did1)) AND b.uid = :uid";
				$sth = $this->db->prepare($sql);
				$sth->execute([":did1" => $did1, ":did2" => $did2, ":uid" => $uid]);
				return true;
			} catch(\Exception) {
				return false;
			}
		} else {
			try {
				if ($this->objSmsplus) {
					$this->objSmsplus->storeDeletedMessages($threadid);
				}
				$this->sendSMSDeletedEvent($did1, $did2, $threadid);
				$sql = "DELETE a FROM `sms_messages` a, `sms_routing` b WHERE a.`threadid` = :threadid AND b.uid = :uid";
				$sth = $this->db->prepare($sql);
				$sth->execute([":threadid" => $threadid, ":uid" => $uid]);
				return true;
			} catch(\Exception) {
				return false;
			}
		}
	}

	/**
	 * Get all DIDs assigned to user
	 * @param {int} $uid the user ID
	 */
	public function getDIDs($uid) {
		$sql = "SELECT DID,Adaptor FROM sms_routing WHERE uid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$uid]);
		$dids = $sth->fetchAll(\PDO::FETCH_ASSOC);
		$final = [];
		foreach($dids as $did) {
			//make sure we can load the adaptor, if not then the DID isnt valid for now
			try{
				$res = $this->loadAdaptor($did['Adaptor']);
				if($res === false) {
					continue;
				}
				$final[] = $did['DID'];
			}catch(\Exception) {}
		}
		return $final;
	}

	/**
	 * Mark a Message Read
	 * @param {int} $msgId The message ID
	 */
	public function markMessageRead($msgId) {
		$sql = "UPDATE sms_messages SET `read` = 1 WHERE direction = 'in' AND `read` = 0 AND id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$msgId]);
	}

	/**
	 * Mark all Messages Read sent to a specific did form did
	 * @param {int} $msgId The message ID
	 */
	public function markAllMessagesReadByDIDs($from, $to) {
		$sql = "UPDATE sms_messages SET `read` = 1 WHERE `read` = 0 AND ((`from` = :from AND `to` = :to) OR (`from` = :to AND `to` = :from))";
		$sth = $this->db->prepare($sql);
		$sth->execute(["from" => $from, "to" => $to]);
	}

	/**
	 * Mark a message delivered
	 * @param {int} $msgId The message ID
	 */
	public function markMessageDelivered($msgId) {
		$sql = "UPDATE sms_messages SET delivered = 1 WHERE direction = 'in' AND delivered = 0 AND id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute([$msgId]);
	}

	/**
	 * Do all the dialplan hooking from other hooks
	 * @param {object} &$ext     The Extension object
	 * @param {string} $engine   The engine (Asterisk)
	 * @param {string} $priority The Priority
	 */
	public function doDialplanHook(&$ext, $engine, $priority) {
		foreach($this->getAllAdaptors() as $adaptor) {
			$adaptor = $this->loadAdaptor($adaptor['adaptor']);
			if($adaptor === false) {
				continue;
			}
			$adaptor->dialPlanHooks($ext, $engine, $priority);
		}
	}

	/**
	 * Try to load the adaptor from a provided DID
	 * @param {int} $did The DID
	 */
	public function getAdaptor($did) {
		$sql = 'SELECT adaptor FROM sms_routing WHERE did = ?';
		try {
			$sth = $this->db->prepare($sql);
			$sth->execute([$did]);
			$a = $sth->fetch(\PDO::FETCH_ASSOC);
			$adaptor = $a['adaptor'];
		} catch(\Exception) {
			$adaptor = 'Generic';
		}
		if(empty($a)) {
			return false;
		}
		if(empty($a['adaptor'])) {
			$adaptor = 'Generic';
		}

		return $this->loadAdaptor($adaptor);
	}

	/**
	 * Load the Adaptor from the Adaptor Name
	 * @param {string} $adaptor The adaptor name
	 */
	public function loadAdaptor($adaptor) {
		$adaptor = ucfirst(strtolower((string) $adaptor));
		if(!class_exists(\FreePBX\modules\Sms\AdaptorBase::class)) {
			include __DIR__.'/includes/AdaptorBase.class.php';
		}

		$class = $this->FreePBX->Hooks->processHooks($adaptor);
		if(!empty($class[$adaptor]) && is_object($class[$adaptor]) && is_subclass_of($class[$adaptor],'\\' . \FreePBX\modules\Sms\AdaptorBase::class)) {
			if(method_exists($class[$adaptor],'Create')) {
				$classname = 'FreePBX\modules\Sms\Adaptor\\'.$adaptor;
				return $classname::Create();
			} else {
				return $class[$adaptor];
			}
		} elseif(empty($class) || empty($class[$adaptor])) {
			return false;
		} else {
			throw new \Exception('I was passed something I did not expect!');
		}
	}

	/**
	 * Get all Adaptors that have a routing assignment
	 */
	public function getAllAdaptors() {
		$sql = "SELECT DISTINCT adaptor FROM sms_routing";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$adaptors = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return $adaptors;
	}

	public function replaceDIDwithDisplay($id, $did) {
		if($this->FreePBX->Modules->checkStatus("contactmanager")) {
			$sdid = strlen((string) $did) == 11 ? substr((string) $did, 1) : $did;
			try {
				$user = $this->FreePBX->Contactmanager->lookupByUserID($id, $sdid, '/\D/');
				if(!empty($user)) {
					return $user['displayname'];
				}
				$user = $this->FreePBX->Contactmanager->lookupByUserID($id, $did, '/\D/');
				if(!empty($user)) {
					return $user['displayname'];
				}
			} catch(\Exception) {
				return $did;
			}
		}
		return $did;
	}
	public function getUnreadCount($id){
		$sql = "SELECT m.* FROM sms_messages m, sms_routing r WHERE r.uid = :id AND (m.from = r.did OR m.to = r.did) AND `read` = 0";
		$sth = $this->db->prepare($sql);
		$sth->execute([':id' => $id]);
		$count = is_countable($sth->fetchall(\PDO::FETCH_ASSOC)) ? count($sth->fetchall(\PDO::FETCH_ASSOC)) : 0;
		return $count;
	}

	public function getAdaptorNameByDID($did) {
		$sql = 'SELECT adaptor FROM sms_routing WHERE did = ?';
		$sth = $this->db->prepare($sql);
		$sth->execute([$did]);
		$a = $sth->fetch(\PDO::FETCH_ASSOC);
		if(empty($a)) {
			return false;
		}
		return $a['adaptor'];
	}

	public function sendChatMessage($to,$from,$message,$time=null) {
		$sql = "INSERT INTO sms_messages (`from`, `to`, `direction`, `tx_rx_datetime`, `body`, `threadid`, `timestamp`) VALUES (?, ?, 'out', from_unixtime(?), ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$time = !empty($time) ? $time : time();
		$message = !is_null($message) ? $message : "";
		$threadid = sha1($from.$to);
		$sth->execute([$from, $to, $time, $message, $threadid, $time]);
		$Lastid = $this->db->lastInsertId();
		$this->FreePBX->astman->UserEvent("sms-outbound",["id" => $Lastid, "to" => $to, "from" => $from, "message" => $message, "time" => $time, "threadid" => $threadid]);
		$this->receiveChatMessage($from,$to,$message,$time=null);
		return $Lastid;
	}

	private function receiveChatMessage($from,$to,$message,$time=null) {
		$sql = "INSERT INTO sms_messages (`from`, `to`, `direction`, `tx_rx_datetime`, `body`, `threadid`, `timestamp`) VALUES (?, ?, 'in', from_unixtime(?), ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$time = !empty($time) ? $time : time();
		$message = !is_null($message) ? $message : "";
		$threadid = sha1($to.$from);
		$sth->execute([$from, $to, $time, $message, $threadid, $time]);
		$id = $this->db->lastInsertId();
		$this->FreePBX->astman->UserEvent("sms-inbound",["id" => $id, "to" => $to, "from" => $from, "message" => $message, "time" => $time, "threadid" => $threadid]);
	}

	public function sendSms($from=null, $to=null, $message=null) {
		$result = ['status'=>false];
		if (empty($from) || empty($to)) {
			return $result;
		}
		$adaptor = $this->getAdaptor($from);
		if(is_object($adaptor)) {
			$result = [];
			$val = $adaptor->sendMessage($to, $from, null, $message);
			if($val['status']) {
				$result['status'] = true;
				$result['id'] = $val['id'];
			}
			return $result;
		} else {
			return $result;
		}
	}

	/**
	 * trigger an sms deleted event when a conversation is deleted from UCP
	 */
	private function sendSMSDeletedEvent($fromDid, $toDid, $threadId) {
		if (empty($fromDid) && empty($toDid)) {
			$sql = "SELECT * FROM sms_messages WHERE `threadid` = :threadId LIMIT 1";
			$sth = $this->db->prepare($sql);
			$sth->execute([':threadId' => $threadId]);
			$res = $sth->fetch(\PDO::FETCH_ASSOC);
			if (empty($res)) {
				return false;
			}
			$fromDid = $res['from'];
			$toDid = $res['to'];
		}
		$this->FreePBX->astman->UserEvent("sms-deleted", ["from_did" => $fromDid, "to_did" => $toDid, "time" => time()]);
	}

	public function convertBodytoImage($body=null) {
		if(!class_exists(\Emojione\Emojione::class)) {
			include(__DIR__."/includes/Emojione.class.php");
		}
		$this->emoji = new \Emojione\Client(new \Emojione\Ruleset());
		$value = $this->emoji->toImage($body);
		return $value;
	}
}
