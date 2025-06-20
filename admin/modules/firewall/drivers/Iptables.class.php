<?php
// vim: :set filetype=php tabstop=4 shiftwidth=4:
//
// TODO: Split this into an interface.
namespace FreePBX\modules\Firewall\Drivers;

// Iptables - Generic.
class Iptables {

	private $currentconf = false;

	private $wlock = "";

	public function __construct() {
		if (version_compare(\PHP_VERSION, '5.6') >= 0) {
			$this->wlock = "-w5 -W10000";
		}
	}

	public function l($str) {
		if (function_exists("fwLog")) {
			fwLog($str);
		} else {
			print "LOG: $str\n";
		}
	}

	public function getKnownNetworks() {
		// Returns array that looks like ("network/cdr" => "zone", "network/cdr" => "zone")
		$known = $this->getCurrentIptables();
		$retarr = array();
		$ipvers = array("ipv6", "ipv4");
		foreach ($ipvers as $i) {
			if (!isset($known[$i]['filter']['fpbxnets'])) {
				// Odd.
				continue;
			}
			foreach ($known[$i]['filter']['fpbxnets'] as $z => $settings) {
				if (preg_match("/-s (.+) -j zone-(.+)/", $settings, $out)) {
					$retarr[$out[1]] = $out[2];
				}
			}
		}
		return $retarr;
	}

	public function validateRunning() {
		// Check to make sure that nothing's jumped all over our rules,so check to
		// make sure that some common rules are there.
		$current = $this->getCurrentIptables();
		$ipvers = array("ipv6", "ipv4");
		foreach ($ipvers as $i) {
			if (!isset($current[$i]['filter']['fpbx-rtp'][0])) {
				print "No fpbx-rtp in $i\n";
				return false;
			}
			if (!isset($current[$i]['filter']['fpbxinterfaces'][0])) {
				print "No fpbxinterfaces in $i\n";
				return false;
			}
			// Make sure our responsive rules are there
			if (!isset($current[$i]['filter']['fpbxrfw'])) {
				print "No fpbxrfw entry in $i\n";
				return false;
			}
			$rfw = $current[$i]['filter']['fpbxrfw'];
			$rules = $this->getDefaultRules();
			// Compare the main 3 rules, that should tell you if the kernel is OK
			//
			// Compatibility fix:
			// 3.10 kernels add --mask ffff:fff.. or 255.255.255.255 to the rules,
			// which are't visible on 2.x kernels. Just strip it in the comparison
			if (strpos(preg_replace("/--mask [25\.f:]+ /", "", $rfw[1]), $rules['fpbxrfw'][1]['other']) === false) {
				print "RFW rule 1 not valid (Is '".$rfw[1]."', should start with '".$rules['fpbxrfw'][1]['other']."')\nTHIS MAY BE A KERNEL ISSUE. IF THIS KEEPS OCCURRING REBOOT YOUR MACHINE URGENTLY.\n";
				return false;
			}
			if (strpos(preg_replace("/--mask [25\.f:]+ /", "", $rfw[2]), $rules['fpbxrfw'][2]['other']) === false) {
				print "rfw rule 2 not valid (Is '".$rfw[2]."', should start with '".$rules['fpbxrfw'][2]['other']."')\nTHIS MAY BE A KERNEL ISSUE. IF THIS KEEPS OCCURRING REBOOT YOUR MACHINE URGENTLY.\n";
				return false;
			}
			if (strpos(preg_replace("/--mask [25\.f:]+ /", "", $rfw[3]), $rules['fpbxrfw'][3]['other']) === false) {
				print "rfw rule 3 not valid (Is '".$rfw[3]."', should start with '".$rules['fpbxrfw'][3]['other']."')\nTHIS MAY BE A KERNEL ISSUE. IF THIS KEEPS OCCURRING REBOOT YOUR MACHINE URGENTLY.\n";
				return false;
			}
		}
		return true;
	}

	// Root process
	public function commit() {
		// TODO: run iptables-save here.
		return;
	}

	// Root process
	public function addNetworkToZone($zone = false, $network = false, $cidr = false) {
		$this->checkFpbxFirewall();

		// Make sure this zone exists
		$this->checkTarget("zone-$zone");

		// We want to add the smallest networks first, and then move up.
		// So start by grabbing our existing nets (Note: Pass by Ref, to update
		// later)
		$current = &$this->getCurrentIptables();

		// Are we IPv6 or IPv4? Note, again, they're passed as ref, as we array_splice
		// them later
		if (filter_var($network, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
			$ipt = "/sbin/ip6tables ".$this->wlock;
			$nets = &$current['ipv6']['filter']['fpbxnets'];
		} elseif (filter_var($network, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
			$ipt = "/sbin/iptables ".$this->wlock;
			$nets = &$current['ipv4']['filter']['fpbxnets'];
		} else {
			throw new \Exception("Not an IP address $network");
		}

		// This is what we're adding.
		$p = "-s $network/$cidr -j zone-$zone";

		// Find the first network with a netmask smaller than this, and
		// insert it before that one.
		$insert = false;
		foreach ($nets as $i => $n) {
			if ($n === $p) {
				// Woah. It already exists?
				return true;
			}
			if (preg_match("/-s (.+)\/(\d+) -j/", $n, $out)) {
				// print "Found a source network ".$out[1]." - ".$out[2]."\n";
				if ($out[2] < $cidr) {
					// The one we found is smaller than this, so we want
					// to catch it here first.
					$insert = true;
					break;
				}
			}
		}

		// If we're not inserting, just add it
		if (!$insert) {
			$nets[] = $p;
			$cmd = "$ipt -A fpbxnets -s $network/$cidr -j zone-$zone";
		} else {
			// Splice it into the array
			array_splice($nets, $i, 0, $p);
			$i++;
			$cmd = "$ipt -I fpbxnets $i -s $network/$cidr -j zone-$zone";
		}
		$this->l($cmd);
		exec($cmd, $output, $ret);
		return $ret;
	}

	// Root process
	public function removeNetworkFromZone($zone = false, $network = false, $cidr = false) {

		$this->checkFpbxFirewall();

		// Check to see if we have a cidr or not.
		if (strpos($network, "/") !== false) {
			list($network, $cidr) = explode("/", $network);
		}
		$current = &$this->getCurrentIptables();
		// Are we IPv6 or IPv4? Note, again, they're passed as ref, as we array_splice
		// them later
		if (filter_var($network, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
			$ipt = "/sbin/ip6tables ".$this->wlock;
			$nets = &$current['ipv6']['filter']['fpbxnets'];
		} elseif (filter_var($network, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
			$ipt = "/sbin/iptables ".$this->wlock;
			$nets = &$current['ipv4']['filter']['fpbxnets'];
		} else {
			throw new \Exception("Not an IP address $network");
		}

		// OK, so, let's see if it exists.
		if ($cidr) {
			$p = "-s $network/$cidr -j zone-$zone";
		} else {
			$p = "-s $network -j zone-$zone";
		}
		foreach ($nets as $i => $n) {
			if ($n === $p) {
				// Found it, yay. Remove it from our cache
				array_splice($nets, $i, 1);
				// And remove it from real life
				$i++;
				$cmd = "$ipt -D fpbxnets $i";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				return $ret;
			}
		}
		return false;
	}

	// Root process
	public function changeNetworksZone($newzone = false, $network = false, $cidr = false) {
		$this->checkFpbxFirewall();

		// Check to see if we have a cidr or not.
		if ($cidr === false && strpos($network, "/") !== false) {
			list($network, $cidr) = explode("/", $network);
		}
		$current = &$this->getCurrentIptables();
		// Are we IPv6 or IPv4? Note, again, they're passed as ref, as we array_splice
		// them later
		if (filter_var($network, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
			$ipt = "/sbin/ip6tables ".$this->wlock;
			$nets = &$current['ipv6']['filter']['fpbxnets'];
			// Fake CIDR to add later, if we don't have one.
			$fcidr = "/64";
		} elseif (filter_var($network, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
			$ipt = "/sbin/iptables ".$this->wlock;
			$nets = &$current['ipv4']['filter']['fpbxnets'];
			$fcidr = "/32";
		} else {
			throw new \Exception("Not an IP address $network");
		}

		// OK, so, let's see if it already exists. It may not, so don't
		// stress too much if it doesn't.
		// Need to check to see if it has a netmask?
		if (strpos($network, "/") === false)  {
			if (!$cidr) {
				$cidr = $fcidr;
			}
		} else {
			list($network, $cidr) = explode($network, "/");
		}

		$p = "-s $network/$cidr -j zone-";

		foreach ($nets as $i => $n) {
			if (strpos($n, $p) === 0) {
				// Found it! Blow it away.
				array_splice($nets, $i, 1);
				// And remove it from real life
				$i++;
				$cmd = "$ipt -D fpbxnets $i";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}
		}

		// Now we can just add it, as we know it's gone.
		return $this->addNetworkToZone($newzone, $network, $cidr);
	}

	// Root process
	public function updateService($service = false, $ports = false) {
		$this->checkFpbxFirewall();

		if (strlen($service) > 16) {
			throw new \Exception("Service name too long. Bug");
		}
		$name = "fpbxsvc-$service";
		$this->checkTarget($name);

		$current = &$this->getCurrentIptables();

		// Create a service!
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		foreach ($ipvers as $ipv => $ipt) {
			$changed = false;
			// Service name is 'fpbxsvc-$service'
			if (!isset($current[$ipv]['filter'][$name])) {
				$changed = true;
				$current[$ipv]['filter'][$name] = array();
			} else {
				// It exists, does it have the correct ports?
				$flipped = array_flip($current[$ipv]['filter'][$name]);

				// Are we deleting/ignoring this?
				if ($ports === false) {
					if (isset($flipped['-j RETURN'])) {
						unset($flipped['-j RETURN']);
					} else {
						$changed = true;
					}
				} else {
					foreach ($ports as $tmparr) {
						$protocol = $tmparr['protocol'];
						$port = $tmparr['port'];
						$ratelimit = isset($tmparr['ratelimit']);

						if ($ratelimit) {
							$param = "-p $protocol -m $protocol --dport $port -j fpbxratelimit";
						} else {
							$param = "-p $protocol -m $protocol --dport $port -j ACCEPT";
						}

						if (isset($flipped[$param])) {
							unset($flipped[$param]);
						} else {
							$changed = true;
							break;
						}
					}
				}

				if (!$changed) {
					// Make sure there's nothing left
					if (count($flipped) !== 0) {
						$changed = true;
					}
				}
			}

			if ($changed) {
				// Flush our old rules, add our new ones.
				$current[$ipv]['filter'][$name] = array();
				$cmd = "$ipt -F $name";
				$this->l($cmd);
				exec($cmd, $output, $ret);

				// Add the new ones
				if ($ports === false) {
					// Just return
					$param = "-j RETURN";
					$current[$ipv]['filter'][$name][] = $param;
					$cmd = "$ipt -A $name $param";
					$this->l($cmd);
					exec($cmd, $output, $ret);
				} else {
					foreach ($ports as $arr) {
						$protocol = $arr['protocol'];
						$port = $arr['port'];

						// If this port is rate limited, use that rather than ACCEPT
						if (isset($arr['ratelimit'])) {
							$param = "-p $protocol -m $protocol --dport $port -j fpbxratelimit";
						} else {
							$param = "-p $protocol -m $protocol --dport $port -j ACCEPT";
						}

						$current[$ipv]['filter'][$name][] = $param;
						$cmd = "$ipt -A $name $param";
						$this->l($cmd);
						exec($cmd, $output, $ret);
					}
				}
			}
		}
	}

	// Root process
	public function getActiveServices() {
		$services = array();

		$current = &$this->getCurrentIptables();
		foreach ($current['ipv4']['filter'] as $id => $tmp) {
			if (strpos($id, "fpbxsvc-") !== false) {
				$rawname = substr($id, 8);
				$services[$rawname] = $rawname;
			} elseif (strpos($id, "rejsvc-") !== false) {
				$rawname = substr($id, 7);
				$services[$rawname] = $rawname;
			}
		}
		return $services;
	}

	// Root process
	public function removeService($service) {

		if (strlen($service) > 16) {
			throw new \Exception("Service name too long. Bug");
		}

		// Firstly, remove it from all zones
		$zones = array("reject", "external", "other", "internal", "trusted");
		$this->updateServiceZones($service, array("removefrom" => $zones, "addto" => array()));

		// Now flush it completely from iptables, as well
		$current = &$this->getCurrentIptables();
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);

		$svc = "fpbxsvc-$service";

		foreach ($ipvers as $ipv => $ipt) {
			$cmd = "$ipt -F $svc";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			$cmd = "$ipt -X $svc";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			if ($ret !== 0) {
				throw new \Exception("Tried to delete a service, but, couldn't! - $cmd - ".json_encode($output));
			}
			unset($current[$ipv]['filter'][$svc]);
		}
	}

	// Root process
	public function updateServiceZones($service = false, $zones = false) {
		$this->checkFpbxFirewall();
		$current = &$this->getCurrentIptables();

		if (strlen($service) > 16) {
			throw new \Exception("Service name too long. Bug");
		}
		$name = "fpbxsvc-$service";

		// Check to make sure we know about this service.
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		foreach ($ipvers as $ipv => $ipt) {
			if (!isset($current[$ipv]['filter'][$name])) {
				throw new \Exception("Can't add a $ipv service for $name, it doesn't exist");
			}
			// Remove service from zones it shouldn't be in..
			$live = &$current[$ipv]['filter'];
			foreach ($zones['removefrom'] as $z) {

				if (!isset($live["zone-$z"])) {
					// This zone doesn't exist. Easy
					continue;
				}

				$this->checkTarget("zone-$z");
				// Loop through, make sure it's not in this zone
				$delids = array();
				foreach ($live["zone-$z"] as $i => $lzone) {
					if ($lzone == "-j $name") {
						// It's in a zone it shouldn't be in.
						$delids[] = $i;
						$i++;
						$cmd = "$ipt -D zone-$z $i";
						$this->l($cmd);
						exec($cmd, $output, $ret);
						if ($ret !== 0) {
							throw new \Exception("Error removing zone $i");
						}
					}
				}
				arsort($delids);
				foreach ($delids as $i) {
					// NOW we can remove it from our cache
					array_splice($live["zone-$z"], $i, 1);
				}
			}

			// Add it to the zones it should be
			foreach ($zones['addto'] as $z) {
				$this->checkTarget("zone-$z");
				// Loop through, add it if it's not here.
				$found = false;
				foreach ($live["zone-$z"] as $i => $lzone) {
					if ($lzone == "-j $name") {
						$found = true;
					}
				}

				if (!$found) {
					// Need to add it.
					$live["zone-$z"][] = "-j $name";
					$cmd = "$ipt -A zone-$z -j $name";
					$this->l($cmd);
					exec($cmd, $output, $ret);
				}
			}
		}
	}

	// Root process
	public function changeInterfaceZone($iface = false, $newzone = false) {
		$this->checkFpbxFirewall();

		// Interfaces are checked AFTER networks, so that source networks
		// can override default interface inputs.
		// First, see if we know about this interface, and delete it if we do.
		$current = &$this->getCurrentIptables();

		// This is the policy we want to remove
		$p = "-i $iface -j zone-";

		// Remove from both ipv4 and ipv6.
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		foreach ($ipvers as $ipv => $ipt) {
			$interfaces = &$current[$ipv]['filter']['fpbxinterfaces'];
			foreach ($interfaces as $i => $n) {
				if (strpos($n, $p) === 0) {
					// Found it! Blow it away.
					array_splice($interfaces, $i, 1);
					// And remove it from real life
					$i++;
					$cmd = "$ipt -D fpbxinterfaces $i";
					$this->l($cmd);
					exec($cmd, $output, $ret);
					// Break disabled, just to make sure that if there
					// are multiple entries for the same interface, they're
					// all gone.
					// break;
				}
			}

			// Now we can just add it, if we're not deleting it
			if ($newzone) {
				$this->checkTarget("zone-$newzone");
				$cmd = "$ipt -A fpbxinterfaces $p$newzone";
				$this->l($cmd);
				$output = null;
				exec($cmd, $output, $ret);
				$interfaces[] = "$p$newzone";
			}
		}

		// If nat isn't enabled, fix it.
		if (empty($current['ipv4']['nat']) || !is_array($current['ipv4']['nat'])) {
			$current['ipv4']['nat'] = array();
		}

		// If this is an 'INTERNET' (external) zone, mark packets that are
		// forwared with a destination of that interface eligible
		// for masq. Note that only ipv4 does NAT, it's ludicrous to NAT
		// IPv6, and it's barely even supported until 4.0+ kernels.

		if ($newzone !== "external") {
			$nat = false;
		} else {
			$nat = true;
		}

		$foundrule = false;
		$rule = "-o $iface -j MARK --set-xmark 0x2/0x2";

		// If there's no masq-output entries, it won't exist.
		if (empty($current['ipv4']['nat']['masq-output'])) {
			$current['ipv4']['nat']['masq-output'] = array();
		}

		foreach ($current['ipv4']['nat']['masq-output'] as $lineno => $line) {
			if ($line == $rule) {
				$foundrule = $lineno;
				break;
			}
		}

		unset($output);
		// If we didn't find the rule, and we need it, add it.
		if ($foundrule === false && $nat) {
			$cmd = "/sbin/iptables ".$this->wlock." -t nat -A masq-output $rule";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			$current['ipv4']['nat']['masq-output'][] = $rule;
		} elseif ($foundrule !== false && !$nat) {
			// We found it, but it shoudn't be there. Delete it.
			$cmd = "/sbin/iptables ".$this->wlock." -t nat -D masq-output $rule";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			array_splice($current['ipv4']['nat']['masq-output'], $foundrule, 1);
		}
	}

	// Root process
	public function setRtpPorts($rtp = false, $udptl = false) {
		if (!is_array($rtp)) {
			throw new \Exception("rtp neesds to be an array");
		}
		if (!is_array($udptl)) {
			$udptl = array("start" => 4000, "end" => 4999);
		}

		// Our two protocol strings
		$rtpports = "-p udp -m udp --dport ".$rtp['start'].":".$rtp['end']." -j ACCEPT";
		$t38ports = "-p udp -m udp --dport ".$udptl['start'].":".$udptl['end']." -j ACCEPT";

		$this->checkTarget("fpbx-rtp");
		
		$current = &$this->getCurrentIptables();
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		foreach ($ipvers as $ipv => $ipt) {
			$me = &$current[$ipv]['filter']['fpbx-rtp'];
			$foundrtp = false;
			$foundt38 = false;
			$unknown = array();
			foreach ($me as $i => $line) {
				// Is this line the rtp or t38 line?
				if ($line === $rtpports) {
					$foundrtp = true;
				} elseif ($line === $t38ports) {
					$foundt38 = true;
				} else {
					$unknown[$line] = $i;
					unset($me[$i]);
				}
			}

			// If we didn't find the correct rtp line, add it.
			if (!$foundrtp) {
				$me[] = $rtpports;
				$cmd = "$ipt -A fpbx-rtp $rtpports";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}

			// If we didn't find the correct t38 line, add it.
			if (!$foundt38) {
				$me[] = $t38ports;
				$cmd = "$ipt -A fpbx-rtp $t38ports";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}

			// Now delete the original RTP lines, if there were
			// any that were wrong.
			foreach ($unknown as $line => $i) {
				$cmd = "$ipt -D fpbx-rtp $line";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}
		}
		return true;
	}

	//Root process
	public function updateRFWtshld($rules) {
		if(!is_array($rules)) {
			throw new \Exception("rules is not an array");
		}

		//Check target
		$this->checkTarget("fpbxrfw");
		$this->checkTarget("fpbxratelimit");

		//search for rules
		$current = &$this->getCurrentIptables();
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		$mask = array("ipv6" => "--mask ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff", "ipv4" => "--mask 255.255.255.255");
		foreach ($ipvers as $ipv => $ipt) {

			//Generate what the rules should be
			$tiera = "-m recent --rcheck --seconds ".$rules['fpbxrfw']['TIERA']['seconds']." --hitcount ".$rules['fpbxrfw']['TIERA']['hitcount']." --name REPEAT ".$mask[$ipv]." --rsource -j fpbxattacker";
			$tierb = "-m recent --rcheck --seconds ".$rules['fpbxrfw']['TIERB']['seconds']." --hitcount ".$rules['fpbxrfw']['TIERB']['hitcount']." --name SIGNALLING ".$mask[$ipv]." --rsource -j fpbxshortblock";
			$tierc = "-m recent --rcheck --seconds ".$rules['fpbxrfw']['TIERC']['seconds']." --hitcount ".$rules['fpbxrfw']['TIERC']['hitcount']." --name REPEAT ".$mask[$ipv]." --rsource -j fpbxattacker";
			$rfwarray = array("a" => $tiera, "b" => $tierb, "c" => $tierc);
			$tier3 = "-m recent --rcheck --seconds ".$rules['fpbxratelimit']['TIER3']['seconds']." --hitcount ".$rules['fpbxratelimit']['TIER3']['hitcount']." --name REPEAT ".$mask[$ipv]." --rsource -j fpbxattacker";
			$tier2 = "-m recent --rcheck --seconds ".$rules['fpbxratelimit']['TIER2']['seconds']." --hitcount ".$rules['fpbxratelimit']['TIER2']['hitcount']." --name REPEAT ".$mask[$ipv]." --rsource -j fpbxattacker";
			$tier1 = "-m recent --rcheck --seconds ".$rules['fpbxratelimit']['TIER1']['seconds']." --hitcount ".$rules['fpbxratelimit']['TIER1']['hitcount']." --name REPEAT ".$mask[$ipv]." --rsource -j fpbxshortblock";
			$rlarray = array("3" => $tier3, "2" => $tier2, "1" => $tier1);

			$me = &$current[$ipv]['filter']['fpbxrfw'];
			$foundrfwrules = 0;
			$rfwruleline = array();
			foreach ($me as $i => $line) {
				$found = false;
				//Ensure we get 3 matching rules
				foreach($rfwarray as $r => $rule) {
					if($line === $rule) {
						$foundrfwrules += 1;
						$rfwruleline[] = $i + 1;
						$found = true;
					}
				}
				if (!$found && strpos($line, 'seconds') && (strpos($line, 'REPEAT') || strpos($line, 'SIGNALLING'))) {
					$rfwruleline[] = $i + 1;
				}
			}

			//Did we not get 3 rules?
			if ($foundrfwrules < 3) {
				//Replace all the rules
				$cmd = "$ipt -R fpbxrfw $rfwruleline[0] $tiera";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				$cmd = "$ipt -R fpbxrfw $rfwruleline[1] $tierb";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				$cmd = "$ipt -R fpbxrfw $rfwruleline[2] $tierc";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}

			//Rinse repeat fpbxratelimit
			unset($rfwruleline);
			$me = &$current[$ipv]['filter']['fpbxratelimit'];
			$foundrfwrules = 0;
			$rfwruleline = array();
			foreach ($me as $i => $line) {
				//Ensure we get 3 matching rules
				$found = false;
				foreach($rlarray as $r => $rule) {
					if($line === $rule) {
						$foundrfwrules += 1;
						$rfwruleline[] = $i + 1;
						$found = true;
					}
				}
				if (!$found && strpos($line, 'seconds') && strpos($line, 'REPEAT')) {
					$rfwruleline[] = $i + 1;
				}
			}

			//Did we not get 3 rules?
			if ($foundrfwrules < 3) {
			//Replace all the rules
			//The tiers are deliberatly assigned in reverse order
				$cmd = "$ipt -R fpbxratelimit $rfwruleline[0] $tier3";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				$cmd = "$ipt -R fpbxratelimit $rfwruleline[1] $tier2";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				$cmd = "$ipt -R fpbxratelimit $rfwruleline[2] $tier1";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}
		}
	}

	// Root process
	public function updateTargets($rules) {
		// Create fpbxsmarthosts targets and signalling targets
		//
		// 1: Signalling targets
		$this->checkTarget("fpbxsignalling");
		$ports = $rules['smartports']['signalling'];
		$current = &$this->getCurrentIptables();
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		foreach ($ipvers as $ipv => $ipt) {
			$me = &$current[$ipv]['filter']['fpbxsignalling'];
			if (!is_array($me)) {
				$me = array();
			}
			$added = array();
			$exists = array_flip($me);
			foreach ($ports as $proto => $r) {
				foreach ($r as $rule) {
					// If we have a dest, remove it, as we're not filtering on
					// destinations for signalling targets.
					if ($rule['dest']) {
						unset($rule['dest']);
					}
					$rule['proto'] = $proto;
					// If we are allowing this protocol through to the rfw, tag it with the second bit, as well.
					if ($rules['settings']['responsive'] && $rules['settings']['rprotocols'][$rule['name']]['state']) {
						$p = trim($this->parseFilter($rule))." -j MARK --set-xmark 0x3/0xffffffff";
					} else {
						$p = trim($this->parseFilter($rule))." -j MARK --set-xmark 0x1/0xffffffff";
					}
					if (isset($exists[$p])) {
						// This avoids duplication of entries
						$added[$p] = true;
						unset($exists[$p]);
						continue;
					}

					// Have I already added this?
					if (isset($added[$p])) {
						continue;
					}

					// Doesn't exist. Add it.
					$added[$p] = true;
					$me[] = $p;
					$cmd = "$ipt -A fpbxsignalling $p";
					$this->l($cmd);
					exec($cmd, $output, $ret);
				}
			}

			// If there are any left in exists, we need to remove them.
			$delids = array();

			foreach ($exists as $rule => $i) {
				// We delete the rule from iptables first...
				$cmd = "$ipt -D fpbxsignalling $rule";
				$this->l($cmd);
				exec($cmd, $output, $ret);

				// And then grab the ID, so we can remove the entries in *reverse* order,
				// so we don't lose our place.
				$delids[] = $i;
			}

			// Now if there were any to be deleted, we delete them from the end backwards, 
			// so our cache doesn't get out of whack.
			arsort($delids);
			foreach ($delids as $i) {
				// NOW we can remove it from our cache
				array_splice($me, $i, 1);
			}
		}

		// Now create the entries in fpbxsmarthosts
		$hosts = $rules['smartports']['known'];
		$me = &$current[$ipv]['filter']['fpbxsmarthosts'];
		if (!is_array($me)) {
			$me = array();
		}

		// Run through the hosts and add them to what we WANT our chains to be
		$wanted = array("4" => array(), "6" => array());
		foreach ($hosts as $addr) {
			// Make sure that addr doesn't have any leading or trailing whitespace
			$addr = trim($addr);
			if (!$addr) {
				// It's blank, ignore
				continue;
			}
			// This can be a network, too!
			if (strpos($addr, "/")) {
				// It's a network
				list($host, $net) = explode("/", $addr);
			} else {
				$host = $addr;
			}

			// Now, is this an IPv4 or IPv6 host?
			if (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
				$wanted[6][] = $addr;
			} elseif (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
				$wanted[4][] = $addr;
			} else {
				throw new \Exception("Unknown host address '$addr'");
			}
		}

		// And now add or remove them as neccesary. We do a bit of
		// array mangling so I can avoid code duplication.

		$smarthosts = array("ipv6" => array("ipt" => "/sbin/ip6tables ".$this->wlock, "targets" => $wanted[6], "prefix" => "128"),
			"ipv4" => array("ipt" => "/sbin/iptables ".$this->wlock, "targets" => $wanted[4], "prefix" => "32"),
		);

		foreach ($smarthosts as $ipv => $tmparr) {
			$me = &$current[$ipv]['filter']['fpbxsmarthosts'];
			$exists = array_flip($me);
			$process = $tmparr['targets'];
			$added = array();
			foreach ($process as $addr) {
				// Does this entry already have a prefix?
				if (strpos($addr, "/") !== false) {
					// Make sure that our prefix is a CIDR, not a subnet
					//
					// Note - this should never ever get a subnet. Throw here?
					list($ip, $prefix) = explode("/", $addr);
					if (filter_var($prefix, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
						$cidr = 32-log((ip2long($prefix)^4294967295)+1,2);
					} else {
						$cidr = $prefix;
					}
					$p = "-s $ip/$cidr -m mark --mark 0x1/0x1 -j ACCEPT";
				} else {
					$p = "-s $addr/".$tmparr['prefix']." -m mark --mark 0x1/0x1 -j ACCEPT";
				}
				if (isset($exists[$p])) {
					// This avoids duplication of entries
					$added[$p] = true;
					// It's already there, no need to change
					unset($exists[$p]);
					continue;
				}

				// Have we already added this entry?
				if (isset($added[$p])) {
					continue;
				}

				// It doesn't exist. We need to add it.
				$added[$p] = true;
				$me[] = $p;
				$cmd = $tmparr['ipt']." -A fpbxsmarthosts $p";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}

			// Are any left over? They can be removed.
			$delids = array();

			foreach ($exists as $rule => $i) {
				// We delete the rule from iptables first...
				$cmd = $tmparr['ipt']." -D fpbxsmarthosts $rule";
				$this->l($cmd);
				exec($cmd, $output, $ret);

				// And then grab the ID, so we can remove the entries in *reverse* order,
				// so we don't lose our place.
				$delids[] = $i;
			}

			// Now if there were any to be deleted, we delete them from the end backwards, 
			// so our cache doesn't get out of whack.
			arsort($delids);
			foreach ($delids as $i) {
				// NOW we can remove it from our cache
				array_splice($me, $i, 1);
			}
		}
	}

	// Root process
	public function updateRegistrations($hosts) {
		// Allow registered hosts through without hitting the rate limits
		$this->checkTarget("fpbxregistrations");
		// Run through the hosts and add them to what we WANT our chains to be
		$wanted = array("4" => array(), "6" => array());
		foreach ($hosts as $addr) {
			if (filter_var($addr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
				$wanted[6][] = $addr;
			} elseif (filter_var($addr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
				$wanted[4][] = $addr;
			} else {
				throw new \Exception("Unknown host address $addr");
			}
		}

		// And now add or remove them as neccesary. We do a bit of
		// array mangling so I can avoid code duplication.
		$ipvers = array("ipv6" => array("ipt" => "/sbin/ip6tables ".$this->wlock, "targets" => $wanted[6], "prefix" => "128"),
			"ipv4" => array("ipt" => "/sbin/iptables ".$this->wlock, "targets" => $wanted[4], "prefix" => "32"),
		);

		$current = &$this->getCurrentIptables();
		foreach ($ipvers as $ipv => $tmparr) {
			if (!$tmparr) {
				continue;
			}
			$me = &$current[$ipv]['filter']['fpbxregistrations'];
			$exists = array_flip($me);
			$process = $tmparr['targets'];
			foreach ($process as $addr) {
				$p = "-s $addr/".$tmparr['prefix']." -j fpbxknownreg";
				if (isset($exists[$p])) {
					// It's already there, no need to change
					unset($exists[$p]);
					continue;
				}
				// It doesn't exist. We need to add it.
				$me[] = $p;
				$cmd = $tmparr['ipt']." -A fpbxregistrations $p";
				$this->l($cmd);
				exec($cmd, $output, $ret);
              	//Pass IP address back to calling function
				$retarr[$addr] = "ipadd";
			}

			// Are any left over? They can be removed.

			$delids = array();
			foreach ($exists as $rule => $i) {
				// We delete the rule from iptables first...
				$cmd = $tmparr['ipt']." -D fpbxregistrations $rule";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				//return IP address to calling function
				//first ipv4
				preg_match("/\d+\.\d+\.\d+\.\d+/", $rule, $f2bip);
				if (!is_null($f2bip[0])) {
				$retarr[$f2bip[0]] = "iprem";
                } else {				
					//ipv6
					$f2bipfull = explode(' ',$rule);
					if (!is_null($f2bipfull[1])) {
                    $f2bip = explode('/',$f2bipfull[1]);
					if (filter_var($f2bip[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
					$retarr[$f2bip[0]] = "iprem";
					}
					}
				
				}
				// And then grab the ID, so we can remove the entries in *reverse* order,
				// so we don't lose our place.				
				$delids[] = $i;
				
			}

			// Now if there were any to be deleted, we delete them from the end backwards, 
			// so our cache doesn't get out of whack.
			arsort($delids);
			foreach ($delids as $i) {
				// NOW we can remove it from our cache
				array_splice($me, $i, 1);
			}
		}
      if (isset($retarr)) {
	return $retarr;
}
	}

	// Root process
	public function updateBlacklist($blacklist) {
		// Make sure our table exists
		$this->checkTarget("fpbxblacklist");

		$wanted = array("4" => array(), "6" => array());

		// $blacklist is array("ip.range.here(optional: /cidr)" => false, "hostname" => array("ip", "ip", "ip"), ...);
		foreach ($blacklist as $entry => $val) {
			if ($val === false) {
				// It's a network.
				$net = explode("/", $entry);
				if (!isset($net[1])) {
					// No CIDR?  Is it an IP address?
					if (filter_var($net[0], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
						// Yes. It's IPv6
						$net[1] = "128";
					} elseif (filter_var($net[0], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
						// Yes. It's IPv4
						$net[1] = "32";
					} else {
						// Well that's just crazy.
						continue;
					}
				}
				$addr = $net[0];
				if (filter_var($addr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
					$cidr = (int) $net[1];
					if ($cidr < 8 || $cidr > 128) {
						// Nope.
						continue;
					}
					$wanted[6][] = "$addr/$cidr";
				} elseif (filter_var($addr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
					$cidr = (int) $net[1];
					if ($cidr < 8 || $cidr > 32) {
						// Nope.
						continue;
					}
					$wanted[4][] = "$addr/$cidr";
				}
			} else {
				// It's a host that's been resolved to something.
				foreach ($val as $addr) {
					if (filter_var($addr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
						$wanted[6][] = "$addr/128";
					} elseif (filter_var($addr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
						$wanted[4][] = "$addr/32";
					}
				}
			}
		}

		// And now add or remove them as neccesary. We do a bit of
		// array mangling so I can avoid code duplication.
		$ipvers = array("ipv6" => array("ipt" => "/sbin/ip6tables ".$this->wlock, "targets" => $wanted[6], "prefix" => "128"),
			"ipv4" => array("ipt" => "/sbin/iptables ".$this->wlock, "targets" => $wanted[4], "prefix" => "32"),
		);

		$current = &$this->getCurrentIptables();
		foreach ($ipvers as $ipv => $tmparr) {
			if (!$tmparr) {
				continue;
			}
			$me = &$current[$ipv]['filter']['fpbxblacklist'];
			$exists = array_flip($me);
			$process = $tmparr['targets'];
			foreach ($process as $addr) {
				$p = "-s $addr -j REJECT --reject-with icmp-port-unreachable";
				if (isset($exists[$p])) {
					// It's already there, no need to change
					unset($exists[$p]);
					continue;
				}
				// It doesn't exist. We need to add it.
				$me[] = $p;
				$cmd = $tmparr['ipt']." -A fpbxblacklist $p";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}

			// Are any left over? They can be removed.

			$delids = array();
			foreach ($exists as $rule => $i) {
				// We delete the rule from iptables first...
				$cmd = $tmparr['ipt']." -D fpbxblacklist $rule";
				$this->l($cmd);
				exec($cmd, $output, $ret);

				// And then grab the ID, so we can remove the entries in *reverse* order,
				// so we don't lose our place.
				$delids[] = $i;
			}

			// Now if there were any to be deleted, we delete them from the end backwards, 
			// so our cache doesn't get out of whack.
			arsort($delids);
			foreach ($delids as $i) {
				// NOW we can remove it from our cache
				array_splice($me, $i, 1);
			}
		}
	}

	// Root process
	public function updateHostZones($hosts) {
		// Make sure our table exists
		$this->checkTarget("fpbxhosts");

		$wanted = array("4" => array(), "6" => array());

		// $hosts are array( ip.add.re.ss => "zone", ip.add.re.ss => "zone", ... )
		foreach ($hosts as $ipaddr => $zone) {
			if (filter_var($ipaddr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
				$wanted[6]["$ipaddr/128"] = $zone;
			} elseif (filter_var($ipaddr, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
				$wanted[4]["$ipaddr/32"] = $zone;
			} else {
				// What do I do here? Throw?
				continue;
			}
		}

		// And now add or remove them as neccesary. We do a bit of
		// array mangling so I can avoid code duplication.
		$ipvers = array("ipv6" => array("ipt" => "/sbin/ip6tables ".$this->wlock, "targets" => $wanted[6], "prefix" => "128"),
			"ipv4" => array("ipt" => "/sbin/iptables ".$this->wlock, "targets" => $wanted[4], "prefix" => "32"),
		);

		$current = &$this->getCurrentIptables();
		foreach ($ipvers as $ipv => $tmparr) {
			if (!$tmparr) {
				continue;
			}
			$me = &$current[$ipv]['filter']['fpbxhosts'];
			$exists = array_flip($me);
			foreach ($tmparr['targets'] as $addr => $zone) {
				$p = "-s $addr -j zone-$zone";
				if (isset($exists[$p])) {
					// It's already there, no need to change
					unset($exists[$p]);
					continue;
				}
				// It doesn't exist. We need to add it.
				$me[] = $p;
				$cmd = $tmparr['ipt']." -A fpbxhosts $p";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}

			// Are any left over? They can be removed.

			$delids = array();
			foreach ($exists as $rule => $i) {
				// We delete the rule from iptables first...
				$cmd = $tmparr['ipt']." -D fpbxhosts $rule";
				$this->l($cmd);
				exec($cmd, $output, $ret);

				// And then grab the ID, so we can remove the entries in *reverse* order,
				// so we don't lose our place.
				$delids[] = $i;
			}

			// Now if there were any to be deleted, we delete them from the end backwards, 
			// so our cache doesn't get out of whack.
			arsort($delids);
			foreach ($delids as $i) {
				// NOW we can remove it from our cache
				array_splice($me, $i, 1);
			}
		}
	}

	// Root process
	public function setRejectMode($drop = false, $log = false) {
		$current = &$this->getCurrentIptables();
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		foreach ($ipvers as $v => $iptcmd) {
			$dropid = 0;
			// Should we log?
			// TODO: Unimplemented
			// Should we drop or reject?
			if ($drop) {
				if (!isset($current[$v]['filter']['fpbxlogdrop'][$dropid])) {
					$cmd = "$iptcmd -I fpbxlogdrop -j DROP";
				} elseif (strpos($current[$v]['filter']['fpbxlogdrop'][$dropid], "DROP") === false) {
					// Change it to be drop
					$current[$v]['filter']['fpbxlogdrop'][$dropid] = "-j DROP";
					$dropid++;
					$cmd = "$iptcmd -R fpbxlogdrop $dropid -j DROP";
				} else {
					// Nothing neesd to change
					continue;
				}
				$this->l($cmd);
				exec($cmd, $output, $ret);
			} else {
				if (!isset($current[$v]['filter']['fpbxlogdrop'][$dropid])) {
					$cmd = "$iptcmd -I fpbxlogdrop -j REJECT";
				} elseif (strpos($current[$v]['filter']['fpbxlogdrop'][$dropid], "REJECT") === false) {
					// Change it to be reject
					$current[$v]['filter']['fpbxlogdrop'][$dropid] = "-j REJECT";
					$dropid++;
					$cmd = "$iptcmd -R fpbxlogdrop $dropid -j REJECT";
				} else {
					// Nothing needs to change
					continue;
				}
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}
		}
	}


	public function refreshCache() {
		$this->currentconf = false;
		return $this->getCurrentIptables();
	}

	// Driver Specific iptables stuff
	// Root process
	public function &getCurrentIptables() {
		$astspooldir = \Sysadmin\FreePBX::Config()->get('ASTSPOOLDIR');
		if (!$this->currentconf) {
			// Am I root?
			if (posix_getuid() === 0) {
				// Parse iptables-save output
				exec('/sbin/iptables-save 2>&1', $ipv4, $ret);
				exec('/sbin/ip6tables-save 2>&1', $ipv6, $ret);
				$this->currentconf = array(
					"ipv4" => $this->parseIptablesOutput($ipv4),
					"ipv6" => $this->parseIptablesOutput($ipv6),
				);
			} else {
				// Not root, need to run a hook.
				// Note that we use /run if we can, otherwise fall back to /tmp
				if (is_dir("/run")) {
					$out = "/run/iptables.out";
				} else {
					$out = $astspooldir."/tmp/iptables.out";
				}
				@unlink($out);
				\FreePBX::Firewall()->runHook("getiptables");
				// Wait for up to 5 seconds for the output.
				$crashafter = time() + 5;
				while (!file_exists($out)) {
					if ($crashafter > time()) {
						throw new \Exception("$out wasn't created");
					}
					usleep(200000);
				}

				// OK, it exists. We should be able to parse it as json
				while (true) {
					$json = file_get_contents($out);
					$res = json_decode($json, true);
					if (!is_array($res)) {
						if ($crashafter > time()) {
							throw new \Exception("$out wasn't valid json");
						}
						usleep(200000);
					} else {
						$this->currentconf = $res;
						break;
					}
				}
			}
		}
		// Return as a ref, people may want to mangle it.
		return $this->currentconf;
	}

	// This function is also responsible for starting up the firewall
	// because isConfigured returns false on first run
	private function checkFpbxFirewall() {
		$current = $this->getCurrentIptables();
		if (!$this->isConfigured($current['ipv4'])) {
			//Backup Fail2Ban ruleset
			$f2b_rules = $this->backupFail2ban($current);
			// Make sure we've cleaned up
			$this->cleanOurRules();
			// And add our defaults in
			$this->loadDefaultRules($f2b_rules);
			//Now, if custom rules are enabled, we need to add those rules
			$advSvc = getServices();
			if (!empty($advSvc['advancedsettings']['customrules']) && $advSvc['advancedsettings']['customrules'] === 'enabled') {
				importCustomRules();
			}
		}
	}

	private function backupFail2ban($ipt) {
		//ipv4
		foreach($ipt['ipv4']['filter']['INPUT'] as $i => $r) {
			if ((strpos($r, "fail2ban") !== false)
			    ||(strpos($r, "f2b") !== false)) {
				$f2b_rule['INPUT'][] = $r;
			}
		}
		foreach($ipt['ipv4']['filter'] as $chain => $i) {
			if ((strpos($chain, "fail2ban") !== false)
			    ||(strpos($chain, "fpbxinterfaces") !== false)
			    ||(strpos($chain, "f2b") !== false)
			    ||(strpos($chain, "fpbxnets") !== false)) {
				foreach($i as $index => $r) {
					$f2b_rule[$chain][] = $r;
				}
			}
		}

		if(!empty($f2b_rule)) {
			foreach($f2b_rule as $chain => $v) {
				foreach($v as $i => $r) {
					$vals = explode("-j", $r);
					$tmparr = array("ipvers" => "4", "other" => trim($vals[0]), "jump" => strtok($vals[1], ' '));
					$f2b_rules[$chain][] = $tmparr;
				}
			}
		}
		unset($f2b_rule);

		//ipv6
		foreach($ipt['ipv6']['filter']['INPUT'] as $i => $r) {
			if ((strpos($r, "fail2ban") !== false)
			    ||(strpos($r, "f2b") !== false)) {
				$f2b_rule['INPUT'][] = $r;
			}
		}

		foreach($ipt['ipv6']['filter'] as $chain => $i) {
			if ((strpos($chain, "fail2ban") !== false)
			    ||(strpos($chain, "fpbxinterfaces") !== false)
			    ||(strpos($chain, "f2b") !== false)
			    ||(strpos($chain, "fpbxnets") !== false)) {
				foreach($i as $index => $r) {
					$f2b_rule[$chain][] = $r;
				}
			}
		}

		if(!empty($f2b_rule)) {
			foreach($f2b_rule as $chain => $v) {
				foreach($v as $i => $r) {
					$vals = explode("-j", $r);
					$tmparr = array("ipvers" => "6", "other" => trim($vals[0]), "jump" => strtok($vals[1], ' '));
					$f2b_rules[$chain][] = $tmparr;
				}
			}
		}

		return $f2b_rules;
	}

	private function cleanOurRules() {
		//We want to resets the iptables rules without breaking xt_recent
		$this->l("Resetting iptables");
		exec("/sbin/iptables ".$this->wlock." -F");
		exec("/sbin/ip6tables ".$this->wlock." -F");
		exec("/sbin/iptables ".$this->wlock." -X");
		exec("/sbin/ip6tables ".$this->wlock." -X");
		$this->currentconf = array();
		$current = $this->getCurrentIptables();
		return;
	}

	private function loadDefaultRules($f2b_rules) {
		// create lefilter ipset
		exec('ipset create -exist lefilter bitmap:port range 80-65535 timeout 60');
		
		$defaults = $this->getDefaultRules();
		// We're here because our first rule isn't there. Insert it.
		$this->insertRule('INPUT', array_shift($defaults['INPUT']));

		// Remove any INPUT rules that may be hanging around, just in case
		// someone adds stuff to 'INPUT' later, and doesn't read the damn
		// code.
		unset($defaults['INPUT']);

		//We read the damn code and need to restore fail2ban rules
		$checkpoint = [];
		if (!empty($f2b_rules['INPUT'])) {
			foreach($f2b_rules['INPUT'] as $i => $r) {
				if (array_search($r['jump'], $checkpoint, true) === false) {
					$this->insertRule('INPUT', $r);
					$checkpoint[] = $r['jump'];
				}
			}
		unset($checkpoint);
		}

		// Now, we need to create the chains for the rest of the rules
		foreach ($defaults as $name => $val) {
			$this->checkTarget($name);
			if (!empty($val)) {
				foreach ($val as $entry) {
					$this->addRule($name, $entry);
				}
			}
			// unset ($rules[$name]);
		}

		//Restore lower level fail2ban and fpbxinterfaces rules
		foreach((array) $f2b_rules as $chain => $vals) {
			if($chain !== 'INPUT') {
				foreach($vals as $i => $r) {
					$this->addRule($chain, $r);
				}
			}
		}

		// Add MASQ rules. They're hardcoded here, because it's just simpler.
		// Note we only NAT IPv4, not IPv6. If you want to nat IPv6, you're doing it wrong.
		$rules = array(
			"-t nat -N masq-input",
			"-t nat -N masq-output",
			"-t nat -A POSTROUTING -j masq-input",  // sets bit 1 if elegible for masq
			"-t nat -A masq-input  -j MARK --set-xmark 0x1/0xffffffff", // TODO: Validate source. Currently allow all.
			"-t nat -A POSTROUTING -j masq-output", // sets bit 2 if elegible for masq
			"-t nat -A POSTROUTING -m mark --mark 0x3/0x3 -j MASQUERADE", // if 1&2 are set, masq
		);

		foreach ($rules as $r) {
			$cmd = "/sbin/iptables ".$this->wlock." $r";
			$this->l($cmd);
			exec($cmd);
		}
		return true;
	}

	private function getDefaultRules() {
		
		$defaults = array();
		$retarr['INPUT'][]= array("jump" => "fpbxfirewall");

		// Default sanity rules. 
		// 1: Always allow all lo traffic, no matter what.
		$retarr['fpbxfirewall'][]= array("int" => "lo", "jump" => "ACCEPT");
		// 2: Allow related/established - TCP all, but udp needs to be managed AFTER
		// we check for any other traffic we care about.
		$retarr['fpbxfirewall'][]= array("proto" => "tcp", "other" => "-m connmark ! --mark 0x20 -m state --state RELATED,ESTABLISHED", "jump" => "ACCEPT");
		// 3: Always allow ICMP (no, really, you always want to allow ICMP, stop thinking blocking
		// it is a good idea)
		$retarr['fpbxfirewall'][]= array("ipvers" => 4, "proto" => "icmp", "jump" => "ACCEPT");
		$retarr['fpbxfirewall'][]= array("ipvers" => 6, "proto" => "ipv6-icmp", "jump" => "ACCEPT");
		// 4: Other misc bits and pieces. DHCP, broadcast traffic, etc.
		$retarr['fpbxfirewall'][]= array("ipvers" => 4, "dest" => "255.255.255.255/32", "jump" => "ACCEPT");
		$retarr['fpbxfirewall'][]= array("other" => "-m pkttype --pkt-type multicast", "jump" => "ACCEPT");
		// This ensures we can act as a DHCP server if we want to.
		$retarr['fpbxfirewall'][]= array("proto" => "udp", "dport" => "67:68", "sport" => "67:68", "jump" => "ACCEPT");
		// IPv6 Link-Local DHCP Traffic must be allowed
		$retarr['fpbxfirewall'][]= array("ipvers" => 6, "proto" => "udp", "dport" => "546:547", "sport" => "546:547", "src" => "fe80::/64", "jump" => "ACCEPT");
		// Check if this is RTP traffic. This is a high priority tag, so it's up the top.
		$retarr['fpbxfirewall'][]= array("jump" => "fpbx-rtp");

		// Now we can do our actual filtering.
		//
		// If any hosts are blacklisted, reject them early.
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxblacklist");
		// This marks VoIP Signalling packets
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxsignalling");
		// This allows packets marked as signalling through if they're from known hosts.
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxsmarthosts");
		// And known registrations
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxregistrations");
		// This allows known networks
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxnets");
		// This allows known hosts
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxhosts");
		// And known interfaces.
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxinterfaces");
		// If anything is tagged as reject, we capture it here
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxreject");
		// If this is a VoIP Signalling packet from an unknown host, and it's eligible for
		// RFW, then send it off there.
		$retarr['fpbxfirewall'][] = array("other" => "-m mark --mark 0x2/0x2", "jump" => "fpbxrfw");
		// Now, it may be other 'related' UDP traffic (tftp, for example)
		$retarr['fpbxfirewall'][]= array("proto" => "udp", "other" => "-m state --state RELATED,ESTABLISHED", "jump" => "ACCEPT");
		// lefilter rarely used, only check on traffic we'd otherwise drop
		$retarr['fpbxfirewall'][] = array("proto" => "tcp", "other" => "-m set --match-set lefilter dst", "jump" => "lefilter");
		// Otherwise, log and drop.
		$retarr['fpbxfirewall'][] = array("jump" => "fpbxlogdrop");

		// Our 'trusted' zone is always allowed access to everything
		$retarr['zone-trusted'][] = array("jump" => "ACCEPT");

		// Mark traffic that comes in from other zones, so that they can be handled
		// differently if needed (Rate limiting cares about zone-internal, and checks
		// for mark 0x4). Nothing else is using it at the moment, but it is left here
		// for expansion by third parties.
		$retarr['zone-internal'][] = array("other" => "-j MARK --set-xmark 0x4/0x4");
		$retarr['zone-other'][] = array("other" => "-j MARK --set-xmark 0x8/0x8");
		$retarr['zone-external'][] = array("other" => "-j MARK --set-xmark 0x10/0x10");

		// VoIP Rate limiting happens here. If they've made it here, they're an unknown host
		// sending VoIP *signalling* here. We want to give them a bit of slack, to make sure
		// it's not a dynamic IP address of a known good client.

		// Before we do anything, we mark the packet to be shown in the UI
		$retarr['fpbxrfw'][] = array("other" => "-m recent --set --name DISCOVERED --rsource");
		//If this has already been discovered by the monitoring daemon, let it access this port for up to 90 seconds.
		//This is enough time for the firewall daemon to discover it in asterisk and add it to the proper tables.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --rcheck --seconds 90 --hitcount 1 --name WHITELIST --rsource", "jump" => "ACCEPT");
		
		//If the External IP address changes for a site with multiple devices, the IP address is unknown and packets flood without AUTH and cause DOS
		//We are going to add a 90 second whitlist to packets coming from unknown IP's, somewhat bypassing the monitoring service whitelist
		//First, if they're already on the list just let them pass
		$retarr['fpbxrfw'][] = array("other" => "-m recent --rcheck --seconds 90 --hitcount 1 --name TEMPWHITELIST --rsource", "jump" => "ACCEPT");
		//If not, check if you're entitled to tempwhitelist
		$retarr['fpbxrfw'][] = array("other" => "-m recent ! --rcheck --seconds 86400 --name TEMPWHITELIST --rsource", "jump" => "fpbxchecktempwhitelist");
		//If you've made it here, you already failed the 90 second whitelist and tempwhitelist, clean up for next time
		$retarr['fpbxrfw'][] = array("other" => "-m recent --remove --name TEMPWHITELIST --rsource");
		
		// To start with, we ensure that we keep track of ALL rfw attempts.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --set --name REPEAT --rsource");
		// These threshold limites can be set in the GUI; below are just defaults
		// The GUI values will be set later by the firewall daemon

		// Testing against various attack tools suggests that they tend to spam packets,
		// even when they are rejected.  So, as a simple 'we know you're doing bad things'
		// check, if they've sent more than 50 packets in 10 seconds, they're baddies.
		// We're just going to block them, and be done with it.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --rcheck --seconds 10 --hitcount 50 --name REPEAT --rsource", "jump" => "fpbxattacker");
		// Has this IP already been detected as a persistent attacker? They're off to
		// the bit bucket.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --rcheck --seconds 86400 --hitcount 1 --name ATTACKER --rsource", "jump" => "fpbxattacker");
		// This is the 'short' block, which allows up to 10 packets in 60 seconds,
		// before they get clamped. 10 packets is enough to establish and hang up two
		// calls, or one with voicemail notification.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --rcheck --seconds 60 --hitcount 10 --name SIGNALLING --rsource", "jump" => "fpbxshortblock");
		// Note, this is *deliberately* after the check. Otherwise it'll never time out. We
		// want to let them actually attempt to connect, albeit slowly. If they're legitimate,
		// their registration will be discovered, and they won't hit here any more. If they're
		// an attacker, we want to encourage them to retry so they are blocked quicker.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --set --name SIGNALLING --rsource");
		// We're a lot less forgiving over the longer term.
		//
		// If this IP has sent more than 100 signalling requests without success in a 24 hour
		// period, we're deeming them as bad guys, and we're not interested in talking to them
		// any more.
		$retarr['fpbxrfw'][] = array("other" => "-m recent --rcheck --seconds 86400 --hitcount 100 --name REPEAT --rsource", "jump" => "fpbxattacker");
		// OK, hasn't exceeded any rate limiting, good to go, for now.
		$retarr['fpbxrfw'][] = array("jump" => "ACCEPT");

		// This is where we mark (or continue to mark) them as an attacker, and drop their traffic.
		// We drop rather than reject, as it slows attack scripts down, and they tend to give up quicker 
		// after a bunch of timeouts than they do with an authoritative 'refused'.
		$retarr['fpbxattacker'][] = array("other" => "-m recent --set --name ATTACKER --rsource");
		// No longer logging attackers, there's too many.
		// $retarr['fpbxattacker'][] = array("jump" => "LOG", "append" => " --log-prefix 'attacker: '");
		$retarr['fpbxattacker'][] = array("jump" => "DROP");

		// We tag this IP so that monitoring knows that they were previously blocked. Reject, rather
		// than drop, for phones.
		$retarr['fpbxshortblock'][] = array("other" => "-m recent --set --name CLAMPED --rsource");
		// No longer logging attackers, there's too many.
		// $retarr['fpbxshortblock'][] = array("jump" => "LOG", "append" => " --log-prefix 'clamped: '");
		$retarr['fpbxshortblock'][] = array("jump" => "REJECT");

		$retarr['fpbxlogdrop'][] = array("jump" => "REJECT");

		// TCP Rate limiting. We use REPEAT and DISCOVERED as names, so they are visible
		// in the UI. Also, we don't want an attacker to try to connect to our SIP ports
		// after being blocked from another service!

		// If this packet is from an INTERNAL network, or registered host, don't rate limit it.
		$retarr['fpbxratelimit'][] = array("other" => "-m mark --mark 0x4/0x4", "jump" => "ACCEPT");

		// If this has already been discovered by the monitoring daemon, let it access this
		// port for up to 90 seconds. This is enough time for the firewall daemon to discover
		// it in asterisk and add it to the proper tables.
		$retarr['fpbxratelimit'][] = array("other" => "-m recent --rcheck --seconds 90 --hitcount 1 --name WHITELIST --rsource", "jump" => "ACCEPT");

		// On a SYN packet, add it to our watch list
		$retarr['fpbxratelimit'][] = array("other" => "-m state --state NEW -m recent --set --name REPEAT --rsource");
		// Note DISCOVERED is only for the UI
		$retarr['fpbxratelimit'][] = array("other" => "-m state --state NEW -m recent --set --name DISCOVERED --rsource");

		$retarr['fpbxratelimit'][] = array("jump" => "LOG");
		// Has this IP already been marked as an attacker? If so, you're still one, go away.
		$retarr['fpbxratelimit'][] = array("other" => "-m recent --rcheck --seconds 86400 --hitcount 1 --name ATTACKER --rsource", "jump" => "fpbxattacker");

		// TCP Packets logic:
		//   Allow up to 50 unauthed connections from a single IP within 60 seconds
		//   Allow up to 100 unauthed connections from a single IP within 5 minutes
		//   Any more than 200 unauthed connections from a single IP within a day is a hardblock
		$retarr['fpbxratelimit'][] = array("other" => "-m recent --rcheck --seconds 86400 --hitcount 200 --name REPEAT --rsource", "jump" => "fpbxattacker");
		$retarr['fpbxratelimit'][] = array("other" => "-m recent --rcheck --seconds 300 --hitcount 100 --name REPEAT --rsource", "jump" => "fpbxattacker");
		$retarr['fpbxratelimit'][] = array("other" => "-m recent --rcheck --seconds 60 --hitcount 50 --name REPEAT --rsource", "jump" => "fpbxshortblock");

		// If they made it past here, they're all good.
		$retarr['fpbxratelimit'][] = array("jump" => "ACCEPT");

		// As this IP is known about, remove it from any tables they may be in (Note that DISCOVERED is only
		// used for the UI, so don't remove it from that)
		$retarr['fpbxknownreg'][] = array("other" => "-m recent --remove --rsource --name REPEAT");
		$retarr['fpbxknownreg'][] = array("other" => "-m recent --remove --rsource --name ATTACKER");
		//Remove from the whitelists so that next time they are new packets and will get another 90 seconds
		$retarr['fpbxknownreg'][] = array("other" => "-m recent --remove --rsource --name TEMPWHITELIST");
		$retarr['fpbxknownreg'][] = array("other" => "-m recent --remove --rsource --name WHITELIST");
		// Mark this as a known-good host, so it's not rate limited.
		$retarr['fpbxknownreg'][] = array("other" => "-j MARK --set-xmark 0x4/0x4");
		// If this is a signaling packet, we can just accept it without further checks.
		$retarr['fpbxknownreg'][] = array("other" => "-m mark --mark 0x1/0x1", "jump" => "ACCEPT");
		// Known Registrations are allowed to access signalling, UCP, Zulu, and Provisioning ports.
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-ucp");
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-restapps");
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-restapps_ssl");
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-provis");
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-provis_ssl");
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-api");
		$retarr['fpbxknownreg'][] = array("jump" => "fpbxsvc-api_ssl");

		// Set up lefilter chain
		$retarr['lefilter'][] = array("other" => "-m state --state NEW -j CONNMARK --set-mark 0x20");
		$retarr['lefilter'][] = array("other" => "-m state --state NEW -j ACCEPT");
		$retarr['lefilter'][] = array("ipvers" => "4", "other" => "-m string --string \"GET /.well-known/acme-challenge/\" --algo kmp -j ACCEPT");
		$retarr['lefilter'][] = array("ipvers" => "6", "other" => "-m string --string \"GET /.well-known/acme-challenge/\" --algo kmp -j ACCEPT");
		$retarr['lefilter'][] = array("ipvers" => "4", "other" => "-m string --string \"GET /.freepbx-known/\" --algo kmp -j ACCEPT");
		$retarr['lefilter'][] = array("ipvers" => "6", "other" => "-m string --string \"GET /.freepbx-known/\" --algo kmp -j ACCEPT");
		$retarr['lefilter'][] = array("other" => "-j RETURN");
		
		//This adds unknown packets to a 90 second whitelist
		$retarr['fpbxchecktempwhitelist'][] = array("other" => "-m recent ! --rcheck --name REPEAT --rsource", "jump" => "fpbxtempwhitelist");
		$retarr['fpbxtempwhitelist'][] = array("other" => "-m recent --set --name TEMPWHITELIST --rsource", "jump" => "ACCEPT");

		return $retarr;
	}

	private function parseIptablesOutput($iptsave) {
		$table = "unknown";

		$conf = array();

		foreach ($iptsave as $line) {
			if (empty($line)) {
				continue;
			}
			// print "Parsing '$line'\n";
			$firstchar = $line[0];

			if ($firstchar == "*") {
				// It's a new table.
				$table = substr($line, 1);
				continue;
			}

			if ($firstchar == ":") {
				// It's a chain definition
				list($chain, $stuff) = explode(" ", $line);
				$chain = substr($chain, 1);
				$conf[$table][$chain] = array();
				continue;
			}

			// Skip lines we don't care about..
			if ($firstchar != "-") { // Everything we care about now starts with -A
				continue;
			}
			$linearr = explode(" ", $line);
			array_shift($linearr);
			$chain = array_shift($linearr);
			$conf[$table][$chain][] = join(" ", $linearr);
		}

		// Make sure we have SOMETHING there.
		if (!isset($conf['filter'])) {
			$conf['filter'] = array("INPUT" => array());
		}

		return $conf;
	}

	private function isConfigured($ipt) {
		// Check to see that our firewall is configured
		if (!isset($ipt['filter']) || !isset($ipt['filter']['INPUT'][0])) {
			$this->l("There is no filter or INPUT chain");
			return false;
		}

		//If custom rules are enabled, you fly at your own risk
		$customenabled = false;
		$dbobj = \Sysadmin\FreePBX::Database();
		$query = 'select `val` from kvstore_FreePBX_modules_Firewall WHERE `key`= "advancedsettings"';
		$sql = $dbobj->prepare($query);
		$sql->execute();
		$val = $sql->fetchColumn();
		$value = json_decode($val, true);
		if (is_array($value)) {
			if ($value['customrules'] === 'enabled') {
				$customenabled = true;
			}
		}

		// Verify that the fpbxfirewall chain is called from INPUT
		foreach ($ipt['filter']['INPUT'] as $i => $r) {
			if ($r === "-j fpbxfirewall") {
			return true;
			} else {
				//It's only OK if the rule above us is Fail2Ban
				if (strpos($r, "fail2ban") === false && strpos($r, "f2b") === false  && (!$customenabled)) {
					$this->l("There is an invading rule above us: $r");
					$this->l("This check can be disabled by enabling Custom Rules under Advanced Settings in the Firewall Module");
					return false;
				}
			}
		}
		$this->l("fpbxfirewall rule not found in INPUT chain");
		return false;
	}

	private function parseFilter($arr) {
		if (!is_array($arr)) {
			throw new \Exception("Wasn't given an array");
		}

		$str = "";

		if (isset($arr['int'])) { 
			$str .= "-i ".$arr['int']." ";
		}

		if (isset($arr['dest'])) {
			// IF this is an ipv6 addres, it MAY start and end with brackets. Remove them if so.
			$dest = preg_replace('/^\[?([^\]]+)\]?$/', '\1', $arr['dest']);
			// It is a valid IP address,  isn't it?
			if (filter_var($dest, \FILTER_VALIDATE_IP)) {
				// It is. If it's 'allow all', we can disregard.
				if ($dest !== "0.0.0.0" && $dest !== "::") {
					// If it's IPv6, we need to add /128. We can cheat here! Rather than
					// running filter_var again, we can see if it's IPv6 by looking if it
					// has a colon in it. IF, somehow, someone handed a ipv4:port here, it
					// wouldn't have made it past the filter_var above.
					if (strpos($dest, ":") === false) {
						// No colon, ipv4
						$str .= "-d $dest/32 ";
					} else {
						$str .= "-d $dest/128 ";
					}
				}
			} else {
				// Just add it. Hopefully you know what you're doing.
				$str .= "-d ".$arr['dest']." ";
			}
		}

		if (isset($arr['proto'])) {
			$str .= "-p ".$arr['proto']." ";
			if (isset($arr['dport'])) {
				if (strpos($arr['dport'], ',') === false) {
					$str .= "-m ".$arr['proto']." ";
				} else {
					$str .= "-m multiport ";
				}
			}
		}
		if (isset($arr['src'])) {
			if (filter_var($arr['src'], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
				$src = $arr['src']."/128";
			} elseif (filter_var($arr['src'], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
				$src = $arr['src']."/32";
			} else {
				$src = $arr['src'];
			}
			$str .= "-s $src ";
		}
		if (isset($arr['dport'])) {
			$str .= "--dport ".$arr['dport']." ";
		}
		if (isset($arr['sport'])) {
			$str .= "--sport ".$arr['sport']." ";
		}
		if (isset($arr['out'])) {
			$str .= "-o ".$arr['out']." ";
		}
		if (isset($arr['other'])) {
			$str .= $arr['other']." ";
		}
		if (isset($arr['jump'])) {
			$str .= "-j ".$arr['jump'];
		}

		if (!$str) {
			throw new \Exception("Wat. Nothing? ".json_encode($arr));
		}

		if (isset($arr['append'])) {
			$str .= $arr['append'];
		}

		// Make sure nothing can escape from this.
		return escapeshellcmd($str);
	}

	private function insertRule($chain = false, $arr = false) {
		if (!$chain || !$arr) {
			throw new \Exception("Error with $chain or $arr\n");
		}

		$this->checkTarget($arr['jump']);
		$parsed = $this->parseFilter($arr);

		// IPv4
		$cmd = "/sbin/iptables ".$this->wlock." -I $chain $parsed";
		$this->l($cmd);
		exec($cmd, $output, $ret);
		// Add it to our local array
		array_unshift($this->currentconf['ipv4']['filter'][$chain], $parsed);

		// IPv6
		$cmd = "/sbin/ip6tables ".$this->wlock." -I $chain $parsed";
		$this->l($cmd);
		exec($cmd, $output, $ret);
		// Add it to our local array
		array_unshift($this->currentconf['ipv6']['filter'][$chain], $parsed);
		return;
	}

	private function addRule($chain = false, $arr = false) {
		if (!$chain || !$arr) {
			throw new \Exception("Error with $chain or $arr\n");
		}

		if (isset($arr['jump'])) {
			$this->checkTarget($arr['jump']);
		}

		if (!isset($arr['ipvers'])) {
			$arr['ipvers'] = "both";
		}

		$parsed = $this->parseFilter($arr);

		if ($arr['ipvers'] == 6 || $arr['ipvers'] == "both") {
			$cmd = "/sbin/ip6tables ".$this->wlock." -A $chain $parsed";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			if ($ret === 0) {
				$this->currentconf['ipv6']['filter'][$chain][] =  $parsed;
			}
		}
		if ($arr['ipvers'] == 4 || $arr['ipvers'] == "both") {
			$cmd = "/sbin/iptables ".$this->wlock." -A $chain $parsed";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			if ($ret === 0) {
				$this->currentconf['ipv4']['filter'][$chain][] =  $parsed;
			}
		}
		return;
	}

	private function checkTarget($target = false) {
		if (!$target) {
			throw new \Exception("No Target");
		}

		switch ($target) {
		case 'ACCEPT':
		case 'REJECT':
		case 'DROP':
			return true;
		default:
			// If it's all upper case, we assume you know what you're doing.
			if (ctype_upper($target)) {
				return true;
			}
			// Does this chain target already exist?
			if (isset($this->currentconf['ipv4']['filter'][$target]) && isset($this->currentconf['ipv6']['filter'][$target])) {
				return true;
			}
		}

		// It doesn't exist.

		// IPv4
		$cmd = "/sbin/iptables ".$this->wlock." -N ".escapeshellcmd($target);
		$this->l($cmd);
		exec($cmd, $output, $ret);
		if ($ret == 0) {
			$this->currentconf['ipv4']['filter'][$target] = array();
		}

		$output = null;
		// IPv6
		$cmd = "/sbin/ip6tables ".$this->wlock." -N ".escapeshellcmd($target);
		$this->l($cmd);
		exec($cmd, $output, $ret);
		if ($ret == 0) {
			$this->currentconf['ipv6']['filter'][$target] = array();
		}
	}

	public function addToReject($name, $settings) {

		$current = &$this->getCurrentIptables();

		// Reject on both ipv6 and ipv4
		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		$svcname = "rejsvc-$name";

		// Make sure our target exists
		$this->checkTarget($svcname);

		// Is the rule correct?
		foreach ($ipvers as $ipv => $ipt) {
			$changed = false;
			$flipped = array_flip($current[$ipv]['filter'][$svcname]);
			$iptcommands = array();
			if (!isset($settings['fw'])) {
				$settings['fw'] = array();
			}
			foreach ($settings['fw'] as $tmparr) {
				$protocol = $tmparr['protocol'];
				$port = $tmparr['port'];
				if ($ipv == "ipv6") {
					$reject = "icmp6-port-unreachable";
				} else {
					$reject = "icmp-port-unreachable";
				}

				$param = "-p $protocol -m $protocol --dport $port -j REJECT --reject-with $reject";
				$iptcommands[] = $param;
				if (isset($flipped[$param])) {
					unset($flipped[$param]);
				} else {
					$changed = true;
				}
			}

			// If something's wrong, blow away the rule and recreate it.
			if ($changed || !empty($flipped)) {
				$cmd = "$ipt -F $svcname";
				$this->l($cmd);
				exec($cmd, $output, $ret);
				foreach ($iptcommands as $param) {
					$cmd = "$ipt -A $svcname $param";
					$this->l($cmd);
					exec($cmd, $output, $ret);
				}
				$current[$ipv]['filter'][$svcname] = $iptcommands;
			}

			// Now check to see if the rule is in the reject table
			$flipped = array_flip($current[$ipv]['filter']['fpbxreject']);
			if (!isset($flipped["-j $svcname"])) {
				$current[$ipv]['filter']['fpbxreject'][] = "-j $svcname";
				$cmd = "$ipt -A fpbxreject -j $svcname";
				$this->l($cmd);
				exec($cmd, $output, $ret);
			}
		}
	}

	public function removeFromReject($name) {
		$current = &$this->getCurrentIptables();

		$ipvers = array("ipv6" => "/sbin/ip6tables ".$this->wlock, "ipv4" => "/sbin/iptables ".$this->wlock);
		$svcname = "rejsvc-$name";

		foreach ($ipvers as $ipv => $ipt) {
			$flipped = array_flip($current[$ipv]['filter']['fpbxreject']);
			if (!isset($flipped["-j $svcname"])) {
				continue;
			}
			// It exists, and it shouldn't.
			$cmd = "$ipt -D fpbxreject -j $svcname";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			// Remove it from rejeect, but we don't care about preserving the index
			$index = $flipped["-j $svcname"];
			unset($current[$ipv]['filter']['fpbxreject'][$index]);
			// Now flush and delete it
			$cmd = "$ipt -F $svcname";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			$cmd = "$ipt -X $svcname";
			$this->l($cmd);
			exec($cmd, $output, $ret);
			unset($current[$ipv]['filter'][$svcname]);
		}
	}
}

