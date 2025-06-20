<?php
if (!defined('FREEPBX_IS_AUTH')) {
	die('No direct script access allowed');
}

require_once __DIR__ . "/functions.inc.php";

global $amp_conf;


// Get the old feature code if it existed to determine
// if it had been changed and if it was enabled
//
$delete_old = false;
$fcc        = new featurecode('daynight', 'toggle-mode');
$code       = $fcc->getCode();
if ($code != '') {
	$delete_old = true;
	$enabled    = $fcc->isEnabled();
	$fcc->delete();
}
unset($fcc);

// If we found the old one then we must create all the new ones
//
if ($delete_old) {
	$list = daynight_list();
	foreach ($list as $item) {
		$id             = $item['ext'];
		$fc_description = $item['dest'];
		$fcc            = new featurecode('daynight', 'toggle-mode-' . $id);
		if ($fc_description) {
			$fcc->setDescription("$id: $fc_description");
		}
		else {
			$fcc->setDescription("$id: Call Flow Toggle");
		}
		$fcc->setDefault('*28' . $id);
		if ($code != '*28' && $code != '') {
			$fcc->setCode($code . $id);
		}
		if (!$enabled) {
			$fcc->setEnabled(false);
		}
		$fcc->update();
		unset($fcc);
	}
}

$fcc = new featurecode('daynight', 'toggle-mode-all');
$fcc->delete();
unset($fcc);


$freepbx_conf =& freepbx_conf::create();

// DAYNIGHTTCHOOK
//
$set['value']       = false;
$set['defaultval']  =& $set['value'];
$set['readonly']    = 0;
$set['hidden']      = 0;
$set['level']       = 1;
$set['module']      = 'daynight';
$set['category']    = 'Call Flow Control Module';
$set['emptyok']     = 0;
$set['name']        = 'Hook Time Conditions Module';
$set['description'] = 'By default, the Call Flow Control module will not hook Time Conditions allowing one to associate a call flow toggle feauture code with a time condition since time conditions have their own feature code as of version 2.9. If there is already an associaiton configured (on an upgraded system), this will have no affect for the Time Conditions that are effected. Setting this to true reverts the 2.8 and prior behavior by allowing for the use of a call flow toggle to be associated with a time conditon. This can be useful for two scenarios. First, to override a Time Condition without the automatic resetting that occurs with the built in Time Condition overrides. The second use is the ability to associate a single call flow toggle with multiple time conditions thus creating a <b>master switch</b> that can be used to override several possible call flows through different time conditions.';
$set['type']        = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('DAYNIGHTTCHOOK', $set, true);