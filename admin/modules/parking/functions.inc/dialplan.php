<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/* 	Generates dialplan for "parking" components
	We call this with retrieve_conf
*/

/** parking_get_config
 * Short dialplan generation for this module
 * Long dialplan generation as well as population of conf_classes etc
 * that this module is responsible for.
 */

function parking_get_config($engine) {
	global $db;
	global $amp_conf;
	global $ext;  // is this the best way to pass this?
	global $asterisk_conf;
	global $core_conf;
	global $version;

	switch($engine) {
	case "asterisk":

		// Some contexts used throughout:
		//
		$por = 'park-orphan-routing';
		$ph  = 'park-hints';
		$pd  = 'park-dial';
        $lot = parking_get();
		parking_generate_parked_call();
		parking_generate_parkedcallstimeout();
		parking_generate_park_dial($pd, $por, $lot);


		//--------------------------------------
		// End Here if there is a parkpro module
		//
		if (function_exists('parkpro_get_config')) {
			return true;
		}

		$fcc = new featurecode('parking', 'parkedcall');
		$parkfetch_code = $fcc->getCodeActive();
		unset($fcc);

		$fcc = new featurecode('parking', 'parkto');
		$parkto_code = $fcc->getCodeActive();
		unset($fcc);

		// Need to setup featurecode.conf configuration for the parking lot:
		//
		$parkpos1	= $lot['parkpos'];
		$parkpos2	= $parkpos1 + $lot['numslots'] - 1;

		// A bit confusing, park_context is when we call to park which seems to want 'default' from various testing
		// hint_context is basically the actual context thus what we set in config file and what we point hints at
		//
		$park_context = 'default';
		$hint_context = 'parkedcalls';

		if(version_compare($version, '12', 'lt')) {
			$core_conf->addFeatureGeneral('parkext', $lot['parkext']);
			$core_conf->addFeatureGeneral('parkpos', $parkpos1."-".$parkpos2);
			$core_conf->addFeatureGeneral('context', $hint_context);
			$core_conf->addFeatureGeneral('parkext_exclusive', 'no');
			$core_conf->addFeatureGeneral('parkingtime', $lot['parkingtime']);
			$core_conf->addFeatureGeneral('comebacktoorigin', 'no'); //Set this to no as we can manage our own internal comebacktoorigin
			$core_conf->addFeatureGeneral('parkedplay', $lot['parkedplay']);
			$core_conf->addFeatureGeneral('courtesytone', 'beep');
			$core_conf->addFeatureGeneral('parkedcalltransfers', $lot['parkedcalltransfers']);
			$core_conf->addFeatureGeneral('parkedcallreparking', $lot['parkedcallreparking']);
			$core_conf->addFeatureGeneral('parkedmusicclass', $lot['parkedmusicclass']);
			$core_conf->addFeatureGeneral('findslot', $lot['findslot']);
		}
		$ext->addInclude('from-internal-additional', $ph);
		$ext->addInclude($ph, $hint_context, $lot['name']);

		// Each lot needs a routing table to handle orphaned calls in the event
		// that the call were to timeout if they were routed to return to
		// originator, we route them to the ${PLOT} previously set

		if ($lot['comebacktoorigin'] == 'yes') {

			// If they haven't provided a destination then we need to make a context to
			// handle orphaned calls, we'll require destinations but this is a stop gap
			// to be nice to cusotmers and broken systems.
			//
			if (!$lot['dest']) {
				$ext->add($por, $lot['parkext'], '', new ext_noop('ERROR: No Alternate Destination Available for Orphaned Call'));
				$ext->add($por, $lot['parkext'], '', new ext_playback('sorry&an-error-has-occured'));
				$ext->add($por, $lot['parkext'], '', new ext_hangup(''));
			} else {
				$ext->add($por, $lot['parkext'], '', new ext_goto($lot['dest']));
			}
		}

		// Setup the specific items to do in the park-return-routing context for each lot, we will deal
		// with the per slot routing to this extension in the per slot loop below
		//
		parking_generate_sub_return_routing($lot, $pd);

		// Now we have to create the hints and the specific parking slots for picking up the calls since
		// we do not use the dynamic generated ParkedCall()
		//
		$finalh = [];
		for ($slot = $parkpos1; $slot <= $parkpos2; $slot++) {

			$ext->add($ph, $slot, '', new ext_macro('parked-call',$slot . ',' . ($lot['type'] == 'public' ? $park_context : '${CHANNEL(parkinglot)}')));

			$hv = "park:$slot@$hint_context";
			$finalh[] = $hv;
			$ext->addHint($ph, $slot, $hv);

			if ($parkfetch_code != '' && $lot['generatefc'] == 'yes') {
				$ext->add($ph, $parkfetch_code.$slot, '', new ext_set('FORCEPICKUP',$park_context));
				$ext->add($ph, $parkfetch_code.$slot, '', new ext_macro('parked-call',$slot . ',' . $park_context));
				$ext->addHint($ph, $parkfetch_code.$slot, $hv);
			}

		}
		$ext->addHint($ph, $lot['parkext'], implode('&',$finalh));

		if($parkto_code != '') {
			$id = 'app-parking';
			$ext->addInclude('from-internal-additional', $id); // Add the include to from-internal
			$ext->add($id, $parkto_code, '', new \ext_park());
		}

		if ($lot['autocidpp'] == 'exten' || $lot['autocidpp'] == 'name') {
			parking_generate_sub_park_user();
		}
		break;
	}
}

function parking_generate_sub_park_user() {
	global $db;
	global $amp_conf;
	global $ext;  // is this the best way to pass this?
	global $asterisk_conf;
	global $version;

	$ast_ge_10 = version_compare($version,'10','ge');

	$spu = 'sub-park-user';
	$exten = 's';

 	if ($ast_ge_10) {
		$ext->add($spu, $exten, '', new ext_set('UEXTEN', 'UNKNOWN'));
		$ext->add($spu, $exten, '', new ext_set('UNAME', 'UNKNOWN'));
		$ext->add($spu, $exten, '', new ext_set('DEVS', '${DB_KEYS(DEVICE)}'));
		$ext->add($spu, $exten, '', new ext_while('$["${SET(DEV=${POP(DEVS)})}" != ""]'));
		$ext->add($spu, $exten, '', new ext_gotoif('$["${DB(DEVICE/${DEV}/dial)}" = "${PARKER}"]','found'));
		$ext->add($spu, $exten, '', new ext_endwhile(''));
		$ext->add($spu, $exten, '', new ext_return(''));
		$ext->add($spu, $exten, 'found', new ext_execif('$[${LEN(${DB(DEVICE/${DEV}/user)})} > 0]','Set','UEXTEN=${DB(DEVICE/${DEV}/user)}'));
		$ext->add($spu, $exten, '', new ext_execif('$[${LEN(${UEXTEN})} > 0]','Set','UNAME=${DB(AMPUSER/${UEXTEN}/cidname)}'));
		$ext->add($spu, $exten, '', new ext_return(''));
	} else {
		$ext->add($spu, $exten, '', new ext_agi('parkuser.php'));
		$ext->add($spu, $exten, '', new ext_return(''));
	}
}

function parking_generate_sub_return_routing($lot, $pd) {
	global $ext;

	$parkpos1	= $lot['parkpos'];
	$parkpos2	= $parkpos1 + $lot['numslots'] - 1;

	$prr = 'park-return-routing';
	$pexten = $lot['parkext'];

	$ext->add($prr, $pexten, '', new ext_set('PLOT',$pexten));
	if ($lot['alertinfo']) {
		$ext->add($prr, $pexten, '', new ext_setvar('__ALERT_INFO', str_replace(';', '\;', (string) $lot['alertinfo'])));
	}

	if (!empty($lot['rvolume'])) {
		$ext->add($prr, $pexten, '', new ext_setvar("__RVOL", $lot['rvolume']));
	}

	// Prepend options are parking_space they were parked on, or the extension number or user name of the user who parked them
	//
	switch ($lot['autocidpp']) {
	case 'slot':
		$autopp = '${PARKING_SPACE}:';
		break;
	case 'exten':
		$ext->add($prr, $pexten, '', new ext_gosub('1','s','sub-park-user'));
		$autopp = '${UEXTEN}:';
		break;
	case 'name':
		$ext->add($prr, $pexten, '', new ext_gosub('1','s','sub-park-user'));
		$autopp = '${UNAME}:';
		break;
	default:
		$autopp = '';
		break;
	}
	if ($lot['cidpp'] || $autopp != '') {
		$cidpp = $lot['cidpp'] . $autopp;
		$ext->add($prr, $pexten, '', new ext_execif('$[${LEN(${PREPARK_CID})} = 0]','Set','PREPARK_CID=${CALLERID(name)}'));
		$ext->add($prr, $pexten, '', new ext_set('CALLERID(name)',$cidpp . '${PREPARK_CID}'));
	}
	if ($lot['announcement_id']) {
		$parkingannmsg = recordings_get_file($lot['announcement_id']);
		$ext->add($prr, $pexten, '', new ext_playback($parkingannmsg));
	}


	// If comeback to origin is set then send the call back to the parking target
	// This is our workaround so that we can send Alert-Info and Prepend on a comeback to origin request
	// The default method in Asterisk will not let us send or setup alert-info or prepend anything
    if ($lot['comebacktoorigin'] == 'yes') {
				/*
				//If we detect PARKCALLBACK then we are coming from a transfer and thus we have no PARK_TARGET set
				$ext->add($prr, $pexten, '', new ext_gotoif('$[${LEN(${PARKCALLBACK})} > 0]','transfercallback'));
        //$ext->add($prr, $pexten, '', new ext_goto($pd . ',${PARK_TARGET},1'));
        //The below string is pretty lame and will cause issues with PJSIP (maybe?)
        //But in Asterisk 12 PARK_TARGET (as a goto, see above) is not working right so we are going to
        //just string replace what we want and hope for the best until this is resolved
				$ext->add($prr, $pexten, '', new ext_dial('${REPLACE(PARK_TARGET,_,/)},15'));
				$ext->add($prr, $pexten, '', new ext_goto('next'));
				$ext->add($prr, $pexten, 'transfercallback', new ext_noop('Yes'));
				$ext->add($prr, $pexten, '', new ext_dial('${PARKCALLBACK},15'));
				$ext->add($prr, $pexten, '', new ext_goto('next'));
				*/
				$ext->add($prr, $pexten, '', new ext_execif('$["${ALERT_INFO}"!=""]', 'Set', 'HASH(__SIPHEADERS,Alert-Info)=${ALERT_INFO}'));
				$ext->add($prr, $pexten, '', new ext_execif('$["${RVOL}"!=""]', 'Set', 'HASH(__SIPHEADERS,Alert-Info)=${ALERT_INFO}\;volume=${RVOL}'));
				// if the parker was pjsip, update dial string to all contacts
				$ext->add($prr, $pexten, '', new ext_gotoif('$["${PARKCALLBACK:0:5}"!="PJSIP"]','dial'));
				$ext->add($prr, $pexten, '', new ext_noop('Debug: Found PJSIP Destination ${PARKCALLBACK}, updating with PJSIP_DIAL_CONTACTS from ${PARKER:6}'));
				$ext->add($prr, $pexten, '', new ext_set('PARKCALLBACK','${PJSIP_DIAL_CONTACTS(${PARKER:6})}'));
				$ext->add($prr, $pexten, 'dial', new ext_dial('${PARKCALLBACK},15,b(func-apply-sipheaders^s^1)'));
				$ext->add($prr, $pexten, '', new ext_set('PARKCALLBACK',''));
				//$ext->add($prr, $pexten, '', new ext_goto('next'));
    }

	// If comback to origin wasn't set or if we have already tried that.
    if (empty($lot['dest'])) {
        $ext->add($prr, $pexten, '', new ext_noop('ERROR: No Alternate Destination Available for Orphaned Call'));
        $ext->add($prr, $pexten, '', new ext_playback('sorry&an-error-has-occured'));
        $ext->add($prr, $pexten, '', new ext_hangup(''));
    } else {
        $ext->add($prr, $pexten, '', new ext_goto($lot['dest']));
    }

	// Route park-return-routing from slot to PARK_TARGET:
	for ($slot = $parkpos1; $slot <= $parkpos2; $slot++) {
		$ext->add($prr, $slot, '', new ext_goto('1', $pexten));
	}
}

function parking_generate_parked_call() {
	global $ext;
	global $version;

	// macro-parked-call
	// pickup a parked call from a specified slot
	//
	// NOTE: consider changing this to a subroutine
	//
	$pc = 'macro-parked-call';
	$exten = 's';

	$ext->add($pc, $exten, '', new ext_macro('user-callerid'));
	//hack for asterisk 12!
	$ext->add($pc, $exten, '', new ext_noop('PARKRETURNTO: ${SHARED(PARKRETURNTO,${CHANNEL})}'));
	$ext->add($pc, $exten, '', new ext_gotoif('$[${LEN(${SHARED(PARKRETURNTO,${CHANNEL})})} > 0]','backtosender'));
	//$ext->add($pc, $exten, '', new ext_gotoif('$[${ISNULL(${PARKRETURNTO})} == 0 & ${LEN(${PARKRETURNTO})} > 0]','backtosender'));
	//We can accept both blind and attended (But attended only in asterisk 12!)
	$ext->add($pc, $exten, '', new ext_gotoif('$[${LEN(${BLINDTRANSFER})} > 0 | ${LEN(${ATTENDEDTRANSFER})} > 0]','attemptpark'));
	// Retrieve all previous recording variables, and set the CDR for this leg of the call
	$ext->add($pc, $exten, '',new ext_set('PARKIE','${PARK_GET_CHANNEL(${ARG1},${ARG2})}'));
	$vars = ['MIXMON_DIR', 'YEAR', 'MONTH', 'DAY', 'CALLFILENAME', 'MIXMON_FORMAT', 'MIXMON_POST', 'MON_FMT', 'MIXMON_ID', 'REC_STATUS', 'REC_POLICY_MODE', 'RECORD_ID'];
	foreach($vars as $v) {
		$ext->add($pc, $exten, '',new ext_set($v,'${IMPORT(${PARKIE},'.$v.')}'));
	}
	$ext->add($pc, $exten, '', new ext_gotoif('$["${REC_STATUS}" != "RECORDING"]','next'));
	if(version_compare($version, "12.0", "lt")) {
		$ext->add($pc, $exten, '', new ext_set('AUDIOHOOK_INHERIT(MixMonitor)','yes'));
	}
	$ext->add($pc, $exten, '', new ext_set('CDR(recordingfile)','${CALLFILENAME}.${MON_FMT}'));
	$ext->add($pc, $exten, 'next', new ext_set('CCSS_SETUP','TRUE'));
	$ext->add($pc, $exten, '', new ext_gotoif('$["${PARKIE}" != ""]','pcall'));
	$ext->add($pc, $exten, '', new ext_resetcdr(''));
	$ext->add($pc, $exten, '', new ext_nocdr(''));
	$ext->add($pc, $exten, '', new ext_wait('1'));
	$ext->add($pc, $exten, '', new ext_noop_trace('User: ${CALLERID(all)} tried to pickup non-existent Parked Call Slot ${ARG1}'));
	$ext->add($pc, $exten, '', new ext_playback('pbx-invalidpark'));
	$ext->add($pc, $exten, '', new ext_wait('1'));
	$ext->add($pc, $exten, '', new ext_hangup(''));
	$ext->add($pc, $exten, 'pcall', new ext_noop('User: ${CALLERID(all)} attempting to pick up Parked Call Slot ${ARG1}'));
	$ext->add($pc, $exten, '', new ext_noop('PARKIE: ${PARKIE}'));
	$ext->add($pc, $exten, '', new ext_set('SHARED(PARKRETURNTO,${PARKIE})',''));
	$ext->add($pc, $exten, '', new ext_set('PARKOWNER','1'));

	// ParkedCalls can't handle picking up the default lot as 'parkedcalls' context, it wants 'default'
	//
	if(version_compare($version, '12', 'ge')) {
		$ext->add($pc, $exten, '', new ext_parkedcall('${ARG2},${ARG1}'));
	} else {
		$ext->add($pc, $exten, '', new ext_parkedcall('${ARG1},${ARG2}'));
	}
	$ext->add($pc, $exten, '', new ext_hangup('')); //prevent going into other contexts?
	$ext->add($pc, 'h', '', new ext_macro('hangupcall'));

	//Direct Slot Parking
	$ext->add($pc, $exten, 'attemptpark', new ext_noop('User: ${CALLERID(all)} attempting to Park into slot ${ARG1}'));
	$ext->add($pc, $exten, '', new ext_noop('Blind Transfer: ${BLINDTRANSFER}, Attended Transfer: ${ATTENDEDTRANSFER}'));
	$ext->add($pc, $exten, '', new ext_noop('$[${LEN(${PARKOWNER})} = 0]'));
	$ext->add($pc, $exten, '', new ext_gotoif('$[${LEN(${PARKOWNER})} = 0]','parkit'));
	$ext->add($pc, $exten, '', new ext_macro('hangupcall'));
	$ext->add($pc, $exten, 'parkit', new ext_set('PARKINGEXTEN','${ARG1}'));
	$ext->add($pc, $exten, '', new ext_execif('$[${LEN(${BLINDTRANSFER})} > 0]','Set','SHARED(PARKRETURNTO,${CHANNEL})=${CUT(BLINDTRANSFER,-,1)}','Set','SHARED(PARKRETURNTO,${CHANNEL})=${CUT(ATTENDEDTRANSFER,-,1)}'));
	$ext->add($pc, $exten, '', new ext_noop('PARKRETURNTO: ${SHARED(PARKRETURNTO,${CHANNEL})}'));

	if(version_compare($version, '12', 'ge')) {
		$ext->add($pc, $exten, '', new ext_park('${ARG2},sc(${CONTEXT},s,200)'));
	} else {
		//TODO: need to check this in Asterisk 11, says use label but I think thats incorrect
		$ext->add($pc, $exten, '', new ext_park(',${CONTEXT},s,200,s,${ARG2}')); //return priority here must be a number, not a label.
	}

	$ext->add($pc, $exten, 'backtosender', new ext_noop('Attempting to go back to sender'),1,199);
	/* These variables work in 13.2, some are broken in lower it appears
	 * PARKING_SPACE - extension that the call was parked in prior to timing out.
	 * PARKINGSLOT - Deprecated. Use PARKING_SPACE instead.
	 * PARKEDLOT - name of the lot that the call was parked in prior to timing out.
	 * PARKER - The device that parked the call
	 * PARKER_FLAT - The flat version of PARKER
	 * $ext->add($pc, $exten, '', new ext_noop('PARKING_SPACE: ${PARKING_SPACE}'));
	 * $ext->add($pc, $exten, '', new ext_noop('PARKINGSLOT: ${PARKINGSLOT}'));
	 * $ext->add($pc, $exten, '', new ext_noop('PARKEDLOT: ${PARKINGLOT}'));
	 * $ext->add($pc, $exten, '', new ext_noop('PARKER: ${PARKER}'));
	 * $ext->add($pc, $exten, '', new ext_noop('PARKER_FLAT: ${PARKER_FLAT}'));
	 */
	if(version_compare($version, '13.2', 'ge')) {
		$ext->add($pc, $exten, '', new ext_set('PARKCALLBACK','${PARKER}'));
		$ext->add($pc, $exten, '', new ext_set('SHARED(PARKRETURNTO,${CHANNEL})',''));
		$ext->add($pc, $exten, '', new ext_goto('park-return-routing,${PARKING_SPACE},1'));
	} else {
		$ext->add($pc, $exten, '', new ext_set('PARKCALLBACK','${SHARED(PARKRETURNTO,${CHANNEL})}'));
		$ext->add($pc, $exten, '', new ext_set('SHARED(PARKRETURNTO,${CHANNEL})',''));
		$ext->add($pc, $exten, '', new ext_goto('park-return-routing,${ARG1},1'));
	}
}

function parking_generate_parkedcallstimeout() {
	global $ext;
	global $version;

	// parkedcallstimeout:
	// All timedout parked calls come here regardless of the lot, we thus use this context to route the call to their properly
	// configured destination or back to the originator through a routing table based on the slot that returned the call
	//
	$pc = 'parkedcallstimeout';
	$exten = '_[0-9a-zA-Z*#].';

	$ext->add($pc, $exten, '', new ext_noop_trace('Slot: ${PARKING_SPACE} returned directed at ${EXTEN}'));
	//$ext->add($pc, $exten, '', new ext_set('PARK_TARGET','${EXTEN}'));
	$ext->add($pc, $exten, '', new ext_set('PARKCALLBACK','${REPLACE(EXTEN,_,/)}'));
	$ext->add($pc, $exten, '', new ext_gotoif('$["${REC_STATUS}" != "RECORDING"]','next'));
	if(version_compare($version, "12.0", "lt")) {
		$ext->add($pc, $exten, '', new ext_set('AUDIOHOOK_INHERIT(MixMonitor)','yes'));
	}
	$ext->add($pc, $exten, '', new ext_mixmonitor('${MIXMON_DIR}${YEAR}/${MONTH}/${DAY}/${CALLFILENAME}.${MIXMON_FORMAT}','a','${MIXMON_POST}'));
	$ext->add($pc, $exten, 'next', new ext_goto('1','${PARKING_SPACE}','park-return-routing'));
}

function parking_generate_park_dial($pd, $por, $lot) {
	global $ext;
	// park-dial
	// This is a special context where calls are routed if they are being sent back to the parker. The parking application dynamically
	// inserts extensions into this context in the form of TECH_DEVICEID but if a call were to fail either from a timeout or otherwise
	// then it will move on to priority 2 ... so we need to catch that and then route the call to the park-orphan-routing context to
	// determine where their final destinaition lies.
	//
	foreach (['t', '_[0-9a-zA-Z*#].'] as $exten) {
		//$ext->add($pd, $exten, '', new ext_goto('1', '${PLOT}', $por));
		$ext->add($pd, $exten, '', new ext_noop('WARNING: PARKRETURN to: [${EXTEN}] failed with: [${DIALSTATUS}]. Trying Alternate Dest On Parking Lot ${PARKING_SPACE}'));
		//$ext->add($pd, $exten, '', new ext_goto('1', '${PLOT}', $por));
        $ext->add($pd, $exten, '', new ext_goto('1', $lot['parkext'], $por));
	}
}
