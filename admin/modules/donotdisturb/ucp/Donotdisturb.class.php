<?php
/**
 * This is the User Control Panel Object.
 *
 * Copyright (C) 2013 Schmooze Com, INC
 * Copyright (C) 2013 Andrew Nagy <andrew.nagy@schmoozecom.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   FreePBX UCP BMO
 * @author   Andrew Nagy <andrew.nagy@schmoozecom.com>
 * @license   AGPL v3
 */
namespace UCP\Modules;
use \UCP\Modules as Modules;
#[\AllowDynamicProperties]
class Donotdisturb extends Modules{
	protected $module = 'Donotdisturb';
	private $user = null;
	private $userId = false;

	function __construct($Modules) {
		$this->Modules = $Modules;
		$this->user = $this->UCP->User->getUser();
		$this->userId = $this->user ? $this->user["id"] : false;
	}

	public function poll($data) {
		$states = array();
		foreach($data as $ext) {
			if(!$this->_checkExtension($ext)) {
				continue;
			}
			$states[$ext] = $this->UCP->FreePBX->Donotdisturb->getStatusByExtension($ext) == "YES" ? true : false;
		}

		return array("states" => $states);
	}

	public function getWidgetList() {
		$widgetList = $this->getSimpleWidgetList();

		return $widgetList;
	}

	/**
	 * validate against rules
	 */
	private function validate($extension = false) {
		$data = array(
			'hasError' => false,
			'errorMessages' => []
		);

		$extensions = $this->UCP->getCombinedSettingByID($this->userId,'Settings','assigned');
		if (empty($extensions)) {
			$data['hasError'] = true;
			$data['errorMessages'][] = _('There are no assigned extensions.');
		}
		if ($extension !== false) {
			if (empty($extension)) {
				$data['hasError'] = true;
				$data['errorMessages'][] = _('The given extension is empty.');
			}
			if (!$this->_checkExtension($extension)) {
				$data['hasError'] = true;
				$data['errorMessages'][] = _('This extension is not assigned to this user.');
			}
		}

		return $data;
	}

	public function getSimpleWidgetList() {
		$responseData = array(
			"rawname" => "donotdisturb",
			"display" => _("Do Not Disturb"),
			"icon" => "fa fa-power-off",
			"list" => []
		);
		$errors = $this->validate();
		if ($errors['hasError']) {
			return array_merge($responseData, $errors);
		}

		$widgets = array();

		$extensions = $this->UCP->getCombinedSettingByID($this->userId,'Settings','assigned');

		if (!empty($extensions)) {
			foreach($extensions as $extension) {
				$data = $this->UCP->FreePBX->Core->getDevice($extension);
				if(empty($data) || empty($data['description'])) {
					$data = $this->UCP->FreePBX->Core->getUser($extension);
					$name = $data['name'] ?? '';
				} else {
					$name = $data['description'];
				}

				$widgets[$extension] = array(
					"display" => $name,
					"description" => sprintf(_("Do Not Disturb for %s"),$name),
					"defaultsize" => array("height" => 2, "width" => 1),
					"minsize" => array("height" => 2, "width" => 1)
				);
			}
		}

		$responseData['list'] = $widgets;
		return $responseData;
	}

	public function getWidgetDisplay($id) {
		$errors = $this->validate($id);
		if ($errors['hasError']) {
			return $errors;
		}

		$displayvars = array(
			"extension" => $id,
			"enabled" => $this->UCP->FreePBX->Donotdisturb->getStatusByExtension($id)
		);

		$display = array(
			'title' => _("Follow Me"),
			'html' => $this->load_view(__DIR__.'/views/widget.php',$displayvars)
		);

		return $display;
	}

	public function getSimpleWidgetSettingsDisplay($id) {
		return $this->getWidgetSettingsDisplay($id);
	}

	/**
	 * Determine what commands are allowed
	 *
	 * Used by Ajax Class to determine what commands are allowed by this class
	 *
	 * @param string $command The command something is trying to perform
	 * @param string $settings The Settings being passed through $_POST or $_PUT
	 * @return bool True if pass
	 */
	function ajaxRequest($command, $settings) {
		if(!$this->_checkExtension($_POST['ext'])) {
			return false;
		}
		switch($command) {
			case 'enable':
				return true;
			default:
				return false;
			break;
		}
	}

	/**
	 * The Handler for all ajax events releated to this class
	 *
	 * Used by Ajax Class to process commands
	 *
	 * @return mixed Output if success, otherwise false will generate a 500 error serverside
	 */
	function ajaxHandler() {
		$return = array("status" => false, "message" => "");
		switch($_REQUEST['command']) {
			case 'enable':
				if($_POST['enable'] == 'true') {
					$this->UCP->FreePBX->Donotdisturb->setStatusByExtension($_POST['ext'],"YES");
				} else {
					$this->UCP->FreePBX->Donotdisturb->setStatusByExtension($_POST['ext']);
				}
				return array("status" => true, "alert" => "success", "message" => _('Do Not Disturb Has Been Updated!'));
				break;
			default:
				return $return;
			break;
		}
	}

	private function _checkExtension($extension) {
		$extensions = $this->UCP->getCombinedSettingByID($this->userId,'Settings','assigned');
		$extensions = is_array($extensions) ? $extensions : array();
		return in_array($extension,$extensions);
	}
}
