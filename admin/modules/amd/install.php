<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//take a copy of existing amd.conf
//lets read the existing configuration
$dir = \FreePBX::Config()->get('ASTETCDIR');
$existingdata = [];
if(file_exists($dir.'/amd.conf') && !file_exists($dir.'/amd.conf.backup')) {
	$contents = file_get_contents($dir.'/amd.conf');
	if(!str_contains($contents,"Do NOT edit this file as it is auto-generated")) {
		out(_("amd.conf Configuration file found"));
		$lines = parse_ini_string($contents,INI_SCANNER_RAW);
		if(isset($lines['general'])) {
			$existingdata = $lines['general'];
		}
		rename($dir.'/amd.conf',$dir.'/amd.conf.backup');
	}
}

if(!empty($existingdata)) {
	FreePBX::AMD()->addAmdSettings($existingdata);
	out(_("Restoring the existing settings"));
}
