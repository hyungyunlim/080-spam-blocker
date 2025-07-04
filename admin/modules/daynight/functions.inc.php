<?php
if (!defined('FREEPBX_IS_AUTH')) {
	die('No direct script access allowed');
}
/* $Id: functions.inc.php 4024 2007-06-09 03:09:16Z p_lindheimer $ */
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
// Class To Create, Access and Change DAYNIGHT objects in the dialplan
//
#[\AllowDynamicProperties]
class dayNightObject {

	// contstructor
	function __construct($id) {
		global $amp_conf;
		$this->id = $id;
		if ($amp_conf['USEDEVSTATE']) {
			$engine_info    = engine_getinfo();
			$version        = $engine_info['version'];
			$this->DEVSTATE = version_compare($version, "1.6", "ge") ? "DEVICE_STATE" : "DEVSTATE";
		}
		else {
			$this->DEVSTATE = false;
		}
	}

	function getState() {
		global $astman;

		if ($astman != null) {
			$mode = $astman->database_get("DAYNIGHT", "C" . ($this->id??''));
			if ($mode != "DAY" && $mode != "NIGHT") {
				// TODO: should this return an error?
				return false;
			}
			else {
				return $mode;
			}
		}
		else {
			die_freepbx("No open connection to asterisk manager, can not access object.");
		}
	}

	function setState($state) {
		global $astman;

		if ($this->getState() === false) {
			die_freepbx("You must create the object before setting the state.");
			return false;
		}
		else {
			switch ($state) {
				case "DAY":
				case "NIGHT":
					if ($astman != null) {
						$astman->database_put("DAYNIGHT", "C" . $this->id, $state);
						if ($this->DEVSTATE) {
							$value_opt = ($state == 'DAY') ? 'NOT_INUSE' : 'INUSE';
							$astman->set_global($this->DEVSTATE . "(Custom:DAYNIGHT" . $this->id . ")", $value_opt);
						}
					}
					else {
						die_freepbx("No open connection to asterisk manager, can not access object.");
					}
					break;
				default:
					die_freepbx("Invalid state: $state");
					break;
			}
		}
	}

	function create($state = "DAY") {
		global $astman;

		$current_state = $this->getState();
		if ($current_state !== false) {
			die_freepbx("Object already exists and is in state: $current_state, you must delete it first");
			return false;
		}
		else {
			switch ($state) {
				case "DAY":
				case "NIGHT":
					if ($astman != null) {
						$astman->database_put("DAYNIGHT", "C" . $this->id, $state);
						if ($this->DEVSTATE) {
							$value_opt = ($state == 'DAY') ? 'NOT_INUSE' : 'INUSE';
							$astman->set_global($this->DEVSTATE . "(Custom:DAYNIGHT" . $this->id . ")", $value_opt);
						}
					}
					else {
						die_freepbx("No open connection to asterisk manager, can not access object.");
					}
					break;
				default:
					die_freepbx("Invalid state: $state");
					break;
			}
		}
	}

	function del() {
		global $astman;

		if ($astman != null) {
			$astman->database_del("DAYNIGHT", "C" . $this->id);
		}
		else {
			die_freepbx("No open connection to asterisk manager, can not access object.");
		}
	}
}

// The destinations this module provides
// returns a associative arrays with keys 'destination' and 'description'
function daynight_destinations() {

	$list = daynight_list();
	foreach ($list as $item) {
		$dests = daynight_get_obj($item['ext']);
		if (!isset($dests['day']) || !isset($dests['night'])) {
			continue;
		}
		$description = $item['dest'] != "" ? $item['dest'] : "Call Flow Toggle";
		$description = "(" . $item['ext'] . ") " . $description;
		$extens[]    = [ 'destination' => 'app-daynight,' . $item['ext'] . ',1', 'description' => $description ];
	}

	// return an associative array with destination and description
	if (isset($extens))
		return $extens;
	else
		return null;
}

function daynight_getdest($exten) {
	return [ 'app-daynight,' . $exten . ',1' ];
}

function daynight_getdestinfo($dest) {
	global $active_modules;

	if (str_starts_with(trim((string) $dest), 'app-daynight,')) {
		$exten = explode(',', (string) $dest);
		$exten = $exten[1];

		$thisexten = [];
		$thislist  = daynight_list();
		foreach ($thislist as $item) {
			if ($item['ext'] == $exten) {
				$thisexten = $item;
				break;
			}
		}
		if (empty($thisexten)) {
			return [];
		}
		else {
			//$type = isset($active_modules['announcement']['type'])?$active_modules['announcement']['type']:'setup';
			return [ 'description' => sprintf(_("Call Flow Toggle (%s) : %s"), $exten, $thisexten['dest']), 'edit_url' => 'config.php?display=daynight&view=form&itemid=' . urlencode($exten) ];
		}
	}
	else {
		return false;
	}
}

function daynight_get_config($engine) {
	global $ext;

	switch ($engine) {
		case "asterisk":

			$id = "app-daynight"; // The context to be included

			$list = daynight_list();

			foreach ($list as $item) {
				$dests = daynight_get_obj($item['ext']);
				$ext->add($id, $item['ext'], '', new ext_gotoif('$["${DB(DAYNIGHT/C${EXTEN})}" = "NIGHT"]', $dests['night'], $dests['day']));
			}

			daynight_toggle();

			break;
	}
}

function daynight_toggle() {
	global $ext;
	global $amp_conf;
	global $version;

	$list      = daynight_list();
	$passwords = daynight_passwords();
	$got_code  = false;

	$day_recording   = daynight_recording('day');
	$night_recording = daynight_recording('night');

	$id = "app-daynight-toggle"; // The context to be included
	foreach ($list as $item) {
		$index = $item['ext'];
		$fcc   = new featurecode('daynight', 'toggle-mode-' . $index);
		$c     = $fcc->getCodeActive();
		unset($fcc);
		if (!$c) {
			continue;
		}
		$got_code = true;
		if ($amp_conf['USEDEVSTATE']) {
			$ext->addHint($id, $c, 'Custom:DAYNIGHT' . $index);
		}
		$ext->add($id, $c, '', new ext_macro('user-callerid'));
		$ext->add($id, $c, '', new ext_answer(''));
		$ext->add($id, $c, '', new ext_wait('1'));
		if (isset($passwords[$index]) && trim((string) $passwords[$index]) != "" && ctype_digit(trim((string) $passwords[$index]))) {
			$ext->add($id, $c, '', new ext_authenticate($passwords[$index]));
		}
		$ext->add($id, $c, '', new ext_setvar('INDEXES', $index));
		// Depends on featurecode.sln which is provided in core's sound files
		//
		$day_file   = "beep&silence/1&featurecode&digits/{$index}&de-activated";
		$night_file = "beep&silence/1&featurecode&digits/{$index}&activated";
		if (function_exists('recordings_get_file')) {
			if ($day_recording[$index] != 0) {
				$day_file = recordings_get_file($day_recording[$index]);
			}
			if ($night_recording[$index] != 0) {
				$night_file = recordings_get_file($night_recording[$index]);
			}
		}
		$ext->add($id, $c, '', new ext_setvar('DAYREC', $day_file));
		$ext->add($id, $c, '', new ext_setvar('NIGHTREC', $night_file));
		$ext->add($id, $c, '', new ext_goto($id . ',s,1'));
	}

	if ($got_code) {
		$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

		$c = 's';
		/* If any are on, all will be turned off.
		 * Otherwise, all will be turned on.
		 */
		$ext->add($id, $c, '', new ext_setvar('LOOPCNT', '${FIELDQTY(INDEXES,&)}'));
		$ext->add($id, $c, '', new ext_setvar('ITER', '1'));
		$ext->add($id, $c, 'begin1', new ext_setvar('INDEX', '${CUT(INDEXES,&,${ITER})}'));

		$ext->add($id, $c, '', new ext_setvar('MODE', '${DB(DAYNIGHT/C${INDEX})}'));
		$ext->add($id, $c, '', new ext_gotoif('$["${MODE}" != "NIGHT"]', 'end1'));

		$ext->add($id, $c, '', new ext_setvar('DAYNIGHTMODE', 'NIGHT'));

		$ext->add($id, $c, 'end1', new ext_setvar('ITER', '$[${ITER} + 1]'));
		$ext->add($id, $c, '', new ext_gotoif('$[${ITER} <= ${LOOPCNT}]', 'begin1'));

		$ext->add($id, $c, '', new ext_setvar('LOOPCNT', '${FIELDQTY(INDEXES,&)}'));
		$ext->add($id, $c, '', new ext_setvar('ITER', '1'));
		$ext->add($id, $c, 'begin2', new ext_setvar('INDEX', '${CUT(INDEXES,&,${ITER})}'));

		$ext->add($id, $c, '', new ext_gotoif('$["${DAYNIGHTMODE}" = "NIGHT"]', 'day', 'night'));

		$ext->add($id, $c, 'day', new ext_setvar('DB(DAYNIGHT/C${INDEX})', 'DAY'));
		if ($amp_conf['USEDEVSTATE']) {
			$ext->add($id, $c, '', new ext_setvar($amp_conf['AST_FUNC_DEVICE_STATE'] . '(Custom:DAYNIGHT${INDEX})', 'NOT_INUSE'));
		}
		$ext->add($id, $c, 'hook_day', new ext_goto('end2'));

		$ext->add($id, $c, 'night', new ext_setvar('DB(DAYNIGHT/C${INDEX})', 'NIGHT'));
		if ($amp_conf['USEDEVSTATE']) {
			$ext->add($id, $c, '', new ext_setvar($amp_conf['AST_FUNC_DEVICE_STATE'] . '(Custom:DAYNIGHT${INDEX})', 'INUSE'));
		}
		$ext->add($id, $c, 'hook_night', new ext_goto('end2'));

		$ext->add($id, $c, 'end2', new ext_setvar('ITER', '$[${ITER} + 1]'));
		$ext->add($id, $c, '', new ext_gotoif('$[${ITER} <= ${LOOPCNT}]', 'begin2'));

		if ($amp_conf['FCBEEPONLY']) {
			$ext->add($id, $c, '', new ext_playback('beep')); // $cmd,n,Playback(...)
		}
		else {
			$ext->add($id, $c, '', new ext_execif('$["${DAYNIGHTMODE}" = "NIGHT"]', 'Playback', '${DAYREC}', 'Playback', '${NIGHTREC}'));
		}
		$ext->add($id, $c, '', new ext_hangup(''));
	}
}

function daynight_get_avail() {
	global $db;
	$sql = "SELECT ext FROM daynight ORDER BY ext";
	$sth = $db->prepare($sql);
	$sth->execute();
	$results = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
	if (DB::IsError($results)) {
		$results = [];
	}
	$list = [];
	for ($i = 0; $i <= 99; $i++) {
		if (!in_array($i, $results)) {
			$list[] = $i;
		}
	}
	return $list;
}

//get the existing daynight codes
function daynight_list() {
	$results = sql("SELECT ext, dest FROM daynight WHERE dmode = 'fc_description' ORDER BY ext", "getAll", DB_FETCHMODE_ASSOC);
	if (is_array($results)) {
		foreach ($results as $result) {
			$list[] = $result;
		}
	}
	if (isset($list)) {
		return $list;
	}
	else {
		return [];
	}
}

//get the existing password codes
function daynight_passwords() {
	$results = sql("SELECT ext, dest FROM daynight WHERE dmode = 'password'", "getAll", DB_FETCHMODE_ASSOC);
	if (is_array($results)) {
		foreach ($results as $result) {
			$list[$result['ext']] = $result['dest'];
		}
	}
	if (isset($list)) {
		return $list;
	}
	else {
		return [];
	}
}

//get the existing daynight recordings
//$mode is either 'day' or 'night'
function daynight_recording($mode) {
	$results = sql("SELECT ext, dest FROM daynight WHERE dmode = '" . $mode . "_recording_id'", "getAll", DB_FETCHMODE_ASSOC);
	if (is_array($results)) {
		foreach ($results as $result) {
			$list[$result['ext']] = $result['dest'];
		}
	}
	if (isset($list)) {
		return $list;
	}
	else {
		return [];
	}
}

function daynight_edit($post, $id = 0) {
	return \FreePBX::Daynight()->edit($post, $id);
}

function daynight_del($id) {
	return \FreePBX::Daynight()->del($id, true);
}

function daynight_get_obj($id = 0) {
	$dmodes = [];
	global $db;

	$sql = "SELECT dmode, dest FROM daynight WHERE dmode IN ('day', 'night', 'password', 'fc_description','day_recording_id','night_recording_id') AND ext = '$id' ORDER BY dmode";
	$res = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($res)) {
		return null;
	}
	foreach ($res as $pair) {
		$dmodes[$pair['dmode']] = $pair['dest'];
	}
	$dn              = new dayNightObject($id);
	$dmodes['state'] = $dn->getState();

	return $dmodes;
}

/*
SELECT s1.ext ext, dest, dmode, s2.description descirption FROM daynight s1
INNER JOIN
	(
				  SELECT ext, dest description FROM daynight WHERE dmode = 'fc_description') s2
						ON s1.ext = s2.ext WHERE dmode in ('day','night')
						AND dest = '$dest'

Provides: ext, dest, dmode, description
*/
function daynight_check_destinations($dest = true) {
	global $active_modules;

	$destlist = [];
	if (is_array($dest) && empty($dest)) {
		return $destlist;
	}
	$sql = "
		SELECT s1.ext ext, dest, dmode, s2.description description FROM daynight s1
		INNER JOIN
    		(
					SELECT ext, dest description FROM daynight WHERE dmode = 'fc_description') s2
					ON s1.ext = s2.ext WHERE dmode in ('day','night')
		";
	if ($dest !== true) {
		$sql .= "AND dest in ('" . implode("','", $dest) . "')";
	}
	$results = sql($sql, "getAll", DB_FETCHMODE_ASSOC);

	//$type = isset($active_modules['announcement']['type'])?$active_modules['announcement']['type']:'setup';

	foreach ($results as $result) {
		$thisdest   = $result['dest'];
		$thisid     = $result['ext'];
		$destlist[] = [ 'dest' => $thisdest, 'description' => sprintf(_("Call Flow Toggle: %s (%s)"), $result['description'], $result['dmode']), 'edit_url' => 'config.php?display=daynight&view=form&itemid=' . urlencode((string) $thisid) ];
	}
	return $destlist;
}

function daynight_change_destination($old_dest, $new_dest) {
	$sql  = 'UPDATE daynight SET dest = :dest WHERE dest = :olddest';
	$stmt = FreePBX::Database()->prepare($sql);
	return $stmt->execute([ ':dest' => $new_dest, ':olddest' => $old_dest ]);
}

//-----------------------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
// TIMECONDITIONS HOOK:
//
// Helper Functions
//

// Note only one of these should be set, a feature code can't be associated with both the day and night mode
function daynight_get_timecondition($id = 0) {
	global $db;

	$sql = "SELECT ext, dmode FROM daynight WHERE dmode IN ('timeday', 'timenight') AND dest = '$id' ORDER BY dmode";
	$res = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($res)) {
		return null;
	}
	// we will start the loop but only return the first occurence since there should only be one
	if (empty($res)) {
		return [ 'ext' => '', 'dmode' => '' ];
	}
	else {
		foreach ($res as $pair) {
			return $pair;
		}
	}
}

function daynight_list_timecondition($daynight_id = 'all') {
	global $db;

	if ($daynight_id == 'all') {
		$results = sql("SELECT ext, dmode, dest FROM daynight WHERE dmode IN ('timeday', 'timenight') ORDER BY dest", "getAll", DB_FETCHMODE_ASSOC);
	}
	else {
		$results = sql("SELECT ext, dmode, dest FROM daynight WHERE dmode IN ('timeday', 'timenight') AND `ext` = '$daynight_id' ORDER BY CAST(dest AS UNSIGNED)", "getAll", DB_FETCHMODE_ASSOC);
	}
	return $results;
}

function daynight_edit_timecondition($viewing_itemid, $daynight_ref) {
	global $db;

	$sql = "DELETE FROM `daynight` WHERE `dmode` IN ('timeday', 'timenight') AND dest = '$viewing_itemid'";
	$res = $db->getAll($sql, DB_FETCHMODE_ASSOC);

	if ($daynight_ref != '') {
		$daynight_vals = explode(',', (string) $daynight_ref, 2);
		$sql           = "INSERT INTO `daynight` (`ext`, `dmode`, `dest`) VALUES ('" . $daynight_vals[0] . "', '" . $daynight_vals[1] . "', '$viewing_itemid')";
		sql($sql);
	}
}

function daynight_add_timecondition($daynight_ref) {
	global $db;

	// We don't know what the new timecondition id is yet so we will put a place holder and check it when the page reloads
	//
	daynight_edit_timecondition('add', $daynight_ref);
}

function daynight_checkadd_timecondition() {
	$timeconditions_ids = [];
	global $db;

	$sql = "SELECT ext FROM daynight WHERE dmode IN ('timeday', 'timenight') AND dest = 'add'";
	$res = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if (DB::IsError($res)) {
		return null;
	}

	// If we find anything, then we get the highest timeconditions_id which should be the last one inserted
	//
	if (!empty($res)) {

		$timeconditions_arr = timeconditions_list();

		foreach ($timeconditions_arr as $item) {
			$timeconditions_ids[] = $item['timeconditions_id'];
		}
		rsort($timeconditions_ids);
		$viewing_itemid = $timeconditions_ids[0];

		$sql = "UPDATE `daynight` SET `dest` = '$viewing_itemid' WHERE `dest` = 'add'";
		sql($sql);
	}
}

function daynight_del_timecondition($viewing_itemid) {
	global $db;

	$sql = "DELETE FROM `daynight` WHERE `dmode` IN ('timeday', 'timenight') AND dest = '$viewing_itemid'";
	$res = $db->getAll($sql, DB_FETCHMODE_ASSOC);
}

// -----------------------------------------------------------------
// Hooks to associate a daynight featurecode with a timecondition
//
function daynight_hook_timeconditions($viewing_itemid, $target_menuid) {
	global $tabindex;
	global $amp_conf;
	switch ($target_menuid) {
		// only provide display for timeconditions
		case 'timeconditions':
			$current = daynight_get_timecondition($viewing_itemid);
			if (!$amp_conf['DAYNIGHTTCHOOK'] && $current['ext'] == '') {
				break;
			}
			$daynightcodes = daynight_list();
			$dnopts = sprintf('<option value="" %s>%s</option>', $current['ext'] == '' ? 'selected' : '', _("No Association"));
			foreach ($daynightcodes as $dn_item) {
				$dnopts .= sprintf('<option value="%d,timeday" %s>%s</option>', $dn_item['ext'], ($current['ext'] . ',' . $current['dmode'] == $dn_item['ext'] . ',timeday' ? 'selected' : ''), $dn_item['dest'] . _(" - Force Time Condition True Destination"));
				$dnopts .= "\n";
				$dnopts .= sprintf('<option value="%d,timenight" %s>%s</option>', $dn_item['ext'], ($current['ext'] . ',' . $current['dmode'] == $dn_item['ext'] . ',timenight' ? 'selected' : ''), $dn_item['dest'] . _(" - Force Time Condition False Destination"));
				$dnopts .= "\n";
			}
			$html = '
				<!--Call Flow Toggle Mode Association-->
				<div class="element-container">
					<div class="row">
						<div class="col-md-12">
							<div class="row">
								<div class="form-group">
									<div class="col-md-3">
										<label class="control-label" for="daynight_ref">' . _("Call Flow Toggle Associate with") . '</label>
										<i class="fa fa-question-circle fpbx-help-icon" data-for="daynight_ref"></i>
									</div>
									<div class="col-md-9">
										<select class="form-control" id="daynight_ref" name="daynight_ref">
											' . $dnopts . '
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<span id="daynight_ref-help" class="help-block fpbx-help-block">' . _("If a selection is made, this timecondition will be associated with the specified call flow toggle  featurecode. This means that if the Call Flow Feature code is set to override (Red/BLF on) then this time condition will always go to its True destination if the chosen association is to 'Force Time Condition True Destination' and it will always go to its False destination if the association is with the 'Force Time Condition False Destination'. When the associated Call Flow Control Feature code is in its Normal mode (Green/BLF off), then then this Time Condition will operate as normal based on the current time. The Destinations that are part of any Associated Call Flow Control Feature Code will have no affect on where a call will go if passing through this time condition. The only thing that is done when making an association is allowing the override state of a Call Flow Toggle to force this time condition to always follow one of its two destinations when that associated Call Flow Toggle is in its override (Red/BLF on) state.") . '</span>
						</div>
					</div>
				</div>
				<!--END Call Flow Toggle Mode Association-->
			';
			return $html;
			break;
		default:
			return false;
			break;
	}
}

function daynight_hookProcess_timeconditions($viewing_itemid, $request) {
	//moved to a BMO hook
}

// Splice into the timecondition dialplan and put an override if associated with a daynight mode code
//
function daynight_hookGet_config($engine) {
	global $ext; // is this the best way to pass this?
	switch ($engine) {
		case "asterisk":
			if (!function_exists('timeconditions_get')) {
				return true;
			}
			$overrides = daynight_list_timecondition();
			$context = "timeconditions";

			if (is_array($overrides)) {
				foreach ($overrides as $item) {
					$daynight_id        = $item['ext'];
					$mode               = ($item['dmode'] == 'timeday') ? 'DAY' : 'NIGHT';
					$timecondition_id   = $item['dest'];
					$timeconditions_arr = timeconditions_get($timecondition_id);
					if (is_array($timeconditions_arr)) {
						$dest = ($mode == 'DAY') ? 'truestate' : 'falsestate';
						$ext->splice($context, $timecondition_id, 0, new ext_gotoif('$["${DB(DAYNIGHT/C' . $daynight_id . ')}" = "' . $mode . '"]', $dest));
					}
				}
			}
			break;
	}
}