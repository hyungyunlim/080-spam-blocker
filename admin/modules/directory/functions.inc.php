<?php
if (!defined('FREEPBX_IS_AUTH')) {
	die('No direct script access allowed');
}
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
function directory_configpageload() {
	global $currentcomponent, $display;
	if ($display == 'directory' && (isset($_REQUEST['action']) && $_REQUEST['action'] == 'add' || isset($_REQUEST['id']) && $_REQUEST['id'] != '')) {
		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'add') {
			$currentcomponent->addguielem('_top', new gui_pageheading('title', _('Add Directory')), 0);

			$deet = [ 'dirname', 'description', 'repeat_loops', 'announcement', 'repeat_recording', 'invalid_recording', 'callid_prefix', 'alert_info', 'invalid_destination', 'retivr', 'say_extension', 'id', 'rvolume' ];

			foreach ($deet as $d) {
				$dir[$d] = match ($d) {
					'repeat_loops' => 2,
					'announcement', 'repeat_recording', 'invalid_recording' => 0,
					default => '',
				};
			}
		}
		else {
			$dir     = directory_get_dir_details($_REQUEST['id']);
			$label   = sprintf(_("Edit Directory: %s"), $dir['dirname'] ?: 'ID ' . $dir['id']);
			$def_dir = directory_get_default_dir();
			if ($dir['id'] == $def_dir) {
				$label .= ' ' . _("[SYSTEM DEFAULT]");
			}
			$currentcomponent->addguielem('_top', new gui_pageheading('title', $label), 0);
			//display usage
			$usage_list         = framework_display_destination_usage(directory_getdest($dir['id']));
			$usage_list_text    = $usage_list['text'] ?? '';
			$usage_list_tooltip = $usage_list['tooltip'] ?? '';
			if (!empty($usage_list)) {
				$currentcomponent->addguielem('_top', new gui_link_label('usage', $usage_list_text, $usage_list_tooltip), 0);
			}
			//display delete link
			$label = sprintf(_("Delete Directory %s"), $dir['dirname'] ?: $dir['id']);
			$label = '<span><img width="16" height="16" border="0" title="'
				. $label . '" alt="" src="images/core_delete.png"/>&nbsp;' . $label . '</span>';
			$currentcomponent->addguielem('_top', new gui_link('del', $label, '?' . $_SERVER['QUERY_STRING'] . '&action=delete', true, false), 0);
		}
		//delete link, dont show if we dont have an id (i.e. directory wasnt created yet)
		$gen_section = _('Directory General Options');
		$category    = "other";
		$currentcomponent->addguielem($gen_section, new gui_textbox('dirname', stripslashes((string) $dir['dirname']), _('Directory Name'), _('Name of this directory.')), $category);
		$currentcomponent->addguielem($gen_section, new gui_textbox('description', stripslashes((string) $dir['description']), _('Directory Description'), _('Description of this directory.')), $category);
		$currentcomponent->addguielem($gen_section, new gui_textbox('callid_prefix', stripslashes((string) $dir['callid_prefix']), _('CallerID Name Prefix'), _('Prefix to be appended to current CallerID Name.')), $category);
		$currentcomponent->addguielem($gen_section, new gui_textbox('alert_info', stripslashes((string) $dir['alert_info']), _('Alert Info'), _('ALERT_INFO to be sent when called from this Directory. Can be used for distinctive ring for SIP devices.')), $category);

		$section = _('Directory Options (DTMF)');

		//build recordings select list
		$currentcomponent->addoptlistitem('recordings', 0, _('Default'));
		foreach (recordings_list() as $r) {
			$currentcomponent->addoptlistitem('recordings', $r['id'], $r['displayname']);
		}
		$currentcomponent->setoptlistopts('recordings', 'sort', false);
		//build repeat_loops select list and defualt it to 3
		for ($i = 0; $i < 11; $i++) {
			$currentcomponent->addoptlistitem('repeat_loops', $i, $i);
		}

		//generate page
		$currentcomponent->addguielem($section, new gui_selectbox('announcement', $currentcomponent->getoptlist('recordings'), $dir['announcement'], _('Announcement'), _('Greeting to be played on entry to the directory.'), false), $category);
		$currentcomponent->addguielem($section, new gui_selectbox('repeat_loops', $currentcomponent->getoptlist('repeat_loops'), $dir['repeat_loops'], _('Invalid Retries'), _('Number of times to retry when receiving an invalid/unmatched response from the caller'), false), $category);
		$currentcomponent->addguielem($section, new gui_selectbox('repeat_recording', $currentcomponent->getoptlist('recordings'), $dir['repeat_recording'], _('Invalid Retry Recording'), _('Prompt to be played when an invalid/unmatched response is received, before prompting the caller to try again'), false), $category);
		$currentcomponent->addguielem($section, new gui_selectbox('invalid_recording', $currentcomponent->getoptlist('recordings'), $dir['invalid_recording'], _('Invalid Recording'), _('Prompt to be played before sending the caller to an alternate destination due to the caller pressing 0 or receiving the maximum amount of invalid/unmatched responses (as determined by Invalid Retries)'), false), $category);
		$currentcomponent->addguielem($section, new gui_drawselects('invalid_destination', 0, $dir['invalid_destination'], _('Invalid Destination'), _('Destination to send the call to after Invalid Recording is played.'), false), $category);
		$currentcomponent->addguielem($section, new gui_checkbox('retivr', $dir['retivr'], _('Return to IVR'), _('When selected, if the call passed through an IVR that had "Return to IVR" selected, the call will be returned there instead of the Invalid destination.'), true), $category);
		$currentcomponent->addguielem($section, new gui_checkbox('say_extension', $dir['say_extension'], _('Announce Extension'), _('When checked, the extension number being transferred to will be announced prior to the transfer'), true), $category);
		$currentcomponent->addguielem($section, new gui_hidden('id', $dir['id']), $category);
		$currentcomponent->addguielem($section, new gui_hidden('action', 'edit'), $category);

		//TODO: the &nbsp; needs to be here instead of a space, guielements freaks for some reason with this specific section name
		$section = _('Directory&nbsp;Entries');
		//draw the entries part of the table. A bit hacky perhaps, but hey - it works!
		$currentcomponent->addguielem($section, new guielement('rawhtml', directory_draw_entries($dir['id']), ''));
	}
}

function directory_configpageinit($pagename) {
	global $currentcomponent;
	if ($pagename == 'directory') {
		return true;
	}
	if ($pagename == 'ivr') {
		$action = $_REQUEST['action'] ?? '';
		$id     = $_REQUEST['id'] ?? '';
		if ($action || $id) {
			//add help text
			$currentcomponent->addgeneralarrayitem('directdial_help', 'directory',
				_('Tied to a Directory allowing all entries in that directory '
					. 'to be dialed directly, as they appear in the directory'));

			//add gui items
			foreach ((array) directory_list() as $dir) {
				$name = $dir['dirname'] ?: 'Directory ' . $dir['id'];
				$currentcomponent->addoptlistitem('directdial', $dir['id'], $name);
			}
		}
		return true;
	}

	// We only want to hook 'users' or 'extensions' pages.
	if ($pagename != 'users' && $pagename != 'extensions') {
		return true;
	}

	$action        = $_REQUEST['action'] ?? null;
	$extdisplay    = $_REQUEST['extdisplay'] ?? null;
	$extension     = $_REQUEST['extension'] ?? null;
	$tech_hardware = $_REQUEST['tech_hardware'] ?? null;

	if ($tech_hardware != null || $pagename == 'users') {
		directory_applyhooks();
		$currentcomponent->addprocessfunc('directory_configprocess_exten', 8);
	}
	elseif ($action == "add") {
		// We don't need to display anything on an 'add', but we do need to handle returned data.
		$currentcomponent->addprocessfunc('directory_configprocess_exten', 8);
	}
	elseif ($extdisplay != '') {
		// We're now viewing an extension, so we need to display _and_ process.
		directory_applyhooks();
		$currentcomponent->addprocessfunc('directory_configprocess_exten', 8);
	}
}

function directory_get_config($engine) {
	global $ext, $db;
	switch ($engine) {
		case 'asterisk':
			$sql = 'SELECT id,dirname,say_extension,retivr FROM directory_details ORDER BY dirname';
			$results = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
			if ($results) {
				$c = 'directory';
				// Note create a dial-id label for each directory to allow other modules to hook on a per
				// directory basis. (Otherwise we could have consolidated this into a call extension)
				foreach ($results as $row) {
					$ext->add($c, $row['id'], '', new ext_answer(''));
					$ext->add($c, $row['id'], '', new ext_wait('1'));
					$ext->add($c, $row['id'], '', new ext_agi('directory.agi,dir=' . $row['id']
						. ',retivr=' . ($row['retivr'] ? 'true' : 'false')
					));
					if ($row['say_extension']) {
						$ext->add($c, $row['id'], '', new ext_playback('pls-hold-while-try&to-extension'));
						$ext->add($c, $row['id'], '', new ext_saydigits('${DIR_DIAL}'));
					}
					$ext->add($c, $row['id'], 'dial-' . $row['id'], new ext_ringing());
					$ext->add($c, $row['id'], '', new ext_goto('1', '${DIR_DIAL}', 'from-internal'));
				}
				$ext->add($c, 'invalid', 'invalid', new ext_playback('${DIR_INVALID_RECORDING}'));
				$ext->add($c, 'invalid', '', new ext_ringing());
				$ext->add($c, 'invalid', '', new ext_goto('${DIR_INVALID_PRI}', '${DIR_INVALID_EXTEN}', '${DIR_INVALID_CONTEXT}'));
				$ext->add($c, 'retivr', 'retivr', new ext_playback('${DIR_INVALID_RECORDING}'));
				$ext->add($c, 'retivr', '', new ext_goto('1', 'return', '${IVR_CONTEXT}'));
				$ext->add($c, 'h', '', new ext_macro('hangupcall'));
			}
			break;
	}
}

function directory_list() {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Directory()->listDirectories();
}

function directory_get_dir_entries($id) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Directory()->getEntriesById($id);
}

function directory_get_dir_details($id) {
	global $db;
	$clean_id = $db->escapeSimple($id);
	$sql      = "SELECT * FROM directory_details WHERE ID = $clean_id";
	$row      = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
	return $row;
}

function directory_delete($id) {
	global $db;
	$id = $db->escapeSimple($id);

	if (directory_get_default_dir() == $id) {
		directory_save_default_dir('');
	}
	sql("DELETE FROM directory_entries WHERE id = $id");
	sql("DELETE FROM directory_details WHERE id = $id");
}

function directory_destinations() {
	global $db;
	$sql     = 'SELECT id,dirname FROM directory_details ORDER BY dirname';
	$results = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

	foreach ($results as $row) {
		$row['dirname'] = $row['dirname'] ?: 'Directory ' . $row['id'];
		$extens[]       = [ 'destination' => 'directory,' . $row['id'] . ',1', 'description' => $row['dirname'], 'category' => 'Directory' ];
	}
	return $extens ?? null;
}

function directory_draw_entries_table_header_directory() {
	return [ _('Name'), _('Name Announcement'), _('Dial'), _('Actions') ];
}
function add_help_msg($help_id) {
	$help = [ _('Name') => _("This should auto-populate with the extension's descriptive name. This is what users will search by when asked to enter the first 3 letters of the person's name. For example, if the name is Bartholomew, the caller would enter 227 for BAR. This field should be a name as the search option is based on Name and not the number.") ];
	if (isset($help[$help_id])) {
		return $help_id . '<span class="help"><i class="fa fa-question-circle"></i><span style="display: none;">' . $help[$help_id] . '</span></span>';
	}
	else {
		return $help_id;
	}
}

function directory_draw_entries($id) {
	$sql     = 'SELECT id,name FROM directory_entries ORDER BY name';
	$results = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
	$html    = '';
	$html .= '<table id="dir_entries_tbl" class="table table-striped">';
	$headers = mod_func_iterator('draw_entries_table_header_directory');

	$html .= '<thead><tr>';
	foreach ($headers as $mod => $header) {
		foreach ($header as $h) {
			if (is_array($h)) {
				$html .= '<th ' . $h['attr'] . '/>';
				$html .= add_help_msg($h['val']);
				$html .= '</th>';
			}
			else {
				$html .= '<th>' . add_help_msg($h) . '</th>';
			}
		}
	}
	$html .= '</tr></thead>';

	$newuser = '<select id="addusersel" class="form-control">';
	$newuser .= '<option value="none" selected> == ' . _('Choose One') . ' == </option>';
	$newuser .= '<option value="all">' . _('All Users') . '</option>';
	$newuser .= '<option value="|">' . _('Custom') . '</option>';

	//TODO: could this cause a problem with the '|' separator if a name has a '|' in it? (probably not check for comment where parsed
	foreach ((array) core_users_list() as $user) {
		$newuser .= '<option value="' . $user[0] . '|' . $user[1] . '">(' . $user[0] . ') ' . $user[1] . "</option>\n";
	}
	$newuser .= '</select>';
	$html .= '<tfoot><tr><td id="addbut"><a href="#" class="info"><i class="fa fa-plus" name="image" style="font-size: 20px;cursor:pointer;color:#0070a3;" /><span>' . _('Add new entry.') . '</span></a></td><td colspan="' . ((is_countable(directory_draw_entries_table_header_directory()) ? count(directory_draw_entries_table_header_directory()) : 0) - 1) . '"id="addrow" style="display: none;">' . $newuser . '</td></tr></tfoot>';
	$html .= '<tbody>';
	$entries = directory_get_dir_entries($id);
	$inuse   = [];
	foreach ($entries as $e) {
		$realid       = $e['type'] == 'custom' ? 'custom' : $e['foreign_id'];
		$value        = $e['foreign_id'] . "|" . $e['foreign_name'];
		$foreign_name = $e['foreign_name'] == '' ? 'Custom Entry' : $e['foreign_name'];
		$html .= directory_draw_entries_tr($id, $realid, $e['name'], $foreign_name, $e['audio'], $e['dial'], $e['e_id'], false, $value);
		if ($e['type'] == 'custom') {
			$inuse[] = $e['name'];
		}
		else {
			$inuse[] = $e['foreign_id'] . "|" . $e['foreign_name'];
		}
	}
	$html .= '</tbody></table>';
	$html .= '<script>var inuse = ' . json_encode($inuse, JSON_THROW_ON_ERROR) . '</script>';
	return $html;
}

//used to add row's the entry table

function directory_draw_entries_tr($id, $realid, $name = '', $foreign_name = '', $audio = '', $num = '', $e_id = '', $reuse_audio = false, $dataname = null) {
	$td = [];
	global $amp_conf, $directory_draw_recordings_list, $audio_select;
	if (!$directory_draw_recordings_list) {
		$directory_draw_recordings_list = recordings_list();
	}
	$e_id = $e_id ?: directory_get_next_id($realid);
	if (!$audio_select || !$reuse_audio) {
		unset($audio_select);
		$audio_select = '<select name="entries[' . $e_id . '][audio]" class="form-control">';
		$audio_select .= '<option value="vm" ' . (($audio == 'vm') ? 'SELECTED' : '') . '>' . _('Voicemail Greeting') . '</option>';
		$audio_select .= '<option value="tts" ' . (($audio == 'tts') ? 'SELECTED' : '') . '>' . _('Text to Speech') . '</option>';
		$audio_select .= '<option value="spell" ' . (($audio == 'spell') ? 'SELECTED' : '') . '>' . _('Spell Name') . '</option>';
		$audio_select .= '<optgroup label="' . _('System Recordings:') . '">';
		foreach ($directory_draw_recordings_list as $r) {
			$audio_select .= '<option value="' . $r['id'] . '" ' . (($audio == $r['id']) ? 'SELECTED' : '') . '>' . $r['displayname'] . '</option>';
		}
		$audio_select .= '</select>';
	}

	if ($realid != 'custom') {
		$user_type = (isset($amp_conf['AMPEXTENSION']) && $amp_conf['AMPEXTENSION']) == 'deviceanduser' ? 'user' : 'extension';
		$tlabel    = sprintf(_("Edit %s: %s"), $user_type, $realid);
		$user      = ' <a href="?display=' . $user_type . 's&skip=0&extdisplay=' . $realid . '"><i class="fa fa-user"></i></a> ';
	}
	else {
		$user = '';
	}
	$delete   = '<i alt="' . _('remove') . '" title="' . _('Click here to remove this entry') . '" class="trash-tr fa fa-trash" style="color:#2779aa;" data-name="' . $dataname . '"></i>';
	$t1_class = $name == '' ? ' class = "dpt-title form-control" ' : 'class="form-control"';
	$t2_class = $realid == 'custom' ? ' placeholder="Custom Dialstring" ' : ' placeholder="' . $realid . '" ';
	if (trim((string) $num) == '') {
		$t2_class .= '" class = "dpt-title form-control" ';
	}
	else {
		$t2_class .= '" class = "form-control"';
	}
	$td[] = '<input type="hidden" readonly="readonly" name="entries[' . $e_id . '][foreign_id]" value="' . $realid . '" /><input type="text" name="entries[' . $e_id . '][name]" placeholder="' . $foreign_name . '"' . $t1_class . ' value="' . $name . '" />';
	$td[] = $audio_select;
	$td[] = '<input type="text" name="entries[' . $e_id . '][num]" ' . $t2_class . ' value="' . $num . '" />';
	$opts = [ 'id' => $id, 'e_id' => $e_id, 'realid' => $realid, 'name' => $name, 'audio' => $audio, 'num' => $num ];

	$more_td = mod_func_iterator('draw_entries_tr_directory', $opts);
	foreach ($more_td as $mod) {
		foreach ($mod as $m) {
			$td[] = $m;
		}
	}

	$td[] = $delete . $user;

	//build html
	$html = '<tr class="entrie' . $e_id . '">';
	foreach ($td as $t) {
		if (is_array($t)) {
			$html .= '<td ' . $t['attr'] . '/>';
			$html .= $t['val'];
			$html .= '</td>';
		}
		else {
			$html .= '<td>' . $t . '</td>';
		}
	}
	$html .= '</tr>';
	return $html;
}

//used to add ALL USERS to the entry table
function directory_draw_entries_all_users($id) {
	$html = '';
	foreach (core_users_list() as $user) {
		$html .= directory_draw_entries_tr($id, $user[0], '', $user[1], 'vm', '', $id++, true);
	}
	return $html;
}


function directory_save_default_dir($default_directory) {
	global $db;

	if ($default_directory) {
		sql("REPLACE INTO `admin` (`variable`, value) VALUES ('default_directory', '$default_directory')");
	}
	else {
		sql("DELETE FROM `admin` WHERE `variable` = 'default_directory'");
	}
}

function directory_get_default_dir() {
	global $db;

	$ret = sql("SELECT value FROM `admin` WHERE `variable` = 'default_directory'", 'getOne');
	return $ret ?: '';

}

function directory_save_dir_details($vals) {
	FreePBX::Modules()->deprecatedFunction();
	if ($vals['id']) {
		return FreePBX::Directory()->updateDirectory($vals);
	}
	return FreePBX::Directory()->addDirectory($vals);
}

function directory_save_dir_entries($id, $entries) {
	FreePBX::Modules()->deprecatedFunction();
	return FreePBX::Directory()->updateEntries($id, $entries);
}

//----------------------------------------------------------------------------
// Deal with default company directory hook

function directory_check_default($extension) {
	$def_dir = directory_get_default_dir();
	$sql     = "SELECT foreign_id FROM directory_entries WHERE foreign_id = '$extension' AND id = '$def_dir' LIMIT 1";
	$results = sql($sql, "getAll");
	return is_countable($results) ? count($results) : 0;
}

function directory_set_default($extension, $value) {
	$default_directory_id = directory_get_default_dir();
	if ($default_directory_id == '') {
		return false;
	}
	if ($value) {
		$entries = sql("SELECT COUNT(*) FROM directory_entries WHERE id = $default_directory_id AND foreign_id = '$extension'", "getOne");
		if (!$entries) {
			$e_id = sql("SELECT MAX(e_id) FROM directory_entries WHERE e_id IS NOT null", "getOne");
			$e_id = $e_id + 1;
			sql("INSERT INTO directory_entries (id, e_id, type, foreign_id, audio) VALUES ($default_directory_id, '$e_id', 'user', '$extension', 'vm')");
		}
	}
	else {
		sql("DELETE FROM directory_entries WHERE id = $default_directory_id AND foreign_id = '$extension'");
	}
}

function directory_applyhooks() {
	global $currentcomponent;

	// Add the 'process' function - this gets called when the page is loaded, to hook into
	// displaying stuff on the page.
	$currentcomponent->addoptlistitem('directory_group', '0', _("Exclude"));
	$currentcomponent->addoptlistitem('directory_group', '1', _("Include"));
	$currentcomponent->setoptlistopts('directory_group', 'sort', false);

	$currentcomponent->addguifunc('directory_configpageload_exten');
}

// This is called before the page is actually displayed, so we can use addguielem().
function directory_configpageload_exten() {
	global $currentcomponent;

	// Init vars from $_REQUEST[]
	$action     = $_REQUEST['action'] ?? null;
	$extdisplay = $_REQUEST['extdisplay'] ?? null;
	// Don't display this stuff it it's on a 'This xtn has been deleted' page.
	if ($action != 'del') {
		$default_directory_id = directory_get_default_dir();
		$section              = _("Default Group Inclusion");
		if ($default_directory_id != "") {
			$in_default_directory = directory_check_default($extdisplay);
			if(empty($extdisplay)){
				$sql ="Select * from admin where variable='default_directory'";
				$row      = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				if(isset($row['value']) && $row['value'] >= 1){
					$in_default_directory = 1;
				}else{
					$in_default_directory = 0;
				}
			}
			$category             = "advanced";
			$currentcomponent->addguielem($section, new gui_selectbox('in_default_directory', $currentcomponent->getoptlist('directory_group'), $in_default_directory, _('Default Directory'), _('You can include or exclude this extension/user from being part of the default directory when creating or editing.'), false), $category);
		}
	}
}

function directory_configprocess_exten() {
	global $db;

	//create vars from the request
	//
	$action               = $_REQUEST['action'] ?? null;
	$ext                  = $_REQUEST['extdisplay'] ?? null;
	$extn                 = $_REQUEST['extension'] ?? null;
	$in_default_directory = $_REQUEST['in_default_directory'] ?? false;

	$extdisplay = ($ext === '') ? $extn : $ext;

	if (($action == "add" || $action == "edit")) {
		if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
			if ($in_default_directory !== false) {
				directory_set_default($extdisplay, $in_default_directory);
			}
		}
	}
	elseif ($extdisplay != '' && $action == "del") {
		$sql = "DELETE FROM directory_entries WHERE foreign_id = '$extdisplay'";
		sql($sql);
	}
}

//----------------------------------------------------------------------------
// Dynamic Destination Registry and Recordings Registry Functions

function directory_check_destinations($dest = true) {
	global $active_modules;

	$destlist = [];
	if (is_array($dest) && empty($dest)) {
		return $destlist;
	}
	$sql = "SELECT id, dirname, invalid_destination FROM directory_details ";
	if ($dest !== true) {
		$sql .= "WHERE invalid_destination in ('" . implode("','", $dest) . "')";
	}
	$results = sql($sql, "getAll", DB_FETCHMODE_ASSOC);

	foreach ($results as $result) {
		$thisdest   = $result['invalid_destination'];
		$thisid     = $result['id'];
		$destlist[] = [ 'dest' => $thisdest, 'description' => sprintf(_("Directory: %s "), ($result['dirname'] ?: $result['id'])), 'edit_url' => 'config.php?display=directory&id=' . urlencode((string) $result['id']) ];
	}
	return $destlist;
}

function directory_change_destination($old_dest, $new_dest) {
	$sql = 'UPDATE directory_details SET invalid_destination = "' . $new_dest . '" WHERE invalid_destination = "' . $old_dest . '"';
	sql($sql, "query");
}

function directory_getdest($id) {
	return [ "directory,$id,1" ];
}

function directory_getdestinfo($dest) {
	if (str_starts_with(trim((string) $dest), 'directory,')) {
		$grp     = explode(',', (string) $dest);
		$id      = $grp[1];
		$thisdir = directory_get_dir_details($id);

		if (empty($thisdir)) {
			return [];
		}
		else {
			return [ 'description' => sprintf(_("Directory %s: "), ($thisdir['dirname'] ?: $id)), 'edit_url' => 'config.php?display=directory&view=form&id=' . urlencode($id) ];
		}
	}
	else {
		return false;
	}
}

function directory_get_next_id($realid) {
	global $db;
	$res = sql('SELECT MAX(e_id) FROM directory_entries WHERE id = "' . $realid . '"', 'getOne');
	return $res ?: 1;
}

function directory_recordings_usage($recording_id) {
	$usage_arr = [];
	global $active_modules;

	$results = sql("SELECT `id`, `dirname` FROM `directory_details`
					WHERE	`announcement` = '$recording_id'
					OR `repeat_recording` = '$recording_id'
					OR `invalid_recording` = '$recording_id'",
		"getAll", DB_FETCHMODE_ASSOC);
	if (empty($results)) {
		return [];
	}
	else {
		//$type = isset($active_modules['ivr']['type'])?$active_modules['ivr']['type']:'setup';
		foreach ($results as $result) {
			$usage_arr[] = [ 'url_query' => 'config.php?display=directory&id=' . urlencode((string) $result['id']), 'description' => sprintf(_("Directory: %s"), ($result['dirname'] ?: $result['id'])) ];
		}
		return $usage_arr;
	}
}