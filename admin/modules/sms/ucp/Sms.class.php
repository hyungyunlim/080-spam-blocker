<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//15
namespace UCP\Modules;
use \UCP\Modules as Modules;

class Sms extends Modules{
	protected $module = 'Sms';
	private $objSmsplus = false;
	private $userID = null;
	private int $limit = 25;
	private array $dids = [];

	function __construct($Modules) {
		$this->Modules = $Modules;
		$this->user = $this->UCP->User->getUser();
		$this->userID = $this->user['id'] ?? '';
		$this->sms = $this->UCP->FreePBX->Sms;
		$dids = $this->sms->getDIDs(($this->user['id'] ?? ''));
		$dids = is_array($dids) ? $dids : [];
		foreach($dids as $did) {
			$adaptor = $this->sms->getAdaptor($did);
			if(is_object($adaptor) && method_exists($adaptor,"showDID") && $adaptor->showDID($this->user['id'], $did)) {
				$this->dids[] = $did;
			} elseif(is_object($adaptor) && !method_exists($adaptor,"showDID")) {
				$this->dids[] = $did;
			}
		}
		if (\FreePBX::Modules()->checkStatus('smsplus')) {
			$this->objSmsplus = \FreePBX::Smsplus()->getObject();
		}
	}

	public function getWidgetList() {
		$responseData = array(
			"rawname" => "sms",
			"display" => _("SMS"),
			"icon"    => "fa fa-comments-o",
			"list"	  => []
		);
		$errors = $this->validate();
		if ($errors['hasError']) {
			return array_merge($responseData, $errors);
		}

		$widgets = [];

		$dids = $this->sms->getDIDs($this->userID);
		if (!empty($dids)) {
			foreach($dids as $did) {
				$widgets[$did] = ["display" => $did, "description" => sprintf(_("SMS for %s"),$did), "hasSettings" => false, "minsize" => ["height" => 5, "width" => 5], "defaultsize" => ["height" => 5, "width" => 6]];
			}
		}

		$responseData['list'] = $widgets;
		return $responseData;
	}

	/**
	 * validate against rules
	 */
	private function validate($extension = false) {
		$data = array(
			'hasError' => false,
			'errorMessages' => []
		);

		$dids = $this->sms->getDIDs($this->userID);
		if (empty($dids)) {
			$data['hasError'] = true;
			$data['errorMessages'][] = _('No SMS DIDs assigned to this user.');
		}

		return $data;
	}

	public function getWidgetDisplay($id) {
		$errors = $this->validate($id);
		if ($errors['hasError']) {
			return $errors;
		}
		$displayvars = ["did" => $id];
		return ['title' => _("SMS"), 'html' => $this->load_view(__DIR__.'/views/widget.php',$displayvars)];
	}

	public function getWidgetSettingsDisplay($id) {
		$displayvars = [];
		return ['title' => _("SMS"), 'html' => $this->load_view(__DIR__.'/views/settings.php',$displayvars)];
	}


	function poll($mdata) {
		if(empty($this->dids)) {
			return ['status' => false, 'lastchecked' => time()];
		}
		$messages = [];
		//see if there are any new messages since the last checked time
		$mdata['lastchecked'] = !empty($mdata['lastchecked']) ? $mdata['lastchecked'] : null;
		$newmessages = $this->sms->getMessagesSinceTime($this->userID,$mdata['lastchecked']);
		$unread = $this->sms->getUnreadCount($this->userID);
		if(!empty($newmessages)) {
			foreach($newmessages as $messageb) {
				$mid = $messageb['emid'];
				if(in_array($messageb['from'],$this->dids)) {
					$messageb['did'] = $messageb['from'];
					$messageb['recp'] = $messageb['to'];
					$from = $messageb['from'];
					$to = $messageb['to'];
				} else {
					$messageb['did'] = $messageb['to'];
					$messageb['recp'] = $messageb['from'];
					$from = $messageb['to'];
					$to = $messageb['from'];
				}
				$wid = $from.$to;
				$threadid = sha1($wid);
				if ($threadid !== $messageb['threadid']) {
					continue;
				}
				$messageb['cnam'] = !empty($messageb['cnam']) ? $messageb['cnam'] : $messageb['from'];
				$html = $this->getMessageHtmlByID($messageb['id']);
				$messageb['body'] = !empty($html) ? $html : htmlentities((string) $messageb['body']);
				$messageb['html'] = !empty($html) ? true : false;
				$messages[$wid][$mid] = $messageb;
			}
		}

		//get all messages from open windows that weren't picked up from lastcheck
		if(!empty($mdata['messageWindows'])) {
			foreach($mdata['messageWindows'] as $window) {
				$msgs = $this->sms->getAllMessagesAfterEMID($this->userID,$window['from'],$window['to'],$window['last']);
				$wid = $window['windowid'];
				$threadid = sha1((string) $wid);
				foreach($msgs as $messageb) {
					if ($threadid !== $messageb['threadid']) {
						continue;
					}
					$mid = $messageb['emid'];
					if(in_array($messageb['from'],$this->dids)) {
						$messageb['did'] = $messageb['from'];
						$messageb['recp'] = $messageb['to'];
						$from = $messageb['from'];
						$to = $messageb['to'];
					} else {
						$messageb['did'] = $messageb['to'];
						$messageb['recp'] = $messageb['from'];
						$from = $messageb['to'];
						$to = $messageb['from'];
					}
					$messageb['cnam'] = !empty($messageb['cnam']) ? $messageb['cnam'] : $messageb['from'];
					if(!isset($messages[$wid][$mid])) {
						$html = $this->getMessageHtmlByID($messageb['id']);
						$messageb['body'] = !empty($html) ? $html : htmlentities((string) $messageb['body']);
						$messageb['html'] = !empty($html) ? true : false;
						$messages[$wid][$mid] = $messageb;
					}
				}
			}
		}

		//reset array keys so they don't get out of control
		foreach($messages as $windowid => &$m) {
			$m = array_values($m);
			$m = array_reverse($m);
		}
		$count = count($messages);
		return ['status' => true, 'messages' => $messages, 'total' => $unread, 'lastchecked' => time()];
	}

	public function getChatHistory($from, $to, $newWindow) {
		$start = ($newWindow == 'true') ? 0 : 1;
		$messages = $this->sms->getAllDeliveredMessages($this->userID,$from,$to,$start,10);
		$final = [];
		if(!empty($messages)) {
			foreach($messages as $m) {
				$html = $this->getMessageHtmlByID($m['id']);
				if(!empty($html)) {
                                         $final['messages'][] = array(
                                        'id' => $m['emid'],
                                        'from' => in_array($m['from'],$this->dids) ? _('Me') : $this->replaceDIDwithDisplay($m['from']),
                                        'message' => $html,
                                        'date' => $m['timestamp'],
                                        'direction' => $m['direction']
					);
                                }
                                $body = $this->UCP->emoji->toImage($m['body']);
				$final['messages'][] = array(
					'id' => $m['emid'],
					'from' => in_array($m['from'],$this->dids) ? _('Me') : $this->replaceDIDwithDisplay($m['from']),
					'message' => $body,
					'date' => $m['timestamp'],
					'direction' => $m['direction']
				);
			}
			$final['lastMessage'] = $final['messages'][0];
			$final['messages'] = array_reverse($final['messages']);
		} else {
			$final = ['messages' => [], 'lastMessage' => ''];
		}
		return $final;
	}

	public function getOldMessages($emid,$from,$to) {
		$emid = null;
  $messages = $this->sms->getMessagesOlderThanEMID($this->userID,$emid,$from,$to,10);
		$final = [];
		if(!empty($messages)) {
			foreach($messages as $m) {
				$html = $this->getMessageHtmlByID($m['id']);
				$body = !empty($html) ? $html : $this->UCP->emoji->toImage($m['body']);
				$final[] = ['id' => $m['emid'], 'from' => in_array($m['from'],$this->dids) ? _('Me') : $this->replaceDIDwithDisplay($m['from']), 'message' => $body, 'date' => $m['timestamp'], 'direction' => $m['direction']];
			}
			$final = array_reverse($final);
		} else {
			$final = [];
		}
		return $final;
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
		return match ($command) {
      'upload', 'messages', 'history', 'delivered', 'read', 'send', 'dids', 'delete', 'contacts', 'grid', 'media', 'deletemany' => true,
      default => false,
  };
	}

	public function getMessageHtmlByID($id) {
		$medias = $this->sms->getMediaByID($id);
		if(empty($medias)) {
			return '';
		}
		$html = '';
		foreach($medias as $data) {
			switch($data['type']) {
				case "img":
					$link = 'ajax.php?module=sms&command=media&name='.$data['link'];
					$html .= '<a href="'.$link.'" target="_blank"><img src="'.$link.'" style="width: 100px;"></a>';
				break;
				case "text":
					$data['data'] = function_exists('mb_convert_encoding') ? mb_convert_encoding((string) $data['data'],'UTF-8','UTF-8') : htmlentities((string) $data['data']);
					$html .= $this->UCP->emoji->toImage($data['data']);
				break;
				default:
					$link = 'ajax.php?module=sms&command=media&name='.$data['link'];
					$html .= '<a href="'.$link.'" target="_blank"><i class="fa fa-file" aria-hidden="true"></i> '.$data['link'].'</a>';
				break;
			}
			$html .= "</br>";
		}
		return $html;
	}

	/**
	 * The Handler for all ajax events releated to this class
	 *
	 * Used by Ajax Class to process commands
	 *
	 * @return mixed Output if success, otherwise false will generate a 500 error serverside
	 */
	function ajaxHandler() {
		$return = ["status" => false, "message" => ""];
		switch($_REQUEST['command']) {
			case 'upload':
				foreach ($_FILES["files"]["error"] as $key => $error) {
					if ($error == UPLOAD_ERR_OK) {
						$tmp_path = \FreePBX::Config()->get("ASTSPOOLDIR") . "/tmp";
						if(!file_exists($tmp_path)) {
							mkdir($tmp_path,0777,true);
						}

						$extension = pathinfo((string) $_FILES["files"]["name"][$key], PATHINFO_EXTENSION);
						$supported = ["png", "jpg", "jpeg", "gif", "tiff", "pdf", "vcf", "mp3", "wav", "ogg", "mov", "avi", "mp4", "m4a", "ical", "ics"];
						if (in_array($extension, $supported)) {
							$tmp_name = $_FILES["files"]["tmp_name"][$key];
							$name = \Media\Media::cleanFileName($_FILES["files"]["name"][$key]);
							$fid = uniqid('sms');
							move_uploaded_file($tmp_name, $tmp_path."/".$fid."-".$name.".".$extension );
							$size = filesize($tmp_path."/".$fid."-".$name.".".$extension);
							if($size > 1_500_000) {
								$return['message'] = "<span class='text-danger'>"._('File Size is too large. Max: 1.5mb')."</span>";
								break;
							}
							$files = [$tmp_path."/".$fid."-".$name.".".$extension];
							$did = $_REQUEST['from'];
							$adaptor = $this->sms->getAdaptor($did);
							if(is_object($adaptor)) {
								$name = !empty($this->user['fname']) ? $this->user['fname'] : $this->user['username'];
								$o = $adaptor->sendMedia($this->formatNumber($_REQUEST['to']),$this->formatNumber($did),$name,"",$files);
								if($o['status']) {
									$return['status'] = true;
									$return['id'] = $o['id'];
									$return['emid'] = $o['emid'];
									$return['html'] = $this->getMessageHtmlByID($o['id']);
								} else {
									$return['message'] = "<span class='text-danger'>".$o['message']."</span>";
									break;
								}
							} else {
								$return['message'] = "<span class='text-danger'>"._("Adaptor not loaded")."</span>";
								break;
							}
						} else {
							$return = ["status" => false, "message" => "<span class='text-danger'>"._("Unsupported file format")."</span>"];
							break;
						}
					}
				}
			break;
			case 'messages':
				$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : '';
				$this->sms->markAllMessagesReadByDIDs($_REQUEST['from'],$_REQUEST['to']);
				$t = $this->sms->getAllMessages($this->userID,$_REQUEST['from'],$_REQUEST['to'],$search);
				$final = [];
				foreach($t as $m) {
					$html = $this->getMessageHtmlByID($m['id']);
					if(!empty($html)) {
						$mme = $m;
						$mme['body'] = $html;
						$final[] = $mme;
					}
					$final[] = $m;
				}
				return $final;
			break;
			case 'grid':
				$sort = $_REQUEST['sort'];
				$order = $_REQUEST['order'];
				$limit = $_REQUEST['limit'];
				$offset = $_REQUEST['offset'];
				$did = $_REQUEST['did'];
				$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : '';
				$data = $this->sms->getUserConversationsByDID($this->userID,$did,$search,$order,$sort,$offset,$limit);
				return ["total" => $data['total'], "rows" => $data['conversations']];
			break;
			case 'contacts':
				$return = [];
				if($this->Modules->moduleHasMethod('Contactmanager','lookupMultiple')) {
					$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : "";
					$results = $this->Modules->Contactmanager->lookupMultiple($search);
					if(!empty($results)) {
						foreach($results as $res) {
							foreach($res['numbers'] as $type => $num) {
								if(!empty($num)) {
									$return[] = ["value" => $num, "text" => $res['displayname'] . " (".$type.")"];
								}
							}
						}
					}
				}
				break;
			case 'delete':
				$this->sms->deleteConversations($this->userID, $_REQUEST['from'], $_REQUEST['to'],$_REQUEST['threadid']);
				$return['status'] = true;
			break;
			case 'deletemany':
				foreach($_POST['threads'] as $thread) {
					$this->sms->deleteConversationsByThreadID($this->userID,$thread);
				}
				$return['status'] = true;
			break;
			case 'history':
				$messages = $this->getOldMessages($_POST['id'],$_POST['from'],$_POST['to']);
				$return['status'] = true;
				$final = [];
				foreach($messages as $m) {
					$html = $this->getMessageHtmlByID($m['id']);
					if(!empty($html)) {
						$m['body'] = $html;
					}
					$final[] = $m;
				}
				$return['messages'] = $messages;
			break;
			case 'dids':
				$return['status'] = true;
				$return['dids'] = $this->dids;
			break;
			case 'read':
				$this->sms->markMessageRead($_POST['id']);
			break;
			case 'delivered':
				foreach($_POST['ids'] as $id) {
					$this->sms->markMessageDelivered($id);
				}
			break;
			case 'send':
				$did = $_POST['from'];
				if (!empty($_POST['to']) && $this->objSmsplus) {
					$getStatus = $this->objSmsplus->getDBTableSmsBlock($_POST['to']);
					if ($getStatus['status']) {
						$return['message'] = "<span class='text-danger'>"._("DID is blocklisted.")."</span>";
						break;
					}
				}
				if ($_POST['message'] != '' && \FreePBX::Modules()->checkStatus("blacklist") && ($this->objSmsplus) && strtoupper(substr((string) $_POST['message'], 0,5)) == 'BLOCK') {
					$addNumberToBlacklist = $this->objSmsplus->addNumberToBlacklist($_POST['message'], $_POST['to']);
					if ($addNumberToBlacklist) {
						if(!class_exists(\FreePBX\modules\Sms\AdaptorBaseSms::class)) {
							include_once dirname(__FILE__, 2) . '/includes/AdaptorBaseSms.class.php';
						}
						$AdaptorBaseSms = new \FreePBX\modules\Sms\AdaptorBaseSms();
						$adaptorName = $this->sms->getAdaptorNameByDID($did);
						$result = $AdaptorBaseSms->sendMessage($this->formatNumber($_POST['to']),$this->formatNumber($did),$name,$_POST['message'], null, $adaptorName, 'sms-'.uniqid());
						if (!empty($result)) {
							$return['status'] = true;
							$return['id'] = $result;
							$return['emid'] = 'sms-'.uniqid();
						}
						break;
					}
				}
				$adaptor = $this->sms->getAdaptor($did);
				if(is_object($adaptor)) {
					$name = !empty($this->user['fname']) ? $this->user['fname'] : $this->user['username'];
					$o = $adaptor->sendMessage($this->formatNumber($_POST['to']),$this->formatNumber($did),$name,$_POST['message']);
					if($o['status']) {
						$return['status'] = true;
						$return['id'] = $o['id'];
						$return['emid'] = $o['emid'];
					} else {
						$return['message'] = "<span class='text-danger'>".$o['message']."</span>";
					}
				} else {
					$return['message'] = "<span class='text-danger'>"._("Adaptor not loaded")."</span>";
				}
			break;
			default:
				return false;
			break;
		}
		return $return;
	}

	function ajaxCustomHandler() {
		switch($_REQUEST['command']) {
			case "media":
				$data = $this->sms->getMediaByName($_REQUEST['name']);
				if(!empty($data)) {
					$finfo = new \finfo(FILEINFO_MIME);
					header('Content-Type: '.$finfo->buffer($data));
					header("Content-Length: " .strlen((string) $data) );
					echo $data;
				}
				return true;
			break;
			default:
				return false;
			break;
		}
		return false;
	}

	private function formatNumber($number) {
		if(strlen((string) $number) == 10) {
			$number = '1'.$number;
		}
		return $number;
	}

	/**
	* Send settings to UCP upon initalization
	*/
	public function getStaticSettings() {
		if(!empty($this->dids)) {
			return ['enabled' => true, 'dids' => $this->dids];
		} else {
			return ['enabled' => false];
		}
	}

	public function replaceDIDwithDisplay($did) {
		return $this->UCP->FreePBX->Sms->replaceDIDwithDisplay($this->userID,$did);
	}
}
