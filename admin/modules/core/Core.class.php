<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\modules;
use FreePBX_Helpers;
use BMO;
use PDO;
use Exception;
//progress bar
use Symfony\Component\Console\Helper\ProgressBar;
use \FreePBX\modules\Core\Components\Dahdichannels as Dahdichannels;
class Core extends FreePBX_Helpers implements BMO  {

	private $drivers = array();
	private $deviceCache = array();
	private $getUserCache = array();
	private $getDeviceCache = array();
	private $listUsersCache = array();
	private $fastAGIState = false;

	public function __construct($freepbx = null) {
		parent::__construct($freepbx);
		//other options
		$this->database = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->freepbx = $freepbx;
		$this->astman = $freepbx->astman;
		//load drivers
		$this->loadDrivers();
	}

	public function __get($var) {
		switch($var) {
			case "routing":
				$this->routing = new \FreePBX\modules\Core\Components\Outboundrouting();
				return $this->routing;
			break;
			case "dahdichannels":
				$this->dahdichannels = new \FreePBX\modules\Core\Components\Dahdichannels();
				return $this->dahdichannels;
			break;
		}
	}

	public function getBackupSettingsDisplay($id) {
		$settings = !empty($id) ? $this->freepbx->Backup->getAll($id) : [];
		$settings["core_disabletrunks"] = $this->getConfig("core_disabletrunks", $id);
		$settings["core_disabletrunks"] = (preg_match('/(yes|no)/', $settings["core_disabletrunks"]))? $settings["core_disabletrunks"] : 'no';
		if(!empty($settings["backup_items"])){
			$backup_items = json_decode($settings["backup_items"], true);
			foreach($backup_items as $idx => $items){
				if($items["modulename"] == "core"){
					$backup_items[$idx]["settings"] = [array("name" => "core_disabletrunks", "value" => $settings["core_disabletrunks"])];
				}
			}
			$settings["backup_items"] = json_encode($backup_items);			
		}
		return load_view(__DIR__.'/views/backupSettings.php',$settings);
	}

	public function processBackupSettings($id, $settings) {		
		if(!empty($settings["core_disabletrunks"]) && (preg_match('/(yes|no)/', $settings["core_disabletrunks"]))){
			$this->setConfig("core_disabletrunks",$settings["core_disabletrunks"], $id);
		}
		$this->freepbx->Backup->setMultiConfig($settings,$id);
	}

	/**
	 * Get Right Nav
	 * @param  array $request The request
	 * @return string          The Right nav html
	 */
	public function getRightNav($request) {
		$display_mode = "advanced";
		$mode = $this->freepbx->Config()->get("FPBXOPMODE");
		if(!empty($mode)) {
			$display_mode = $mode;
		}
		switch($request['display']){
			case 'extensions':
			case 'devices':
				$popover = isset($request['fw_popover']) ? "&amp;fw_popover=".$request['fw_popover'] : '';
				$show = isset($request['tech_hardware']) || (isset($request['view']) && $request['view'] == "add") || (isset($request['extdisplay']) && freepbx_trim ($request['extdisplay']) != "");
				if($display_mode == "basic" && (!isset($request['extdisplay']) || freepbx_trim ($request['extdisplay']) == "")) {
					return array();
				}
				return load_view(__DIR__."/views/rnav.php",array("show" => $show, "display" => $request['display'], "popover"=>$popover));
			break;
			case 'trunks':
				if(isset($request['tech'])||(isset($request['extdisplay']) && !empty($request['extdisplay']))){
					$html = load_view(__DIR__.'/views/trunks/bootnav.php', array('trunk_types' => $this->listTrunkTypes()));
					return $html;
				}
			break;
			case 'did':
				if(isset($request['view'])){
					$html = load_view(__DIR__.'/views/did/rnav.php');
					return $html;
				}
			break;
			case 'routing':
				if(isset($request['view'])){
					$html = load_view(__DIR__.'/views/routing/bootnav.php');
					return $html;
				}
			break;
			case 'dahdichandids':
				if(isset($request['view'])){
					$html = load_view(__DIR__.'/views/dahdichandids/bootnav.php');
					return $html;
				}
			break;
			case 'ampusers':
				$html = load_view(__DIR__.'/views/ampusers/bootnav.php');
				return $html;
			break;
		}
	}

	/**
	 * Get all drivers from the private handler
	 * this is so they cant be modified by some outside source
	 */
	public function getAllDrivers() {
		return $this->drivers;
	}

	/**
	 * Get a single driver object
	 * @param  string $driver The driver name
	 * @return object         The driver Object
	 */
	public function getDriver($driver) {
		return isset($this->drivers[$driver]) ? $this->drivers[$driver] : false;
	}

	/**
	 * Load all "core" drivers
	 */
	public function loadDrivers() {
		if(!class_exists("FreePBX\Modules\Core\Driver",false)) {
			include(__DIR__."/functions.inc/Driver.class.php");
		}
		$driverNamespace = "\\FreePBX\\Modules\\Core\\Drivers";
		$driverList = glob(__DIR__."/functions.inc/drivers/*.class.php");
		$order = ['PJSip.class.php','Sip.class.php','Virtual.class.php','Dahdi.class.php','Iax2.class.php','Custom.class.php'];
		usort($driverList, function($a, $b) use ($order) {
			$indexA = array_search(basename($a), $order);
			$indexB = array_search(basename($b), $order);
			return $indexA <=> $indexB;
		});
		foreach($driverList as $driver) {
			if(preg_match("/\/([a-z1-9]*)\.class\.php$/i",$driver,$matches)) {
				$name = $matches[1];
				$class = $driverNamespace . "\\" . $name;
				if(!class_exists($class,false)) {
					include($driver);
				}
				if(class_exists($class,false)) {
					$this->drivers[strtolower($name)] = new $class($this->freepbx);
				} else {
					throw new \Exception("Invalid Class inside the drivers folder");
				}
			}
		}
	}

	/**
	 * Get all information about all drivers
	 */
	public function getAllDriversInfo() {
		$final = array();

		foreach($this->drivers as $driver) {
			$info = $driver->getInfo();
			if($info === false) {
				continue;
			}
			$rn = $info['rawName'];
			$final[$rn] = $info;
		}
		return $final;
	}

	/**
	 * Quick Extension Create Display
	 */
	public function getQuickCreateDisplay() {
		$sql = "SELECT extension FROM users ORDER BY extension DESC LIMIT 1";
		$lastExension = $this->freepbx->Database->query($sql)->fetchColumn();
		$startExt = (is_numeric($lastExension)) ? (int)$lastExension + 1 : 1;

		$pages = array();
		$pages[0][] = array(
			'html' => load_view(__DIR__.'/views/quickCreate.php',array('startExt' => $startExt)),
			'validate' => 'if($("#extension").val().trim() == "") {warnInvalid($("#extension"),"'._("Extension can not be blank!").'");return false}if(typeof extmap[$("#extension").val().trim()] !== "undefined") {warnInvalid($("#extension"),"'._("Extension already in use!").'");return false}if($("#name").val().trim() == "") {warnInvalid($("#name"),"'._("Display Name can not be blank!").'");return false}if($("#tech").val() == "dahdi" && $("#channel").val().trim() == "") {warnInvalid($("#channel"),"'._("Channel can not be blank!").'");return false}'
		);
		$modules = $this->freepbx->Hooks->processHooks();
		foreach($modules as $module) {
			foreach($module as $page => $datas) {
				foreach($datas as $html) {
					$pages[$page][] = $html;
				}
			}
		}
		return $pages;
	}

	/**
	 * Process the Quick Extension Create Display
	 * @param string $tech      The tech type (provided by the driver)
	 * @param int $extension The extension number
	 * @param array $data      Data passed from $_POST on submit
	 */
	public function processQuickCreate($tech, $extension, $data) {
		$channel = false;
		if(!is_numeric($extension)) {
			return array("status" => false, "message" => _("Extension was not numeric!"));
		}
		if($tech == "dahdi") {
			$channel = $data['channel'];
		}
		$settings = $this->generateDefaultDeviceSettings($tech,$extension,$data['name'],$channel);

		if (isset($data['secret']) && !empty($data['secret'])) {
			$settings['secret']['value']  = $data['secret'];
		}

      	if($tech == "pjsip") {
			if (isset($data['max_contacts']) && !empty($data['max_contacts'])) {
				$settings['max_contacts']['value'] = ($data['max_contacts'] > 100 ? 100 : $data['max_contacts']);
			}else{
				$settings['max_contacts']['value'] = 1;
			}
        }

		$settings['emergency_cid']['value'] = isset($data['emergency_cid']) ? $data['emergency_cid'] : '';
		$settings['callerid']['value'] = isset($data['callerid']) ? $data['callerid'] : '' ;

        if($tech == "pjsip"){
            $settings['dtmfmode']['value'] = isset($data['dtmfmode']) ? $data['dtmfmode'] : "rfc4733";
            $settings['defaultuser']['value'] = isset($data['defaultuser']) ? $data['defaultuser'] : "";
            $settings['trustrpid']['value'] = isset($data['trustrpid']) ? $data['trustrpid'] : "yes";
            $settings['send_connected_line']['value'] = isset($data['send_connected_line']) ? $data['send_connected_line'] : "yes";
            $settings['user_eq_phone']['value'] = isset($data['user_eq_phone']) ? $data['user_eq_phone'] : "no";
            $settings['sendrpid']['value'] = isset($data['sendrpid']) ? $data['sendrpid'] : "pai";
            $settings['qualifyfreq']['value'] = isset($data['qualifyfreq']) ? $data['qualifyfreq'] : 60;
            $settings['transport']['value'] = isset($data['transport']) ? $data['transport'] : "";
            $settings['avpf']['value'] = isset($data['avpf']) ? $data['avpf'] : "no";
            $settings['icesupport']['value'] = isset($data['icesupport']) ? $data['icesupport'] : "no";
            $settings['rtcp_mux']['value'] = isset($data['rtcp_mux']) ? $data['rtcp_mux'] : "no";
            $settings['namedcallgroup']['value'] = isset($data['namedcallgroup']) ? $data['namedcallgroup'] : "";
            $settings['namedpickupgroup']['value'] = isset($data['namedpickupgroup']) ? $data['namedpickupgroup'] : "";
            $settings['disallow']['value'] = isset($data['disallow']) ? $data['disallow'] : "";
            $settings['allow']['value'] = isset($data['allow']) ? $data['allow'] : "";
            $settings['dial']['value'] = isset($data['dial']) ? $data['dial'] : "PJSIP/$extension";
            $settings['mailbox']['value'] = isset($data['mailbox']) ? $data['mailbox'] : $extension."@device";
            $settings['vmexten']['value'] = isset($data['vmexten']) ? $data['vmexten'] : "";
            $settings['accountcode']['value'] = isset($data['accountcode']) ? $data['accountcode'] : "";
            $settings['remove_existing']['value'] = isset($data['remove_existing']) ? $data['remove_existing'] : "no";
            $settings['media_use_received_transport']['value'] = isset($data['media_use_received_transport']) ? $data['media_use_received_transport'] : "no";
            $settings['rtp_symmetric']['value'] = isset($data['rtp_symmetric']) ? $data['rtp_symmetric'] : "yes";
            $settings['rewrite_contact']['value'] = isset($data['rewrite_contact']) ? $data['rewrite_contact'] : "yes";
            $settings['force_rport']['value'] = isset($data['force_rport']) ? $data['force_rport'] : "yes";
            $settings['mwi_subscription']['value'] = isset($data['mwi_subscription']) ? $data['mwi_subscription'] : "auto";
            $settings['aggregate_mwi']['value'] = isset($data['aggregate_mwi']) ? $data['aggregate_mwi'] : "no";
            $settings['max_audio_streams']['value'] = isset($data['max_audio_streams']) ? $data['max_audio_streams'] : "1";
            $settings['max_video_streams']['value'] = isset($data['max_video_streams']) ? $data['max_video_streams'] : "1";
            $settings['media_encryption']['value'] = isset($data['media_encryption']) ? $data['media_encryption'] : "no";
            $settings['timers']['value'] = isset($data['timers']) ? $data['timers'] : "yes";
            $settings['timers_min_se']['value'] = isset($data['timers_min_se']) ? $data['timers_min_se'] : "90";
            $settings['direct_media']['value'] = isset($data['direct_media']) ? $data['direct_media'] : "yes";
            $settings['media_encryption_optimistic']['value'] = isset($data['media_encryption_optimistic']) ? $data['media_encryption_optimistic'] : "no";
            $settings['refer_blind_progress']['value'] = isset($data['refer_blind_progress']) ? $data['refer_blind_progress'] : "yes";
            $settings['device_state_busy_at']['value'] = isset($data['device_state_busy_at']) ? $data['device_state_busy_at'] : "0";
            $settings['match']['value'] = isset($data['match']) ? $data['match'] : "";
            $settings['maximum_expiration']['value'] = isset($data['maximum_expiration']) ? $data['maximum_expiration'] : "7200";
            $settings['minimum_expiration']['value'] = isset($data['minimum_expiration']) ? $data['minimum_expiration'] : "60";
            $settings['rtp_timeout']['value'] = isset($data['rtp_timeout']) ? $data['rtp_timeout'] : "0";
            $settings['rtp_timeout_hold']['value'] = isset($data['rtp_timeout_hold']) ? $data['rtp_timeout_hold'] : "0";
            $settings['outbound_proxy']['value'] = isset($data['outbound_proxy']) ? $data['outbound_proxy'] : '';
            $settings['outbound_auth']['value'] = isset($data['outbound_auth']) ? $data['outbound_auth'] : "no";
            $settings['message_context']['value'] = isset($data['message_context']) ? $data['message_context'] : "";
        }

		if(!$this->addDevice($extension,$tech,$settings)) {
			return array("status" => false, "message" => _("Device was not added!"));
		}
		$settings = $this->generateDefaultUserSettings($extension,$data['name']);
		$settings['outboundcid'] = $data['outboundcid'];
		if(isset($data['password']) && !empty($data['password'])){
			$settings['password']  = $data['password'];
		}

        if($tech == "pjsip"){
            $settings['sipname'] = isset($data['sipname']) ? $data['sipname'] : "";
            $settings['cid_masquerade'] = isset($data['cid_masquerade']) ? $data['cid_masquerade'] : "";
            $settings['dialopts'] = isset($data['dialopts']) ? $data['dialopts'] : "";
            $settings['ringtimer'] = isset($data['ringtimer']) ? $data['ringtimer'] : 0;
            $settings['rvolume'] = isset($data['rvolume']) ? $data['rvolume'] : "";
            $settings['concurrency_limit'] = isset($data['concurrency_limit']) ? $data['concurrency_limit'] : "";
            $settings['callwaiting'] = isset($data['callwaiting']) ? $data['callwaiting'] : 'enabled';
            $settings['cwtone'] = isset($data['cwtone']) ? $data['cwtone'] : "disabled";
            $settings['call_screen'] = isset($data['call_screen']) ? $data['call_screen'] : "0";
            $settings['answermode'] = isset($data['answermode']) ? $data['answermode'] : "disabled";
            $settings['intercom'] = isset($data['intercom']) ? $data['intercom'] : "enabled";
            $settings['recording_in_external'] = isset($data['recording_in_external']) ? $data['recording_in_external'] : 'dontcare';
            $settings['recording_out_external'] = isset($data['recording_out_external']) ? $data['recording_out_external'] : 'dontcare';
            $settings['recording_in_internal'] = isset($data['recording_in_internal']) ? $data['recording_in_internal'] : 'dontcare';
            $settings['recording_out_internal'] = isset($data['recording_out_internal']) ? $data['recording_out_internal'] : 'dontcare';
            $settings['recording_ondemand'] = isset($data['recording_ondemand']) ? $data['recording_ondemand'] : 'disabled';
            $settings['recording_priority'] = isset($data['recording_priority']) ? $data['recording_priority'] : "10";
            $settings['noanswer_dest'] = isset($data['noanswer_dest']) ? $data['noanswer_dest'] : "";
            $settings['noanswer_cid'] = isset($data['noanswer_cid']) ? $data['noanswer_cid'] : "";
            $settings['busy_dest'] = isset($data['busy_dest']) ? $data['busy_dest'] : "";
            $settings['busy_cid'] = isset($data['busy_cid']) ? $data['busy_cid'] : "";
            $settings['chanunavail_dest'] = isset($data['chanunavail_dest']) ? $data['chanunavail_dest'] : "";
            $settings['chanunavail_cid'] = isset($data['chanunavail_cid']) ? $data['chanunavail_cid'] : "";
            $settings['outboundcid'] = isset($data['outboundcid']) ? $data['outboundcid'] : "";
            $settings['pinless'] = isset($data['pinless']) ? $data['pinless'] : 'disabled';
        }

		try {
			if(!$this->addUser($extension, $settings)) {
				//cleanup
				$this->delDevice($extension);
				return array("status" => false, "message" => _("There was an unknown error creating this extension"));
			}
		} catch(\Exception $e) {
			//cleanup
			$this->delDevice($extension);
			throw $e;
		}

		try {
			$modules = $this->freepbx->Hooks->processHooks($tech, $extension, $data);
		} catch(\Exception $e) {
			//cleanup
			$this->delDevice($extension);
			$this->delUser($extension);
			throw $e;
		}
		needreload();
		if ($this->freepbx->Modules->checkStatus("sysadmin") && (!\FreePBX::Modules()->moduleHasMethod('sysadmin', 'isCommercialDeployment'))) { 
			$isCommercialDep = \FreePBX::Sysadmin()->isCommercialDeployment();
		} else {
			$isCommercialDep = false;
		}
		return array("status" => true, "ext" => $extension, "name" => $data['name'], "isCommercialDep" => $isCommercialDep);
	}

	/* this is useful only for emergencydevice creation*/
	public function emergencyProcessQuickCreate($tech, $extension, $data) {
		if(!is_numeric($extension)) {
			return array("status" => false, "message" => _("Extension was not numeric!"));
		}
		$settings = $this->generateDefaultDeviceSettings($tech,$extension,$data['name']);
		$settings['emergency_cid']['value'] = $data['emergency_cid'];
		$settings['context']['value'] = $data['context'];
		$settings['callerid']['value'] = $data['emergency_cid'];
		$settings['calleridname']['value'] = $data['name'];
		if(strtolower($tech) == 'pjsip') {
			if (isset($data['max_contacts']) && !empty($data['max_contacts'])) {
				$settings['max_contacts']['value'] = ($data['max_contacts'] > 100 ? 100 : $data['max_contacts']);
			}else{
				$settings['max_contacts']['value'] = 1;
			}
		}
		if(!$this->emergencyAddDevice($extension,$tech,$settings)) {
			return array("status" => false, "message" => _("Device was not added!"));
		}
		// we dont want to do the userthings for emergency
		needreload();
		return array("status" => true, "ext" => $extension, "name" => $data['name']);
	}

	/**
	 * Propagator for genConfig() from BMO that is passed on
	 * to child drivers
	 */
	public function genConfig() {
		$conf = array();
		foreach($this->drivers as $driver) {
			$c = $driver->genConfig();
			if(!empty($c)) {
				$conf = array_merge($c, $conf);
			}
		}
		return $conf;
	}

	/**
	 * Propagator for writeConfig() from BMO that is passed on
	 * to child drivers
	 * @param array $config Config Array
	 */
	public function writeConfig($config) {
		foreach($this->drivers as $driver) {
			$config = $driver->writeConfig($config);
		}
		return $config;
	}

	public function getActionBar($request) {
		$buttons = array();
		switch($request['display']) {
			case 'ampusers':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if (empty($request['userdisplay'])) {
					unset($buttons['delete']);
				}
			break;
			case 'users':
			case 'devices':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if ((isset($request['view']) && $request['view'] == 'add') || !empty($request['tech_hardware'])) {
					unset($buttons['delete']);
				} elseif (!isset($request['extdisplay']) || freepbx_trim($request['extdisplay']) == '') {
					$buttons = array();
				}
			break;
			case 'extensions':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if(!empty($request['tech_hardware'])) {
					unset($buttons['delete']);
				} elseif(!isset($request['extdisplay']) || trim($request['extdisplay']) == '') {
					$buttons = array();
				}
			break;
			case 'advancedsettings':
				$buttons = array(
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
			break;
			case 'dahdichandids':
                		return Dahdichannels::getButtons($request);
			case 'did':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if (empty($request['extdisplay'])) {
					unset($buttons['delete']);
				}
				if(!isset($request['view'])||$request['view'] == ''){
					$buttons = array();
				}
			break;
			case 'routing':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'duplicate' => array(
						'name' => 'duplicate',
						'id' => 'duplicate',
						'value' => _('Duplicate')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if (empty($request['id'])) {
					unset($buttons['delete'], $buttons['duplicate']);
				}
				if (empty($request['view'])){
					$buttons = array();
				}
			break;
			case 'trunks':
				$tmpButtons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'duplicate' => array(
						'name' => 'duplicate',
						'id' => 'duplicate',
						'value' => _('Duplicate')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if (!empty($request['extdisplay'])) {
					$buttons = $tmpButtons;
				} else if (!empty($request['tech'])) {
					unset($tmpButtons['delete'], $tmpButtons['duplicate']);
					$buttons = $tmpButtons;
				}
			break;

		}
		return $buttons;
	}

	public function install() {
		$this->startdaemon();
	}
	private function startdaemon($output=null) {
		$this->writeln('Starting Call Transfer Monitoring Service');
		$this->setWriter($output);
		if(!$this->freepbx->Modules->checkStatus("pm2")) {
			$this->writeln('PM2 is not installed/enabled. Unable to start Call transfer monitoring');
			return;
		}
		$pm2 = $this->freepbx->Pm2;
		$data = $pm2->getStatus("core-calltransfer-monitor");
		if (empty($data) || $data['pm2_env']['status'] == 'online') {
			$this->stopdaemon();
			$this->writeln('Restarting Call Transfer Monitoring Service');
		}
		$pm2->start("core-calltransfer-monitor",__DIR__."/call-transfer-events.php");
		$pm2->reset("core-calltransfer-monitor");
		if(is_object($output)) {
			$progress = new ProgressBar($output, 0);
			$progress->setFormat('[%bar%] %elapsed%');
			$progress->start();
		}
		$i = 0;
		while($i < 100) {
			$data = $pm2->getStatus("core-calltransfer-monitor");
			if(!empty($data) && $data['pm2_env']['status'] == 'online') {
				if(is_object($output)) {
					$progress->finish();
				}
				break;
			}
			if(is_object($output)) {
				$progress->setProgress($i);
			}
			$i++;
			usleep(100000);
		}
		if(is_object($output)) {
			$output->writeln("");
		}
		if(!empty($data) && $data['pm2_env']['status'] == 'online') {
			if(is_object($output)) {
				$output->writeln(sprintf(_("Started call trasnfer monitoring Service. PID is %s"),$data['pid']));
			}
		} else {
			if(is_object($output)) {
				$output->writeln("<error>"._("Failed to start call trasnfer monitoring service")."</error>");
			}
			return false;
		}
	}

	private function stopdaemon($output=null){
		$this->writeln('Stopping Call Transfer Monitoring Service');
		// Kill the monitoring script
		$launcher = freepbx_trim (`pidof -x call-transfer-events.php`);
		if ($launcher) {
			$pids = explode(" ", $launcher);
			foreach ($pids as $pid) {
				posix_kill($pid, 9);
			}

			if(is_object($output)) {
				$output->writeln(_("Stopped call trasnfer monitoring service"));
			}
			$this->freepbx->Pm2->stop("core-calltransfer-monitor");
			$data = $this->freepbx->Pm2->getStatus("core-calltransfer-monitor");
			if (empty($data) || $data['pm2_env']['status'] != 'online') {
				if (is_object($output)){
					$output->writeln(_("call trasnfer monitoring service stopped"));
				}
				return true;
			}
				//fallback
			$adv = freepbx_trim (`pidof -x call-transfer-events.php.php`);
			if ($adv) {
				$pids = explode(" ", $adv);
				foreach ($pids as $p) {
					posix_kill($p, 9);
				}
				if (is_object($output)) {
					$output->writeln(_("Call trasnfer monitoring service stopped"));
				}
			} else {
				if(is_object($output)) {
					$output->writeln(_("Call trasnfer monitoring service was not running"));
				}
			}
		}
		$data = $this->freepbx->Pm2->getStatus("core-calltransfer-monitor");
		if(empty($data) || $data['pm2_env']['status'] != 'online') {
			if(is_object($output)) {
				$output->writeln("<error>"._("Call trasnfer monitoring service is not running")."</error>");
			}
		} else {
			// executes after the command finishes
			if(is_object($output)) {
				$output->writeln(_("Stopping Call trasnfer monitoring service "));
			}

			$this->freepbx->Pm2->stop("core-calltransfer-monitor");

			$data = $this->freepbx->Pm2->getStatus("core-calltransfer-monitor");
			if (empty($data) || $data['pm2_env']['status'] != 'online') {
				if(is_object($output)){
					$output->writeln(_("Stopped Call trasnfer monitoring service "));
				}
			} else {
				if(is_object($output)) {
					$output->writeln("<error>".sprintf(_("Call trasnfer monitoring service  Failed: %s")."</error>",$process->getErrorOutput()));
				}
			}
		}
		return true;
	}

	public function uninstall() {
		if (!$this->freepbx->Modules->checkStatus("pm2")) {
			return;
		}
		try {
			$this->freepbx->pm2->delete("core-calltransfer-monitor");
		} catch(\Exception $e) {}
	}

	public function doTests($db) {
		return true;
	}

	public function ajaxRequest($req, &$setting) {
		switch($req) {
			case "delastmodule":
			case "addastmodule":
			case "quickcreate":
			case "delete":
			case "getJSON":
			case "getExtensionGrid":
			case "getDeviceGrid":
			case "getUserGrid":
			case "updateRoutes":
			case "delroute":
			case "getnpanxxjson":
			case "populatenpanxx":
			case "updatetrunks":
			case "deleteChansipDetails":
				return true;
			break;
		}
		return false;
	}

	public function ajaxHandler() {
		$request = $this->getSanitizedRequest();
		switch($request['command']) {
			case "updatetrunks":
				$this->routing->updateTrunks($request['route_id'], $request['trunkpriority'], true);
				needreload();
				return ['status' => true];
			break;
			case "addastmodule":
				$section = isset($request['section'])?$request['section']:'';
				$module = isset($request['astmod'])?$request['astmod']:'';
				switch($section){
					case 'amodload':
						return $this->freepbx->ModulesConf->load($module);
					break;
					case 'amodnoload':
						return $this->freepbx->ModulesConf->noload($module);
					break;
					case 'amodpreload':
						return $this->freepbx->ModulesConf->preload($module);
					break;
				}
			break;
			case "delastmodule":
				$section = isset($request['section'])?$request['section']:'';
				$module = isset($request['astmod'])?$request['astmod']:'';
				switch($section){
					case 'amodload':
						return $this->freepbx->ModulesConf->removeload($module);
					break;
					case 'amodnoload':
						return $this->freepbx->ModulesConf->removenoload($module);
					break;
					case 'amodpreload':
						return $this->freepbx->ModulesConf->removepreload($module);
					break;
				}
			break;
			case "delroute":
				$this->routing->deleteById($_POST['id']);
				return ["status" => true];
			case "updateRoutes":
				$order = $request['data'];
				array_shift($order);
				return $this->setRouteOrder($order);
			break;
			case "getUserGrid":
				$users = $this->getAllUsers();
				if(empty($users)) {
					return array();
				}
				$ampuser = $this->astman->database_show("AMPUSER");
				// Get all CW settings
				$cwsetting = $this->astman->database_show("CW");
				// get all CF settings
				$cfsetting = $this->astman->database_show("CF");
				// get all CFB settings
				$cfbsetting = $this->astman->database_show("CFB");
				// get all CFU settings
				$cfusetting = $this->astman->database_show("CFU");
				// get all DND settings
				$dndsetting = $this->astman->database_show("DND");
				foreach($users as &$user) {
					$exten = $user['extension'];
					$user['settings'] = array(
						'cw' => (isset($cwsetting['/CW/'.$exten]) && $cwsetting['/CW/'.$exten] == "ENABLED"),
						'dnd' => (isset($dndsetting['/DND/'.$exten]) && $dndsetting['/DND/'.$exten] == "YES"),
						'cf' => (isset($cfsetting['/CF/'.$exten])),
						'cfb' => (isset($cfbsetting['/CFB/'.$exten])),
						'cfu' => (isset($cfusetting['/CFU/'.$exten])),
						'fmfm' => (isset($ampuser['/AMPUSER/'.$exten.'/followme/ddial']) && $ampuser['/AMPUSER/'.$exten.'/followme/ddial'] == "DIRECT")
					);
					$user['actions'] = '<a href="?display=users&amp;extdisplay='.$exten.'"><i class="fa fa-edit"></i></a><a class="clickable delete" data-id="'.$exten.'"><i class="fa fa-trash"></i></a>';
				}
				return array_values($users);
			break;
			case "getExtensionGrid":
				$ampuser = $this->astman->database_show("AMPUSER");
				// Get all CW settings
				$cwsetting = $this->astman->database_show("CW");
				// get all CF settings
				$cfsetting = $this->astman->database_show("CF");
				// get all CFB settings
				$cfbsetting = $this->astman->database_show("CFB");
				// get all CFU settings
				$cfusetting = $this->astman->database_show("CFU");
				// get all DND settings
				$dndsetting = $this->astman->database_show("DND");
				if($request['type'] == "all") {
					$devices = $this->getAllUsersByDeviceType();
					if(empty($devices)) {
						return array();
					}
					foreach($devices as &$device) {
						$exten = $device['extension'];
						$device['settings'] = array(
							'cw' => (isset($cwsetting['/CW/'.$exten]) && $cwsetting['/CW/'.$exten] == "ENABLED"),
							'dnd' => (isset($dndsetting['/DND/'.$exten]) && $dndsetting['/DND/'.$exten] == "YES"),
							'cf' => (isset($cfsetting['/CF/'.$exten])),
							'cfb' => (isset($cfbsetting['/CFB/'.$exten])),
							'cfu' => (isset($cfusetting['/CFU/'.$exten])),
							'fmfm' => (isset($ampuser['/AMPUSER/'.$exten.'/followme/ddial']) && $ampuser['/AMPUSER/'.$exten.'/followme/ddial'] == "DIRECT")
						);
						$device['actions'] = '<a href="?display=extensions&amp;extdisplay='.$exten.'"><i class="fa fa-edit"></i></a><a class="clickable delete" data-id="'.$exten.'"><i class="fa fa-trash"></i></a>';
					}
					return $devices;
				} else {
					$devices = $this->getAllUsersByDeviceType($request['type']);
					if(empty($devices)) {
						return array();
					}
					foreach($devices as &$device) {
						$exten = $device['extension'];
						$device['settings'] = array(
							'cw' => (isset($cwsetting['/CW/'.$exten]) && $cwsetting['/CW/'.$exten] == "ENABLED"),
							'dnd' => (isset($dndsetting['/DND/'.$exten]) && $dndsetting['/DND/'.$exten] == "YES"),
							'cf' => (isset($cfsetting['/CF/'.$exten])),
							'cfb' => (isset($cfbsetting['/CFB/'.$exten])),
							'cfu' => (isset($cfusetting['/CFU/'.$exten]))
						);
						$device['actions'] = '<a href="?display=extensions&amp;extdisplay='.$exten.'"><i class="fa fa-edit"></i></a><a class="clickable delete" data-id="'.$exten.'"><i class="fa fa-trash"></i></a>';
					}
					return $devices;
				}
				break;
			case "getDeviceGrid":
				if($request['type'] == "all") {
					$devices = $this->getAllDevicesByType();
					if(empty($devices)) {
						return array();
					}
					foreach($devices as &$device) {
						$exten = $device['id'];
						$device['actions'] = '<a href="?display=devices&amp;extdisplay='.$exten.'"><i class="fa fa-edit"></i></a><a class="clickable delete" data-id="'.$exten.'"><i class="fa fa-trash"></i></a>';
					}
					return $devices;
				} else {
					$devices = $this->getAllDevicesByType($request['type']);
					if(empty($devices)) {
						return array();
					}
					foreach($devices as &$device) {
						$exten = $device['id'];
						$device['actions'] = '<a href="?display=devices&amp;extdisplay='.$exten.'"><i class="fa fa-edit"></i></a><a class="clickable delete" data-id="'.$exten.'"><i class="fa fa-trash"></i></a>';
					}
					return $devices;
				}
			break;
			case "delete":
				if(!empty($_POST['extensions'])) {
					switch($_POST['type']) {
						case "extensions":
							if ($this->freepbx->Modules->checkStatus("sysadmin") && (!\FreePBX::Modules()->moduleHasMethod('sysadmin', 'isCommercialDeployment'))) { 
								$isCommercialDep = \FreePBX::Sysadmin()->isCommercialDeployment();
							} else {
								$isCommercialDep = false;
							}

							foreach($_POST['extensions'] as $ext) {
								$this->delUser($ext);
								$this->delDevice($ext);
							}
							needreload();
							return array("status" => true, "isCommercialDep" => $isCommercialDep);
						break;
						case "users":
							foreach($_POST['extensions'] as $ext) {
								$this->delUser($ext);
							}
							return array("status" => true);
						break;
						case "devices":
							foreach($_POST['extensions'] as $ext) {
								$this->delDevice($ext);
							}
							return array("status" => true);
						break;
					}
				}
			break;
			case "quickcreate":

				$status = $this->processQuickCreate($_POST['tech'], $_POST['extension'], $_POST);
				return $status;
			break;
			case "getJSON":
				switch ($request['jdata']) {
					case 'allDID':
						$dids = $this->getAllDIDs();
						$dids = is_array($dids)?$dids:array();
						foreach ($dids as $key => $value) {
							$dids[$key]['cidnum'] = urlencode($value['cidnum']);
							$dids[$key]['extension'] = urlencode($value['extension']);
						}
						return array_values($dids);
					break;
					case 'allTrunks':
						$displayOnly = (isset($request['jdisplay']) && $request['jdisplay']=='onlyVisible') ? true : false;
						return array_values($this->listTrunks($displayOnly));
					break;
					case 'routingrnav':
						return array_values($this->getAllRoutes());
					break;
					case 'dahdichannels':
						return array_values($this->listDahdiChannels());
					break;
					case 'ampusers':
						return array_values($this->listAMPUsers('assoc'));
					break;
				}
			break;
			case 'getnpanxxjson':
				$npa = $request['npa'];
				$nxx = $request['nxx'];
				$data = $this->freepbx->Curl->get('http://www.localcallingguide.com/xmllocalprefix.php?npa='.$npa.'&nxx='.$nxx);
				if(!$data->success || $data->status_code != 200) {
					return array('error' => 'Error getting data');
				}
				$xml = new \SimpleXMLElement($data->body);
				$pfdata = $xml->xpath('//lca-data/prefix');
				$retdata = array();
				foreach($pfdata as $item){
					$inpa = (string)$item->npa;
					$inxx = (string)$item->nxx;
					$retdata[$inpa.$inxx] = array('npa' => $inpa, 'nxx' => $inxx);
				}
				return $retdata;
			break;
			case 'deleteChansipDetails':
				$trunkid = $request['trunkid'] ?? 0;
				$this->freepbx->Core->delConfig("converted_SIP",$trunkid);
				return true;
			break;
			case 'populatenpanxx':
				$dialpattern_array = $dialpattern_insert;
				if (preg_match("/^([2-9]\d\d)-?([2-9]\d\d)$/", $request["npanxx"], $matches)) {
					// first thing we do is grab the exch:
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_URL, "http://www.localcallingguide.com/xmllocalprefix.php?npa=".$matches[1]."&nxx=".$matches[2]);
					curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; FreePBX Local Trunks Configuration)");
					$str = curl_exec($ch);
					curl_close($ch);

					// quick 'n dirty - nabbed from PEAR
					global $amp_conf;
					require_once($amp_conf['AMPWEBROOT'] . '/admin/modules/core/XML_Parser.php');
					require_once($amp_conf['AMPWEBROOT'] . '/admin/modules/core/XML_Unserializer.php');

					$xml = new xml_unserializer;
					$xml->unserialize($str);
					$xmldata = $xml->getUnserializedData();

					$hash_filter = array(); //avoid duplicates
					if (isset($xmldata['lca-data']['prefix'])) {
						// we do the loops separately so patterns are grouped together

						// match 1+NPA+NXX (dropping 1)
						foreach ($xmldata['lca-data']['prefix'] as $prefix) {
							if (isset($hash_filter['1'.$prefix['npa'].$prefix['nxx']])) {
								continue;
							} else {
								$hash_filter['1'.$prefix['npa'].$prefix['nxx']] = true;
							}
							$dialpattern_array[] = array(
								'prepend_digits' => '',
								'match_pattern_prefix' => '1',
								'match_pattern_pass' => freepbx_htmlspecialchars($prefix['npa'].$prefix['nxx']).'XXXX',
								'match_cid' => '',
								);
						}
						// match NPA+NXX
						foreach ($xmldata['lca-data']['prefix'] as $prefix) {
							if (isset($hash_filter[$prefix['npa'].$prefix['nxx']])) {
								continue;
							} else {
								$hash_filter[$prefix['npa'].$prefix['nxx']] = true;
							}
							$dialpattern_array[] = array(
								'prepend_digits' => '',
								'match_pattern_prefix' => '',
								'match_pattern_pass' => freepbx_htmlspecialchars($prefix['npa'].$prefix['nxx']).'XXXX',
								'match_cid' => '',
								);
						}
						// match 7-digits
						foreach ($xmldata['lca-data']['prefix'] as $prefix) {
							if (isset($hash_filter[$prefix['nxx']])) {
								continue;
							} else {
								$hash_filter[$prefix['nxx']] = true;
							}
								$dialpattern_array[] = array(
									'prepend_digits' => '',
									'match_pattern_prefix' => '',
									'match_pattern_pass' => freepbx_htmlspecialchars($prefix['nxx']).'XXXX',
									'match_cid' => '',
									);
						}
						unset($hash_filter);
					} else {
						$errormsg = _("Error fetching prefix list for: "). $request["npanxx"];
					}
				} else {
					// what a horrible error message... :p
					$errormsg = _("Invalid format for NPA-NXX code (must be format: NXXNXX)");
				}

				if (isset($errormsg)) {
					return array('error' => "<script language=\"javascript\">alert('".addslashes($errormsg)."');</script>");
					unset($errormsg);
				}
			break;
		}
	}

	public function doConfigPageInit($page) {
		//Reassign $_REQUEST as it will be immutable in the future.
		$request = $this->getSanitizedRequest();
		$unsanitized = array(
			'CC_AGENT_ALERT_INFO_DEFAULT',
			'CC_MONITOR_ALERT_INFO_DEFAULT',
			'ATTTRANSALERTINFO'
			,'BLINDTRANSALERTINFO',
			'INTERNALALERTINFO'
		);
		foreach($unsanitized as $s) {
			if(isset($_POST[$s])) {
				$request[$s] = $_POST[$s];
			}
		}
		global $amp_conf;
		if ($page == "advancedsettings"){
			$freepbx_conf = $this->config;
			$settings = $freepbx_conf->get_conf_settings();
			foreach($request as $key => $val){
				if (isset($settings[$key])) {
					if($key == 'CRONMAN_UPDATES_CHECK') {
						$cm = \cronmanager::create($db);
						if($val == 'true') {
							$cm->enable_updates();
						} else {
							$cm->disable_updates();
						}
					}
					switch($settings[$key]['type']) {
						case CONF_TYPE_BOOL:
							$val = ($val == 'true') ? 1 : 0;
						break;
						default:
							$val = freepbx_trim ($val ?? "");
						break;
					}
					//FREEPBX-11431 Call Forward Ringtimer Default - Setting does not work
					// lets add CFRINGTIMERDEFAULT value in to asteriskDB so that we can use latter in the dialplan
					if($key === 'CFRINGTIMERDEFAULT'){
						$astman = $this->FreePBX->astman;
						if ($astman->connected()){
							$astman->database_put("FREEPBXCONF",'CFRINGTIMERDEFAULT',$val);
						}
					}

					$freepbx_conf->set_conf_values(array($key => $val),true,$amp_conf['AS_OVERRIDE_READONLY']);
					$status = $freepbx_conf->get_last_update_status();
					if ($status[$key]['saved']) {
						//debug(sprintf(_("Advanced Settings changed freepbx_conf setting: [$key] => [%s]"),$val));
						needreload();
					}
				}
			}
		}// $page == "advancedsettings"
		if ($page == "dahdichandids"){
			if(!isset($_REQUEST['action'])){
				return;
			}
			$type = isset($request['type']) ? $request['type'] :  'setup';
			$action = isset($request['action']) ? $request['action'] :  '';
			if (isset($request['delete'])) $action = 'delete';
			$extdisplay  = isset($request['extdisplay']) ? $request['extdisplay'] : '';
			$channel = isset($request['channel']) ? $request['channel'] :  false;
			$description = isset($request['description']) ? $request['description'] :  '';
			$did = isset($request['did']) ? $request['did'] :  '';
			$dahdichannels = new Dahdichannels();
			switch ($action) {
				case 'add':
					try {
						if($dahdichannels->add($description, $channel, $did)) {
							needreload();
							$_REQUEST['extdisplay'] = $channel;
							unset($_REQUEST['view']);
						}
					} catch(\PDOException $e) {
						if($e->getCode() == 23000){
							echo "<script>javascript:alert('" . _("Error Duplicate Channel Entry") . "')</script>";
						} else {
							throw $e;
						}
					}
				break;
				case 'edit':
					if ($dahdichannels->edit($description, $channel, $did)) {
						needreload();
						unset($_REQUEST['view']);
					}
				break;
				case 'delete':
					$dahdichannels->delete($channel);
					needreload();
				break;
			}
		}// $page == "dahdichandids"

		if ($page == "routing") {
			$display='routing';
			$extdisplay=isset($request['extdisplay'])?$request['extdisplay']:'';
			$action = isset($request['action'])?$request['action']:'';
			if (isset($request['copyroute'])) {
				$action = 'copyroute';
			}
			$repotrunkdirection = isset($request['repotrunkdirection'])?$request['repotrunkdirection']:'';
			//this was effectively the sequence, now it becomes the route_id and the value past will have to change
			$repotrunkkey = isset($request['repotrunkkey'])?$request['repotrunkkey']:'';
			// Check if they uploaded a CSV file for their route patterns
			//
			if (isset($_FILES['pattern_file']) && $_FILES['pattern_file']['tmp_name'] != '') {
				$uploaded_file = file($_FILES['pattern_file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if (count($uploaded_file) !== 0) {
					$csv_file = array();
					$index = array();
				}
				$first_line = true;
				$has_headers = false;
				foreach($uploaded_file AS $line) {
					$line = str_replace('"', '', $line);
					$line_as_array = explode(',', $line);
					if ($first_line) {
						if (str_contains(strtolower($line), 'prepend') || str_contains(strtolower($line), 'prefix') || str_contains(strtolower($line), 'match pattern') || str_contains(strtolower($line), 'callerid')) {
							$has_headers = true;
							$count_headers = count($line_as_array);
							for ($i=0;$i<$count_headers;$i++) {
								switch (strtolower($line_as_array[$i])) {
									case 'prepend':
									case 'prefix':
									case 'match pattern':
									case 'callerid':
										$index[strtolower($line_as_array[$i])] = $i;
									break;
									default:
									break;
								}
							}
							$first_line = false;
							continue;
						} else if (preg_match("/^[XZN0-9\+\.\-\[\]]*,[XZN0-9\+\.\-\[\]]*,[XZN0-9\+\.\-\[\]]*,[XZN0-9\+\.\-\[\]]*$/i", $line)) {
							$count = count($line_as_array);
							// If no headers then assume standard order
							$index['prepend'] = 0;
							$index['prefix'] = 1;
							$index['match pattern'] = 2;
							$index['callerid'] = 3;
							if ($count == 4) {
								$csv_file[] = $line_as_array;
							}
							$first_line = false;
							continue;
						}
						echo "<script>javascript:alert('" . _("Unsupported Pattern file format") . "')</script>";
						return;
					}
					if ($has_headers) {
						if (!preg_match("/^[XZN0-9\+\.\-\[\]]*,{0,1}[XZN0-9\+\.\-\[\]]*,{0,1}[XZN0-9\+\.\-\[\]]*,{0,1}[XZN0-9\+\.\-\[\]]*$/i", $line)) {
							echo "<script>javascript:alert('" . _("Unsupported Pattern file format") . "')</script>";
							return;
						} else if (count($line_as_array) != $count_headers) {
							echo "<script>javascript:alert('" . _("Malformated csv file") . "')</script>";
							return;
						}

					} else {
						if (!preg_match("/^[XZN0-9\+\.\-\[\]]*,[XZN0-9\+\.\-\[\]]*,[XZN0-9\+\.\-\[\]]*,[XZN0-9\+\.\-\[\]]*$/i", $line)) {
							echo "<script>javascript:alert('" . _("Unsupported Pattern file format") . "')</script>";
							return;
						}
					}
					$index_count = count($index);
					if (count($line_as_array) == $index_count) {
						$csv_file[] = $line_as_array;
					}
				}
			}
			// If we have a CSV file it replaces any existing patterns
			//
			if (!empty($csv_file)) {
				foreach ($csv_file as $row) {
					$this_prepend = isset($index['prepend']) ? freepbx_htmlspecialchars(freepbx_trim ($row[$index['prepend']])) : '';
					$this_prefix = isset($index['prefix']) ? freepbx_htmlspecialchars(freepbx_trim ($row[$index['prefix']])) : '';
					$this_match_pattern = isset($index['match pattern']) ? freepbx_htmlspecialchars(freepbx_trim ($row[$index['match pattern']])) : '';
					$this_callerid = isset($index['callerid']) ? freepbx_htmlspecialchars(freepbx_trim ($row[$index['callerid']])) : '';

					if ($this_prepend != '' || $this_prefix  != '' || $this_match_pattern != '' || $this_callerid != '') {
						$dialpattern_insert[] = array(
							'prepend_digits' => $this_prepend,
							'match_pattern_prefix' => $this_prefix,
							'match_pattern_pass' => $this_match_pattern,
							'match_cid' => $this_callerid,
						);
					}
				}
			} else if (isset($request["bulk_patterns"])) {
				$prepend = '/^([^+]*)\+/';
				$prefix = '/^([^|]*)\|/';
				$match_pattern = '/([^/]*)/';
				$callerid = '/\/(.*)$/';

				$data = explode("\n",$request['bulk_patterns']);
				foreach($data as $list) {
					if (preg_match('/^\s*$/', $list)) {
						continue;
					}

					$this_prepend = $this_prefix = $this_callerid = '';

					if (preg_match($prepend, $list, $matches)) {
						$this_prepend = $matches[1];
						$list = preg_replace($prepend, '', $list);
					}

					if (preg_match($prefix, $list, $matches)) {
						$this_prefix = $matches[1];
						$list = preg_replace($prefix, '', $list);
					}

					if (preg_match($callerid, $list, $matches)) {
						$this_callerid = $matches[1];
						$list = preg_replace($callerid, '', $list);
					}

					$dialpattern_insert[] = array(
						'prepend_digits' => freepbx_htmlspecialchars(freepbx_trim ($this_prepend)),
						'match_pattern_prefix' => freepbx_htmlspecialchars(freepbx_trim ($this_prefix)),
						'match_pattern_pass' => freepbx_htmlspecialchars(freepbx_trim ($list)),
						'match_cid' => freepbx_htmlspecialchars(freepbx_trim ($this_callerid)),
					);
				}
			} else if (isset($_REQUEST["dialpatterndata"])) {
				$dp = json_decode($_REQUEST['dialpatterndata'],true);
				$dp = is_array($dp) ? $dp : array();
				foreach ($dp as $pattern) {
					if ($pattern['prepend_digit'] !='' || $pattern['pattern_prefix']!='' || $pattern['pattern_pass'] !='' || $pattern['match_cid'] !='') {
						$dialpattern_insert[] = array(
							'prepend_digits' => freepbx_htmlspecialchars(freepbx_trim ($pattern['prepend_digit'])),
							'match_pattern_prefix' => freepbx_htmlspecialchars(freepbx_trim ($pattern['pattern_prefix'])),
							'match_pattern_pass' => freepbx_htmlspecialchars(freepbx_trim ($pattern['pattern_pass'])),
							'match_cid' => freepbx_htmlspecialchars(freepbx_trim ($pattern['match_cid'])),
						);
					}
				}
			}

			if ( isset($request['reporoutedirection']) && $request['reporoutedirection'] != '' && isset($request['reporoutekey']) && $request['reporoutekey'] != '') {
			  $request['route_seq'] = core_routing_setrouteorder($request['reporoutekey'], $request['reporoutedirection']);
			}

			$trunkpriority = array();
			if (isset($request["trunkpriority"])) {
				$trunkpriority = $request["trunkpriority"];

				if (!$trunkpriority) {
					$trunkpriority = array();
				}

				// delete blank entries and reorder
				foreach (array_keys($trunkpriority) as $key) {
					if ($trunkpriority[$key] == '') {
						// delete this empty
						unset($trunkpriority[$key]);

					} else if (is_numeric($repotrunkkey) && ($key==($repotrunkkey-1)) && ($repotrunkdirection=="up")) {
						// swap this one with the one before (move up)
						$temptrunk = $trunkpriority[$key];
						$trunkpriority[ $key ] = $trunkpriority[ $key+1 ];
						$trunkpriority[ $key+1 ] = $temptrunk;

					} else if (($key==($repotrunkkey)) && ($repotrunkdirection=="down")) {
						// swap this one with the one after (move down)
						$temptrunk = $trunkpriority[ $key+1 ];
						$trunkpriority[ $key+1 ] = $trunkpriority[ $key ];
						$trunkpriority[ $key ] = $temptrunk;
					}
				}
				unset($temptrunk);
				$trunkpriority = array_unique(array_values($trunkpriority)); // resequence our numbers
			  if ($action == '') {
				$action = "updatetrunks";
			  }

			}
			$routename = isset($request['routename']) ? $request['routename'] : '';
			$routepass = isset($request['routepass']) ? $request['routepass'] : '';
			$emergency = isset($request['emergency']) ? $request['emergency'] : '';
			$intracompany = isset($request['intracompany']) ? $request['intracompany'] : '';
			$mohsilence = isset($request['mohsilence']) ? $request['mohsilence'] : '';
			$outcid = isset($_REQUEST['outcid']) ? $_REQUEST['outcid'] : '';
			$outcid_mode = isset($request['outcid_mode']) ? $request['outcid_mode'] : '';
			$time_group_id = $time_group_id = isset($request['time_group_id']) && !empty($request['time_group_id']) ? $request['time_group_id'] : NULL;
			$route_seq = isset($request['route_seq']) ? $request['route_seq'] : '';
			$time_mode = isset($request['time_mode']) ? $request['time_mode'] : '';
			$timezone = isset($request['timezone']) ? $request['timezone'] : '';
			$calendar_id = isset($request['calendar_id']) ? $request['calendar_id'] : '';
			$calendar_group_id = isset($request['calendar_group_id']) ? $request['calendar_group_id'] : '';
			//email values will be taken from _POST because we don't want the sanitized
			//values(from freepbxGetSanitizedRequest), where stuff between angle brackets were removed. 
			$notification_on = isset($_POST['notification_on']) ? $_POST['notification_on'] : '';
			$emailfrom = isset($_POST['emailfrom']) ? $_POST['emailfrom'] : '';
			$emailto = isset($_POST['emailto']) ? $_POST['emailto'] : '';
			$emailsubject = isset($_POST['emailsubject']) ? $_POST['emailsubject'] : '';
			$emailbody = isset($_POST['emailbody']) ? $_POST['emailbody'] : '';
			$goto = isset($request['goto0'])?$request['goto0']:'';
			$dest = $goto ? $request[$goto . '0'] : '';
			//if submitting form, update database
			switch ($action) {
				case "copyroute":
					$routename .= "_copy_$extdisplay";
					$extdisplay='';
					$route_seq++;
					// Fallthrough to addtrunk now...
					//
				case "addroute":
					$extdisplay = core_routing_addbyid($routename, $outcid, $outcid_mode, $routepass, $emergency, $intracompany, $mohsilence, $time_group_id, $dialpattern_insert, $trunkpriority, $route_seq, $dest, $time_mode, $timezone, $calendar_id, $calendar_group_id, $notification_on, $emailfrom, $emailto, $emailsubject, $emailbody);
					$_REQUEST['id'] = $extdisplay;
					needreload();
				break;
				case "editroute":
					$extdisplay = $_REQUEST['id'];
					core_routing_editbyid($extdisplay, $routename, $outcid, $outcid_mode, $routepass, $emergency, $intracompany, $mohsilence, $time_group_id, $dialpattern_insert, $trunkpriority, $route_seq, $dest, $time_mode, $timezone, $calendar_id, $calendar_group_id, $notification_on, $emailfrom, $emailto, $emailsubject, $emailbody);
					needreload();
				break;
				case "delroute":
					if (!function_exists('core_routing_delbyid')) {
						if (file_exists(__DIR__."/functions.inc.php")) {
							include __DIR__."/functions.inc.php";
						}
					}
					$ret = core_routing_delbyid($_REQUEST['id']);
					// re-order the routes to make sure that there are no skipped numbers.
					// example if we have 001-test1, 002-test2, and 003-test3 then delete 002-test2
					// we do not want to have our routes as 001-test1, 003-test3 we need to reorder them
					// so we are left with 001-test1, 002-test3
					needreload();
					return $ret;
				break;
			}


		}// $page == "routing"

		if ($page == "did") {
			$extdisplay= freepbx_htmlspecialchars(isset($request['extdisplay'])?$request['extdisplay']:'');
			$old_extdisplay = $extdisplay;
			$dispnum = 'did'; //used for switch on config.php
			$action = isset($request['action'])?$request['action']:'';
			$rnavsort = isset($request['rnavsort'])?$request['rnavsort']:'description';
			$didfilter = isset($request['didfilter'])?$request['didfilter']:'';
			if (isset($request['submitclear']) && isset($request['goto0'])) {
				$request[$request['goto0'].'0'] = '';
			}

			if (isset($request['extension']) && isset($request['cidnum'])) {
				$extdisplay = $request['extension']."/".$request['cidnum'];
			}
			if (isset($request['old_extension']) && isset($request['old_cidnum'])) {
				$old_extdisplay = $request['old_extension']."/".$request['old_cidnum'];
			}

			//update db if submiting form
			switch ($action) {
				case 'addIncoming':
					//create variables from request
					extract($request, EXTR_SKIP);
					//add details to the 'incoming' table
					if (core_did_add($request)) {
						needreload();
						$_REQUEST['extdisplay'] = $_REQUEST['extension']."/".$_REQUEST['cidnum'];
						$this->freepbx->View->redirect_standard('extdisplay', 'didfilter', 'rnavsort');
					}
				break;
				case 'delIncoming':
					$extarray=explode('/',$extdisplay,2);
					core_did_del($extarray[0],$extarray[1]);
					needreload();
				break;
				case 'edtIncoming':
					$extarray=explode('/',$old_extdisplay,2);
					if (core_did_edit($extarray[0],$extarray[1],$_REQUEST)) {
						needreload();
					}
				break;
			}

		}// $page == "did"

		if ($page == "astmodules") {
			$action = !empty($request['action']) ? $request['action'] : "";
			$section = !empty($request['section']) ? $request['section'] : "";
			if(empty($request['module'])){
				return false;
			}
			$modinfo = new \SplFileInfo($module);
			if($modinfo->getExtension() !== 'so'){
				return false;
			}
			$module = $modinfo->getBasename();
			unset($modinfo);
			switch($action){
				case 'add':
					switch($section){
						case 'amodload':
							$this->freepbx->ModulesConf->load($module);
							return true;
						break;
						case 'amodnoload':
							$this->freepbx->ModulesConf->noload($module);
							return true;
						break;
						case 'amodpreload':
							$this->freepbx->ModulesConf->preload($module);
							return true;
						break;
						default:
							return false;
						break;
					}
				break;
				case 'del':
					switch($section){
						case 'amodnoload':
							$this->freepbx->ModulesConf->removenoload($module);
							return true;
						break;
						case 'amodpreload':
							$this->freepbx->ModulesConf->removepreload($module);
							return true;
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
		} // $page == "astmodules"

	}

	/**
	 * Converts a request into an array that core wants.
	 * @param {int} $account The Account Number
	 * @param {string} The TECH type
	 * @param {int} &$flag   The Flag Number
	 */
	public function convertRequest2Array($account,$tech,&$flag = 2) {
		if(!isset($account) || (freepbx_trim ($account) === "") || !ctype_digit($account)) {
			throw new \Exception("Account must be set!");
		}
		if(empty($tech)) {
			throw new \Exception("tech must be set!");
		}
		$flag = !empty($flag) ? $flag : 2;
		$fields = array();
		$tech = strtoupper($tech);
		foreach ($_REQUEST as $req=>$data) {
			$keyword = substr($req, 8);
			if($tech == 'VIRTUAL'){
				$fields[$keyword] = array("value" => $data, "flag" => $flag++);
				continue;
			}
			if ( substr($req, 0, 8) == 'devinfo_' ) {
				$data = freepbx_trim ($data);
				if ( $keyword == 'dial' && $data == '' ) {
					if($tech == 'ZAP' || $tech == 'DAHDI') {
						$chan = $_REQUEST['devinfo_channel'] != '' ? $_REQUEST['devinfo_channel'] : $_REQUEST['channel'];
						$fields[$keyword] = array("value" => $tech.'/'.$chan, "flag" => $flag++);
					} else {
						$fields[$keyword] = array("value" => $tech.'/'.$account, "flag" => $flag++);
					}
				} elseif ($keyword == 'mailbox' && $data == '') {
					if(isset($_REQUEST['vm']) && $_REQUEST['vm'] == 'enabled') {
						$fields['mailbox'] = array("value" => $account.'@device', "flag" => $flag++);
					}
				} elseif ($keyword == 'vmexten' && $data == '') {
					// don't add it
				} else {
					$fields[$keyword] = array("value" => $data, "flag" => $flag++);
				}
			}
		}
		if(empty($fields)) {
			die_freepbx('Fields are empty');
		}
		$fields['account'] = array("value" => $account, "flag" => $flag++);
		$fields['callerid'] = array("value" => (isset($_REQUEST['name']) && $_REQUEST['name']) ? $_REQUEST['name']." <".$account.'>' : 'device'." <".$account.'>', "flag" => $flag++);
		return $fields;
	}

	/**
	 * Generate the default settings when creating a user
	 * @param int $number      The exten or device number
	 * @param string $displayname The displayname
	 */
	public function generateDefaultUserSettings($number,$displayname) {
		return array(
			"extension" => $number,
			"name" => $displayname,
			"outboundcid" => "",
			"password" => "",
			"sipname" => "",
			"ringtimer" => 0,
			"callwaiting" => "enabled",
			"pinless" => "disabled",
			"recording_in_external" => "dontcare",
			"recording_out_external" => "dontcare",
			"recording_in_internal" => "dontcare",
			"recording_out_internal" => "dontcare",
			"recording_ondemand" => "disabled",
			"recording_priority" => "10",
			"answermode" => "disabled",
			"intercom" => "enabled",
			"cid_masquerade" => "",
			"noanswer_dest" => "",
			"busy_dest" => "",
			"noanswer_cid" => "",
			"busy_cid" => "",
			"chanunavail_cid" => "",
			"cwtone" => "disabled",
			"concurrency_limit" => "",
			"chanunavail_dest" => "",
			"accountcode" => "",
            "dialopts" => "",
            "call_screen" => "0",
            "rvolume" => ""
		);
	}

	/**
	 * Generate a secret to be used as a password Up to the SIPSECRETSIZE
	 */
	public function generateSecret(){
		global $amp_conf;
		$secret = md5(uniqid());//32 char
		if(isset($amp_conf['SIPSECRETSIZE']) && $amp_conf['SIPSECRETSIZE'] < 32){
				$secret = substr($secret, 0, $amp_conf['SIPSECRETSIZE']);
		}
		return $secret;
	}
	/**
	 * Generate the default settings when creating a device
	 * @param {string} The TECH
	 * @param {int} The exten or device number
	 * @param {string} $displayname The displayname
	 */
	public function generateDefaultDeviceSettings($tech,$number,$displayname,$channel = false,&$flag = 2) {
		$flag = !empty($flag) ? $flag : 2;
		$dial = '';
		$settings = array();
		//Ask our tech if it has default settings
		if(isset($this->drivers[$tech])) {
			$settings = $this->drivers[$tech]->getDefaultDeviceSettings($number, $displayname, $flag);
			if(empty($settings)) {
				return array();
			}
		}
		if($tech == "dahdi") {
			if(isset($channel)){
				$settings['settings']['channel']['value'] = $channel;
				$dial_value = $settings['dial']."/".$channel;
			}
		} else {
			$dial_value = $settings['dial']."/".$number;
		}
		$gsettings  = array(
			"devicetype" => array(
				"value" => "fixed"
			),
			"user" => array(
				"value" => $number
			),
			"description" => array(
				"value" => $displayname
			),
			"emergency_cid" => array(
				"value" => '',
			),
			"dial" => array(
				"value" => $dial_value,
				"flag" => $flag++
			),
			"secret" => array(
				"value" => $this->generateSecret(),
				"flag" => $flag++
			),
			"context" => array(
				"value" => "from-internal",
				"flag" => $flag++
			),
			"mailbox" => array(
				"value" => $number."@device",
				"flag" => $flag++
			),
			"account" => array(
				"value" => $number,
				"flag" => $flag++
			),
			"callerid" => array(
				"value" => "$displayname <".$number.">",
				"flag" => $flag++
			),
			"outbound_proxy" => array(
				"value" => '',
				"flag" => $flag++
			),
			"outbound_proxy" => array(
				"value" => '',
				"flag" => $flag++
			)
		);
		return array_merge($settings['settings'],$gsettings);
	}

	/**
	 * Add Device
	 * @param {int} The Device Number
	 * @param {string} The TECH type
	 * @param {array} $settings=array() Array with all settings
	 * @param {bool} $editmode=false   If edited, (this is so it doesnt destroy the AsteriskDB)
	 */
	public function addDevice($id,$tech,$settings=array(),$editmode=false) {
		if ($tech == '') {
			return true;
		}

		if ($tech == "pjsip") {
			if (isset($settings['max_contacts']['value']) && !empty($settings['max_contacts']['value'])) {
				$settings['max_contacts']['value'] = ($settings['max_contacts']['value'] > 100 ? 100 : $settings['max_contacts']['value']);
			}else{
				$settings['max_contacts']['value'] = 1;
			}

			if($settings['max_contacts']['value'] == 1) {
				$settings['remove_existing']['value'] = 'yes';
			}
		}
		if (freepbx_trim ($id) == '' || empty($settings)) {
			throw new \Exception(_("Device Extension was blank or there were no settings defined"));
			return false;
		}

		//ensure this id is not already in use
		$dev = $this->getDevice($id);
		if(!empty($dev)) {
			throw new \Exception(_("This device id is already in use"));
		}

		//unless defined, $dial is TECH/id
		if ($settings['dial']['value'] == '') {
			//zap, dahdi are exceptions
			if (strtolower($tech) == "zap" || strtolower($tech) == 'dahdi') {
				$thischan = $settings['devinfo_channel']['value'] != '' ? $settings['devinfo_channel']['value'] : $settings['channel']['value'];
				$settings['dial']['value'] = strtoupper($tech).'/'.$thischan;
				//-------------------------------------------------------------------------------------------------
				// Added to enable the unsupported misdn module
				//
			} else if (strtolower($tech) == "misdn") {
				$settings['dial']['value'] = $settings['devinfo_port']['value'].'/'.($settings['devinfo_msn']['value'] ? $settings['devinfo_msn']['value'] : $id);
				//-------------------------------------------------------------------------------------------------
			} else {
				$settings['dial']['value'] = strtoupper($tech)."/".$id;
			}
		}

		$settings['user']['value'] = ($settings['user']['value'] == 'new') ? $id : $settings['user']['value'];
		$settings['emergency_cid']['value'] = freepbx_trim ($settings['emergency_cid']['value']);
		$settings['description']['value'] = freepbx_trim ($settings['description']['value']);
		$settings['hint_override']['value'] = isset($settings['hint_override']['value'])?freepbx_trim ($settings['hint_override']['value']) : null;

		//insert into devices table
		if($tech != 'virtual'){
			$sql="INSERT INTO devices (id,tech,dial,devicetype,user,description,emergency_cid, hint_override) values (?,?,?,?,?,?,?,?)";
			$sth = $this->database->prepare($sql);
			try {
				$sth->execute(array($id,$tech,$settings['dial']['value'],$settings['devicetype']['value'],$settings['user']['value'],$settings['description']['value'],$settings['emergency_cid']['value'],$settings['hint_override']['value']));
			} catch(\Exception $e) {
				die_freepbx("Could Not Insert Device", $e->getMessage());
				return false;
			}
		}

		$astman = $this->FreePBX->astman;
		//add details to astdb
		if ($astman->connected()) {
			// if adding or editting a fixed device, user property should always be set
			if ($settings['devicetype']['value'] == 'fixed' || !$editmode) {
				$astman->database_put("DEVICE",$id."/user",$settings['user']['value']);
			}
			// If changing from a fixed to an adhoc, the user property should be intialized
			// to the new default, not remain as the previous fixed user
			if ($editmode) {
				$previous_type = $astman->database_get("DEVICE",$id."/type");
				if ($previous_type == 'fixed' && $settings['devicetype']['value'] == 'adhoc') {
					$astman->database_put("DEVICE",$id."/user",$settings['user']['value']);
				}
			}
			$astman->database_put("DEVICE",$id."/tech",$tech);
			$astman->database_put("DEVICE",$id."/dial",$settings['dial']['value']);
			$astman->database_put("DEVICE",$id."/type",$settings['devicetype']['value']);
			$astman->database_put("DEVICE",$id."/default_user",(!empty($settings['defaultuser']['value']) ? $settings['defaultuser']['value'] : $settings['user']['value']));
			if($settings['emergency_cid']['value'] != '') {
				$astman->database_put("DEVICE",$id."/emergency_cid",$settings['emergency_cid']['value']);
			} else {
				$astman->database_del("DEVICE",$id."/emergency_cid");
			}

			$apparent_connecteduser = ($editmode && $settings['user']['value'] != "none") ? $astman->database_get("DEVICE",$id."/user") : $settings['user']['value'];
			if ($settings['user']['value'] != "none" && $apparent_connecteduser == $settings['user']['value'])  {
				$existingdevices = $astman->database_get("AMPUSER",$settings['user']['value']."/device");
				if (empty($existingdevices)) {
					$astman->database_put("AMPUSER",$settings['user']['value']."/device",$id);
				} else {
					$existingdevices_array = explode('&',$existingdevices);
					if (!in_array($id, $existingdevices_array)) {
						$existingdevices_array[]=$id;
						$existingdevices = implode('&',$existingdevices_array);
						$astman->database_put("AMPUSER",$settings['user']['value']."/device",$existingdevices);
					}
				}
			}

		} else {
			die_freepbx("Cannot connect to Asterisk Manager with ".$this->config->get('AMPMGRUSER')."/".$this->config->get('AMPMGRPASS'));
		}

		if ( $this->FreePBX->Modules->moduleHasMethod('Voicemail','mapMailBox') ) {
			$this->FreePBX->Voicemail->mapMailBox($settings['user']['value']);
		}

		if(!$editmode){
			$this->setPresenceState($id, 'available');
		}
		// before calling device specifc funcitions, get rid of any bogus fields in the array
		//
		if (isset($settings['devinfo_secret_origional'])) {
			unset($settings['devinfo_secret_origional']);
		}

		unset($settings['devicetype']);
		unset($settings['user']);
		unset($settings['description']);
		unset($settings['emergency_cid']);
		unset($settings['changecdriver']);
		unset($settings['hint_override']);

		//take care of sip/iax/zap config
		$tech = strtolower($tech);
		if(isset($this->drivers[$tech])) {
			return $this->drivers[$tech]->addDevice($id, $settings);
		}

		$this->deviceCache = array();
		return true;
	}

	/**
	 * Take an output from a getDevice() and convert it to a format that addDevice() expects
	 * @param {array} Array of device values
	 * 
	 * @return array Array of add device values
	 */
	private function kvArrayifyDeviceValues($values) {
		$response = array();
		$flag = 2;
		$ignoreTheseKeys = array('id', 'tech');
		foreach($values as $key => $value) {
			if (in_array($key, $ignoreTheseKeys)) {
				continue;
			}

			$response[$key] = array(
				'value' => $value,
				'flag' => $flag++
			);
		}
		return $response;
	}

	/**
	 * Change a Device Tech from SIP -> PJSIP and visa versa
	 * @param {int} The Device Number
	 * @param {string} Convert to the specified TECH type
	 * 
	 * @return boolean If the method was successful
	 */
	public function changeDeviceTech($deviceid, $tech) {
		$device = $this->getDevice($deviceid);

		if (empty($device)) {
			$errorMsg = _("Unable to change device driver. Unable to fetch the device");
			throw new \Exception($errorMsg);
			return false;			
		}

		if ($device['tech'] === $tech) {
			$errorMsg = _("Unable to change device driver. The device is already set to the specified driver");
			throw new \Exception($errorMsg);
			return false;
		}

		$device['dial'] = strtoupper($tech).'/'.$deviceid;
		$device['sipdriver'] = ($tech == 'pjsip') ? 'chan_pjsip' : 'chan_sip'; 

		$default_setting = $this->generateDefaultDeviceSettings($tech, $deviceid, $device['description']);
		foreach($default_setting as $key => $data){
			$default[$key] = $data['value'];
		}

		$getencryptionval = $this->getencryptionval($deviceid, $tech);
		if (!empty($getencryptionval)) {
			if ($tech == 'pjsip') {
				if (isset($getencryptionval[0]['data']) && $getencryptionval[0]['data'] == 'yes') {
					$default['media_encryption'] = 'sdes';
				} else {
					$default['media_encryption'] = 'no';
				}
			} else {
				if (isset($getencryptionval[0]['data']) && $getencryptionval[0]['data'] != 'no') {
					$default['encryption'] = 'yes';
				} else {
					$default['encryption'] = 'no';
				}
			}
		}
		$updated_dev_setting = array_intersect_key(array_merge($default,$device), $default);
		$settings = $this->kvArrayifyDeviceValues($updated_dev_setting);

		//reboot the associated endpoint to pull the new configuration
		$this->processEPM($deviceid, $tech, true);

		// delete then re add, insanity.
		$this->delDevice($deviceid, true);

		$ret = $this->addDevice($deviceid, $tech, $settings, true);

		//update the associated endpoint configuration
		$this->processEPM($deviceid, $tech);

                return $ret;

	}
	public function processEPM($ext, $tech, $reboot = false) {
		if ($tech != 'pjsip')  {
			return ;
		}
		if (!$this->freepbx->Modules->checkStatus("endpoint")) {
			return ;
		}

		$this->freepbx->Modules->loadFunctionsInc('endpoint');

		if ($reboot && function_exists('endpoint_forcereboot')) {
			endpoint_forcereboot($ext);
		} else {
			if (function_exists('endpoint_convertExt')) {
				endpoint_convertExt($ext, $tech);
			}
		}
	}

	/* create emergency Device
	* Allowed only sip and Pjsip
	*/
	public function emergencyAddDevice($id,$tech,$settings=array(),$editmode=false) {
		if ($tech == '' || freepbx_trim ($tech) == 'virtual') {
			return true;
		}

		if (freepbx_trim ($id) == '' || empty($settings)) {
			throw new \Exception(_("Device Extension was blank or there were no settings defined"));
			return false;
		}

		//ensure this id is not already in use
		$dev = $this->getEmergencyDevice($id);
		if(!empty($dev)) {
			throw new \Exception(_("This device id is already in use"));
		}

		//unless defined, $dial is TECH/id
		if ($settings['dial']['value'] == '') {
			$settings['dial']['value'] = strtoupper($tech)."/".$id;
		}

		$settings['user']['value'] = 'DummyUser';
		$settings['emergency_cid']['value'] = freepbx_trim ($settings['emergency_cid']['value']);
		$settings['description']['value'] = freepbx_trim ($settings['calleridname']['value']);
		$settings['hint_override']['value'] = isset($settings['hint_override']['value'])?freepbx_trim ($settings['hint_override']['value']) : null;

		//insert into devices table
		$sql="INSERT INTO emergencydevices (id,tech,dial,devicetype,user,description,emergency_cid,hint_override) values (?,?,?,?,?,?,?,?)";
		$sth = $this->database->prepare($sql);
		try {
			$sth->execute(array($id,$tech,$settings['dial']['value'],$settings['devicetype']['value'],$settings['user']['value'],$settings['description']['value'],$settings['emergency_cid']['value'],$settings['hint_override']['value']));
		} catch(\Exception $e) {
			die_freepbx("Could Not Insert Device", $e->getMessage());
			return false;
		}

		$astman = $this->FreePBX->astman;
		//add details to astdb
		if ($astman->connected()) {
			// if adding or editting a fixed device, user property should always be set
			if ($settings['devicetype']['value'] == 'fixed' || !$editmode) {
				$astman->database_put("EDEVICE",$id."/user",$settings['user']['value']);
			}
			// If changing from a fixed to an adhoc, the user property should be intialized
			// to the new default, not remain as the previous fixed user
			if ($editmode) {
				$previous_type = $astman->database_get("EDEVICE",$id."/type");
				if ($previous_type == 'fixed' && $settings['devicetype']['value'] == 'adhoc') {
					$astman->database_put("EDEVICE",$id."/user",$settings['user']['value']);
				}
			}
			$astman->database_put("EDEVICE",$id."/dial",$settings['dial']['value']);
			$astman->database_put("EDEVICE",$id."/type",$settings['devicetype']['value']);
			$astman->database_put("EDEVICE",$id."/default_user",$settings['user']['value']);
			$astman->database_put("EDEVICE",$id."/location",$settings['description']['value']);
			if($settings['emergency_cid']['value'] != '') {
				$astman->database_put("EDEVICE",$id."/emergency_cid",$settings['emergency_cid']['value']);
			} else {
				$astman->database_del("EDEVICE",$id."/emergency_cid");
			}


		} else {
			die_freepbx("Cannot connect to Asterisk Manager with ".$this->config->get('AMPMGRUSER')."/".$this->config->get('AMPMGRPASS'));
		}
		unset($settings['devinfo_secret_origional']);
		unset($settings['devicetype']);
		unset($settings['user']);
		unset($settings['description']);
		unset($settings['emergency_cid']);
		unset($settings['changecdriver']);
		unset($settings['hint_override']);

		//take care of sip/iax/zap config
		$tech = strtolower($tech);
		if($tech == 'sip' || $tech == 'pjsip') {
			$sql = 'INSERT INTO sip (id, keyword, data, flags) values (?,?,?,?)';
			$sth = $this->database->prepare($sql);
			$settings = is_array($settings)?$settings:array();
			foreach($settings as $key => $setting) {
				if($key == 'calleridname') {
					continue; // addtional param we dont want this in sip table But need it in astDB
				}
				$sth->execute(array($id,$key,$setting['value'],$setting['flag']));
			}
		}

		$this->deviceCache = array();
		return true;
	}

	/**
	 * List All DAHDi Channels
	 * @return array Array of DAHDi Channels
	 */
	public function listDahdiChannels(){
        return $this->dahdichannels->listChannels();
	}

	/**
	 * Delete trunk from inbound route by id.
	 * @param  int $trunk_id id of the trunk
	 * @return bool           Return from db call
	 */
	public function delRouteTrunkByID($trunk_id){
        return $this->routing->deleteOutboundRouteTrunksByTrunkId($trunk_id);
	}

	/**
	 * Delete Trunk
	 * @param  string $trunknum Trunk ID
	 * @param  srting $tech     Trunk tech
	 * @return mixed           Return (bool) true on success or array on failure.
	 */
	public function deleteTrunk($trunknum, $tech = null, $edit = false){
		if ($tech === null) { // in EditTrunk, we get this info anyways
			$tech = $this->getTrunkTech($trunknum);
		}

		// conditionally, delete from iax or sip
		switch (strtolower($tech)) {
			case "iax2":
			$tech = "iax";
			// fall through
			case "iax":
			case "sip":
				$sql = "DELETE FROM $tech WHERE id IN (:trunknum1, :trunknum2, :trunknum3)";
				$stmt = $this->database->prepare($sql);
				$ret1 = $stmt->execute(array(':trunknum1'=>'tr-peer-'.$trunknum,':trunknum2' => 'tr-user-'.$trunknum, ':trunknum3' => 'tr-reg-'.$trunknum));
			break;
			case "pjsip":
				$sql = "DELETE FROM pjsip WHERE id = :trunknum";
				$stmt = $this->database->prepare($sql);
				$ret1 = $stmt->execute(array(':trunknum'=>$trunknum));
			break;
		}
		$sql = "DELETE FROM trunks WHERE trunkid = :trunknum";
		$stmt = $this->database->prepare($sql);
		$ret = $stmt->execute(array(':trunknum'=>$trunknum));
		if ($this->astman) {
			$this->astman->database_del("TRUNK", $trunknum . '/dialopts');
		}
		$this->freepbx->Core->delConfig("converted_SIP",$trunknum);
		//Handle hooks
		$this->freepbx->Hooks->processHooks($trunknum, $tech);
		//Remove trunk from inbound routes
		if(!$edit){
			$this->delRouteTrunkByID($trunknum);
		}
		if($ret){
			return true;
		}else{
			return array('status' => false, 'techdelete' => $ret1, 'trunksdel' => $ret);
		}
	}

	/**
	 * List All Trunks
	 * if$displayOnly is true, will get only the trunks with routedisplay field set to on
	 * @return array Array of Trunks
	 */
	public function listTrunks($displayOnly = false) {
		$sql = 'SELECT * from `trunks` ORDER BY `trunkid`';
		$stmt = $this->database->prepare($sql);
		$ret  = $stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$trunk_list = array();
		foreach ($result as $trunk) {
			if ($trunk['name'] == '') {
				$tech = strtoupper($trunk['tech']);
				switch ($tech) {
					case 'CUSTOM':
						$trunk['name'] = 'AMP:'.$trunk['channelid'];
					break;
					default:
						$trunk['name'] = $tech.'/'.$trunk['channelid'];
					break;
				}
			}
			if (!$displayOnly || ($displayOnly && (!isset($trunk['routedisplay']) || $trunk['routedisplay'] == 'on'))) {
				// if displayOnly is set let's return only the trunks with routedisplay set to 'off'
				$trunk['dialopts'] = $this->freepbx->astman->database_get("TRUNK",$trunk['trunkid'] . "/dialopts");
				$trunk_list[$trunk['trunkid']] = $trunk;
			} 
			
		}
		return $trunk_list;
	}

	public function trunkHasRegistrations($type = ''){
		$types = array(
			'zap',
			'dahdi',
			'custom',
			''
		);
		return !in_array($type, $types);
	}

	public function deleteTrunkDialRulesByID($trunknum) {
		$sql = "DELETE FROM `trunk_dialpatterns` WHERE `trunkid` = ?";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($trunknum));
	}

	public function getTrunkRoutesByID($trunknum) {
		$sql = 'SELECT a.seq, b.name FROM outbound_route_trunks a JOIN outbound_routes b ON a.route_id = b.route_id WHERE trunk_id = ?';
		$sth = $this->database->prepare($sql);
		$sth->execute(array($trunknum));
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);

		$routes = array();
		foreach ($results as $entry) {
			$routes[$entry['name']] = $entry['seq'];
		}
		return $routes;
	}

	public function updateRouteTrunks($route_id, &$trunks, $delete = false) {
        return $this->routing->updateTrunks($route_id, $trunks, $delete);
	}

	public function updateTrunkDialRules($trunknum, &$patterns, $delete = false) {
		$filter_prepend = '/[^0-9+*#wW]/';
		$filter_prefix = '/[^0-9*#+xnzXNZ\-\[\]]/';
		$filter_match =  '/[^0-9.*#+xnzXNZ\-\[\]]/';

		$insert_pattern = array();
		$seq = 0;
		foreach ($patterns as $pattern) {
			$match_pattern_prefix = preg_replace($filter_prefix,'',strtoupper(freepbx_trim ($pattern['match_pattern_prefix'])));
			$match_pattern_pass = preg_replace($filter_match,'',strtoupper(freepbx_trim ($pattern['match_pattern_pass'])));
			$prepend_digits = str_replace('W', 'w', preg_replace($filter_prepend,'',strtoupper(freepbx_trim ($pattern['prepend_digits']))));
			if ($match_pattern_prefix.$match_pattern_pass == '') {
				continue;
			}
			// if duplicate prepend, get rid of subsequent since they will never be checked
			$hash_index = md5($match_pattern_prefix.$match_pattern_pass);
			if (!isset($insert_pattern[$hash_index])) {
				$insert_pattern[$hash_index] = array($match_pattern_prefix, $match_pattern_pass, $prepend_digits, $seq);
				$seq++;
			}
		}

		if ($delete) {
			$sth = $this->database->prepare("DELETE FROM `trunk_dialpatterns` WHERE `trunkid`= ?");
			$sth->execute(array($trunknum));
		}
		$sth = $this->database->prepare('INSERT INTO `trunk_dialpatterns` (`trunkid`, `match_pattern_prefix`, `match_pattern_pass`, `prepend_digits`, `seq`) VALUES (?,?,?,?,?)');
		foreach($insert_pattern as $pattern) {
			array_unshift($pattern, $trunknum);
			$sth->execute($pattern);
		}
	}

	public function getRouteByID($route_id) {
        return $this->routing->get($route_id);
	}

	public function getAllTrunkDialRules() {
		$sql = "SELECT * FROM `trunk_dialpatterns` ORDER BY `trunkid`, `seq`";
		$sth = $this->database->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getRouteTrunksByID($route_id) {
        return $this->routing->getRouteTrunksById($route_id);
	}

	public function getRoutePatternsByID($route_id) {
        return $this->routing->getRoutePatternsById($route_id);
	}

    public function getRouteEmailByID($route_id) {
        return $this->routing->getRouteEmailById($route_id);
    }

	public function getTrunkDialRulesByID($trunkid) {
		$sql = "SELECT * FROM `trunk_dialpatterns` WHERE `trunkid` = ?  ORDER BY `seq`";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($trunkid));
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getTrunkTrunkNameByID($trunknum) {
		$name = "SELECT `name` FROM `trunks` WHERE `trunkid` = ?";
		$sth = $this->database->prepare($name);
		$sth->execute(array($trunknum));
		$results = $sth->fetch(PDO::FETCH_ASSOC);
		return isset($results['name']) ? $results['name'] : false;
	}

	public function getTrunkDetails($trunkid) {
		$tech = $this->getTrunkTech($trunkid);
		$sql = "SELECT keyword,data FROM $tech WHERE `id` = ? ORDER BY flags, keyword DESC";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($trunkid));
		return $sth->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	public function getTrunkPeerDetailsByID($trunkid) {
		$tech = $this->getTrunkTech($trunkid);
		if (!$this->trunkHasRegistrations($tech)){
			return '';
		}

		$sql = "SELECT keyword,data FROM $tech WHERE `id` = ? ORDER BY flags, keyword DESC";
		$sth = $this->database->prepare($sql);
		$sth->execute(array('tr-peer-'.$trunkid));
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		foreach ($results as $result) {
			if ($result['keyword'] != 'account') {
				if (isset($confdetail)) {
					$confdetail .= $result['keyword'] .'='. $result['data'] . "\n";
				} else {
					$confdetail = $result['keyword'] .'='. $result['data'] . "\n";
				}
			}
		}
		return isset($confdetail)?$confdetail:null;
	}

	public function getTrunkUserContext($trunkid) {
		$sql = "SELECT `usercontext` FROM `trunks` WHERE `trunkid` = ?";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($trunkid));
		$results = $sth->fetch(PDO::FETCH_ASSOC);

		return ((isset($results['usercontext'])) ? $results['usercontext'] : '');
	}

	public function getTrunkUserConfigByID($trunkid) {
		$tech = $this->getTrunkTech($trunkid);
		if (!$this->trunkHasRegistrations($tech)){
			return '';
		}

		$sql = "SELECT keyword,data FROM $tech WHERE `id` = ? ORDER BY flags, keyword DESC";
		$sth = $this->database->prepare($sql);
		$sth->execute(array('tr-user-'.$trunkid));
		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		foreach ($results as $result) {
			if ($result['keyword'] != 'account') {
				if (isset($confdetail)) {
					$confdetail .= $result['keyword'] .'='. $result['data'] . "\n";
				} else {
					$confdetail = $result['keyword'] .'='. $result['data'] . "\n";
				}
			}
		}
		return isset($confdetail)?$confdetail:null;
	}

	public function getTrunkRegisterStringByID($trunkid) {
		$tech = $this->getTrunkTech($trunkid);
		if (!$this->trunkHasRegistrations($tech)){
			return '';
		}
		// TODO: These should be deferred to their respective driver
		if('pjsip' == $tech){
			$sql = "SELECT `data` FROM pjsip WHERE `id` = :trunkid and `keyword` = 'registration'";
			$sth = $this->database->prepare($sql);
			$sth->execute(array(':trunkid' => $trunkid));
			$result = $sth->fetchColumn();
			return in_array($result,array('send','receive'))?$result:null;
		}
		$sql = "SELECT `data` FROM $tech WHERE `id` = ? ";
		$sth = $this->database->prepare($sql);
		$sth->execute(array('tr-reg-'.$trunkid));
		$result = $sth->fetch(PDO::FETCH_ASSOC);
		return isset($result['data'])?$result['data']:null;
	}

	public function getTrunkByID($trunkid) {
		$sql = 'SELECT * from `trunks` WHERE `trunkid` = ?';
		$stmt = $this->database->prepare($sql);
		$ret  = $stmt->execute(array($trunkid));
		$trunk = $stmt->fetch(PDO::FETCH_ASSOC);
		if(empty($trunk)) {
			return false;
		}
		if ($trunk['name'] == '') {
			$tech = strtoupper($trunk['tech']);
			switch ($tech) {
				case 'CUSTOM':
					$trunk['name'] = 'AMP:'.$trunk['channelid'];
				break;
				default:
					$trunk['name'] = $tech.'/'.$trunk['channelid'];
				break;
			}
		}
		$trunk['dialopts'] = $this->freepbx->astman->database_get("TRUNK",$trunkid . "/dialopts");
		return $trunk;
	}

	public function getTrunkByChannelID($trunkid) {
		$sql = 'SELECT * from `trunks` WHERE `channelid` = ?';
		$stmt = $this->database->prepare($sql);
		$ret  = $stmt->execute(array($trunkid));
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		return $result;
	}

	/**
	* Get all the users
	* @param {bool} $get_all=false Whether to get all of check in the range
	*/
	function listUsers($get_all=false) {
		if (empty($this->listUsersCache)) {
			$sql = 'SELECT extension,name,voicemail FROM users ORDER BY extension';
			$sth = $this->database->prepare($sql);
			$sth->execute();
			$results = $sth->fetchAll(PDO::FETCH_BOTH);
			$this->listUsersCache = $results;
		} else {
			$results = $this->listUsersCache;
		}

		//only allow extensions that are within administrator's allowed range
		foreach($results as $result){
			if ($get_all || \checkRange($result[0])){
				$extens[] = array($result[0],$result[1],$result[2]);
			}
		}

		if (isset($extens)) {
			sort($extens);
			return $extens;
		} else {
			return array();
		}
	}

	/**
	 * Delete a Device
	 * @param {int} The Device ID
	 * @param {bool} $editmode=false If in edit mode (this is so it doesnt destroy the AsteriskDB)
	 */
	public function delDevice($account,$editmode=false) {
		$astman = $this->FreePBX->astman;
		//get all info about device
		$devinfo = $this->getDevice($account);
		if (empty($devinfo)) {
			$devinfo['tech'] = "virtual";
		}

		//delete details to astdb
		if ($astman->connected()) {
			// If a user was selected, remove this device from the user
			$deviceuser = $astman->database_get("DEVICE",$account."/user");
			if (isset($deviceuser) && $deviceuser != "none") {
				// Remove the device record from the user's device list
				$userdevices = $astman->database_get("AMPUSER",$deviceuser."/device");

				// We need to remove just this user and leave the rest alone
				$userdevicesarr = explode("&", $userdevices);
				$userdevicesarr_hash = array_flip($userdevicesarr);
				unset($userdevicesarr_hash[$account]);
				$userdevicesarr = array_flip($userdevicesarr_hash);
				$userdevices = implode("&", $userdevicesarr);

				if (empty($userdevices)) {
					$astman->database_del("AMPUSER",$deviceuser."/device");
				} else {
					$astman->database_put("AMPUSER",$deviceuser."/device",$userdevices);
				}
			}
			if (!$editmode) {
				$astman->database_del("DEVICE",$account."/dial");
				$astman->database_del("DEVICE",$account."/type");
				$astman->database_del("DEVICE",$account."/user");
				$astman->database_del("DEVICE",$account."/default_user");
				$astman->database_del("DEVICE",$account."/emergency_cid");
				$astman->database_del("CustomPresence",$account);
			}

			//delete from devices table
			$sql = "DELETE FROM devices WHERE id = ?";
			$sth = $this->database->prepare($sql);
			try {
				$sth->execute(array($account));
			} catch(\Exception $e) {
				dbug($e->getMessage());
			}

			//voicemail symlink
            if (!$editmode) {
                $spooldir = $this->config->get('ASTSPOOLDIR');
                $account = preg_replace("/\D/", "", $account);
                if (freepbx_trim($account) !== "" && file_exists($spooldir . "/voicemail/device/" . $account)) {
                    exec("rm -f " . escapeshellarg($spooldir . "/voicemail/device/" . $account));
                }
            }
		} else {
			die_freepbx("Cannot connect to Asterisk Manager with ".$this->config->get("AMPMGRUSER")."/".$this->config->get("AMPMGRPASS"));
		}

		$tech = $devinfo['tech'];

		//TODO should only delete the record for this device buuuutttt......
		if(isset($this->drivers[$tech])) {
			$this->drivers[$tech]->delDevice($account);
		}
		$this->freepbx->Hooks->processHooks($account, $editmode);
		$this->getDeviceHeadersCache = array();
		$this->deviceCache = array();
		$this->getDeviceCache = array();
		return true;
	}
	/**
	 * Delete a emergencyDevice
	 * @param {int} The Device ID
	 * @param {bool} $editmode=false If in edit mode (this is so it doesnt destroy the AsteriskDB)
	 */
	public function delEmergencyDevice($account,$editmode=false) {
		$astman = $this->FreePBX->astman;
		//get all info about device
		$devinfo = $this->getEmergencyDevice($account);
		if(!isset($devinfo['id'])) {
			return;
		}
		//delete details to astdb
		if ($astman->connected()) {
		if (!$editmode) {
				$astman->database_del("EDEVICE",$account."/dial");
				$astman->database_del("EDEVICE",$account."/type");
				$astman->database_del("EDEVICE",$account."/user");
				$astman->database_del("EDEVICE",$account."/default_user");
				$astman->database_del("EDEVICE",$account."/location");
				$astman->database_del("EDEVICE",$account."/emergency_cid");
			}

			//delete from devices table
			$sql = "DELETE FROM emergencydevices WHERE id = ?";
			$sth = $this->database->prepare($sql);
			try {
				$sth->execute(array($account));
			} catch(\Exception $e) {
			}
		} else {
			die_freepbx("Cannot connect to Asterisk Manager with ".$this->config->get("AMPMGRUSER")."/".$this->config->get("AMPMGRPASS"));
		}

		$tech = $devinfo['tech'];
		if(isset($this->drivers[$tech])&& isset($devinfo['id'])) {
			$this->drivers[$tech]->delDevice($account);
		}
		needreload();
		return true;
	}


	/**
	 * Get all devices by type
	 * @param string $type The device type
	 */
	public function getAllDevicesByType($type="") {
		if(empty($type)) {
			if (empty($this->deviceCache['full'])) {
				$sql = "SELECT * FROM devices ORDER BY id";
				$sth = $this->database->prepare($sql);
				try {
					$sth->execute();
					$results = $sth->fetchAll(PDO::FETCH_ASSOC);
				} catch(\Exception $e) {
					return array();
				}
				$this->deviceCache['full'] = $results;
			} else {
				$results = $this->deviceCache['full'];
			}
		} else {
			if (empty($this->deviceCache[$type])) {
				$sql = "SELECT * FROM devices WHERE tech = ? ORDER BY id";
				$sth = $this->database->prepare($sql);
				try {
					$sth->execute(array($type));
					$results = $sth->fetchAll(PDO::FETCH_ASSOC);
				} catch(\Exception $e) {
					return array();
				}
				$this->deviceCache[$type] = $results;
			} else {
				$results = $this->deviceCache[$type];
			}
		}
		return $results;
	}

	/**
	 * Get all valid devices
	 * @param null
	 */
	public function getAllValidDevices() {
	
		$sql = "SELECT devices.* FROM users LEFT JOIN devices ON users.extension = devices.id WHERE devices.tech in ('pjsip','chainsip') ORDER BY devices.id";
		$sth = $this->database->prepare($sql);
		try {
			$sth->execute(array());
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}
		
		return $results;
	}

	/**
	* Get All Routes
	*/
	public function getAllRoutes(){
		$sql = "SELECT a.*, b.seq FROM `outbound_routes` a JOIN `outbound_route_sequence` b ON a.route_id = b.route_id ORDER BY `seq`";
		$stmt = $this->database->prepare($sql);
		$stmt->execute();
		$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $routes;
	}

	/**
     * Get a route
     */
	public function getRoute($id) {
		$sql = "SELECT a.*, b.seq FROM `outbound_routes` a JOIN `outbound_route_sequence` b ON a.route_id = b.route_id WHERE a.route_id = ?";
		$stmt = $this->database->prepare($sql);
		$stmt->execute(array($id));
		$route = $stmt->fetchObject();
		return $route;
	}

	/**
     * Delete a route
     */
	public function delRoute($id) {
		$sql = "DELETE FROM outbound_routes WHERE route_id = ?";
		$stmt = $this->database->prepare($sql);
		$stmt->execute(array($id));
		$sql = "DELETE FROM outbound_route_patterns WHERE route_id = ?";
		$stmt = $this->database->prepare($sql);
		$stmt->execute(array($id));
		$sql = "DELETE FROM outbound_route_trunks WHERE route_id = ?";
		$stmt = $this->database->prepare($sql);
		$stmt->execute(array($id));
		$sql = "DELETE FROM outbound_route_sequence WHERE route_id = ?";
		$stmt = $this->database->prepare($sql);
		$stmt->execute(array($id));
	}

	/**
	 * Delete trunk association from
	 */
	public function delRouteTrunk($routeId, $trunkId) {
		$sql = "DELETE FROM outbound_route_trunks WHERE route_id = ? AND trunk_id = ?";
		$stmt = $this->database->prepare($sql);
		$stmt->execute(array($routeId, $trunkId));
	}

	/**
	 * Get all Users
	 */
	public function getAllUsers() {
		if (empty($this->allUsersCache)) {
			$sql = 'SELECT extension,name,voicemail FROM users ORDER BY extension';
			$sth = $this->database->prepare($sql);
			try {
				$sth->execute();
				$results = $sth->fetchAll(PDO::FETCH_ASSOC);
				$final = array();
				foreach($results as $res) {
					$ext = $res['extension'];
					$ret = checkRange($ext);
					if($ret){
						$final[$ext] = $res;
					}
				}
				$this->allUsersCache = $final;
			} catch(\Exception $e) {
				return array();
			}
		} else {
			$final = $this->allUsersCache;
		}
		return $final;
	}

	/**
	 * Get all Users by Device Type
	 * @param string $type A specific device type to get
	 */
	public function getAllUsersByDeviceType($type="") {
		if(empty($type) || $type == "virtual") {
			$sql = "SELECT * FROM users LEFT JOIN devices ON users.extension = devices.id ORDER BY users.extension";
			$sth = $this->database->prepare($sql);
			try {
				$sth->execute();
				$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			} catch(\Exception $e) {
				return array();
			}
		} else {
			$sql = "SELECT * FROM users LEFT JOIN devices ON users.extension = devices.id WHERE devices.tech = ? ORDER BY users.extension";
			$sth = $this->database->prepare($sql);
			try {
				$sth->execute(array($type));
				$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			} catch(\Exception $e) {
				return array();
			}
		}

		$astman = $this->FreePBX->astman;
		$dbfamily = $astman->connected() ? $astman->database_show("AMPUSER") : array();

		//Virtual Extensions are strange
		$final = array();
		foreach($results as $result) {
			if(!checkRange($result['extension'])){
				continue;
			}
			if(empty($result['tech'])) {
				$result['tech'] = 'virtual';
			} elseif(!empty($result['tech']) && $type == "virtual") {
				continue;
			}
			$result['cwtone'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/cwtone']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/cwtone'] : "";
			$result['recording_in_external'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/recording/in/external']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/recording/in/external'] : "";
			$result['recording_out_external'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/recording/out/external']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/recording/out/external'] : "";
			$result['recording_in_internal'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/recording/in/internal']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/recording/in/internal'] : "";
			$result['recording_out_internal'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/recording/out/internal']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/recording/out/internal'] : "";
			$result['recording_ondemand'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/recording/ondemand']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/recording/ondemand'] : "";
			$result['recording_priority'] = isset($dbfamily['/AMPUSER/'.$result['extension'].'/recording/priority']) ? (int) $dbfamily['/AMPUSER/'.$result['extension'].'/recording/priority'] : "10";
			$result['answermode'] = $this->FreePBX->Modules->checkStatus("paging") && isset($dbfamily['/AMPUSER/'.$result['extension'].'/answermode']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/answermode'] : "";
			$result['intercom'] = $this->FreePBX->Modules->checkStatus("paging") && isset($dbfamily['/AMPUSER/'.$result['extension'].'/intercom']) ? $dbfamily['/AMPUSER/'.$result['extension'].'/intercom'] : "";

			$final[] = $result;
		}
		return $final;
	}

	/**
	 * Check if a SIP Name is in use
	 * For SIP Alias/Direct SIP dialing
	 * @param string $sipname   The SIP Name to check
	 * @param int $extension The Extension to check against
	 */
	public function checkSipnameInUse($sipname, $extension) {
		if (!isset($sipname) || freepbx_trim ($sipname)=='') {
			return true;
		}

		$sql = "SELECT sipname FROM users WHERE sipname = ? AND extension != ?";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($sipname, $extension));
		try {
			$results = $sth->fetch(PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			die_freepbx($e->getMessage().$sql);
		}

		if (isset($results['sipname']) && freepbx_trim ($results['sipname']) == $sipname) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get Inbound Routes (DIDs)
	 * @param string $order Whether to order results by extension or description
	 */
	public function getAllDIDs($order='extension') {
		switch ($order) {
			case 'description':
			$sql = "SELECT * FROM incoming ORDER BY description,extension,cidnum";
			break;
			case 'extension':
			default:
			$sql = "SELECT * FROM incoming ORDER BY extension,cidnum";
		}
		$sth = $this->database->prepare($sql);
		$sth->execute();
		try {
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}
		return $results;
	}

	public function addTrunk($name, $tech, $settings, $editmode=false) {
		$settings['tech'] = $tech;
		$name = empty($name) ? $settings['channelid'] : $name;

		// find the next available ID
		if(!$editmode) {
			$trunknum = 1;

			// This is pretty ugle, will fix when we redo trunks and routes with proper uniqueids.
			// get the list, sort them, then look for a hole and use it, or overflow to the end if
			// not and use that
			//
			$trunk_hash = array();
			foreach($this->listTrunks() as $trunk) {
				$trunknum = ltrim($trunk['trunkid'],"OUT_");
				$trunk_hash[] = $trunknum;
			}
			sort($trunk_hash);
			$trunknum = 1;
			foreach ($trunk_hash as $trunk_id) {
				if ($trunk_id != $trunknum) {
					break;
				}
				$trunknum++;
			}
		} else {
			$trunknum = $settings['trunknum'];
			unset($settings['trunknum']);
		}

		$nonull = array('failtrunk','outcid','dialoutprefix');
		foreach($nonull as $item) {
			$settings[$item] = isset($settings[$item]) && !is_null($settings[$item]) ? $settings[$item] : ""; // can't be NULL
		}

		$disable_flag = (isset($settings['disabletrunk']) && $settings['disabletrunk'] == "on")?1:0;

		switch (strtolower($tech)) {
			case "iax":
			case "iax2":
				$this->addSipOrIaxTrunk($settings['peerdetails'],'iax',$settings['channelid'],$trunknum,$disable_flag,'peer');
				if ($settings['usercontext'] != ""){
					$this->addSipOrIaxTrunk($settings['userconfig'],'iax',$settings['usercontext'],$trunknum,$disable_flag,'user');
				}
				if ($settings['register'] != ""){
					$this->addTrunkRegister($trunknum,'iax',$settings['register'],$disable_flag);
				}
			break;
			case "sip":
				$this->addSipOrIaxTrunk($settings['peerdetails'],'sip',$settings['channelid'],$trunknum,$disable_flag,'peer');
				if ($settings['usercontext'] != ""){
					$this->addSipOrIaxTrunk($settings['userconfig'],'sip',$settings['usercontext'],$trunknum,$disable_flag,'user');
				}
				if ($settings['register'] != ""){
					$this->addTrunkRegister($trunknum,'sip',$settings['register'],$disable_flag);
				}
			break;
			case "pjsip":
				$pjsip = $this->getDriver('pjsip');
				if($pjsip !== false) {
					if(!empty($_POST["imports"])){
						$settings = $this->checkPJSIPsettings($settings, $_POST);
					}

					$settings = array_merge($settings, $_POST);	

					$pjsip->addTrunk($trunknum,$settings);
				}
			break;
		}

		$sql = "REPLACE INTO `trunks`
		(`trunkid`, `name`, `tech`, `outcid`, `keepcid`, `maxchans`, `failscript`, `dialoutprefix`, `channelid`, `usercontext`, `provider`, `disabled`, `continue`)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
		$sth = $this->database->prepare($sql);
		$sth->execute(array(
			$trunknum,
			$name,
			$settings['tech'],
			$settings['outcid'],
			$settings['keepcid'] ?? 'off',
			$settings['maxchans'] ?? '',
			$settings['failtrunk'] ?? '',
			$settings['dialoutprefix'] ?? '',
			$settings['channelid'] ?? '',
			$settings['usercontext'] ?? NULL,
			$settings['provider'] ?? NULL,
			$settings['disabletrunk'] ?? 'off',
			$settings['continue'] ?? 'off'
		));

		if (isset($settings['dialopts']) && $settings['dialopts'] !== false) {
			$this->freepbx->astman->database_put("TRUNK", $trunknum . '/dialopts',$settings['dialopts']);
		} else {
			$this->freepbx->astman->database_del("TRUNK", $trunknum . '/dialopts');
		}
		return $trunknum;
	}

	public function checkPJSIPsettings($settings, $posts){
		$imports 										= $posts["imports"] ?? '';
		$settings										= [];
		$default_settings["channelid"] 					= "";
		$default_settings["dialoutprefix"] 				= "";
		$default_settings["maxchans"] 					= "";
		$default_settings["outcid"] 					= "";
		$default_settings["peerdetails"] 				= "";
		$default_settings["usercontext"] 				= "";
		$default_settings["userconfig"] 				= "";
		$default_settings["register"]					= "";
		$default_settings["keepcid"] 					= "off";
		$default_settings["failtrunk"] 					= "";
		$default_settings["disabletrunk"] 				= "off";
		$default_settings["provider"] 					= "";
		$default_settings["continue"] 					= "off";
		$default_settings["dialopts"] 					= "";
		$default_settings["tech"] 						= "pjsip";
		$default_settings["extdisplay"] 				=  "";
		$default_settings["sv_trunk_name"] 				= "";
		$default_settings["sv_usercontext"] 			= "";
		$default_settings["sv_channelid"] 				= "";
		$default_settings["npanxx"] 					= "";
		$default_settings["trunk_name"] 				= "";
		$default_settings["hcid"] 						= "on";
		$default_settings["dialoutopts_cb"] 			= "sys";
		$default_settings["failtrunk_enable"] 			= "0";
		$default_settings["prepend_digit"] 				= "";
		$default_settings["username"] 					= "";
		$default_settings["auth_username"] 				= "";
		$default_settings["secret"] 					= "";
		$default_settings["authentication"] 			= "outbound";
		$default_settings["registration"] 				= "send";
		$default_settings["language"] 					= "";
		$default_settings["sip_server"] 				=  "";
		$default_settings["sip_server_port"] 			= "";
		$default_settings["context"] 					= "from-pstn";
		$default_settings["transport"] 					= "0.0.0.0-udp";
		$default_settings["dtmfmode"] 					= "auto";
		$default_settings["auth_rejection_permanent"] 	= "off";
		$default_settings["allow_unauthenticated_options"] 	= "off";
		$default_settings["forbidden_retry_interval"] 	= "30";
		$default_settings["fatal_retry_interval"] 		= "30";
		$default_settings["retry_interval"] 			= "60";
		$default_settings["expiration"] 				= "3600";
		$default_settings["max_retries"] 				= "10000";
		$default_settings["qualify_frequency"] 			= "60";
		$default_settings["outbound_proxy"] 			= "";
		$default_settings["user_eq_phone"] 				= "no";
		$default_settings["contact_user"] 				= "";
		$default_settings["from_domain"] 				= "";
		$default_settings["from_user"] 					= "";
		$default_settings["client_uri"] 				= "";
		$default_settings["server_uri"] 				= "";
		$default_settings["media_address"] 				= "";
		$default_settings["aors"] 						= "";
		$default_settings["aor_contact"] 				= "";
		$default_settings["match"] 						= "";
		$default_settings["support_path"] 				= "no";
		$default_settings["t38_udptl"] 					= "no";
		$default_settings["t38_udptl_ec"]				= "none";
		$default_settings["t38_udptl_nat"] 				= "no";
		$default_settings["t38_udptl_maxdatagram"] 		= "";
		$default_settings["fax_detect"] 				= "no";
		$default_settings["trust_rpid"] 				= "no";
		$default_settings["sendrpid"] 					= "no";
		$default_settings["trust_id_outbound"] 			= "no";
		$default_settings["identify_by"] 				= "default";
		$default_settings["inband_progress"] 			= "no";
		$default_settings["direct_media"] 				= "no";
		$default_settings["rewrite_contact"] 			= "no";
		$default_settings["rtp_symmetric"] 				= "yes";
		$default_settings["media_encryption"] 			= "no";
		$default_settings["force_rport"] 				= "yes";
		$default_settings["message_context"] 			= "";

		foreach($default_settings as $key => $value){
			if(empty($settings[$key])){
				$settings[$key] = $value;
			}

			if(!empty($imports[$key])){
				$settings[$key] = $imports[$key];

				/**
				 * If $codec doesn't exist, It will take the default values with PJSIP driver.
				 * Else, we parse them.
				 */
				if($key == "codec" && !empty($value) && is_string($value) ){
					$codecs = explode(",",$value);
					$i = 0;
					foreach($codecs as $c){
						$i++;
						$codec[$c] = $i;
					}
					$settings[$key] = $codec;
				}
			}
		}

		return $settings;
	}

	public function addSipOrIaxTrunk($config,$table,$channelid,$trunknum,$disable_flag=0,$type='peer') {
		switch ($type) {
			case 'peer':
			$trunknum = 'tr-peer-'.$trunknum;
			break;
			case 'user':
			$trunknum = 'tr-user-'.$trunknum;
			break;
		}

		$confitem['account'] = $channelid;
		$gimmieabreak = nl2br($config);
		$lines = preg_split('#<br />#',$gimmieabreak);
		foreach ($lines as $line) {
			$line = freepbx_trim ($line);
			if (count(preg_split('/=/',$line)) > 1) {
				$tmp = preg_split('/=/',$line,2);
				$key=freepbx_trim ($tmp[0]);
				$value=freepbx_trim ($tmp[1]);
				if (isset($confitem[$key]) && !empty($confitem[$key]))
				$confitem[$key].="&".$value;
				else
				$confitem[$key]=$value;
			}
		}
		$sth = $this->database->prepare("REPLACE INTO $table (id, keyword, data, flags) values ('$trunknum',?,?,?)");
		// rember 1=disabled so we start at 2 (1 + the first 1)
		$seq = 1;
		foreach($confitem as $k=>$v) {
			$seq = ($disable_flag == 1) ? 1 : $seq+1;
			$sth->execute(array($k,$v,$seq));
		}

	}

	public function addTrunkRegister($trunknum,$tech,$reg,$disable_flag=0) {
		$sql = "INSERT INTO $tech (id, keyword, data, flags) values (:id,'register',:data,:flags)";
		$sth = $this->database->prepare($sql);
		$sth->execute(array(
			':id' => 'tr-reg-'.$trunknum,
			':data' => $reg,
			':flags' => $disable_flag
		));
	}

	/* Fill non-mandatory fields with default if not present */
	public function addDIDDefaults(&$settings) {
		$settings['extension'] = isset($settings['extension'])?$settings['extension']:'';
		$settings['cidnum'] = isset($settings['cidnum'])?$settings['cidnum']:'';
		$settings['description'] = isset($settings['description'])?$settings['description']:'';
		$settings['fanswer'] = isset($settings['fanswer'])?$settings['fanswer']:'';
		$settings['delay_answer'] = isset($settings['delay_answer'])&&$settings['delay_answer']?$settings['delay_answer']:'0';
		$settings['rvolume'] = isset($settings['rvolume']) && $settings['rvolume'] != '' ? $settings['rvolume'] : '0';
		$settings['privacyman'] = isset($settings['privacyman'])?$settings['privacyman']:'0';
		$settings['pmmaxretries'] = isset($settings['pmmaxretries']) && $settings['pmmaxretries'] != '' ?$settings['pmmaxretries']:'3';
		$settings['pmminlength'] = isset($settings['pmminlength']) && $settings['pmminlength'] != '' ?$settings['pmminlength']:'10';
		$settings['alertinfo'] = isset($settings['alertinfo'])?$settings['alertinfo']:'';
		$settings['ringing'] = isset($settings['ringing'])?$settings['ringing']:'';
		$settings['reversal'] = isset($settings['reversal'])?$settings['reversal']:'';
		$settings['mohclass'] = isset($settings['mohclass'])?$settings['mohclass']:'default';
		$settings['grppre'] = isset($settings['grppre'])?$settings['grppre']:'';
		$settings['pricid'] = isset($settings['pricid'])?$settings['pricid']:'';
		$settings['rnavsort'] = isset($settings['rnavsort'])?$settings['rnavsort']:'description';
		$settings['didfilter'] = isset($settings['didfilter'])?$settings['didfilter']:'';
		$settings['indication_zone'] = isset($settings['indication_zone'])?$settings['indication_zone']:'default';
	}

	/**
	 * Add Inbound Route
	 * @param array $settings Array of Inbound Route Settings
	 */
	public function addDID($settings) {
		//Strip <> just to be on the safe side otherwise this is not deleteable from the GUI
		$invalidDIDChars = array('<', '>');
		$settings['extension'] = freepbx_trim (str_replace($invalidDIDChars, "", $settings['extension']));
		$settings['cidnum'] = freepbx_trim (str_replace($invalidDIDChars, "", $settings['cidnum']));

		// Check to make sure the did is not being used elsewhere
		//
		$existing = $this->getDID($settings['extension'], $settings['cidnum']);
		if (empty($existing)) {
			$this->addDIDDefaults($settings);
			$sql="INSERT INTO incoming (rvolume, cidnum, extension, destination, privacyman, pmmaxretries, pmminlength, alertinfo, ringing,fanswer, reversal, mohclass, `description`, grppre, delay_answer, pricid, indication_zone) VALUES (:rvolume, :cidnum, :extension, :destination, :privacyman, :pmmaxretries, :pmminlength, :alertinfo, :ringing,:fanswer, :reversal, :mohclass, :description, :grppre, :delay_answer, :pricid, :indication_zone)";
			$sth = $this->database->prepare($sql);
			$params = array(
				':rvolume' => (isset($settings['rvolume']) && $settings['rvolume']) ? $settings['rvolume'] :  "",
				':cidnum' => (isset($settings['cidnum']) && $settings['cidnum']) ? $settings['cidnum'] :  "",
				':extension' => (isset($settings['extension']) && $settings['extension']) ? $settings['extension'] :  "",
				':destination' => (isset($settings['destination']) && $settings['destination']) ? $settings['destination'] :   "",
				':privacyman' => (isset($settings['privacyman']) && !empty($settings['privacyman'])) ? $settings['privacyman'] : "0",
				':pmmaxretries' => (isset($settings['pmmaxretries']) && $settings['pmmaxretries']) ? $settings['pmmaxretries'] :  "",
				':pmminlength' => (isset($settings['pmminlength']) && $settings['pmminlength']) ? $settings['pmminlength'] :  "",
				':alertinfo' => (isset($settings['alertinfo']) && $settings['alertinfo']) ? $settings['alertinfo'] :  "",
				':ringing' => (isset($settings['ringing']) && $settings['ringing']) ? $settings['ringing'] :  "",
				':fanswer' => (isset($settings['fanswer']) && $settings['fanswer']) ? $settings['fanswer'] :  "",
				':reversal' => (isset($settings['reversal']) && $settings['reversal']) ? $settings['reversal'] :  "",
				':mohclass' => (isset($settings['mohclass']) && $settings['mohclass']) ? $settings['mohclass'] :  "",
				':description' => (isset($settings['description']) && $settings['description']) ? $settings['description'] :  "",
				':grppre' => (isset($settings['grppre']) && $settings['grppre']) ? $settings['grppre'] :  "",
				':delay_answer' => (isset($settings['delay_answer']) && $settings['delay_answer']) ? $settings['delay_answer'] : "0",
				':pricid' => (isset($settings['pricid']) && $settings['pricid']) ? $settings['pricid'] :  "",
				':indication_zone' => (isset($settings['indication_zone']) && $settings['indication_zone']) ? $settings['indication_zone'] :  ""
			);
			$sth->execute($params);
			$this->freepbx->Hooks->processHooks($settings);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Delete a DID
	 * @param  string $extension The DID Extension
	 * @param  string $cidnum    The DID cidnum
	 */
	public function delDID($extension,$cidnum) {
		$sql = "DELETE FROM incoming WHERE cidnum = ? AND extension = ?";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($cidnum, $extension));
		$this->freepbx->Hooks->processHooks($extension, $cidnum);
	}

	/**
	 * Get Inbound Route (DID) based on extension (DID) or CID Number
	 * @param int $extension Inbound Route DID
	 * @param int $cidnum    The CID Number
	 */
	public function getDID($extension="",$cidnum="") {
		$sql = "SELECT * FROM incoming WHERE cidnum = ? AND extension = ?";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($cidnum, $extension));
		try {
			$results = $sth->fetch(PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}
		return $results;
	}

	/**
	 * Edit DID
	 * @param  string $oldExtension The old extension
	 * @param  string $oldCidnum    The old CID Num
	 * @param  array $incoming     Array of new data to use
	 */
	public function editDID($oldExtension,$oldCidnum, $incoming) {
		$extension = freepbx_trim ($incoming['extension']);
		$cidnum = freepbx_trim ($incoming['cidnum']);

		// if did or cid changed, then check to make sure that this pair is not already being used.
		//
		if (($extension != $oldExtension) || ($cidnum != $oldCidnum)) {
			$existing = $this->getDID($extension,$cidnum);
		}

		if (empty($existing)) {
			$this->delDID($oldExtension,$oldCidnum);
			$this->addDID($incoming);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Create a new did with values passed into $did_vars and defaults used otherwise
	 * @param  array $did_vars Array of new values
	 */
	public function createUpdateDID($did_vars) {
		$did_create['extension'] = isset($did_vars['extension']) ? $did_vars['extension'] : '';
		$did_create['cidnum']    = isset($did_vars['cidnum']) ? $did_vars['cidnum'] : '';
		$coredid = $this->getDID($did_create['extension'], $did_create['cidnum']);
		if (!empty($coredid) && count($coredid)) {
			return $this->editDIDProperties($did_vars); //already exists so just edit properties
		} else {
			$did_create['privacyman']  = isset($did_vars['privacyman'])  ? $did_vars['privacyman']  : '0';
			$did_create['pmmaxretries']  = isset($did_vars['pmmaxretries']) && $did_vars['pmmaxretries'] != '' ? $did_vars['pmmaxretries']  : '3';
			$did_create['pmminlength']  = isset($did_vars['pmminlength']) && $did_vars['pmminlength'] != ''  ? $did_vars['pmminlength']  : '10';
			$did_create['alertinfo']   = isset($did_vars['alertinfo'])   ? $did_vars['alertinfo']   : '';
			$did_create['ringing']     = isset($did_vars['ringing'])     ? $did_vars['ringing']     : '';
			$did_create['fanswer']     = isset($did_vars['fanswer'])     ? $did_vars['fanswer']     : '';
			$did_create['reversal']     = isset($did_vars['reversal'])     ? $did_vars['reversal']     : '';
			$did_create['mohclass']    = isset($did_vars['mohclass'])    ? $did_vars['mohclass']    : 'default';
			$did_create['description'] = isset($did_vars['description']) ? $did_vars['description'] : '';
			$did_create['grppre']      = isset($did_vars['grppre'])      ? $did_vars['grppre']      : '';
			$did_create['delay_answer']= isset($did_vars['delay_answer'])? $did_vars['delay_answer']: '0';
			$did_create['pricid']      = isset($did_vars['pricid'])      ? $did_vars['pricid']      : '';

			$did_create['destination'] = isset($did_vars['destination']) ? $did_vars['destination'] : '';
			return $this->addDID($did_create);
		}
	}

	/**
	 * Edits the poperties of a did
	 * but not the did or cid nums since those could of course be in conflict
	 * @param  array $did_vars Array of variables
	 * @return [type]           [description]
	 */
	public function editDIDProperties($did_vars) {
		if (!is_array($did_vars)) {
			return false;
		}

		$extension = isset($did_vars['extension']) ? $did_vars['extension'] : '';
		$cidnum    = isset($did_vars['cidnum']) ? $did_vars['cidnum'] : '';
		$sql = "";
		foreach ($did_vars as $key => $value) {
			switch ($key) {
				case 'privacyman':
				case 'pmmaxretries':
				case 'pmminlength':
				case 'alertinfo':
				case 'ringing':
				case 'fanswer':
				case 'reversal':
				case 'mohclass':
				case 'description':
				case 'grppre':
				case 'delay_answer':
				case 'pricid':
				case 'destination':
					$sql_value = $this->database->escapeSimple($value);
					$sql .= " `$key` = $sql_value,";
				break;
				default:
			}
		}
		if ($sql == '') {
			return false;
		}
		$sql = substr($sql,0,(strlen($sql)-1)); //strip off tailing ','
		$sql_update = "UPDATE `incoming` SET"."$sql WHERE `extension` = ? AND `cidnum` = ?";
		$sth = $this->database->prepare($sql_update);
		return $sth->execute(array($extension, $cidnum));
	}

	/**
	 * Add user (Part of Users/Devices)
	 * @param int $extension The exten numer
	 * @param array $settings  Array of settings to pass in
	 * @param bool $editmode  If in edit mode (So that the AsteriskDB is not destroyed)
	 */
	public function addUser($extension, $settings, $editmode=false) {
		if (freepbx_trim ($extension) == '' ) {
			throw new \Exception(_("You must put in an extension (or user) number"));
		}
		//ensure this id is not already in use
		$extens = $this->listUsers();
		if(is_array($extens)) {
			foreach($extens as $exten) {
				if ($exten[0]===$extension) {
					throw new \Exception(sprintf(_("This user/extension %s is already in use"),$extension));
				}
			}
		}

		$settings['newdid_name'] = isset($settings['newdid_name']) ? $settings['newdid_name'] : '';
		$settings['newdid'] = isset($settings['newdid']) ? preg_replace("/[^0-9._XxNnZz\[\]\-\+]/" ,"", freepbx_trim ($settings['newdid'])) : '';
		$settings['newdidcid'] = isset($settings['newdidcid']) ? freepbx_trim ($settings['newdidcid']) : '';

		if (!preg_match('/^priv|^block|^unknown|^restrict|^unavail|^anonym|^withheld/',strtolower($settings['newdidcid']))) {
			$settings['newdidcid'] = preg_replace("/[^0-9._XxNnZz\[\]\-\+]/" ,"", $settings['newdidcid']);
		}

		if ($settings['newdid'] != '' || $settings['newdidcid'] != '') {
			$existing = $this->getDID($settings['newdid'], $settings['newdidcid']);
			if (!empty($existing)) {
				throw new \Exception(sprintf(_("A route with this DID/CID: %s/%s already exists"),$existing['extension'],$existing['cidnum']));
			}
		}

		$settings['sipname'] = isset($settings['sipname']) ? preg_replace("/\s/" ,"", freepbx_trim ($settings['sipname'])) : '';
		if (!$this->checkSipnameInUse($settings['sipname'], $extension)) {
			throw new \Exception(_("This sipname: {$sipname} is already in use"));
		}

		// strip the ugly return of the gui radio funciton which comes back as "recording_out_internal=always" for example
		// TODO this should be done with a hook
		if (isset($settings['recording_in_external'])) {
			$rec_tmp = explode('=',$settings['recording_in_external'],2);
			$settings['recording_in_external'] = count($rec_tmp) == 2 ? $rec_tmp[1] : $rec_tmp[0];
		} else {
			$settings['recording_in_external'] = 'dontcare';
		}
		if (isset($settings['recording_out_external'])) {
			$rec_tmp = explode('=',$settings['recording_out_external'],2);
			$settings['recording_out_external'] = count($rec_tmp) == 2 ? $rec_tmp[1] : $rec_tmp[0];
		} else {
			$settings['recording_out_external'] = 'dontcare';
		}
		if (isset($settings['recording_in_internal'])) {
			$rec_tmp = explode('=',$settings['recording_in_internal'],2);
			$settings['recording_in_internal'] = count($rec_tmp) == 2 ? $rec_tmp[1] : $rec_tmp[0];
		} else {
			$settings['recording_in_internal'] = 'dontcare';
		}
		if (isset($settings['recording_out_internal'])) {
			$rec_tmp = explode('=',$settings['recording_out_internal'],2);
			$settings['recording_out_internal'] = count($rec_tmp) == 2 ? $rec_tmp[1] : $rec_tmp[0];
		} else {
			$settings['recording_out_internal'] = 'dontcare';
		}
		if (isset($settings['recording_ondemand'])) {
			$rec_tmp = explode('=',$settings['recording_ondemand'],2);
			$settings['recording_ondemand'] = count($rec_tmp) == 2 ? $rec_tmp[1] : $rec_tmp[0];
		} else {
			$settings['recording_ondemand'] = 'disabled';
		}

		//if voicemail is enabled, set the box@context to use
		//TODO use a hook here
		if ( $this->FreePBX->Modules->moduleHasMethod('Voicemail','getMailbox') ) {
			$vmbox = $this->FreePBX->Voicemail->getMailbox($extension);
			if ( $vmbox == null ) {
				$settings['voicemail'] = "novm";
			} else {
				$settings['voicemail'] = $vmbox['vmcontext'];
			}
		}

		$sql = "INSERT INTO users (extension,password,name,voicemail,ringtimer,noanswer,recording,outboundcid,sipname,noanswer_cid,busy_cid,chanunavail_cid,noanswer_dest,busy_dest,chanunavail_dest) " .
						"VALUES (:extension, :password, :name, :voicemail, :ringtimer, :noanswer, :recording, :outboundcid, :sipname, :noanswer_cid, :busy_cid, :chanunavail_cid, :noanswer_dest, :busy_dest, :chanunavail_dest)";
		$sth = $this->database->prepare($sql);
		try {
			$sth->execute(array(
				"extension" => $extension,
				"password" => isset($settings['password']) ? $settings['password'] : '',
				"name" => isset($settings['name']) ? preg_replace(array('/</','/>/'), array('(',')'), freepbx_trim ($settings['name'])) : '',
				"voicemail" => isset($settings['voicemail']) ? $settings['voicemail'] : 'default',
				"ringtimer" => isset($settings['ringtimer']) ? $settings['ringtimer'] : '',
				"noanswer" => isset($settings['noanswer']) ? $settings['noanswer'] : '',
				"recording" => isset($settings['recording']) ? $settings['recording'] : '',
				"outboundcid" => isset($settings['outboundcid']) ? $settings['outboundcid'] : '',
				"sipname" => isset($settings['sipname']) ? $settings['sipname'] : '',
				"noanswer_cid" => isset($settings['noanswer_cid']) ? $settings['noanswer_cid'] : "",
				"busy_cid" => isset($settings['busy_cid']) ? $settings['busy_cid'] : "",
				"chanunavail_cid" => isset($settings['chanunavail_cid']) ? $settings['chanunavail_cid'] : "",
				"noanswer_dest" => !empty($settings['noanswer_dest'])? $settings['noanswer_dest']: "",
				"busy_dest" => !empty($settings['busy_dest']) ? $settings['busy_dest'] : "",
				"chanunavail_dest" => !empty($settings['chanunavail_dest']) ? $settings['chanunavail_dest'] : ""
			));
		} catch(\Exception $e) {
			throw new \Exception("Unable to insert into users: ".addSlashes($e->getMessage()));
		}

		//write to astdb
		$astman = $this->FreePBX->astman;
		$fpc = $this->FreePBX->Config();
		if ($astman->connected()) {
			$astman->database_put("AMPUSER",$extension."/cwtone",isset($settings['cwtone']) ? $settings['cwtone'] : '');
			if( !empty($settings["devinfo_accountcode"]) ){
				$astman->database_put("AMPUSER",$extension."/accountcode",$settings["devinfo_accountcode"]);
			} else {
				$astman->database_put("AMPUSER",$extension."/accountcode",!empty($settings["accountcode"]) ? $settings["accountcode"] : '');
			}
			$astman->database_put("AMPUSER",$extension."/rvolume",isset($settings['rvolume']) ? $settings['rvolume'] : '');
			$astman->database_put("AMPUSER",$extension."/password",isset($settings['password']) ? $settings['password'] : '');
			$astman->database_put("AMPUSER",$extension."/ringtimer",isset($settings['ringtimer']) ? $settings['ringtimer'] : $fpc->get('RINGTIMER'));
			$astman->database_put("AMPUSER",$extension."/cfringtimer",isset($settings['cfringtimer']) ? $settings['cfringtimer'] : $fpc->get('CFRINGTIMERDEFAULT'));
			$astman->database_put("AMPUSER",$extension."/concurrency_limit",isset($settings['concurrency_limit']) && (string)$settings['concurrency_limit'] != "" ? $settings['concurrency_limit'] : $fpc->get('CONCURRENCYLIMITDEFAULT'));
			$astman->database_put("AMPUSER",$extension."/noanswer",isset($settings['noanswer']) ? $settings['noanswer'] : '');
			$astman->database_put("AMPUSER",$extension."/recording",isset($settings['recording']) ? $settings['recording'] : '');
			$astman->database_put("AMPUSER",$extension."/outboundcid",isset($settings['outboundcid']) ? $settings['outboundcid'] : '');
			$astman->database_put("AMPUSER",$extension."/cidname",isset($settings['name']) ? $settings['name'] : '');
			$astman->database_put("AMPUSER",$extension."/cidnum",(isset($settings['cid_masquerade']) && freepbx_trim ($settings['cid_masquerade']) != "") ? freepbx_trim ($settings['cid_masquerade']) : $extension);
			$astman->database_put("AMPUSER",$extension."/voicemail",isset($settings['voicemail']) ? $settings['voicemail'] : '');
			//TODO need to be in paging soon
			$astman->database_put("AMPUSER",$extension."/answermode",isset($settings['answermode']) ? $settings['answermode']: 'disabled');
			$astman->database_put("AMPUSER",$extension."/intercom",isset($settings['intercom']) ? $settings['intercom']: 'enabled');
			$astman->database_put("AMPUSER",$extension."/cwtone",isset($settings['cwtone']) ? $settings['cwtone']: 'disabled');
			$astman->database_put("AMPUSER",$extension."/recording/in/external",$settings['recording_in_external']);
			$astman->database_put("AMPUSER",$extension."/recording/out/external",$settings['recording_out_external']);
			$astman->database_put("AMPUSER",$extension."/recording/in/internal",$settings['recording_in_internal']);
			$astman->database_put("AMPUSER",$extension."/recording/out/internal",$settings['recording_out_internal']);
			$astman->database_put("AMPUSER",$extension."/recording/ondemand",$settings['recording_ondemand']);
			$astman->database_put("AMPUSER",$extension."/recording/priority",isset($settings['recording_priority']) ? $settings['recording_priority'] : '10');

			// If not set then we are using system default so delete the tree all-together
			//
			if (isset($settings['dialopts'])) {
				$astman->database_put("AMPUSER",$extension."/dialopts", $settings['dialopts']);
			} else {
				$astman->database_del("AMPUSER",$extension."/dialopts");
			}

			$call_screen = isset($settings['call_screen']) ? $settings['call_screen'] : '0';
			switch ($call_screen) {
				case '0':
					$astman->database_del("AMPUSER",$extension."/screen");
				break;
				case 'nomemory':
					$astman->database_put("AMPUSER",$extension."/screen",'nomemory');
				break;
				case 'memory':
					$astman->database_put("AMPUSER",$extension."/screen",'memory');
				break;
				default:
				break;
			}

			if (!$editmode) {
				$astman->database_put("AMPUSER",$extension."/device",isset($settings['device']) ? $settings['device'] : $extension);
			}

			if (freepbx_trim ($settings['callwaiting']) == 'enabled') {
				$astman->database_put("CW",$extension,"ENABLED");
			} else if (freepbx_trim ($settings['callwaiting']) == 'disabled') {
				$astman->database_del("CW",$extension);
			}

			if (freepbx_trim ($settings['pinless']) == 'enabled') {
				$astman->database_put("AMPUSER",$extension."/pinless","NOPASSWD");
			} else if (freepbx_trim ($settings['pinless']) == 'disabled') {
				$astman->database_del("AMPUSER",$extension."/pinless");
			}
		} else {
			die_freepbx("Cannot connect to Asterisk Manager with ".$this->FreePBX->Config->get("AMPMGRUSER")."/".$this->FreePBX->Config->get("AMPMGRPASS"));
		}

		// OK - got this far, if they entered a new inbound DID/CID let's deal with it now
		// remember - in the nice and ugly world of this old code, $vars has been extracted
		// newdid and newdidcid

		// Now if $newdid is set we need to add the DID to the routes
		if ($settings['newdid'] != '' || $settings['newdidcid'] != '') {
			$did_dest                = 'from-did-direct,'.$extension.',1';
			$did_vars = array();
			$did_vars['extension']   = $settings['newdid'];
			$did_vars['cidnum']      = $settings['newdidcid'];
			$did_vars['privacyman']  = '';
			$did_vars['alertinfo']   = '';
			$did_vars['ringing']     = '';
			$did_vars['fanswer']     = '';
			$did_vars['reversal']     = '';
			$did_vars['mohclass']    = 'default';
			$did_vars['description'] = $settings['newdid_name'];
			$did_vars['grppre']      = '';
			$did_vars['delay_answer']= '0';
			$did_vars['pricid']= '';
			core_did_add($did_vars, $did_dest);
		}

		$this->getUserCache = array();
		$this->listUsersCache = array();
		$this->freepbx->Hooks->processHooks($extension, $settings, $editmode);
		return true;
	}

	/**
	 * Delete a User
	 * @param int $extension The user extension
	 * @param {bool} $editmode=false If in edit mode (this is so it doesnt destroy the AsteriskDB)
	 */
	public function delUser($extension, $editmode=false) {
		global $db;
		global $amp_conf;
		global $astman;

		//delete from devices table
		$sql = "DELETE FROM users WHERE extension = ?";
		$sth = $this->database->prepare($sql);
		$sth->execute(array($extension));

		//delete details to astdb
		$astman = $this->FreePBX->astman;
		if($astman->connected())  {
			$astman->database_del("AMPUSER",$extension."/screen");
		}
		if ($astman->connected() && !$editmode) {
			// TODO just change this to delete everything
			$astman->database_deltree("AMPUSER/".$extension);
			$astman->database_deltree("CustomDevstate/FOLLOWME".$extension);
			$astman->database_deltree("DEVICE/".$extension);
			$astman->database_deltree("ZULU/".$extension);
		}

		$astman->database_del("CW",$extension);

		//TODO: Should only delete it's reference
		//but the array is multidimensional and sucks
		//OUT > Array
		//(
		//    [0] => Array
		//        (
		//            [extension] => 1005
		//            [0] => 1005
		//            [name] => WTF
		//            [1] => WTF
		//            [voicemail] => novm
		//            [2] => novm
		//        )
		//
		//)

		$this->getUserCache = array();
		$this->listUsersCache = array();
		$this->freepbx->Hooks->processHooks($extension, $editmode);

		return true;
	}

	/**
	 * Get User Details
	 * @param int $extension The user number (extension)
	 */
	public function getUser($extension) {
		if (!empty($this->getUserCache[$extension])) {
			return $this->getUserCache[$extension];
		}
		$sql = "SELECT * FROM users WHERE extension = ?";
		$sth = $this->database->prepare($sql);
		try {
			$sth->execute(array($extension));
			$results = $sth->fetch(PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}

		if(empty($results)) {
			return array();
		}

		$astman = $this->FreePBX->astman;
		if ($astman->connected()) {

			if ($this->FreePBX->Modules->checkStatus("paging")) {
				$answermode=$astman->database_get("AMPUSER",$extension."/answermode");
				$results['answermode'] = (freepbx_trim ($answermode) == '') ? $this->freepbx->Config->get("DEFAULT_INTERNAL_AUTO_ANSWER") : $answermode;
				$astman->database_put("AMPUSER",$extension."/answermode",$results['answermode']); //incase it was updated from above

				$intercom=$astman->database_get("AMPUSER",$extension."/intercom");
				$results['intercom'] = (freepbx_trim ($intercom) == '') ? 'enabled' : $intercom;
			}

			$cw = $astman->database_get("CW",$extension);
			$results['callwaiting'] = (freepbx_trim ($cw) == 'ENABLED') ? 'enabled' : 'disabled';
			$cid_masquerade=$astman->database_get("AMPUSER",$extension."/cidnum");
			$results['cid_masquerade'] = (freepbx_trim ($cid_masquerade) != "")?$cid_masquerade:$extension;

			$call_screen=$astman->database_get("AMPUSER",$extension."/screen");
			$results['call_screen'] = (freepbx_trim ($call_screen) != "")?$call_screen:'0';

			$pinless=$astman->database_get("AMPUSER",$extension."/pinless");
			$results['pinless'] = (freepbx_trim ($pinless) == 'NOPASSWD') ? 'enabled' : 'disabled';

			$results['ringtimer'] = (int) $astman->database_get("AMPUSER",$extension."/ringtimer");

			$results['cfringtimer'] = (int) $astman->database_get("AMPUSER",$extension."/cfringtimer");
			$results['concurrency_limit'] = (int) $astman->database_get("AMPUSER",$extension."/concurrency_limit");

			$results['dialopts'] = $astman->database_get("AMPUSER",$extension."/dialopts");

			$results['cwtone'] = $astman->database_get("AMPUSER",$extension."/cwtone");
			$results['accountcode'] = $astman->database_get("AMPUSER",$extension."/accountcode");
			
			$results['recording_in_external'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/in/external"));
			$results['recording_out_external'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/out/external"));
			$results['recording_in_internal'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/in/internal"));
			$results['recording_out_internal'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/out/internal"));
			$results['recording_ondemand'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/ondemand"));
			$results['recording_priority'] = (int) $astman->database_get("AMPUSER",$extension."/recording/priority");
			$results['rvolume'] = strtolower($astman->database_get("AMPUSER",$extension."/rvolume"));

		} else {
			throw new \Exception("Cannot connect to Asterisk Manager with using user[".$this->FreePBX->Config->get("AMPMGRUSER")."]");
		}
		$this->getUserCache[$extension] = $results;
		return $results;
	}

	/**
	 * Get Device Details
	 * @param {int} $account The Device ID
	 */
	public function getDevice($account) {
		if (!empty($this->getDeviceCache[$account])) {
			return $this->getDeviceCache[$account];
		}
		$sql = "SELECT * FROM devices WHERE id = ?";
		$sth = $this->database->prepare($sql);
		try {
			$sth->execute(array($account));
			$device = $sth->fetch(PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}

		if (empty($device)) {
			return array();
		}

		$t = $device['tech'];
		$tech = array();
		if(isset($this->drivers[$t])) {
			$tech = $this->drivers[$t]->getDevice($account);
		}

		$results = array_merge($device,$tech);

		$this->getDeviceCache[$account] = $results;
		return $this->getDeviceCache[$account];
	}

	/*
	* Get emergencyDevice
	*/
	public function getEmergencyDevice($account) {
		$sql = "SELECT * FROM emergencydevices WHERE id = ?";
		$sth = $this->database->prepare($sql);
		try {
			$sth->execute(array($account));
			$device = $sth->fetch(\PDO::FETCH_ASSOC);
		} catch(\Exception $e) {
			return array();
		}

		if (empty($device)) {
			return array();
		}

		$t = $device['tech'];
		$tech = array();
		if(isset($this->drivers[$t])) {
			$tech = $this->drivers[$t]->getDevice($account);
		}

		$results = array_merge($device,$tech);

		return $results;
	}

	/**
	 * Hook Tabs for hooking
	 * @param  [type] $page [description]
	 * @return [type]       [description]
	 */
	public function hookTabs($page){
		$module_hook = \moduleHook::create();
		$mods = $this->freepbx->Hooks->processHooks($page);
		$sections = array();
		foreach($mods as $mod => $contents) {
			if(empty($contents)) {
				continue;
			}

			if(is_array($contents)) {
				foreach($contents as $content) {
					if(!isset($sections[$content['rawname']])) {
						$sections[$content['rawname']] = array(
							"title" => $content['title'],
							"rawname" => $content['rawname'],
							"content" => $content['content']
						);
					} else {
						$sections[$content['rawname']]['content'] .= $content['content'];
					}
				}
			} else {
				if(!isset($sections[$mod])) {
					$sections[$mod] = array(
						"title" => ucfirst(strtolower($mod)),
						"rawname" => $mod,
						"content" => $contents
					);
				} else {
					$sections[$mod]['content'] .= $contents;
				}
			}
		}
		$hookTabs = $hookcontent = '';
		foreach ($sections as $data) {
			$hookTabs .= '<li role="presentation"><a href="#corehook'.$data['rawname'].'" aria-controls="corehook'.$data['rawname'].'" role="tab" data-toggle="tab">'.$data['title'].'</a></li>';
			$hookcontent .= '<div role="tabpanel" class="tab-pane" id="corehook'.$data['rawname'].'">';
			$hookcontent .=	 $data['content'];
			$hookcontent .= '</div>';
		}
		return array("hookTabs" => $hookTabs, "hookContent" => $hookcontent, "oldHooks" => $module_hook->hookHtml);
	}

	public function bulkhandlerGetTypes() {	
		return array(
			'extensions' => array(
				'name' => _('Extensions'),
				'description' => _('Extensions')
			),
			'dids' => array(
				'name' => _('DIDs'),
				'description' => _('DIDs / Inbound Routes')
			),
			'trunks' => array(
				'name' => _('Trunks'),
				'description' => _('Use Bulk Handler to import or export PJSIP trunk configuration.')
			),			
		);
	}

	/**
	 * Bulk Handler Get headers
	 * @param  string $type The type of bulk handler
	 * @return array       Array of headers
	 */
	public function bulkhandlerGetHeaders($type) {
		switch ($type) {
			case 'extensions':
				$headers = array(
					'extension' => array(
						'required' => true,
						'identifier' => _('Extension'),
						'description' => _('Extension'),
					),
					'name' => array(
						'required' => true,
						'identifier' => _('Name'),
						'description' => _('Name'),
					),
					'description' => array(
						'identifier' => _('Description'),
						'description' => _('Description'),
					),
					'tech' => array(
						'identifier' => _('Device Technology'),
						'description' => _('Device Technology'),
					),
				);

				foreach($this->drivers as $driver) {
					if (method_exists($driver, 'getDeviceHeaders')) {
						$driverheaders = $driver->getDeviceHeaders();
						if ($driverheaders) {
							$headers = array_merge($headers, $driverheaders);
						}
					}
				}
				return $headers;

				break;
			case 'dids':
				$headers = array(
					'description' => array(
						'identifier' => _('Description'),
						'description' => _('Description'),
					),
					'extension' => array(
						'identifier' => _('Incoming DID'),
						'description' => _('Incoming DID')
					),
					'cidnum' => array(
						'identifier' => _('Caller ID'),
						'description' => _('Caller ID Number')
					),
					'destination' => array(
						'identifier' => _('Destination'),
						'description' => _('The context, extension, priority to go to when this DID is matched. Example: app-daynight,0,1'),
						'type' => 'destination',
					),
				);

				return $headers;

				break;
			case "trunks":	
				$headers = [
					'trunk_name' => [
						'identifier' => _('trunk name'),
						'description' => _('trunk name'),
						],
					'sip_server' => [
						'identifier' => _('sip server'),
						'description' => _('sip server'),
						],
					];	
				return $headers;				
				break;
			}
	}

	/**
	 * Used to Validate bulk handler items
	 * @param  string $type    The bulk handler item
	 * @param  array $rawData         Array of data to import
	 * @return array                  Message containing status
	 */
	public function bulkhandlerValidate($type, $rawData) {
		$techType = array('pjsip', 'sip', 'virtual', 'iax2', 'dahdi', 'custom');
		switch ($type) {
			case 'extensions':
				foreach ($rawData as $data) {
					if (empty($data['extension'])) {
						return array("status" => false, "message" => _("Extension is missing."));
					}
					if (!is_numeric($data['extension'])) {
						return array("status" => false, "message" => _("Extension is not numeric."));
					}
					if(empty($data['name'])){
						return array("status" => false, "message" => _("Extension name is blank."));
					}
					if(!empty($data['tech']) && !in_array($data['tech'], $techType)) {
						return array("status" => false, "message" => _("Please provide valid device technology"));
					}
				}
				return array("status" => true);

			case "trunks":
				foreach ($rawData as $data) {
					if (empty($data['trunk_name'])) {
						return array("status" => false, "message" => _("trunk_name is missing."));
					}
					if (empty($data['sip_server'])) {
						return array("status" => false, "message" => _("sip_server is missing."));
					}
				}
				return array("status" => true);
		}
	}

	/**
	 * Bulk Handler import
	 * @param  string $type           The type of import
	 * @param  array $rawData         Array of data to import
	 * @param  boolean $replaceExisting Should we replace existing data?
	 * @return array                  Message containing status
	 */
	public function bulkhandlerImport($type, $rawData, $replaceExisting = true) {
		$ret = NULL;

		switch ($type) {
			case 'extensions':
				$defaulttech = $this->FreePBX->Sipsettings->getSipPortOwner();
				foreach ($rawData as $data) {
					$data = array_change_key_case($data, CASE_LOWER);
					if(empty($data['tech'])) {
						if ($defaulttech == "none") {
							$data['tech'] = 'sip';
						} else {
							$data['tech'] = $defaulttech;
						}
					}
					$settings = $this->generateDefaultDeviceSettings($data['tech'], $data['extension'], $data['name']);
					foreach ($settings as $key => $value) {
						$data_tech = strtoupper($data['tech']);
						if (isset($data[$key])) {
							/* Override default setting with our value. */
							if($key == "secret" && $data[$key] == "REGEN"){
								continue;
							}
							if(isset($data['user']) && $data_tech == "VIRTUAL" && $data['user'] == ""){
								continue;
							}
							$settings[$key]['value'] = $data[$key];
						}
					}
					$device = $this->getDevice($data['extension']);
					if($replaceExisting && !empty($device)) {
						$this->delDevice($data['extension'],true);
					}
					$user = $this->getUser($data['extension']);
					if($replaceExisting && !empty($user)) {
						$this->delUser($data['extension'],true);
					}
					try {
						if (!$this->addDevice($data['extension'], $data['tech'], $settings)) {
							return array("status" => false, "message" => _("Device could not be added."));
						}
					} catch(\Exception $e) {
						return array("status" => false, "message" => $e->getMessage());
					}

					$settings = $this->generateDefaultUserSettings($data['extension'], $data['name']);
					foreach ($settings as $key => $value) {
						if (isset($data[$key])) {
							/* Override default setting with our value. */
							// if concurrency_limit is "" dont override
							if ($key == 'concurrency_limit') {
								if ($data[$key] != "") {// there is some valid value
									$settings[$key] = $data[$key];
								} else { // unsetting.. So while adding it will set default value
									unset($settings[$key]);
								}
							} else {
								$settings[$key] = $data[$key];
							}
						}
					}

					try {
						if (!$this->addUser($data['extension'], $settings)) {
							//cleanup
							$this->delDevice($data['extension'], $replaceExisting);
							return array("status" => false, "message" => _("User could not be added."));
						}
					} catch(\Exception $e) {
						//cleanup
						$this->delDevice($data['extension'], $replaceExisting);
						return array("status" => false, "message" => $e->getMessage());
					}
					if(isset($data['devicedata']) && $data['devicedata'] !=''){
						$this->astman->database_put("AMPUSER",$data['extension']."/device",$data['devicedata']);
					}
				}
				$ret = ['status' => true];
				break;
			case "dids":
				foreach ($rawData as $data) {
					$exists = $this->getDID($data['extension'], $data['cidnum']);
					//FREEPBX-15285 bulk handler for did's check case on destination
					$data['destination'] = strtolower($data['destination']);
					if(!$replaceExisting && !empty($exists)) {
						return array("status" => false, "message" => _("DID already exists"));
					} elseif($replaceExisting && !empty($exists)) {
						$this->delDID($data['extension'], $data['cidnum']);
					}
					if (isset($data['sf_enable']) && !empty($data['sf_enable'])) {
					//Superfect module presence
					if ($this->freepbx->Modules->checkStatus("superfecta")) {
						\FreePBX::Superfecta()->bulkhandler_superfecta_cfg($data);
						}
					}
					$this->addDID($data);
				}
				$ret = ['status' => true];
				break;
			case "trunks":				
				foreach ($rawData as $data) {
					$this_trunk = $this->getPJSIPtrunkIDByName($data["trunk_name"]);
					if(!empty($this_trunk)){
						$this->deleteTrunk($this_trunk["id"], "pjsip");
					}			
					$this->addTrunk($data["trunk_name"], "pjsip", $data);
				}
				$ret = ['status' => true];
				break;
		
		}

		if(is_array($ret)){needreload();}

		return $ret;
	}

	/**
	 * Bulk Handler Export hook
	 * @param  string $type The type of bulk handling
	 * @return array       Array of data
	 */
	public function bulkhandlerExport($type) {
		$data = NULL;

		switch ($type) {
			case 'extensions':
				$users = $this->getAllUsersByDeviceType();
				foreach ($users as $user) {
					$device = $this->getDevice($user['extension']);
					if (isset($device['secret_origional'])) {
						/* Don't expose our typo laden craziness to users.  We like our users! */
						unset($device['secret_origional']);
					}
					if (isset($device['vm'])) {
						/* This value doesnt make sense since we control vm externally through voicemail */
						unset($device['vm']);
					}
					$du = $this->freepbx->Config->get("AMPEXTENSIONS");
					if($du != "deviceanduser") {
						unset($device['password']);
						unset($device['devicetype']);
						unset($device['user']);
						unset($device['id']);
						unset($device['name']);
						unset($device['account']);
					}
					$tmp_user_data = $this->getUser($user['extension']);
					if(isset($tmp_user_data['cid_masquerade'])) {
						$user['cid_masquerade'] = $tmp_user_data['cid_masquerade'];
					}
					if(isset($tmp_user_data['concurrency_limit'])) {
						$user['concurrency_limit'] = $tmp_user_data['concurrency_limit'];;
					}
					$existingdevices = $this->astman->database_get("AMPUSER",$user['extension']."/device");
					$user['devicedata'] = $existingdevices;
					$data[$user['extension']] = array_merge($user, $device);
				}

				break;
			case 'dids':
				$dids = $this->getAllDIDs();
				$data = array();
				foreach($dids as $did) {
					$key = $did['extension']."/".$did["cidnum"];
					$data[$key] = $did;
				}
				break;
			case "trunks":
				$data = [];
				$pjsip_trunks = $this->drivers['pjsip']->getAllTrunks();
				foreach($pjsip_trunks as $pjsip_trunk){
					$trunks = $this->getPJSIPtrunkIDByName($pjsip_trunk["trunk_name"]);
					$sql	= "SELECT * FROM trunks WHERE trunkid = :id and tech = 'pjsip' LIMIT 1";
					$sth 	= $this->database->prepare($sql);
					$sth->execute([":id" => $trunks["id"]]);
					$row 	= $sth->fetch(PDO::FETCH_ASSOC);
					foreach($row as $key => $setting){
						$pjsip_trunk[$key] = $setting;
					}
					$data[] = $pjsip_trunk;
				}
				break;
		}

		return $data;
	}

	/**
	 * Set Outbound Routes Order
	 */
	public function setRouteOrder($routes){
		$routes = array_unique($routes);
		$routes = array_values($routes);
		$dbh = $this->database;
		$stmt = $dbh->prepare('DELETE FROM `outbound_route_sequence` WHERE 1');
		$stmt->execute();
		$stmt = $dbh->prepare('INSERT INTO outbound_route_sequence (route_id, seq) VALUES (?,?)');
		$ret = array();
		foreach ($routes as $key => $value) {
			$seq = ($key+1);
			$ret[] = $stmt->execute(array($value,$seq));
		}
		needreload();
		return $ret;
	}

	/**
	 * List Admin Users
	 * @param  string $datatype The type of data to return
	 * @return array           Return Array
	 */
	public function listAMPUsers($datatype = '', $full = false){
        $sql = "SELECT username FROM ampusers ORDER BY username";
        if (false !== $full) {
            $sql = 'SELECT * FROM ampusers ORDER BY username';
        }
		$stmt = $this->database->prepare($sql);
		$stmt->execute();
		switch ($datatype) {
			case 'assoc':
				return $stmt->fetchall(PDO::FETCH_ASSOC);
			break;
			default:
				return $stmt->fetchall(PDO::FETCH_BOTH);
			break;
		}
	}

	/**
	 * Add Admin User
	 * @param string $username       The username
	 * @param string $password       The password
	 * @param integer $extension_low  The lowest extension
	 * @param integer $extension_high The highest extension
	 * @param string $deptname       The department name
	 * @param array $sections       Sections to allow
	 */
	public function addAMPUser($username, $password, $extension_low, $extension_high, $deptname, $sections, $skipSHA1 = false){
		if ($skipSHA1 || strlen($password) == 40 && ctype_xdigit($password)) {
			$password_sha1 = $password;
		}else {
			$password_sha1 = sha1($password);
		}
		$sections = implode(";",$sections);
		$vars = array(
			':username' => $username,
			':password_sha1' => $password_sha1,
			':extension_low' => $extension_low,
			':extension_high' => $extension_high,
			':deptname' => $deptname,
			':sections' => $sections,
		);
		$sql = "REPLACE INTO ampusers (username, password_sha1, extension_low, extension_high, deptname, sections) VALUES (:username,
					:password_sha1,
					:extension_low,
					:extension_high,
					:deptname,
                    :sections)
                ";
		$stmt = $this->database->prepare($sql);
		try{
			$stmt->execute($vars);
			
			if ($this->freepbx->Modules->checkStatus('pbxmfa')) {
				$this->freepbx->Pbxmfa->syncMFAUsers('admin');
			}

			return true;
		}catch(PDOException $e){
			//data colission
			if($e->getCode() == '23000'){
				return false;
			}else{
				echo $e->getMessage();
        throw $e;
			}
		}
	}

	/**
	 * List Trunk Types
	 * @return array Array of Trunk Types
	 */
	public function listTrunkTypes(){
		$sipdriver = $this->FreePBX->Config->get_conf_setting('ASTSIPDRIVER');
		$default_trunk_types = array(
			"DAHDI" => 'DAHDi',
			"IAX2" => 'IAX2',
			"ENUM" => 'ENUM',
			"DUNDI" => 'DUNDi',
			"CUSTOM" => 'Custom'
		);

		$sip = ($sipdriver == 'both' || $sipdriver == 'chan_sip') ? array("SIP" => sprintf(_('SIP (%s)'),'chan_sip')) : array();
		$pjsip = ($sipdriver == 'both' || $sipdriver == 'chan_pjsip') ? array("PJSIP" => sprintf(_('SIP (%s)'),'chan_pjsip')) : array();
		$trunk_types = $pjsip+$sip+$default_trunk_types;
		// Added to enable the unsupported misdn module
		if (function_exists('misdn_ports_list_trunks') && count(misdn_ports_list_trunks())) {
			$trunk_types['MISDN'] = 'mISDN';
		}
		return $trunk_types;
	}

	/**
	 * Search query for global search
	 * @param  string $query   The query
	 * @param  array $results Results
	 */
	public function search($query, &$results) {
		if($this->freepbx->Config->get('AMPEXTENSIONS') == "extensions") {
			$sql = "SELECT * FROM devices WHERE id LIKE ? or description LIKE ?";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("%".$query."%","%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				if(ctype_digit($query)) {
					$results[] = array("text" => _("Extension")." ".$row['id'], "type" => "get", "dest" => "?display=extensions&extdisplay=".$row['id']);
				} else {
					$results[] = array("text" => $row['description']." (".$row['id'].")", "type" => "get", "dest" => "?display=extensions&extdisplay=".$row['id']);
				}
			}
		} else {
			$sql = "SELECT * FROM devices WHERE id LIKE ? or description LIKE ?";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("%".$query."%","%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				if(ctype_digit($query)) {
					$results[] = array("text" => _("Device")." ".$row['id'], "type" => "get", "dest" => "?display=devices&extdisplay=".$row['id']);
				} else {
					$results[] = array("text" => $row['description']." (".$row['id'].")", "type" => "get", "dest" => "?display=devices&extdisplay=".$row['id']);
				}
			}

			$sql = "SELECT * FROM users WHERE extension LIKE ? or name LIKE ?";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("%".$query."%","%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				if(ctype_digit($query)) {
					$results[] = array("text" => _("User")." ".$row['extension'], "type" => "get", "dest" => "?display=extensions&extdisplay=".$row['extension']);
				} else {
					$results[] = array("text" => $row['name']." (".$row['extension'].")", "type" => "get", "dest" => "?display=extensions&extdisplay=".$row['extension']);
				}
			}
		}

		if(!ctype_digit($query)) {
			$sql = "SELECT * FROM outbound_routes WHERE name LIKE ?";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				$results[] = array("text" => _("Outbound Route:")." ".$row['name'], "type" => "get", "dest" => "?display=routing&view=form&id=".$row['route_id']);
			}

			$sql = "SELECT * FROM trunks WHERE name LIKE ?";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				$results[] = array("text" => _("Trunk:")." ".$row['name'], "type" => "get", "dest" => "?display=trunks&tech=".$row['tech']."&extdisplay=OUT_".$row['trunkid']);
			}

			$sql = "SELECT * FROM incoming WHERE description LIKE ?";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				$display = urlencode($row['extension']."/".$row['cidnum']);
				$results[] = array("text" => _("Inbound Route:")." ".$row['description'], "type" => "get", "dest" => "?display=did&view=form&extdisplay=".$display);
			}
		} else {
			$sql = "SELECT * FROM incoming WHERE cidnum LIKE :search OR extension LIKE :search";
			$sth = $this->database->prepare($sql);
			$sth->execute(array("search" => "%".$query."%"));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row) {
				$display = urlencode($row['extension']."/".$row['cidnum']);
				$results[] = array("text" => _("Inbound Route:")." ".$row['extension']."/".$row['cidnum'], "type" => "get", "dest" => "?display=did&view=form&extdisplay=".$display);
			}
		}
	}

	/**
	 * Disable Trunk
	 * @method disableTrunk
	 * @param  string       $id The trunk ID
	 * @return boolean           Result of execute
	 */
	public function disableTrunk($id){
		$tech = $this->getTrunkTech($id);
		if(!$tech){
			return false;
		}
		$sql = "UPDATE trunks set disabled = 'on' WHERE trunkid = ?";
		$ob = $this->database->prepare($sql);
		$ret = $ob->execute(array($id));

		if ($ret && $tech == 'pjsip') {
			$sql = "UPDATE pjsip SET data = 'on' WHERE id = ? AND keyword = 'disabletrunk'";
			$ob = $this->database->prepare($sql);
			$ret = $ob->execute(array($id));
		}
		return $ret;
	}

	/**
	 * Enable Trunk
	 * @method enableTrunk
	 * @param  string       $id The trunk ID
	 * @return boolean           Result of execute
	 */
	public function enableTrunk($id){
		$tech = $this->getTrunkTech($id);

		$sql = "UPDATE trunks set disabled = 'off' WHERE trunkid = ?";
		$ob = $this->database->prepare($sql);
		$ret = $ob->execute(array($id));

		if ($ret && $tech == 'pjsip') {
			$sql = "UPDATE pjsip SET data = 'off' WHERE id = ? AND keyword = 'disabletrunk'";
			$ob = $this->database->prepare($sql);
			$ret = $ob->execute(array($id));
		}
		return $ret;
	}

	public function getPJSIPtrunkIDByName($name){
		$sql	= "SELECT id FROM pjsip WHERE keyword = 'trunk_name' and data = :name LIMIT 1";
		$sth 	= $this->database->prepare($sql);
		$sth->execute([":name" => $name]);
		$rows = $sth->fetch(PDO::FETCH_ASSOC);
		return $rows;
	}

	/**
	 * Hide Trunk in routes ui
	 * @method hideTrunk
	 * @param  string       $id The trunk ID
	 * @return boolean           Result of execute
	 */
	public function hideTrunk($id){
		$sql = "UPDATE trunks set routedisplay = 'off' WHERE trunkid = ?";
		$ob = $this->database->prepare($sql);
		$ret = $ob->execute(array($id));
		return $ret;
	}

	/**
	 * Show Trunk in routes ui
	 * @method showTrunk
	 * @param  string       $id The trunk ID
	 * @return boolean           Result of execute
	 */
	public function showTrunk($id){
		$sql = "UPDATE trunks set routedisplay = 'on' WHERE trunkid = ?";
		$ob = $this->database->prepare($sql);
		$ret = $ob->execute(array($id));
		return $ret;
	}
	
	/**
	 * Get Trunk Tech
	 * @method getTrunkTech
	 * @param  string       $trunknum The trunk id
	 * @return string                 The trunk tech
	 */
	public function getTrunkTech($trunknum) {
		$sql = "SELECT tech FROM trunks WHERE trunkid = ?";
		$ob = $this->database->prepare($sql);
		$ob->execute(array($trunknum));
		$tech = $ob->fetchColumn();
		if (!$tech) {
			return false;
		}
		$tech = strtolower($tech);
		if ($tech == "iax2") {
			$tech = "iax"; // same thing, here
		}
		return $tech;
	}

	/**
	 * Filter valid codecs from list
	 * @method filterValidCodecs
	 * @param  string      $codecs List of codecs to check
	 * @return string              The filtered codecs
	 */
	public function filterValidCodecs($codecs) {
		$codecs = str_replace('&', ',', $codecs); //remove invalid & joiner if its there
		$codecs = explode(",",$codecs);
		$validCodecs = array_merge(
			array_keys($this->freepbx->Codecs->getAudio()),
			array_keys($this->freepbx->Codecs->getVideo()),
			array_keys($this->freepbx->Codecs->getText()),
			array_keys($this->freepbx->Codecs->getImage()),
			array('all','!all')
		);
		$final = array();
		foreach($codecs as $codec) {
			if(preg_match("/([a-z0-9]+):?/i", $codec, $match) && isset($match[1]) && in_array($match[1],$validCodecs)) {
				$final[] = $codec;
			}
		}
		$codecs = implode(",",$final);
		return $codecs;
	}

	/**
	 * Start FreePBX for fwconsole hook
	 * @param object $output The output object.
	 */
	public function startFreepbx($output=null, $debug=true, $startdaemon=true) {
		if($startdaemon){
			$this->startdaemon();
		}
		if(!$this->freepbx->Config->get('LAUNCH_AGI_AS_FASTAGI')) {
			return;
		}
		$this->setWriter($output);
		if($this->freepbx->Config->get('LAUNCH_AGI_AS_FASTAGI') && !$this->freepbx->Modules->checkStatus("pm2")) {
			$this->writeln('PM2 is not installed/enabled. Unable to start Core FastAGI Server');
			return;
		}
		$pm2 = $this->freepbx->Pm2;
		$status = $pm2->getStatus("core-fastagi");
		if($status && $status['pm2_env']['status']) {
			if($debug) {
				$this->writeln(sprintf(_("Core FastAGI Server has already been running on PID %s for %s"),$status['pid'],$status['pm2_env']['created_at_human_diff']));
			}
			return $status['pid'];
		} else {
			if($debug) {
				$this->writeln(_("Starting Core FastAGI Server..."));
			}
			$opts = array(
				'ASTAGIDIR' => $this->freepbx->Config->get('ASTAGIDIR')
			);
			if($this->freepbx->Config->get('DEVEL')) {
				$opts['NODE_ENV'] = 'development';
			}
			$this->freepbx->pm2->start(
				"core-fastagi",
				__DIR__."/node/fastagi-server.js",
				$opts
			);
			if(is_object($output)) {
				$progress = new ProgressBar($output, 0);
				$progress->setFormat('[%bar%] %elapsed%');
				$progress->start();
			}
			$i = 0;
			while($i < 100) {
				$data = $pm2->getStatus("core-fastagi");
				if(!empty($data) && $data['pm2_env']['status'] == 'online') {
					if(is_object($output)) {
						$progress->finish();
					}
					break;
				}
				if(is_object($output)) {
					$progress->setProgress($i);
				}
				$i++;
				usleep(100000);
			}
			if(is_object($output)) {
				if($debug) {
					$this->writeln("");
				}
			}
			if(!empty($data)) {
				$pm2->reset("core-fastagi");
				if($debug) {
					$this->writeln(sprintf(_("Started Core FastAGI Server. PID is %s"),$data['pid']));
				}
				return $data['pid'];
			}
			if($debug) {
				$this->writeln("<error>".sprintf(_("Failed to run: '%s'")."</error>",$command));
			}
		}
	}


	/**
	 * Stop FreePBX for fwconsole hook
	 * @param object $output The output object.
	 */
	public function stopFreepbx($output=null, $debug=true) {
		$this->stopdaemon();
		$this->setWriter($output);
		if(!$this->freepbx->Modules->checkStatus("pm2")) {
			$this->writeln('PM2 is not installed/enabled. Unable to stop Core FastAGI Server');
			return;
		}
		$pm2 = $this->freepbx->Pm2;
		$data = $this->freepbx->pm2->getStatus("core-fastagi");
		if(empty($data) || $data['pm2_env']['status'] != 'online') {
			if($debug && $this->freepbx->Config->get('LAUNCH_AGI_AS_FASTAGI')) {
				$this->writeln("<error>"._("Core FastAGI Server is not running")."</error>");
			}
			return false;
		}
		// executes after the command finishes
		if($debug) {
			$this->writeln(_("Stopping Core FastAGI Server"));
		}

		$pm2->stop("core-fastagi");

		$data = $pm2->getStatus("core-fastagi");
		if (empty($data) || $data['pm2_env']['status'] != 'online') {
			$pm2->delete("core-fastagi");
			if($debug) {
				$this->writeln(_("Stopped FastAGI Server"));
			}
		} else {
			if($debug) {
				$this->writeln("<error>".sprintf(_("FastAGI Server Failed: %s")."</error>",$process->getErrorOutput()));
			}
			return false;
		}

		return true;
	}

	/**
	 * This is called in extensions.class.php to tell if FastAGI Dialplan should be written out
	 *
	 * @return boolean
	 */
	public function fastAGIStatus() {
        $this->preReloadFreepbx();
		return $this->fastAGIState;
	}

	public function preReloadFreepbx() {
		//dont do anything if FASTAGI is disabled
		if(!$this->freepbx->Config->get('LAUNCH_AGI_AS_FASTAGI')) {
			//should we do a notification here? probably not.
			$this->freepbx->Notifications->delete('core','FASTAGI');
			return;
		}

		//make sure pm2 is installed
		if(!$this->freepbx->Modules->checkStatus("pm2")) {
			$this->freepbx->Notifications->add_warning('core','FASTAGI',_("PM2 Not installed"),_("'Launch local AGIs through FastAGI Server' was enabled in Advanced Settings but PM2 is not installed so we were unable to start the FastAGI server, As a result all AGIs will be forked inside of Asterisk until this is resolved"),"",true,true);
		}

		//lets see if core-fastagi is running
		$pm2 = $this->freepbx->Pm2;
		$status = $pm2->getStatus("core-fastagi");
		//its not started so lets attempt to start it
		if($status && $status['pm2_env']['status'] !== 'online') {
			//its not so lets try to start it!
			try {
				//attempting
				$this->startFreepbx(new \Symfony\Component\Console\Output\NullOutput(), false, false);
			} catch(\Exception $e) {
				//it failed. log out the reason why and return
				$this->freepbx->Notifications->add_warning('core','FASTAGI',_("Fast AGI Server not running"),sprintf(_("Launch local AGIs through FastAGI Server' was enabled in Advanced Settings but we were unable to start FastAGI server because: %s, As a result all AGIs will be forked inside of Asterisk until this is resolved"),$e->getMessage()),"",true,true);
				return;
			}

			//did it actually start?
			$status = $pm2->getStatus("core-fastagi");
			if($status['pm2_env']['status'] !== 'online') {
				$this->freepbx->Notifications->add_warning('core','FASTAGI',_("Fast AGI Server not running"),_("'Launch local AGIs through FastAGI Server' was enabled in Advanced Settings but the FastAGI server was unable to start, As a result all AGIs will be forked inside of Asterisk until this is resolved"),"",true,true);
				return;
			}
		}

		//delete any warnings because we are successfull
		$this->freepbx->Notifications->delete('core','FASTAGI');
		//set state to true
		$this->fastAGIState = true;
	}

	public function postReloadFreepbx() {

	}

	/**
	 * FreePBX chown hooks
	 */
	public function chownFreepbx() {

	}

	public function updateFreePBXSetting($keyword, $value) {
		if($keyword === 'LAUNCH_AGI_AS_FASTAGI') {
			if(!empty($value)) {
				if(!$this->freepbx->Modules->checkStatus("pm2")) {
					//cant start it as no pm2.
					//This will be resolved on next reload
					return;
				}
				$this->startFreepbx(null, false, false);
			} else {
				$this->stopFreepbx(null, false);
			}
		}
	}

	public function removeFreePBXSetting($keyword) {
		if($keyword === 'LAUNCH_AGI_AS_FASTAGI') {
			$this->stopFreepbx(null, false);
		}
	}


	private function setWriter($writer) {
		$this->writer = $writer;
	}

	public function writeln($message) {
		if(is_object($this->writer)) {
			return $this->writer->writeln($message);
		} else {
			out(preg_replace("/<\/?\w*>/", "", $message));
		}
	}

	public function write($message) {
		if(is_object($this->writer)) {
			return $this->writer->write($message);
		} else {
			outn(preg_replace("/<\/?\w*>/", "", $message));
		}
	}

	// this function rebuilds the astdb based on device table contents
	// used on devices.php if action=resetall
	function devices2astdb(){

		$devresults = $this->database->query("SELECT * FROM devices")->fetchAll(\PDO::FETCH_ASSOC);
		$uservoicemails = $this->database->query("SELECT extension, voicemail FROM users")->fetchAll(\PDO::FETCH_KEY_PAIR);

		//add details to astdb
		if ($this->astman->connected()) {
			$this->astman->database_deltree("DEVICE");
			foreach ($devresults as $dev) {
				$this->astman->database_put("DEVICE",$dev['id']."/dial",$dev['dial']);
				$this->astman->database_put("DEVICE",$dev['id']."/type",$dev['devicetype']);
				$this->astman->database_put("DEVICE",$dev['id']."/user",$dev['user']);
				$this->astman->database_put("DEVICE",$dev['id']."/default_user",$dev['user']);
				$this->astman->database_put("DEVICE",$dev['id']."/tech",$dev['tech']);
				if(freepbx_trim ($dev['emergency_cid']) != '') {
					$this->astman->database_put("DEVICE",$dev['id']."/emergency_cid",$dev['emergency_cid']);
				}
				// If a user is selected, add this device to the user
				if (isset($dev['user']) && $dev['user'] != "none") {
					$existingdevices = $this->astman->database_get("AMPUSER",$dev['user']."/device");
					if (empty($existingdevices)) {
						$this->astman->database_put("AMPUSER",$dev['user']."/device",$dev['id']);
					} else {
						$existingdevices_array = explode('&',$existingdevices);
						if (!in_array($dev['id'], $existingdevices_array)) {
							$existingdevices_array[] = $dev['id'];
							$existingdevices = implode('&',$existingdevices_array);
							$this->astman->database_put("AMPUSER",$dev['user']."/device",$existingdevices);
						}
					}
				}

				// create a voicemail symlink if needed
				if(isset($uservoicemails[$dev['user']]['voicemail']) && ($uservoicemails[$dev['user']]['voicemail'] != "novm") && $this->freepbx->Modules->checkStatus('voicemail')) {
					$this->freepbx->Voicemail->mapMailBox($dev['user']);
				}
			}
			return true;
		} else {
			return false;
		}
	}

	// this function rebuilds the astdb based on users table contents
	// used on devices.php if action=resetall
	function users2astdb(){
		$userresults = $this->database->query("SELECT * FROM users")->fetchAll(\PDO::FETCH_ASSOC);

		//add details to astdb
		if ($this->astman->connected()) {
			foreach($userresults as $usr) {
				$this->astman->database_put("AMPUSER",$usr['extension']."/password",$usr['password']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/ringtimer",$usr['ringtimer']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/noanswer",$usr['noanswer']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/recording",$usr['recording']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/outboundcid",$usr['outboundcid']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/cidname",$usr['name']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/cidnum",$usr['extension']);
				$this->astman->database_put("AMPUSER",$usr['extension']."/voicemail",$usr['voicemail']);
			}
			return true;
		} else {
			return false;
		}
	}

	public function getencryptionval($ext, $tech) {
		if ($tech == 'pjsip' ) {
			$sql = "SELECT * FROM sip WHERE id ='$ext' AND keyword='encryption'";
		} else {
			$sql = "SELECT * FROM sip WHERE id ='$ext' AND keyword='media_encryption'";
		}
		$stmt = $this->database->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $result;
	}

	public function getSipSecret($ext){
		$sql = "SELECT * FROM sip WHERE id = :id AND keyword='secret' LIMIT 1";
		$stmt = $this->database->prepare($sql);
        $stmt->bindParam(':id', $ext);
		$stmt->execute();
		$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $result;
	}

	public function checkExtensionLicenseCount(){
		if ($this->freepbx->Modules->checkStatus("sysadmin")) { 
			$sysLimitRemaining = \FreePBX::Sysadmin()->get_sysadmin_extensions_limit('remaining');
			if($sysLimitRemaining <= 0 ){					
				return false;
			}
		}
		return true;
	}

	public function editGqlDID($oldExtension,$oldCidnum, $incoming) {
		$extension = freepbx_trim ($incoming['extension']);
		$cidnum = freepbx_trim ($incoming['cidnum']);

		// if did or cid changed, then delete the old did and create new did.
		if(!empty($oldExtension) && !empty($oldCidnum)){
			$this->delDID($oldExtension,$oldCidnum);
			$this->addDID($incoming);
			return true;
		}else{
			$existing = $this->getDID($extension,$cidnum);
			if (!empty($existing)) {
				$this->delDID($extension,$cidnum);
				$this->addDID($incoming);
				return true;
			} else {
				return false;
			}
		}

	}

	/**
	 * Hook for adding additional contents
	 * @param  string $page
	 * @return array  $data
	 */
	public function hookAdditionalContent($page) {
		$data = array();
		if ($this->config->get('ALLOW_MODULE_HOOK_IN')) {
			$mods = $this->freepbx->Hooks->processHooks($page);
			foreach($mods as $contents) {
				if(empty($contents)) {
					continue;
				}
	
				foreach($contents as $key => $content) {
					if(!isset($data[$key])) {
						$data[$key] = '';
					}
					$data[$key] .= $content;
				}
			}
		}
		return $data;
	}

	/**
	 * Hook for adding additional parameters to mail
	 * @param  string $emailText
	 * @param  string $hotDeskExten
	 * @param  string $emergencyRoute
	 * @return string $emailText
	 */
	public function hookAdditionalVariableValues($emailText, $hotDeskExten, $emergencyRoute) {
		if ($this->config->get('ALLOW_MODULE_HOOK_IN')) {
			$mods = $this->freepbx->Hooks->processHooks($hotDeskExten, $emergencyRoute);
			foreach($mods as $contents) {
				if(empty($contents)) {
					continue;
				}

				foreach($contents as $key => $value) {
					$emailText = str_replace('{{' . $key . '}}', $value, $emailText);
				}
			}
		}
		return $emailText;
	}

	private function setPresenceState($device, $type) {
		$astman = $this->FreePBX->astman;
		$astman->set_global(\FreePBX::Config()->get_conf_setting('AST_FUNC_PRESENCE_STATE') . '(CustomPresence:' . $device . ')', '"'.$type . ',,"');
	}

	public function convert2pjsip() {
		// get a list of all sip extensions
		$extensions = $this->getAllDevicesByType('sip');
		foreach($extensions as $exten) {
			try {
				$this->changeDeviceTech($exten['id'], 'pjsip');
			} catch(Exception $e) {
				dbug($e->getMessage());
			}
		}
	}

	public function skipchansip() {
		$extensions = $this->getAllDevicesByType('sip');
		foreach($extensions as $exten) {
			try {
				//delete from users table
				$sthuser = $this->FreePBX->Database->prepare("DELETE FROM users WHERE `extension`= ".$exten['id']);
				$sthuser->execute();
				//delete from sip table
				$sthsip = $this->FreePBX->Database->prepare("DELETE FROM ".$exten['tech']." WHERE `id`= ".$exten['id']);
				$sthsip->execute();
				//unlink from userman_users table
				$sthuserman = $this->FreePBX->Database->prepare("UPDATE userman_users SET default_extension = '' WHERE `username`= ".$exten['id']);
				$sthuserman->execute();

				//delete from devices table
				$sthd = $this->FreePBX->Database->prepare("DELETE FROM devices WHERE `id`= ".$exten['id']);
				$sthd->execute();
			} catch(Exception $e) {
				dbug($e->getMessage());
			}
		}
	}

	public function getTrunksByTech($type=null,$trunkid='') {
		$listTrunks = $this->listTrunks();
		$trunks = [];
		if (!empty($listTrunks) && !empty($type)) {
			foreach($listTrunks as $key=>$trunk) {
				if ($trunk['tech'] == $type && (empty($trunkid) || $trunkid == $trunk['trunkid'])) {
					$trunks[$key] = $trunk;
				}
			}
		}
		return $trunks;
	}

	public function confirmPJSIPAdoption() {
		$curversion = engine_getinfo();
		$astversion = $curversion['version'];
		if (version_compare("21.0", $astversion)<=0) {
			$sipChannelText = $sipExtText = $sipTrunkText = '';
			$sipTrunks = $this->getTrunksByTech('sip');
			$extensions	= $this->getAllDevicesByType('sip');
			$sipDriver = $this->freepbx->Config->get_conf_setting('ASTSIPDRIVER');
			if (empty($sipTrunks) && empty($extensions)) {
				$this->freepbx->Config->set('ASTSIPDRIVER', 'chan_pjsip');
				$this->freepbx->Config->set('HTTPWEBSOCKETMODE', 'pjsip');
				//hide ASTSIPDRIVER and HTTPWEBSOCKETMODE if asterisk version is 21+
				$sql_update = "UPDATE `freepbx_settings` SET `hidden` = 1 WHERE `keyword` IN ('ASTSIPDRIVER','HTTPWEBSOCKETMODE') ";
				$sth = $this->database->prepare($sql_update);
				$sth->execute();
				$this->freepbx->Notifications->delete('core','NO_CHANSIP');
				return true;
			}
			$heading = _('Chan_sip module deprecated in Asterisk V21+');
			if (!empty($extensions)) {
				$sipExtText = _(' Convert the chan_sip extension to PJSIP. Please Run: ') . '<b>' . _('fwconsole convert2pjsip -a.').'</b>';
			}
			if (!empty($sipTrunks)) {
				$sipTrunkText = _(' Convert the sip trunks to pjsip. Please Run: ') . '<b>' . _('fwconsole trunks --converttopjsip <all/trunkid>.').'</b>';
			}
			if ($sipDriver == "chan_sip" || $sipDriver == "both") {
				$sipChannelText = _(' In Advance settings set SIP Channel Driver option to chan_pjsip.');
			}
			if (!$this->freepbx->Notifications->exists('core', 'NO_CHANSIP')) {
				$text = $heading . _('. It is recommended not to use chan_sip settings.');
				$text.= $sipExtText.$sipTrunkText.$sipChannelText;
				$this->freepbx->Notifications->add_warning('core', 'NO_CHANSIP', $heading, $text, '', true, true);
			}
			if (!empty($extensions) || !empty($sipTrunks)) {
				echo json_encode(["error" => $heading, "trace" => trim($sipExtText). '<br>' . trim($sipTrunkText)]);
				exit(-1);
			}
		} else {
			$sql_update = "UPDATE `freepbx_settings` SET `hidden` = 0 WHERE `keyword` IN ('ASTSIPDRIVER','HTTPWEBSOCKETMODE') ";
			$sth = $this->database->prepare($sql_update);
			$sth->execute();
			$this->freepbx->Notifications->delete('core','NO_CHANSIP');
			return true;
		}
	}

	public function chansipToPJSIP($output='',$trunkid='') {
		$sipTrunks = $this->getTrunksByTech('sip',$trunkid);
		if (!empty($sipTrunks)) {
			foreach($sipTrunks as $rowData) {
				if(!empty($output))	$output->writeln(_("Convert the sip trunks to pjsip (Trunk name : ".($rowData['name'] ?? '').")"));
				$trunkid = $rowData['trunkid'] ?? 0;
				$sth = $this->database->prepare("SELECT * FROM sip where `id` = 'tr-peer-".$trunkid."' or `id` = 'tr-reg-".$trunkid."' or `id` = 'tr-user-".$trunkid."'");
				$sth->execute();
				$res = $sth->fetchAll(\PDO::FETCH_ASSOC);
				$result = [];
				$result['sip_server_port'] = 5060;
				$result['trunk_name'] = $rowData['name'] ?? '';
				$result['secret'] = $result['username'] = $result['auth_username'] ='';
				$pjsipcolumn = ["host" => "sip_server","port" => "sip_server_port"];
				if (is_array($res) && count($res) > 0) {
					foreach ($res as $item) {
						if (isset($item['keyword']) && isset($item['data'])) {
							$array_key = isset($pjsipcolumn[$item['keyword']]) ? $pjsipcolumn[$item['keyword']] : $item['keyword'];
							$result[$array_key] = $item['data'];
						}
					}
				}
				$settings = $this->checkPJSIPsettings([],[]);
				$settings = array_merge($settings, $result);
				if (!empty($settings['register'])) {
					$host=$username=$password ='';
					try {
						list($usernamePassword, $host) = explode("@", $settings['register'],2);
						list($username, $password) = explode(":", $usernamePassword,2);
						$settings['authentication'] = 'outbound';
						$settings['registration'] = 'send';
						$settings['secret'] = $password;
						$settings['username'] = $username;
						$settings['auth_username'] = $username;
						$settings['sip_server'] = $host;
					} catch(\Exception $e) { } finally { unset($settings['register']); }
				} else {
					$settings['authentication'] = 'off';
					$settings['registration'] = 'none';
				}
				$settings['pjsip_line'] = 'true';
				$settings['sv_trunk_name'] = $settings['sv_channelid'] = $rowData['name'] ?? '';
				$settings['send_connected_line'] = 'no';
				$settings['extdisplay'] = 'ÓUT_'.$trunkid;
				$pjsip = $this->getDriver('pjsip');
				$pjsip->addTrunk($trunkid,$settings);
				$sql = "UPDATE trunks SET tech = 'pjsip' WHERE trunkid = ?";
				$ob = $this->database->prepare($sql);
				$ret = $ob->execute(array($trunkid));
				$this->database->query("Delete from sip where `id` ='tr-peer-".$trunkid."'");
				$this->database->query("Delete from sip where `id` ='tr-reg-".$trunkid."'");
				$this->database->query("Delete from sip where `id` ='tr-user-".$trunkid."'");
				$this->setConfig("converted_SIP",json_encode($res), $trunkid);
			}
			if(!empty($output)) $output->writeln(_("Trunk converted successfully!"));
			if(!empty($output)) $output->writeln(_("Run 'fwconsole reload' to reload config"));
		} else {
			if(!empty($output)) $output->writeln(_("No SIP trunks found."));
		}
	}

	public function skipchansipTrunk() {
		$sipTrunks = $this->getTrunksByTech('sip');
		if (!empty($sipTrunks)) {
			foreach($sipTrunks as $rowData) {
				try {
					$trunkid = $rowData['trunkid'] ?? 0;
					$this->database->query("Delete from sip where `id` ='tr-peer-".$trunkid."'");
					$this->database->query("Delete from sip where `id` ='tr-reg-".$trunkid."'");
					$this->database->query("Delete from sip where `id` ='tr-user-".$trunkid."'");
					$this->database->query("Delete from trunks where `trunkid` ='".$trunkid."'");
				} catch(\Exception $e) { dbug($e->getMessage()); }
			}
		}
	}

	public function getAstdbConfigs($extension) {
		$results = [];
		$astman = $this->FreePBX->astman;
		if ($astman->connected()) {

			if ($this->FreePBX->Modules->checkStatus("paging")) {
				$answermode=$astman->database_get("AMPUSER",$extension."/answermode");
				$results['answermode'] = (trim($answermode) == '') ? $this->freepbx->Config->get("DEFAULT_INTERNAL_AUTO_ANSWER") : $answermode;

				$intercom=$astman->database_get("AMPUSER",$extension."/intercom");
				$results['intercom'] = (trim($intercom) == '') ? 'enabled' : $intercom;
			}

			$cw = $astman->database_get("CW",$extension);
			$results['callwaiting'] = (trim($cw) == 'ENABLED') ? 'enabled' : 'disabled';
			$cid_masquerade=$astman->database_get("AMPUSER",$extension."/cidnum");
			$results['cid_masquerade'] = (trim($cid_masquerade) != "")?$cid_masquerade:$extension;

			$call_screen=$astman->database_get("AMPUSER",$extension."/screen");
			$results['call_screen'] = (trim($call_screen) != "")?$call_screen:'0';

			$pinless=$astman->database_get("AMPUSER",$extension."/pinless");
			$results['pinless'] = (trim($pinless) == 'NOPASSWD') ? 'enabled' : 'disabled';

			$results['ringtimer'] = (int) $astman->database_get("AMPUSER",$extension."/ringtimer");

			$results['cfringtimer'] = (int) $astman->database_get("AMPUSER",$extension."/cfringtimer");
			$results['concurrency_limit'] = (int) $astman->database_get("AMPUSER",$extension."/concurrency_limit");

			$results['dialopts'] = $astman->database_get("AMPUSER",$extension."/dialopts");

			$results['cwtone'] = $astman->database_get("AMPUSER",$extension."/cwtone");

			$results['recording_in_external'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/in/external"));
			$results['recording_out_external'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/out/external"));
			$results['recording_in_internal'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/in/internal"));
			$results['recording_out_internal'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/out/internal"));
			$results['recording_ondemand'] = strtolower($astman->database_get("AMPUSER",$extension."/recording/ondemand"));
			$results['recording_priority'] = (int) $astman->database_get("AMPUSER",$extension."/recording/priority");
			$results['rvolume'] = strtolower($astman->database_get("AMPUSER",$extension."/rvolume"));
			$results['novmpw'] = strtolower($astman->database_get("AMPUSER",$extension."/novmpw"));

		} else {
			throw new \Exception("Cannot connect to Asterisk Manager with using user[".$this->FreePBX->Config->get("AMPMGRUSER")."]");
		}
		return $results;
	}

	public function putAstdbConfigs($configs) {
		$astman = $this->FreePBX->astman;
		//add details to astdb
		if ($astman->connected()) {
			$replace_char = ['recording_in_external','recording_out_external','recording_in_internal','recording_out_internal','recording_ondemand','recording_priority'];
			foreach ($configs as $ext => $confs) {
				foreach ($confs as $key => $value) {
					if(in_array($key,$replace_char)) {
						$key = str_replace("_","/",$key);
					}
					if($key == 'callwaiting') {
						$astman->database_put("CW",$ext,strtoupper($value));
					} else if($key == 'dialopts') {
						if($value) {
							$astman->database_put("AMPUSER",$ext."/".$key,$value);
						}
					} else {
						$astman->database_put("AMPUSER",$ext."/".$key,$value);
					}
				}
			}
		}
	}
}
