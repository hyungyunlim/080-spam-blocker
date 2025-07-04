<?php
namespace FreePBX\modules;
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
#[\AllowDynamicProperties]
class Findmefollow implements \BMO {

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}

	public function doConfigPageInit($page) {
		global $amp_conf;
		if(!isset($_REQUEST['action'])){
			return;
		}
		$request = $_REQUEST;
		$action = $request['action'] ?? '';
		$extdisplay= $request['extdisplay'] ?? '';
		$account = $request['account'] ?? '';
		//the extension we are currently displaying
		$settings = ['grptime' => $request['grptime'] ?? $amp_conf['FOLLOWME_TIME'], 'grppre' => $request['grppre'] ?? '', 'strategy' => $request['strategy'] ?? $amp_conf['FOLLOWME_RG_STRATEGY'], 'annmsg_id' => $request['annmsg_id'] ?? '', 'dring' => $request['dring'] ?? '', 'needsconf' => $request['needsconf'] ?? '', 'remotealert_id' => $request['remotealert_id'] ?? '', 'toolate_id' => $request['toolate_id'] ?? '', 'ringing' => $request['ringing'] ?? '', 'pre_ring' => $request['pre_ring'] ?? $amp_conf['FOLLOWME_PRERING'], 'changecid' => $request['changecid'] ?? 'default', 'fixedcid' => $request['fixedcid'] ?? '', 'rvolume' => $request['rvolume'] ?? '', 'calendar_enable' => $request['calendar_enable'] ?? '', 'calendar_id' => $request['calendar_id'] ?? '', 'calendar_group_id' => $request['calendar_group_id'] ?? '', 'calendar_match' => $request['calendar_match'] ?? 'yes'];

		if (isset($request['ddial'])) {
			$settings['ddial'] =	$request['ddial'];
		}	else {
			$settings['ddial'] = $request['ddial_value'] ?? ($amp_conf['FOLLOWME_DISABLED'] ? 'CHECKED' : '');
		}

		if (isset($request['goto0']) && isset($request[$request['goto0']."0"])) {
			$settings['postdest'] = $request[$request['goto0']."0"];
		} else {
			$settings['postdest'] = "ext-local,$extdisplay,dest";
		}

		if (isset($request["grplist"])) {
			$settings['grplist'] = explode("\n",(string) $request["grplist"]);

			if (!$settings['grplist']) {
				$settings['grplist'] = null;
			}

			foreach (array_keys($settings['grplist']) as $key) {
				//trim it
				$settings['grplist'][$key] = trim($settings['grplist'][$key]);

				// remove invalid chars
				$settings['grplist'][$key] = preg_replace("/[^0-9#*+]/", "", $settings['grplist'][$key]);

				//Dont allow self to be a local channel
				if ($settings['grplist'][$key] == ltrim((string) $extdisplay,'GRP-').'#') {
					$settings['grplist'][$key] = rtrim($settings['grplist'][$key],'#');
				}

				// remove blanks
				if ($settings['grplist'][$key] == "") unset($settings['grplist'][$key]);
			}

			// check for duplicates, and re-sequence
			$settings['grplist'] = array_values(array_unique($settings['grplist']));
		}

		$settings['grplist'] = implode("-",$settings['grplist']);

		// do if we are submitting a form
		if(isset($request['action'])){
			//check if the extension is within range for this user
			if (isset($account) && !checkRange($account)){
				echo "<script>javascript:alert('". _("Warning! Extension")." ".$account." "._("is not allowed for your account").".');</script>";
			} else {
				//add group
				if ($action == 'addGRP') {
					$this->add($account,$settings);
					needreload();
				}

				//del group
				if ($action == 'delGRP') {
					$this->del($account, true);
					needreload();
				}

				//edit group - just delete and then re-add the extension
				if ($action == 'edtGRP') {
					$this->del($account);
					$this->add($account,$settings);
					needreload();
				}
			}
		}

	}

	public function install() {

	}
	public function uninstall() {

	}

	public function genConfig() {

	}

	public function getQuickCreateDisplay() {
		$fm = $this->FreePBX->Config->get('FOLLOWME_AUTO_CREATE');
		if($fm) {
			return [];
		}
		return [1 => [['html' => load_view(__DIR__.'/views/quickCreate.php',["fmfm" => !$this->FreePBX->Config->get('FOLLOWME_DISABLED')])]]];
	}

	/**
	 * Delete user function, it's run twice because of scemantics with
	 * old freepbx but it's harmless
	 * @param  string $extension The extension number
	 * @param  bool $editmode  If we are in edit mode or not
	 */
	public function delUser($extension, $editmode=false) {
		if(!$editmode) {
			if(!function_exists('findmefollow_destinations')) {
				$this->FreePBX->Modules->loadFunctionsInc('findmefollow');
			}
			$this->del($extension, true);
		}
	}

	/* UCP template to get the user assigned vm extension details
	* @defaultexten is the default_extensionof the userman userid
	* @userid is userman user id
	* @widget is an array we need to replace few item based on the userid
	*/
	public function getWidgetListByModule($defaultexten, $userid,$widget) {
		// if the widget_type_id is not defaultextension and widget_type_id is not in extensions
		// then return only the defaultexten details
		$widgets = [];
		$widget_type_id = $widget['widget_type_id'];// this will be an extension number
		$enabled = $this->FreePBX->Ucp->getCombinedSettingByID($userid,'Findmefollow','enable');
		if (!$enabled) {
			return false;
		}
		$extensions = $this->FreePBX->Ucp->getCombinedSettingByID($userid,'Findmefollow','assigned');
		if(in_array($widget_type_id,$extensions)){
			// nothing to do return the same widget
			return $widget;
		}else {// sent the default extension
			$data = $this->FreePBX->Core->getDevice($defaultexten);
			if(empty($data) || empty($data['description'])) {
				$data = $this->FreePBX->Core->getUser($defaultexten);
				$name = $data['name'];
			} else {
				$name = $data['description'];
			}
			$widget['widget_type_id'] = $defaultexten;
			$widget['name'] = $name;
			return $widget;
		}
		return false;
	}


	/**
	 * Quick Create hook
	 * @param string $tech      The device tech
	 * @param int $extension The extension number
	 * @param array $data      The associated data
	 */
	public function processQuickCreate($tech, $extension, $data) {
		if($this->FreePBX->Config->get('FOLLOWME_AUTO_CREATE') || (!empty($data['fmfm']) && $data['fmfm'] == "yes")) {
			if(!function_exists('findmefollow_destinations')) {
				$this->FreePBX->Modules->loadFunctionsInc('findmefollow');
			}
			$data['ddial'] = ($data['fmfm'] == "yes") ? "" : "CHECKED";
			$this->add($extension,$data);
		}
	}

	/*
	 * Gets Follow Me Confirmation Setting
	 *
	 * @param string $exten Extension to get information about
	 * @return bool True is confirmed, False is not
	 */
	function getConfirm($exten) {
		$response = $this->FreePBX->astman->database_get("AMPUSER","$exten/followme/grpconf");
		return preg_match("/ENABLED/",(string) $response);
	}

	/*
	 * Sets Follow Confirmation Setting
	 *
	 * @param string $exten Extension to modify
	 * @param bool $follow_me_cofirm Follow Me Confirm Setting
	 */
	function setConfirm($exten,$follow_me_confirm) {
		$value = ($follow_me_confirm)?'ENABLED':'DISABLED';
		$this->FreePBX->astman->database_put('AMPUSER', "$exten/followme/grpconf", $value);
	}

	/*
	 * Sets Follow Me List
	 *
	 * @param $exten Extension to modify
	 * @param $follow_me_list Follow Me List
	 */
	function setList($exten,$follow_me_list) {

		$clean_follow_me_list = [];
		foreach($follow_me_list as $value) {
			$value = $this->lookupSetExtensionFormat($exten, $value);
			if ($value) {
				array_push($clean_follow_me_list, $value);
			}
		}

		$follow_me_list = implode("-", $clean_follow_me_list);
		$this->FreePBX->astman->database_put('AMPUSER', "$exten/followme/grplist", $follow_me_list);
	}

	/**
	 * Lookup extension format
	 * This should be depreciated eventually
	 * @param {int} $grp The FMFM Group
	 * @param {int} $exten The Phone Number
	 */
	function lookupSetExtensionFormat($grp, $exten) {

		$hadPound = preg_match("/#$/",(string) $exten);
		$exten = preg_replace("/[^0-9*+]/", "", (string) $exten);

		// Rem: This was moved from above to catch also cases of lines containing only bogus stuf
		if (trim($exten) == "") {
			return null;
		};

		//Dont allow self to be a local channel
		if($hadPound && $exten != $grp) {
			return $exten.'#';
		}

		$result = $this->FreePBX->Core->getUser($exten);
		if (empty($result)) {
			return $exten.'#';
		} else {
			return $exten;
		}
	}

	/*
	 * Gets Follow Me List if set
	 *
	 * @param $exten Extension to get information about
	 * @return $data follow me list if set
	 */
	function getList($exten) {
		$response = $this->FreePBX->astman->database_get("AMPUSER","$exten/followme/grplist");
		return preg_replace("/[^0-9#*\-+]/", "", (string) $response);
	}

	/*
	 * Sets Follow Me List Ring Time
	 *
	 * @param $exten Extension to modify
	 * @param $follow_me_listring_time List Ring Time to ring
	 */
	function setListRingTime($exten,$follow_me_listring_time) {
		$this->FreePBX->astman->database_put('AMPUSER', "$exten/followme/grptime", $follow_me_listring_time);
	}

	/*
	 * Gets Follow Me List-Ring Time if set
	 *
	 * @param $exten Extension to get information about
	 * @return $number follow me list-ring time returned if set
	 */
	function getListRingTime($exten) {
		$response = $this->FreePBX->astman->database_get("AMPUSER","$exten/followme/grptime");
		return is_numeric($response) ? $response : '';
	}

	/*
	 * Sets Follow Me Pre-Ring Time
	 *
	 * @param $exten Extension to modify
	 * @param $follow_me_prering_time Pre-Ring Time to ring
	 */
	function setPreRingTime($exten,$follow_me_prering_time) {
		$this->FreePBX->astman->database_put('AMPUSER', "$exten/followme/prering", $follow_me_prering_time);
	}

	/*
	 * Gets Follow Me Pre-Ring Time if set
	 *
	 * @param $exten Extension to get information about
	 * @return $number follow me pre-ring time returned if set
	 */
	function getPreRingTime($exten) {
		$response = $this->FreePBX->astman->database_get("AMPUSER","$exten/followme/prering");
		return is_numeric($response) ? $response : '';
	}

	/*
	 * Sets Follow Ddial Setting
	 *
	 * @param $exten Extension to modify
	 * @param $follow_me_ddial Follow Me Ddial Setting
	 */
	function setDDial($exten,$follow_me_ddial) {
		$value_opt = ($follow_me_ddial)?'DIRECT':'EXTENSION';
		$response = $this->FreePBX->astman->database_put('AMPUSER',"$exten/followme/ddial",$value_opt);

		// Now that we have set the state (DIRECT is enabled, EXTENSION is disabled)
		// Get the devices associated with this user first and then we will set them all as needed
		//
		//
		if ($this->FreePBX->Config->get_conf_setting('USEDEVSTATE')) {
			$value_opt = ($follow_me_ddial)?'BUSY':'NOT_INUSE';
			$devices = $this->FreePBX->astman->database_get("AMPUSER",$exten."/device");
			$device_arr = explode('&',(string) $devices);
			foreach ($device_arr as $device) {
				$this->FreePBX->astman->set_global($this->FreePBX->Config->get_conf_setting('AST_FUNC_DEVICE_STATE') . "(Custom:FOLLOWME$device)", $value_opt);
			}
		}

		$this->triggerSettingsChangeEvent($exten);

		return $response;
	}

	/*
	 * Gets Follow Me Ddial Setting
	 *
	 * @param $exten Extension to get information about
	 * @return $data follow me ddial setting
	 */
	function getDDial($exten) {
		$response = $this->FreePBX->astman->database_get("AMPUSER",$exten."/followme/ddial");
		if (trim((string) $response) == 'EXTENSION') {
			return true;
		} elseif (trim((string) $response) == 'DIRECT') {
			return false;
		} else {
			// If here then followme must not be set so use default
			return $this->FreePBX->Config->get_conf_setting('FOLLOWME_DISABLED') ? true : false;
		}
	}

	/*
	 * Sets Follow-Me Settings in FreePBX MySQL Database
	 *
	 * @param $exten Extension to modify
	 * @param $follow_me_prering_time Pre-Ring Time to ring
	 * @param $follow_me_listring_time List Ring Time to ring
	 * @param $follow_me_list Follow Me List
	 * @param $follow_me_list Follow Me Confirm Setting
	 *
	 */
	function setMySQL($exten, $follow_me_prering_time, $follow_me_listring_time, $follow_me_list, $follow_me_confirm) {

		$follow_me_confirm = ($follow_me_confirm)?'CHECKED':'';
		$sql = 'UPDATE findmefollow SET grptime = :grptime, grplist = :grplist, pre_ring = :pre_ring, needsconf = :needsconf WHERE grpnum = :grpnum LIMIT 1';
		$params = [':grptime' => $follow_me_listring_time, ':grplist' => trim((string) $follow_me_list), ':pre_ring' => $follow_me_prering_time, ':needsconf' => $follow_me_confirm, ':grpnum' => $exten];
		$stmt = $this->db->prepare($sql);
		$sql_results = $stmt->execute($params);
		return 1;
	}

	function listAll() {
		$list = findmefollow_list();
		return !empty($list) ? $list : [];
	}

	function addSettingById($grpnum,$setting,$value='') {
		return $this->addSettingsById($grpnum, [$setting => $value]);
	}

	function addSettingsById($grpnum,$settings) {
		$valid = ['strategy', 'grptime', 'grppre', 'grplist', 'annmsg_id', 'postdest', 'dring', 'needsconf', 'remotealert_id', 'toolate_id', 'ringing', 'pre_ring', 'ddial', 'changecid', 'fixedcid', 'calendar_id', 'calendar_match', 'calendar_group_id', 'calendar_enable', 'rvolume'];

		$settings = array_intersect_key($settings, array_flip($valid));

		if (count($settings) == 0) {
			return false;
		}

		$ret = true;

		foreach ($settings as $setting => $value) {
			//TODO This should just be one query.
			$sql = "INSERT INTO findmefollow (grpnum,$setting) VALUES (:grpnum,:value) ON DUPLICATE KEY UPDATE $setting = :value";
			$sth = $this->db->prepare($sql);

			switch($setting) {
				case 'strategy':
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/strategy",$value);
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'grptime':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
					$this->setListRingTime($grpnum,$value);
				break;
				case 'grppre':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'grplist':
					$this->setList($grpnum,$value);
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => implode("-",$value)]);
				break;
				case 'annmsg_id':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'postdest':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'dring':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'needsconf':
					$val = ($value) ? 'CHECKED' : '';
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $val]);
					$val = ($value) ? 'ENABLED' : 'DISABLED';
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/grpconf",$val);
				break;
				case 'remotealert_id':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'toolate_id':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'ringing':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'rvolume':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'calendar_id':
				case 'calendar_group_id':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
				break;
				case 'calendar_enable':
					$enabled = $value ? 1 : 0;
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $enabled]);
				break;
				case 'calendar_match':
					$enabled = 'yes'; // Default value is `yes`
					if ($value === 'no' || empty($value)) {
						$enabled = 'no';
					}
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $enabled]);
				break;
				case 'pre_ring':
					$sth->execute([':grpnum' => $grpnum, ':key' => $setting, ':value' => $value]);
					$this->setPreRingTime($grpnum,$value);
				break;
				case 'ddial':
					//(DIRECT is enabled, EXTENSION is disabled)
					$ddialstate = ($value) ? 'NOT_INUSE' : 'BUSY';
					$val = ($value) ? 'EXTENSION' : 'DIRECT';
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/ddial",$val);
					if ($this->FreePBX->Config->get_conf_setting('USEDEVSTATE')) {
						$devices = $this->FreePBX->astman->database_get("AMPUSER", $grpnum . "/device");
						$device_arr = explode('&', (string) $devices);
						foreach ($device_arr as $device) {
							$this->FreePBX->astman->set_global($this->FreePBX->Config->get_conf_setting('AST_FUNC_DEVICE_STATE') . "(Custom:FOLLOWME$device)", $ddialstate);
						}
					}
					if(!$value) {
						$sql = "INSERT INTO findmefollow (grpnum,grptime,grplist) VALUES (:grpnum,20,:grpnum)";
						$sth = $this->db->prepare($sql);
						//wrapped into a try/catch incase the find me is already defined, then we won't do the additional steps.
						try {
							$sth->execute([':grpnum' => $grpnum]);
							//these are the additional steps
							$this->setListRingTime($grpnum,20);
							$this->setList($grpnum,[$grpnum]);
						} catch(\Exception) {}
					}
				break;
				case 'changecid':
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/changecid",$value);
				break;
				case 'fixedcid':
					$value = preg_replace("/[^0-9\+]/" ,"", trim((string) $value));
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/fixedcid",$value);
				break;
				default:
					$ret = false;
				break;
			}
		}

		$this->triggerSettingsChangeEvent($grpnum);

		return $ret;
	}

	function getSettingsById($grpnum, $check_astdb=0) {
		$astdb_prering = null;
  $astdb_grptime = null;
  $astdb_grplist = null;
  $astdb_grpconf = null;
  $db = $this->db;
		$sql = "SELECT grpnum, strategy, grptime, grppre, grplist, annmsg_id, postdest, dring, needsconf, remotealert_id, toolate_id, ringing, pre_ring, voicemail, calendar_id, calendar_match FROM findmefollow INNER JOIN `users` ON `extension` = `grpnum` WHERE grpnum = ?";
		$sth = $db->prepare($sql);
		$sth->execute([$grpnum]);
		$results = $sth->fetch(\PDO::FETCH_ASSOC);

		if (empty($results)) {
			//defaults
			return ["ddial" => true, "needsconf" => false, "grplist" => $grpnum, "pre_ring" => '', "grpnum" => $grpnum, "annmsg_id" => '', "remotealert_id" => '', "toolate_id" => '', "grptime" => '20'];
		}

		if (!isset($results['voicemail'])) {
			$sql = "SELECT `voicemail` FROM `users` WHERE `extension` = ?";
			$sth = $db->prepare($sql);
			$sth->execute([$grpnum]);
			$results['voicemail'] = $sth->fetchColumn();
		}

		if (!isset($results['strategy'])) {
			$results['strategy'] = $this->FreePBX->Config->get_conf_setting('FOLLOWME_RG_STRATEGY');
		}

		if ($check_astdb) {
			if ($this->FreePBX->astman->Connected()) {
				$astdb_prering = $this->getPreRingTime($grpnum);
				$astdb_grptime = $this->getListRingTime($grpnum);
				$astdb_grplist = $this->getList($grpnum);
				$astdb_grpconf = $this->getConfirm($grpnum);

				$astdb_changecid = strtolower((string) $this->FreePBX->astman->database_get("AMPUSER",$grpnum."/followme/changecid"));
				switch($astdb_changecid) {
					case 'default':
					case 'did':
					case 'forcedid':
					case 'fixed':
					case 'extern':
					break;
					default:
						$astdb_changecid = 'default';
				}
				$results['changecid'] = $astdb_changecid;
				$fixedcid = $this->FreePBX->astman->database_get("AMPUSER",$grpnum."/followme/fixedcid");
				$results['fixedcid'] = preg_replace("/[^0-9\+]/" ,"", trim((string) $fixedcid));
			}
			$astdb_ddial = $this->getDDial($grpnum);
			// If the values are different then use what is in astdb as it may have been changed.
			// If sql returned no results for pre_ring/grptime then it's not configued so we reset
			// the astdb defaults as well
			//
			$changed=0;
			if (!isset($results['pre_ring'])) {
				$results['pre_ring'] = $astdb_prering = $this->FreePBX->Config->get_conf_setting('FOLLOWME_PRERING');
			}
			if (!isset($results['grptime'])) {
				$results['grptime'] = $astdb_grptime = $this->FreePBX->Config->get_conf_setting('FOLLOWME_TIME');
			}
			if (!isset($results['grplist'])) {
				$results['grplist'] = '';
			}
			if (!isset($results['needsconf'])) {
				$results['needsconf'] = '';
			}
			if (($astdb_prering != $results['pre_ring']) && (trim((string) $astdb_prering) != '')) {
				$results['pre_ring'] = $astdb_prering;
				$changed=1;
			}
			if (($astdb_grptime != $results['grptime']) && (trim((string) $astdb_grptime) != '')) {
				$results['grptime'] = $astdb_grptime;
				$changed=1;
			}

			if ((trim((string) $astdb_grplist) != trim((string) $results['grplist'])) && (trim((string) $astdb_grplist) != '')) {
				$results['grplist'] = $astdb_grplist;
				$changed=1;
			}

			$confvalue = ($astdb_grpconf) ? 'CHECKED' : '';
			if ($confvalue != trim((string) $results['needsconf'])) {
				$results['needsconf'] = $confvalue;
				$changed=1;
			}

			$results['ddial'] = $astdb_ddial;

			if ($changed) {
				$sql = "UPDATE findmefollow SET grptime = ?, grplist = ?, pre_ring = ?, needsconf = ? WHERE grpnum = ? LIMIT 1";
				$sth = $db->prepare($sql);
				$sth->execute([$results['grptime'], $results['grplist'], $results['pre_ring'], $results['needsconf'], $results['grpnum']]);
			}
		} // if check_astdb
		$results['needsconf'] = ($results['needsconf'] == "CHECKED") ? true : false;
		return $results;
	}

	public function getActionBar($request) {
		$buttons = null;
  if (empty($request['extdisplay']) || empty($request['view']) || $request['view'] != 'form') {
			return null;
		}
		switch($request['display']) {
			case 'findmefollow':
				$buttons = ['submit' => ['name' => 'submit', 'id' => 'submit', 'value' => _('Submit')], 'reset' => ['name' => 'reset', 'id' => 'reset', 'value' => _('Reset')]];
				break;
		}
		return $buttons;
	}
	function add($grpnum,$data) {
		$grplist = null;
  $annmsg_id = null;
  $remotealert_id = null;
  $toolate_id = null;
  $changecid = null;
  $fixedcid = null;
  if(!is_array($data)) {
			throw new \Exception("The format for adding a fmfm has changed. Please fix the calling code to reflect this");
		}
		$defaults = ['strategy' => $this->FreePBX->Config->get('FOLLOWME_RG_STRATEGY'), 'grptime' => $this->FreePBX->Config->get('FOLLOWME_TIME'), 'grplist' => $grpnum, 'postdest' => 'ext-local,'.$grpnum.',dest', 'grppre' => '', 'annmsg_id' => '', 'dring' => '', 'needsconf' => '', 'remotealert_id' => '', 'toolate_id' => '', 'ringing' => 'Ring', 'pre_ring' => $this->FreePBX->Config->get('FOLLOWME_PRERING'), 'ddial' => $this->FreePBX->Config->get('FOLLOWME_DISABLED') ? 'CHECKED' : '', 'changecid' => 'default', 'fixedcid' => '', 'calendar_enable' => 0, 'calendar_id' => '', 'calendar_group_id' => '', 'calendar_match' => 'yes', 'rvolume'=>''];
		$final = [];
		foreach($defaults as $key => $val) {
			$final[$key] = $data[$key] ?? $val;
		}
		extract($final);

		$astman = $this->FreePBX->astman;
		$dbh = $this->db;
		$conf = $this->FreePBX->Config();

		//Follow Me auto # on external number.
		//http://code.freepbx.org/cru/FREEPBX-51#CFR-111
		$users = \findmefollow_allusers();
		$users = is_array($users) ? $users : [];
		foreach ($users as $user) {
			$extens[$user[0]] = $user[1];
		}

		$list = !is_array($grplist) ? explode("-", (string) $grplist) : $grplist;
		foreach (array_keys($list) as $key) {
			// remove invalid chars
			//FREEPBX-14788 -Issue in Find Me follow me list under Extensions.
			$hadPound = preg_match("/#$/",trim($list[$key]));// trim -> if there is a blank space  pref_match retruns 0 ;
			$list[$key] = preg_replace("/[^0-9*+]/", "", $list[$key]);

			if ($list[$key] == "") {
				unset($list[$key]);
				continue;
			}

			if($hadPound) {
				$list[$key].= '#';
				continue;
			}

			if (empty($extens[$list[$key]])) {
				/* Extension not found.  Must be an external number. */
				$list[$key].= '#';
			}
		}
		$grplist = implode("-", $list);

		if($annmsg_id == ''){
			$annmsg_id = NULL;
		}
		if ($remotealert_id == '') {
			$remotealert_id = NULL;
		}
		if ($toolate_id == '') {
			$toolate_id = NULL;
		}
		$sql = "INSERT INTO findmefollow (grpnum, strategy, grptime, grppre, grplist, annmsg_id, postdest, dring, needsconf, remotealert_id, toolate_id, ringing, pre_ring, calendar_enable, calendar_id, calendar_group_id, calendar_match, rvolume) VALUES (:grpnum, :strategy, :grptime, :grppre, :grplist, :annmsg_id, :postdest, :dring, :needsconf, :remotealert_id, :toolate_id, :ringing, :pre_ring, :calendar_enable, :calendar_id, :calendar_group_id, :calendar_match, :rvolume)";
		$insertarr = [':grpnum' => $grpnum, ':strategy' => $strategy, ':grptime' => $grptime, ':grppre' => $grppre, ':grplist' => $grplist, ':annmsg_id' => $annmsg_id, ':postdest' => $postdest, ':dring' => $dring, ':needsconf' => $needsconf, ':remotealert_id' => $remotealert_id, ':toolate_id' => $toolate_id, ':ringing' => $ringing, ':pre_ring' => (empty($pre_ring) ? '0' : $pre_ring), ':calendar_enable' => $calendar_enable, ':calendar_group_id' => $calendar_group_id, ':calendar_id' => $calendar_id, ':calendar_match' => $calendar_match, ':rvolume' => $rvolume];

		$stmt = $dbh->prepare($sql);
		$results = $stmt->execute($insertarr);
		if ($astman) {
			$astman->database_put("AMPUSER",$grpnum."/followme/strategy",$strategy ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/prering",$pre_ring ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/grptime",$grptime ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/grplist",$grplist ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/grppre",$grppre ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/rvolume",$rvolume ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/dring",$dring ?? '');
			$astman->database_put("AMPUSER",$grpnum."/followme/annmsg",(!empty($annmsg_id) ? recordings_get_file($annmsg_id) : ''));
			$astman->database_put("AMPUSER",$grpnum."/followme/remotealertmsg",(!empty($remotealert_id) ? recordings_get_file($remotealert_id) : ''));
			$astman->database_put("AMPUSER",$grpnum."/followme/toolatemsg",(!empty($toolate_id) ? recordings_get_file($toolate_id) : ''));
			$astman->database_put("AMPUSER",$grpnum."/followme/postdest",$postdest);
			$astman->database_put("AMPUSER",$grpnum."/followme/ringing",$ringing);

			$needsconf ??= '';
			$confvalue = ($needsconf == 'CHECKED')?'ENABLED':'DISABLED';
			$astman->database_put("AMPUSER",$grpnum."/followme/grpconf",$confvalue);

			$ddial ??= '';
			$ddialvalue = ($ddial == 'CHECKED')?'EXTENSION':'DIRECT';
			$astman->database_put("AMPUSER",$grpnum."/followme/ddial",$ddialvalue);
			if ($conf->get('USEDEVSTATE')) {
				$ddialstate = ($ddial == 'CHECKED')?'NOT_INUSE':'BUSY';

				$devices = $astman->database_get("AMPUSER", $grpnum . "/device");
				$device_arr = explode('&', (string) $devices);
				foreach ($device_arr as $device) {
					$astman->set_global($conf->get('AST_FUNC_DEVICE_STATE') . "(Custom:FOLLOWME$device)", $ddialstate);
				}
			}

			$astman->database_put("AMPUSER",$grpnum."/followme/changecid",$changecid);
			$fixedcid = preg_replace("/[^0-9\+]/" ,"", trim((string) $fixedcid));
			$astman->database_put("AMPUSER",$grpnum."/followme/fixedcid",$fixedcid);
		} else {
			\fatal("Cannot connect to Asterisk Manager with ".$conf->get("AMPMGRUSER")."/".$conf->get("AMPMGRPASS"));
		}
		
		$this->triggerSettingsChangeEvent($grpnum);
	}

	public function del($grpnum, $triggerEvent = false) {
		$astman = $this->FreePBX->astman;
		$dbh = $this->db;
		$conf = $this->FreePBX->Config();

		$sql= "DELETE FROM findmefollow WHERE grpnum = :grpnum";
		$stmt = $dbh->prepare($sql);
		$results = $stmt->execute([':grpnum' => $grpnum]);
		if ($astman) {
			$astman->database_deltree("AMPUSER/".$grpnum."/followme");
		} else {
			\fatal("Cannot connect to Asterisk Manager with ".$conf->get("AMPMGRUSER")."/".$conf->get("AMPMGRPASS"));
		}

		if ($triggerEvent) {
			$this->triggerSettingsChangeEvent($grpnum);
		}
	}

	// Only check astdb if check_astdb is not 0. For some reason, this fails if the asterisk manager code
	// is included (executed) by all calls to this function. This results in silently not generating the
	// extensions_additional.conf file. page.findmefollow.php does set it to 1 which means that when running
	// the GUI, any changes not reflected in SQL will be detected and written back to SQL so that they are
	// in sync. Ideally, anything that changes the astdb should change SQL. (in some ways, these should both
	// not be here but ...
	//
	// Need to go back and confirm at some point that the $check_astdb error is still there and deal with it.
	// as variables like $ddial get introduced to only be in astdb, the result array will not include them
	// if not able to get to astdb. (I suspect in 2.2 and beyond this may all be fixed).
	//
	public function get($grpnum, $check_astdb=0) {
		$astdb_prering = null;
  $astdb_grptime = null;
  $astdb_grplist = null;
  $astdb_grpconf = null;
  $astman = $this->FreePBX->astman;
		$dbh = $this->db;
		$conf = $this->FreePBX->Config();
		$user = $this->FreePBX->Core->getUser($grpnum);
		$sql = 'SELECT grpnum, strategy, grptime, grppre, grplist, annmsg_id, postdest, dring, needsconf, remotealert_id, toolate_id, ringing, pre_ring, voicemail, calendar_enable, calendar_id, calendar_group_id, calendar_match, rvolume FROM findmefollow INNER JOIN `users` ON `extension` = `grpnum` WHERE grpnum = :grpnum LIMIT 1';
		$stmt = $dbh->prepare($sql);
		$stmt->execute([':grpnum'=> $grpnum]);
		$results = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (empty($results)) {
			return [];
		}
		if (!isset($results['voicemail'])) {

			$results['voicemail'] = $user['voicemail'] ?? 'novm';
		}
		if (!isset($results['strategy']) || empty($results['strategy'])) {
			$results['strategy'] = $conf->get('FOLLOWME_RG_STRATEGY');
		}

		if ($check_astdb) {
			if ($astman) {
				$astdb_prering = $this->getPreRingTime($grpnum);
				$astdb_grptime = $this->getListRingTime($grpnum);
				$astdb_grplist = $this->getList($grpnum);
				$astdb_grpconf = $this->getConfirm($grpnum);

				$astdb_changecid = strtolower((string) $astman->database_get("AMPUSER",$grpnum."/followme/changecid"));
				switch($astdb_changecid) {
					case 'default':
					case 'did':
					case 'forcedid':
					case 'fixed':
					case 'extern':
						break;
					default:
						$astdb_changecid = 'default';
				}
				$results['changecid'] = $astdb_changecid;
				$fixedcid = $astman->database_get("AMPUSER",$grpnum."/followme/fixedcid");
				$results['fixedcid'] = preg_replace("/[^0-9\+]/" ,"", trim((string) $fixedcid));
			} else {
				fatal("Cannot connect to Asterisk Manager with ".$conf->get('AMPMGRUSER')."/".$conf->get('AMPMGRPASS'));
			}
			$astdb_ddial   = $astman->database_get("AMPUSER",$grpnum."/followme/ddial");
			// If the values are different then use what is in astdb as it may have been changed.
			// If sql returned no results for pre_ring/grptime then it's not configued so we reset
			// the astdb defaults as well
			//
			$changed=0;
			if (!isset($results['pre_ring'])) {
				$results['pre_ring'] = $astdb_prering = $conf->get('FOLLOWME_PRERING');
			}
			if (!isset($results['grptime'])) {
				$results['grptime'] = $astdb_grptime = $conf->get('FOLLOWME_TIME');
			}
			if (!isset($results['grplist'])) {
				$results['grplist'] = '';
			}
			if (!isset($results['needsconf'])) {
				$results['needsconf'] = '';
			}
			if (!isset($results['rvolume'])) {
				$results['rvolume'] = '';
			}

			if (($astdb_prering != $results['pre_ring']) && (trim((string) $astdb_prering) != '')) {
				$results['pre_ring'] = $astdb_prering;
				$changed=1;
			}
			if (($astdb_grptime != $results['grptime']) && (trim((string) $astdb_grptime) != '')) {
				$results['grptime'] = $astdb_grptime;
				$changed=1;
			}
			if ((trim((string) $astdb_grplist) != trim((string) $results['grplist'])) && (trim((string) $astdb_grplist) != '')) {
				$results['grplist'] = $astdb_grplist;
				$changed=1;
			}

			$confvalue = ($astdb_grpconf) ? 'CHECKED' : '';
			if ($confvalue != trim((string) $results['needsconf'])) {
				$results['needsconf'] = $confvalue;
				$changed=1;
			}

			// Not in sql so no sanity check needed
			//
			if (trim((string) $astdb_ddial) == 'EXTENSION') {
				$ddial = 'CHECKED';
			} elseif (trim((string) $astdb_ddial) == 'DIRECT') {
				$ddial = '';
			} else {
				// If here then followme must not be set so use default
				$ddial = $conf->get('FOLLOWME_DISABLED') ? 'CHECKED' : '';
			}
			$results['ddial'] = $ddial;

			if ($changed) {
				$sql = 'UPDATE findmefollow SET grptime = :grptime, grplist = :grplist, pre_ring = :pre_ring, needsconf = :needsconf WHERE grpnum = :grpnum LIMIT 1';
				$params = [':grptime' => $results['grptime'], ':grplist' => trim((string) $results['grplist']), ':pre_ring' => $results['pre_ring'], ':needsconf' => $results['needsconf'], ':grpnum' => $grpnum];
				$stmt = $this->db->prepare($sql);
				$sql_results = $stmt->execute($params);
			}
		} // if check_astdb

		return $results;
	}

	public function update($grpnum,$settings) {
		$old = $this->get($grpnum);
		if(!empty($old)) {
			$this->del($grpnum);
			$old['grplist'] = explode("-",(string) $old['grplist']);
			$settings = array_merge($old,$settings);
		}
		$this->add($grpnum,$settings);
	}

	public function bulkhandlerGetHeaders($type) {
		switch ($type) {
		case 'extensions':
			$headers = ['findmefollow_enabled' => ['description' => _('Follow Me Enabled [Blank to disable]')], 'findmefollow_grplist' => ['description' => _('Follow Me List')], 'findmefollow_postdest' => ['description' => _('Follow Me No Answer Destination'), "display" => false, "type" => "destination"]];

			return $headers;
			break;
		}
	}

	public function bulkhandlerImport($type, $rawData) {
		$ret = NULL;

		switch ($type) {
		case 'extensions':
			foreach ($rawData as $data) {
				$extension = $data['extension'];

				foreach ($data as $key => $value) {
					if (str_starts_with((string) $key, 'findmefollow_')) {
						$settingname = substr((string) $key, 13);
						switch ($settingname) {
							case 'grplist':
								$settings[$settingname] = explode('-', (string) $value);
							break;
							case 'enabled':
								//reversy and backwards yeah. I know.
								//ITS THE CODE FROM 7 YEARS AGO
								//:'(
								$value = trim((string) $value);
								$settings['ddial'] = (!empty($value)) ? false : true;
							break;
							default:
								$settings[$settingname] = $value;
							break;
						}
					}
				}

				if (!empty($settings) && count($settings) > 0) {
					$this->addSettingRow($extension, $settings);
				}
			}

			$ret = ['status' => true];

			break;
		}

		return $ret;
	}

	function addSettingRow($grpnum,$settings) {
		$valid = ['strategy', 'grptime', 'grppre', 'grplist', 'annmsg_id', 'postdest', 'dring', 'needsconf', 'remotealert_id', 'toolate_id', 'ringing', 'pre_ring', 'ddial', 'changecid', 'fixedcid'];

		$settings = array_intersect_key($settings, array_flip($valid));

		if (count($settings) == 0) {
			return false;
		}

		$ret = true;

		$sql = "SELECT COUNT(*) = 0 as is_new FROM findmefollow WHERE grpnum = :grpnum";
		$sth = $this->db->prepare($sql);
		$sth->bindParam(":grpnum", $grpnum);
		$sth->execute();
		$is_new = $sth->fetch();
		$is_new = (bool) $is_new['is_new'];

		if ($is_new)
			$sql = "REPLACE INTO findmefollow (grpnum, strategy, grptime, grppre, grplist, annmsg_id, postdest,
						dring, needsconf, remotealert_id, toolate_id, ringing, pre_ring)
					VALUES (:grpnum, :strategy, :grptime, :grppre, :grplist, :annmsg_id, :postdest,
						:dring, :needsconf, :remotealert_id, :toolate_id, :ringing, :pre_ring);";
		else
			$sql = "UPDATE findmefollow SET strategy = :strategy, grptime = :grptime, grppre = :grppre, grplist = :grplist, annmsg_id = :annmsg_id,
						postdest = :postdest, dring = :dring, needsconf = :needsconf, remotealert_id = :remotealert_id,
						toolate_id = :toolate_id, ringing = :ringing, pre_ring = :pre_ring
					WHERE grpnum = :grpnum";

		$sth = $this->db->prepare($sql);
		$set_keys = [];
		$grptime_not_null = false;
		foreach ($settings as $setting => $value) {
			switch($setting) {
				case 'strategy':
					$set_keys[$setting] = ($value) ? $value : $this->FreePBX->Config->get('FOLLOWME_RG_STRATEGY');
				break;
				case 'grptime':
					$this->setListRingTime($grpnum,$value);
					$set_keys[$setting] = $value;
					if (trim((string) $value) != '')
						$grptime_not_null = true;
				break;
				case 'grppre':
					$set_keys[$setting] = $value;
				break;
				case 'grplist':
					$grplist = implode("-", $value);
					$this->setList($grpnum,$value);
					$set_keys[$setting] = $grplist;
				break;
				break;
				case 'annmsg_id':
					$v = (int) $value;
					$set_keys[$setting] = ($v == 0)? NULL: $v ;
				break;
				case 'postdest':
					$set_keys[$setting] = $value;
				break;
				case 'dring':
					$set_keys[$setting] = $value;
				break;
				case 'needsconf':
					$val = ($value) ? 'CHECKED' : '';
					$val = ($value) ? 'ENABLED' : 'DISABLED';
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/grpconf",$val);
					$set_keys[$setting] = $value;
				break;
				case 'remotealert_id':
					$v = (int) $value;
					$set_keys[$setting] = ($v == 0)? NULL: $v ;
				break;
				case 'toolate_id':
					$v = (int) $value;
					$set_keys[$setting] = ($v == 0)? NULL: $v ;
				break;
				case 'ringing':
					$set_keys[$setting] = $value;
				break;
				case 'pre_ring':
					$this->setPreRingTime($grpnum,$value);
					$set_keys[$setting] = $value;
				break;
				case 'ddial':
					//(DIRECT is enabled, EXTENSION is disabled)
					$ddialstate = ($value) ? 'NOT_INUSE' : 'BUSY';
					$val = ($value) ? 'EXTENSION' : 'DIRECT';
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/ddial",$val);
					if ($this->FreePBX->Config->get_conf_setting('USEDEVSTATE')) {
						$devices = $this->FreePBX->astman->database_get("AMPUSER", $grpnum . "/device");
						$device_arr = explode('&', (string) $devices);
						foreach ($device_arr as $device) {
							$this->FreePBX->astman->set_global($this->FreePBX->Config->get_conf_setting('AST_FUNC_DEVICE_STATE') . "(Custom:FOLLOWME$device)", $ddialstate);
						}
					}
					if ((!$value) && empty($settings['grplist'])) {
						$sql = "INSERT INTO findmefollow (grpnum,grptime,grplist) VALUES (:grpnum,20,:grpnum)";
						$sth2 = $this->db->prepare($sql);
						//wrapped into a try/catch incase the find me is already defined, then we won't do the additional steps.
						try {
							$sth2->execute([':grpnum' => $grpnum]);
							//these are the additional steps
							$this->setListRingTime($grpnum,20);
							$this->setList($grpnum,[$grpnum]);
						} catch(\Exception) {}
					}
				break;
				case 'changecid':
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/changecid",$value);
				break;
				case 'fixedcid':
					$value = preg_replace("/[^0-9\+]/" ,"", trim((string) $value));
					$this->FreePBX->astman->database_put("AMPUSER",$grpnum."/followme/fixedcid",$value);
				break;
				default:
					$ret = false;
				break;
			}
		}

		$except = ['ddial', 'changecid', 'fixedcid'];
		foreach($valid as $key)
		{
			if (!in_array($key, $except) && !isset($set_keys[$key]))
			{
				$set_keys[$key] = null;
			}
		}
		$set_keys['grpnum'] = $grpnum;

		$parameters = [];
		foreach($set_keys as $key => $value)
			$parameters[':'.$key] = $value;

		if ($grptime_not_null)
			$sth->execute($parameters);

		return $ret;
	}

	public function bulkhandlerExport($type, $astdb = true) {
		$data = [];

		switch ($type) {
		case 'extensions':
			$extensions = $this->listAll();

			foreach ($extensions as $extension) {
				$settings = $this->getSettingsById($extension, $astdb);
				$psettings = [];
				foreach ($settings as $key => $value) {
					switch ($key) {
					case 'grpnum':
						break;
					case 'ddial':
						//reversy and backwards yeah. I know.
						//ITS THE CODE FROM 7 YEARS AGO
						//:'(
						$psettings['findmefollow_' . 'enabled'] = (!empty($value)) ? '' : 'yes';
						break;
					default:
						$psettings['findmefollow_' . $key] = $value;
						break;
					}
				}
				$data[$extension] = $psettings;
			}

			break;
		}

		return $data;
	}
	public function ajaxRequest($req, &$setting) {
		return match ($req) {
      'toggleFM', 'getJSON' => true,
      default => false,
  };
	}
	public function ajaxHandler(){
		switch ($_REQUEST['command']) {
			case 'toggleFM':
				$extdisplay = $_REQUEST['extdisplay'] ?? '';
				$state = '';
				if($_REQUEST['state'] == 'enable'){
					$state = true;
				}
				if($_REQUEST['state'] == 'disable'){
					$state = false;
				}
				if($state === '' || empty($extdisplay)){
					return ['toggle' => 'invalid'];
				}
				$ret = $this->setDDial($extdisplay,$state);
				return ['toggle' => 'received', 'return' => $ret];
			break;
			case 'getJSON':
				switch ($_REQUEST['jdata']) {
					case 'grid':
						$ret = [];
						foreach($this->listFollowme() as $fm){
							$ret[] = ['ext'=>$fm[0]];
						}
						return array_values($ret);
					break;

					default:
						return false;
					break;
				}
			break;

			default:
				return false;
			break;
		}
	}
	public function listFollowme($get_all=false){
		$sql = "SELECT grpnum FROM findmefollow ORDER BY CAST(grpnum as UNSIGNED)";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$results = $stmt->fetchall();
		if (isset($results)) {
			foreach($results as $result) {
				if ($get_all || checkRange($result)){
					$grps[] = $result;
				}
			}
		}
		if (isset($grps)) {
			return $grps;
		}
		else {
			return [];
		}
	}

	public function getAllFollowmes() {
		$sql = "SELECT grpnum, strategy, rvolume, grptime, grppre, grplist, annmsg_id, postdest, dring, needsconf, remotealert_id, toolate_id, ringing, pre_ring, voicemail, calendar_enable, calendar_group_id, calendar_id, calendar_match FROM findmefollow INNER JOIN `users` ON `extension` = `grpnum`";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$results = $sth->fetchall(\PDO::FETCH_ASSOC);
		return $results;
	}


	public function getRightNav($request) {
		if(isset($request['view'])&& $request['view'] == 'form'){
			return load_view(__DIR__."/views/bootnav.php",['request' => $request]);
		}
	}
	//Core extension Hooks
	public function addUser($extension,$post,$editmode){
		$conf = $this->FreePBX->Config();
		$ext = $post['extdisplay'] ?? null;
		$extn = $post['extension'] ?? null;

		if ($ext=='') {
			$extdisplay = $extn;
		} else {
			$extdisplay = $ext;
		}
		$settings = [];
		foreach($post as $key => $value) {
			if(preg_match("/^fmfm_(.*)/",(string) $key,$matches)) {
				$settings[$matches[1]] = $value;
			}
		}
		if(!empty($settings)) {
			$settings['ddial'] = ($settings['ddial'] == "enabled") ? "" : "CHECKED";
			if(isset($settings['needsconf'])) {
				$settings['needsconf'] = ($settings['needsconf'] == "enabled") ? "CHECKED" : "";
			}

			if(isset($post[$post[$settings['goto']]."fmfm"])) {
				$settings['postdest'] = $post[$post[$settings['goto']]."fmfm"];
			} else {
				$settings['postdest'] = "ext-local,$extdisplay,dest";
			}
			unset($settings['quickpick']);

			if (!isset($settings['fixedcid'])) {
				$settings['fixedcid'] = '';
			}

			if (!isset($settings['rvolume'])) {
				$settings['rvolume'] = '';
			}


			//check destination. make sure it is valid
			$settings['postdest'] = ($settings['postdest'] == 'ext-local,,dest') ? 'ext-local,'.$extdisplay.',dest' : $settings['postdest'];
			//dont let group list be empty. ever.
			$settings['grplist'] = empty($settings['grplist']) ? $extdisplay : $settings['grplist'];
			$settings['grplist'] = explode("\n",(string) $settings['grplist']);
			if($editmode){
				$this->update($extdisplay, $settings);
			}else{
				$this->add($extdisplay, $settings);
			}
		} elseif($conf->get('FOLLOWME_AUTO_CREATE')) {
			$this->add($extdisplay);
		}

	}
	//UCP hooks
	public function ucpConfigPage($mode, $user, $action) {
		 if(isset($_REQUEST['action'])) {
		     switch($_REQUEST['action']) {
				case 'showgroup':
					$mode = "group";
					$fmr = $this->FreePBX->Userman->getModuleSettingByGID($_REQUEST['group'],'ucp|Findmefollow','fmr');
					$enabled = $this->FreePBX->Userman->getModuleSettingByGID($_REQUEST['group'],'ucp|Findmefollow','enable');
					$fmassigned = $this->FreePBX->Userman->getModuleSettingByGID($_REQUEST['group'],'ucp|Findmefollow','assigned');
				break;
				case 'showuser':
					$mode = "user";
					$fmr = $this->FreePBX->Userman->getModuleSettingByID($_REQUEST['user'],'ucp|Findmefollow','fmr');
					$enabled = $this->FreePBX->Userman->getModuleSettingByID($_REQUEST['user'],'ucp|Findmefollow','enable');
					$fmassigned = $this->FreePBX->Userman->getModuleSettingByID($_REQUEST['user'],'ucp|Findmefollow','assigned');
				break;
				case 'addgroup':
					$mode = "group";
					$fmr = "disable";
					$enabled = true;
				break;
				case 'adduser':
				     $mode = "user";
				     $fmr = "disable";
					 $enabled = true;
                break;
			 }
		 }

		 $fmassigned = !empty($fmassigned) ? $fmassigned : [];
		 $ausers = [];
		 $action = $_REQUEST['action'];
		 if($action == "showgroup" || $action == "addgroup") {
             $ausers['self'] = _("User Primary Extension");
		}

		 if($action == "addgroup") {
		     $fmassigned = ['self'];
	     }

		 foreach(core_users_list() as $list) {
		     $cul[$list[0]] = ["name" => $list[1], "vmcontext" => $list[2]];
		     $ausers[$list[0]] = $list[1] . " &#60;".$list[0]."&#62;";
	     }

		$html = [];
		$html[0] = ["title" => _("FindmeFollow"), "rawname" => "findmefollow", "content" => load_view(__DIR__."/views/ucp_config.php",["enabled" => $enabled, "fmr" => $fmr, "mode" => $mode, "ausers" => $ausers, "fmassigned" => $fmassigned])];
       return $html;
	}

	public function ucpAddGroup($id, $display, $data) {
	    $this->ucpUpdateGroup($id,$display,$data);
	}

	public function ucpUpdateGroup($id,$display,$data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'group') {
	            if(isset($_POST['findmefollow_enable']) && $_POST['findmefollow_enable'] == 'yes') {
		                $this->FreePBX->Ucp->setSettingByGID($id,'findmefollow','enable',$_POST['findmefollow_enable']);
	            } else {
		                $this->FreePBX->Ucp->setSettingByGID($id,'findmefollow','enable',($_POST['findmefollow_enable'] ?? ''));
				}
				if(isset($_POST['fmr']) && $_POST['fmr'] == 'enable') {
                        $this->FreePBX->Ucp->setSettingByGID($id,'findmefollow','fmr','enable');
                } else {
                     $this->FreePBX->Ucp->setSettingByGID($id,'findmefollow','fmr','disable');
				}
				if(!empty($_POST['followme_ext'])) {
	                $this->FreePBX->Ucp->setSettingByGID($id,'Findmefollow','assigned',$_POST['followme_ext']);
	            } else {
	                $this->FreePBX->Ucp->setSettingByGID($id,'Findmefollow','assigned',['self']);
	            }

       }
	}

	public function ucpDelGroup($id,$display,$data) {
	}

	public function ucpDelUser($id, $display, $ucpStatus, $data) {

	}

	public function ucpAddUser($id, $display, $ucpStatus, $data) {
	     $this->ucpUpdateUser($id, $display, $ucpStatus);
	}

	public function ucpUpdateUser($id, $display, $data) {
		if ($display != 'userman') {
			return;
		}
		if(isset($_POST['findmefollow_enable']) && $_POST['findmefollow_enable'] == 'yes') {
	          $this->FreePBX->Ucp->setSettingByID($id,'findmefollow','enable',$_POST['findmefollow_enable']);
	    } elseif(isset($_POST['findmefollow_enable']) && $_POST['findmefollow_enable'] == 'no') {
	          $this->FreePBX->Ucp->setSettingByID($id,'findmefollow','enable',$_POST['findmefollow_enable']);
		}else{
			 $this->FreePBX->Ucp->setSettingByID($id,'findmefollow','enable',null);
		}

		if(isset($_POST['fmr']) && $_POST['fmr'] == 'enable') {
	        $this->FreePBX->Ucp->setSettingByID($id,'findmefollow','fmr','enable');
        } elseif(isset($_POST['fmr']) && $_POST['fmr'] == 'disable') {
            $this->FreePBX->Ucp->setSettingByID($id,'findmefollow','fmr','disable');
		}else{
			 $this->FreePBX->Ucp->setSettingByID($id,'findmefollow','fmr',null);
		}

		if(!empty($_POST['followme_ext'])) {
              $this->FreePBX->Ucp->setSettingByID($id,'Findmefollow','assigned',$_POST['followme_ext']);
        } else {
               $this->FreePBX->Ucp->setSettingByID($id,'Findmefollow','assigned',null);
        }
	}

	/**
	 * find me follow me settings change event
	 */
	private function triggerSettingsChangeEvent($extension) {
		$user = $this->FreePBX->Userman->getUserByDefaultExtension($extension);
		if(isset($user['id']) ){
			$res =  $this->FreePBX->astman->send_request("UserEvent", array(
				"userEvent" => "find-me-follow-me-settings-change",
				"userId" => $user['id']
			));
		}
	}
}
