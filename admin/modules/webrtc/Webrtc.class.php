<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\modules;
use BMO;
use FreePBX_Helpers;
use PDO;
use Exception;
class Webrtc extends FreePBX_Helpers implements BMO {

	/**
	 * Device Overrides depending on TECH to enable WebRTC
	 * @type {array}
	 */
	private array $overrides = ["sip" => ["transport" => "wss,ws", "avpf" => "yes", "force_avp" => "yes", "icesupport" => "yes", "encryption" => "yes", "rtcp_mux" => "yes"], "pjsip" => ["media_use_received_transport" => "yes", "avpf" => "yes", "icesupport" => "yes", "rtcp_mux" => "yes"]];

	/**
	 * Prefix added to all WebRTC Extensions
	 * @type {int}
	 */
	private string $prefix = '99';

	public function doConfigPageInit($page) {

	}

	public function install() {
		$status = $this->validVersion();
		if($status !== true) {
			out($status);
			throw new Exception($status);
		}
		//Remove Old Link if need be
		if(file_exists($this->FreePBX->Config->get('ASTETCDIR').'/http.conf') && is_link($this->FreePBX->Config->get('ASTETCDIR').'/http.conf') && (readlink($this->FreePBX->Config->get('ASTETCDIR').'/http.conf') == __DIR__.'/etc/httpd.conf')) {
			unlink($this->FreePBX->Config->get('ASTETCDIR').'/http.conf');
		}


		if($this->FreePBX->Config->conf_setting_exists('HTTPENABLED')) {
			$this->FreePBX->Config->set_conf_values(['HTTPENABLED' => true],true);
		}

		try {
			$sql = "SELECT * FROM webrtc_settings";
			$sth = $this->Database->prepare($sql);
			$sth->execute();
			$settings = $sth->fetchAll(PDO::FETCH_ASSOC);
			if(!empty($settings)) {
				foreach($settings as $setting) {
					$this->setConfig($setting['key'], $setting['value']);
				}
			}
			$sql = "DROP TABLE IF EXISTS `webrtc_settings`";
			$sth = $this->Database->prepare($sql);
			$sth->execute();
		} catch(Exception) {}

		$prefix = $this->getConfig('prefix');
		if(empty($prefix)) {
			$this->setConfig('prefix','99');
		}

		$clients = $this->getClientsEnabled();
		foreach($clients as $client) {
			$prefix = $client['prefix'] ?? $this->prefix;
			$module = isset($client['module'])&& ($client['module']!='')?$client['module']:'UCP';
			$this->createDevice($client['user'],$client['certid'],$prefix,$module);
		}
		// update settings from core which are already saved
		$sql = "SELECT DISTINCT(`user`) FROM webrtc_clients";
		$sth = $this->Database->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(!empty($results)) {
			foreach($results as $row) {
			$setting = [];
			$id= $row['user'];
			$q1 = "SELECT `data` FROM sip where id=? AND `keyword` = ?";
			$sth1 = $this->Database->prepare($q1);
			$sth1->execute([$id, 'accountcode']);
			$r1 = $sth1->fetch(PDO::FETCH_ASSOC);
			$setting['devinfo_accountcode'] = (is_array($r1) && array_key_exists('data', $r1)) ? $r1['data'] : '';
			//
			$sth1->execute([$id, 'namedcallgroup']);
			$r2 = $sth1->fetch(PDO::FETCH_ASSOC);
			$setting['devinfo_namedcallgroup'] = (is_array($r2) && array_key_exists('data', $r2)) ? $r2['data'] : '';
			//
			$sth1->execute([$id, 'namedpickupgroup']);
			$r3 = $sth1->fetch(PDO::FETCH_ASSOC);
			$setting['devinfo_namedpickupgroup'] = (is_array($r3) && array_key_exists('data', $r3)) ? $r3['data'] : '';
			$this->updatefromcore($id,$setting);
			unset($setting);
                        }
		}
		return true;
	}
	public function uninstall() {
		$sql = "SELECT * FROM webrtc_clients";
		$sth = $this->Database->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(!empty($results)) {
			foreach($results as $row) {
				$this->removeDevice($row['user']);
			}
		}
		return true;
	}

	public function genConfig() {
	}

	/**
	 * Enable WebRTC and originate for said user
	 * @param string $username Username
	 */
	public function migrationEnable($username) {
		$user = $this->FreePBX->Userman->getUserByUsername($username);
		if(!empty($user) && !empty($user['default_extension']) && $user['default_extension'] != "none") {
			if($this->FreePBX->Certman->checkCAexists()) {
				$certs = $this->FreePBX->Certman->getAllManagedCertificates();
				if(!empty($certs)) {
					$this->createDevice($user['default_extension']);
				}
			}
		}
	}

	public function ucpDelGroup($id,$display,$data) {
		if(!empty($data['users'])) {
			foreach($data['users'] as $id) {
				$enabled = $this->FreePBX->Ucp->getCombinedSettingByID($id, 'Webrtc', 'enabled');

				$user = $this->FreePBX->Userman->getUserByID($id);
				if(!empty($user['default_extension']) && $user['default_extension'] != 'none' && $enabled) {
					if(!$this->checkEnabled($user['default_extension'])) {
						$this->createDevice($user['default_extension'],$_REQUEST['webrtc_cert']);
					}
				} else {
					if($this->checkEnabled($user['default_extension'])) {
						$this->removeDevice($user['default_extension']);
					}
				}
			}
		}
	}

	public function ucpAddGroup($id, $display, $data) {
		$this->ucpUpdateGroup($id,$display,$data);
	}

	public function ucpUpdateGroup($id,$display,$data) {
		if($display == "userman") {
			if(isset($_POST['webrtc_enable']) && $_POST['webrtc_enable'] == 'yes') {
				$this->FreePBX->Ucp->setSettingByGID($id,'Webrtc','enabled',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Webrtc','enabled',false);
			}
		}

		$group = $this->FreePBX->Userman->getGroupByGID($id);
		foreach($group['users'] as $user) {
			$enabled = $this->FreePBX->Ucp->getCombinedSettingByID($user, 'Webrtc', 'enabled');
			$user = $this->FreePBX->Userman->getUserByID($user);
			if(!empty($user['default_extension']) && $user['default_extension'] != 'none' && $enabled) {
				$dev = $this->FreePBX->Core->getDevice($user['default_extension']);
				$id = $this->prefix.$user['default_extension'];
				$settings = $this->FreePBX->Certman->getDTLSOptions($id);
				$defaultCert = $this->FreePBX->Certman->getDefaultCertDetails();
				if(empty($defaultCert)) {
					return false;
				}

				if(!empty($dev) && (!$this->checkEnabled($user['default_extension']) || ($this->checkEnabled($user['default_extension']) && $settings['cid'] != $defaultCert['cid']))) {
					$this->createDevice($user['default_extension']);
				}
			} elseif($user['default_extension'] != 'none') {
				if($this->checkEnabled($user['default_extension'])) {
					$this->removeDevice($user['default_extension']);
				}
			}
		}
	}

	/**
	* Hook functionality from userman when a user is deleted
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpDelUser($id, $display, $ucpStatus, $data) {
		$enabled = $this->FreePBX->Ucp->getCombinedSettingByID($id, 'Webrtc', 'enabled');

		$user = $this->FreePBX->Userman->getUserByID($id);
		if(!empty($user['default_extension']) && $user['default_extension'] != 'none' && $enabled) {
		} else {
			if(isset($user['default_extension']) && $this->checkEnabled($user['default_extension'])) {
				$this->removeDevice($user['default_extension']);
			}
		}
	}

	/**
	* Hook functionality from userman when a user is added
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpAddUser($id, $display, $ucpStatus, $data) {
		$this->ucpUpdateUser($id, $display, $ucpStatus, $data);
	}

	/**
	* Hook functionality from userman when a user is updated
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpUpdateUser($id, $display, $ucpStatus, $data) {
		if($display == "userman") {
			if(isset($_POST['webrtc_enable']) && $_POST['webrtc_enable'] == 'yes') {
				$this->FreePBX->Ucp->setSettingByID($id,'Webrtc','enabled',true);
			} elseif(isset($_POST['webrtc_enable']) && $_POST['webrtc_enable'] == 'no') {
				$this->FreePBX->Ucp->setSettingByID($id,'Webrtc','enabled',false);
			} elseif(isset($_POST['webrtc_enable']) && $_POST['webrtc_enable'] == 'inherit') {
				$this->FreePBX->Ucp->setSettingByID($id,'Webrtc','enabled',null);
			}
		}

		$enabled = $this->FreePBX->Ucp->getCombinedSettingByID($id, 'Webrtc', 'enabled');

		$user = $this->FreePBX->Userman->getUserByID($id);
		if(!empty($user['default_extension']) && $user['default_extension'] != 'none' && $enabled) {
			$id = $this->prefix.$user['default_extension'];
			$defaultCert = $this->FreePBX->Certman->getDefaultCertDetails();
			if(empty($defaultCert)) {
				return false;
			}
			$settings = $this->FreePBX->Certman->getDTLSOptions($id);
			if(!$this->checkEnabled($user['default_extension']) || ($this->checkEnabled($user['default_extension']) && $settings['cid'] != $defaultCert['cid'])) {
				$this->createDevice($user['default_extension']);
			}
		} else {
			if($this->checkEnabled($user['default_extension'])) {
				$this->removeDevice($user['default_extension']);
			}
		}
		//
	}

	public function ucpConfigPage($mode, $user, $action) {
		$html = [];
		$defaultCert = $this->FreePBX->Certman->getDefaultCertDetails();
		if(empty($user)) {
			$enabled = ($mode == 'group') ? true : null;
		} else {
			if($mode == 'group') {
				$enabled = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Webrtc','enabled');
				$enabled = !($enabled) ? false : true;
			} else {
				$enabled = $this->FreePBX->Ucp->getSettingByID($user['id'],'Webrtc','enabled');
			}
		}

		$html[0] = ["title" => _("Phone"), "rawname" => "webrtc", "content" => ""];
		if($this->validVersion() === true && !empty($defaultCert)) {
			$html[0]['content'] = load_view(__DIR__."/views/ucp_config.php",["mode" => $mode, "enabled" => $enabled, "webrtcmessage" => '', "config" => true]);
		} elseif($this->validVersion() === true) {
			$html[0]['content'] = load_view(__DIR__."/views/ucp_config.php",["mode" => $mode, "enabled" => $enabled, "webrtcmessage" => sprintf(_('You have no default certificates setup in %s'),'<a href="?display=certman">'._('Certificate Manager').'</a>'), "config" => false]);
		} else {
			$html[0]['content'] = load_view(__DIR__."/views/ucp_config.php",["mode" => $mode, "enabled" => $enabled, "webrtcmessage" => $this->validVersion(), "config" => false]);
		}
		return $html;
	}

	public function validVersion() {
		$version = $this->FreePBX->Config->get('ASTVERSION');
		$vParts = explode(".",(string) $version);
		$base = $vParts[0];
		$res_ver = IsAsteriskSupported($base); // method located in framework utility.function.php
		if ($res_ver["status"] == false) {
			return (sprintf(_("Running an unsupported version of Asterisk. %s Detected Asterisk version: %s "), $res_ver["message"], $version));
		}
		return true;
	}

	public function getAllSettings() {
			return $this->getAll();
	}

	public function getSetting($setting) {
			return $this->getConfig($setting);
	}

	public function setSetting($setting,$value) {
		return $this->setConfig($setting,$value);
	}

	public function delSetting($setting) {
		return $this->delConfig($setting);
	}

	public function checkEnabled($user,$prefix ='') {
		$prefix = ($prefix == '')?$this->prefix:$prefix;
		$settings = $this->getClientSettingsByUser($user,$prefix);
		return !empty($settings);
	}

	public function setClientSettings($user,$device,$prefix,$module) {
		try {
			$sql = "REPLACE INTO webrtc_clients (`user`, `device`,`prefix`,`module`) VALUES(?,?,?,?)";
			$sth = $this->Database->prepare($sql);
			return $sth->execute([$user, $device, $prefix, $module]);
		} catch(Exception) {
			return false;
		}
    }

    /** This does the same as setClientSettings but adds certid. did not want to break existing calls with a bad certid default */
	public function upsertClientSettings($user,$device,$certid) {
		try {
			$sql = "REPLACE INTO webrtc_clients (`user`, `device`, `certid`) VALUES(?,?,?)";
			$sth = $this->Database->prepare($sql);
			return $sth->execute([$user, $device, $certid]);
		} catch(Exception) {
			return false;
		}
	}

	public function getClientsEnabled() {
		$sql = "SELECT * FROM webrtc_clients";
		$sth = $this->Database->prepare($sql);
		$sth->execute();
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(empty($results)) {
			return [];
		}
		return $results;
	}

	public function getClientSettingsByUser($user,$prefix='') {
		 $prefix = ($prefix == '')?$this->prefix:$prefix;
		$sql = "SELECT * FROM webrtc_clients WHERE `user` = ? AND `prefix`=?";
		$sth = $this->Database->prepare($sql);
		$sth->execute([$user, $prefix]);
		$results = $sth->fetch(PDO::FETCH_ASSOC);
		if(empty($results)) {
			return false;
		}

		$serverparts = explode(":", (string) $_SERVER['HTTP_HOST']); //strip off port because we define it
		$sip_server = $serverparts[0];
		$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on";
		$dev = $this->FreePBX->Core->getDevice($results['device']);
		if(empty($dev)) {
			//no device so remove the settings, someone deleted the device basically
			$this->removeClientSettingsByUser($user,$prefix);
			return false;
		}
		if($this->FreePBX->Config->get('HTTPTLSENABLE') && $dev['transport'] == "chan_sip" && ($dev['transport'] != "wss" && $dev['transport'] != "wss,ws")) {
			return false;
		}
		//$usr = core_users_get($results['user']);
		$results['status'] = true;
		$results['realm'] = !empty($results['realm']) ? $results['realm'] : $sip_server;
		$results['username'] = !empty($results['username']) ? $results['username'] : $dev['id'];
		$results['sipuri'] = !empty($results['sipuri']) ? $results['sipuri'] : 'sip:'.$results['username'].'@'.$sip_server;
		$results['password'] = !empty($results['password']) ? $results['password'] : $dev['secret'];
		$prefix = $this->FreePBX->Config->get('HTTPPREFIX');
		$suffix = !empty($prefix) ? "/".$prefix."/ws" : "/ws";

		if($secure && !$this->FreePBX->Config->get('HTTPTLSENABLE')) {
			return ["status" => false, "message" => _("HTTPS is not enabled for Asterisk")];
		}

		$type = ($this->FreePBX->Config->get('HTTPTLSENABLE') && $secure) ? 'wss' : 'ws';
		$port = ($this->FreePBX->Config->get('HTTPTLSENABLE') && $secure) ? $this->FreePBX->Config->get('HTTPTLSBINDPORT') : $this->FreePBX->Config->get('HTTPBINDPORT');
		$results['websocket'] = !empty($results['websocket']) ? $results['websocket'] : $type.'://'.$sip_server.':'.$port.$suffix;
		try {
			$stunaddr = $this->FreePBX->Sipsettings->getConfig("webrtcstunaddr");
			$stunaddr = !empty($stunaddr) ? $stunaddr : $this->FreePBX->Sipsettings->getConfig("stunaddr");
			$results['stunaddr'] = $stunaddr;
		} catch(Exception) {}
		$results['stunaddr'] = !empty($results['stunaddr']) ? "stun:".$results['stunaddr'] : "stun:stun.l.google.com:19302";
		return $results;
	}

	public function removeClientSettingsByUser($user,$prefix) {
		try {
			$sql = "DELETE FROM webrtc_clients WHERE `user` = ? AND prefix=?";
			$sth = $this->Database->prepare($sql);
			return $sth->execute([$user, $prefix]);
		} catch(Exception) {
			return true;
		}
	}

	/* Updates the extension settings from Core  */

	public function updatefromcore($ext,$settings=[]){
		//update accountcode from primary extension to all its devices of webrtc
		$sql = "SELECT `device` FROM webrtc_clients WHERE `user` = ? ";
		$sth = $this->Database->prepare($sql);
		$sth->execute([$ext]);
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(is_array($results)){
			foreach($results as $res) {
				$device = $res['device'];
				if(isset($settings['devinfo_accountcode']) && strlen(trim((string) $settings['devinfo_accountcode'])) > 0){
					$data = $settings['devinfo_accountcode'];
					$query = "Update sip SET `data`=? Where `id`=? AND `keyword`=?";
					$sth1 = $this->Database->prepare($query);
					$sth1->execute([$data, $device, 'accountcode']);
				}
				// update pickup groups
				if(isset($settings['devinfo_namedcallgroup']) && strlen(trim((string) $settings['devinfo_namedcallgroup'])) > 0){
					$data = $settings['devinfo_namedcallgroup'];
					$query = "REPLACE INTO sip (`id`, `data`,`keyword`) VALUES(?,?,?)";
					$sth1 = $this->Database->prepare($query);
					$sth1->execute([$device, $data, 'namedcallgroup']);
				}
				//devinfo_namedpickupgroup
				if(isset($settings['devinfo_namedpickupgroup']) && strlen(trim((string) $settings['devinfo_namedpickupgroup'])) > 0){
					$data = $settings['devinfo_namedpickupgroup'];
					$query = "REPLACE INTO sip (`id`, `data`,`keyword`) VALUES(?,?,?)";
					$sth1 = $this->Database->prepare($query);
					$sth1->execute([$device, $data, 'namedpickupgroup']);
				}
			}
		}
	}

	public function createDevice($extension,$cid = '',$prefix='',$module='UCP') {
		if($prefix == ''){
			$id = $this->prefix.$extension;
			$prefix = $this->prefix;
		}else {
			$id = $prefix.$extension;
		}
		$previous = $this->FreePBX->Core->getDevice($id);
		if(!empty($previous)) {
			$this->FreePBX->Core->delDevice($id);
		}
		$version = $this->FreePBX->Config->get('ASTVERSION');
		$user = $this->FreePBX->Core->getUser($extension);
		$user['name'] = (array_key_exists('name', $user)) ? $user['name'] : '';
		$dev = $this->FreePBX->Core->getDevice($extension);
		$socket = $this->getSocketMode();
		$settings = $this->FreePBX->Core->generateDefaultDeviceSettings($socket,$id,'WebRTC '.$user['name']);
		if(!empty($previous['secret'])) {
			$settings['secret']['value'] = $previous['secret'];
		}
		if($module == 'SangomaConnect'){
			$settings['force_callerid']['value'] = 'yes';
			$settings['callerid']['value'] = $user['name'].' <'.$extension.'>';
		}
		if (isset($dev['accountcode'])) {
			$settings['accountcode']['value'] = $dev['accountcode'];
		} else {
			$accountcode = $this->FreePBX->astman->database_get("AMPUSER",$extension."/accountcode");
			$settings['accountcode']['value'] = $accountcode;
		}

		$settings['namedcallgroup']['value'] = $dev['namedcallgroup'] ?? '';
		$settings['namedpickupgroup']['value'] = $dev['namedpickupgroup'] ?? '';
		$settings['devicetype']['value'] = 'fixed';
		$settings['context']['value'] = !empty($dev['context']) ? $dev['context'] : "from-internal";
		$settings['user']['value'] = $extension;
		$settings['webrtc']['value'] = !empty($dev['webrtc']) ? $dev['webrtc'] : "yes";
		$defaultCert = $this->FreePBX->Certman->getDefaultCertDetails();
		if(empty($defaultCert)) {
			return false;
		}
		if($cid == ''){
			$cid = $defaultCert['cid'];
		}
		$cert = ["certificate" => $cid, "verify" => "fingerprint", "setup" => "actpass", "rekey" => "0"];
		switch($socket) {
			case 'sip':
				$settings['avpf']['value'] = 'yes';
				$settings['force_avp']['value'] = 'yes';
				$settings['transport']['value'] = 'wss,ws';
				$settings['icesupport']['value'] = 'yes';
				$settings['encryption']['value'] = 'yes';
				$settings['sessiontimers']['value'] = 'refuse';
				$settings['videosupport']['value'] = 'no';
				if((version_compare($version,'13.15.0','ge') && version_compare($version,'14.0','lt')) || version_compare($version,'14.4.0','ge')) {
					$settings['rtcp_mux']['value'] = 'yes';
				}
				$this->FreePBX->Core->addDevice($id,'sip',$settings);
			break;
			case 'pjsip':
				$settings['avpf']['value'] = 'yes';
				$settings['icesupport']['value'] = 'yes';
				$settings['media_use_received_transport']['value'] = 'yes';
				$settings['timers']['value'] = 'no';
				$settings['media_encryption']['value'] = 'dtls';
				$settings['webrtc']['value'] = 'yes';
				if((version_compare($version,'13.15.0','ge') && version_compare($version,'14.0','lt')) || version_compare($version,'14.4.0','ge')) {
					$settings['rtcp_mux']['value'] = 'yes';
				}
				$this->FreePBX->Core->addDevice($id,'pjsip',$settings);
			break;
			default:
				return false;
			break;
		}
		$this->FreePBX->Certman->addDTLSOptions($id, $cert);
		$this->setClientSettings($extension,$id,$prefix,$module);
		return true;
	}

	public function getSocketMode() {
		$websocketMode = null;
		if($this->FreePBX->astman->mod_loaded("res_pjsip_transport_websocket")) {
			$type = $this->FreePBX->astman->Command("module show like res_pjsip_transport_websocket");
			if(preg_match("/Not Running/",(string) $type['data'])) {
				$websocketMode = 'sip';
			} else {
				$websocketMode = 'pjsip';
			}
		} else {
			$websocketMode = 'sip';
		}
		return $websocketMode;
	}

	public function removeDevice($extension,$prefix = '') {
		if($prefix == ''){
			$prefix = $this->prefix;
		}
		$id = $prefix.$extension;
		$this->removeClientSettingsByUser($extension,$prefix);
		$this->deleteDevice($id);
	}

	private function deleteDevice($device) {
		try {
			return $this->FreePBX->Core->delDevice($device);
		} catch(Exception) {
			return false;
		}
	}
	public function dashboardIgnoreExt(){
		return [['length' => 2, 'value' => '99']];
	}
	public function delUser($extension, $editmode=false) {
		if(!$editmode) {
			$this->removeDevice($extension);
		}
	}

	public function setDatabase($pdo){
		$this->Database = $pdo;
		return $this;
	}
	
	public function resetDatabase(){
		$this->Database = $this->FreePBX->Database;
		return $this;
	}
}
