<?php
namespace FreePBX\modules\Sms;
use FreePBX\modules\Backup as Base;
use Symfony\Component\Process\Process;

class Restore Extends Base\RestoreBase{

	public function runRestore(){
		$files = $this->getFiles();
		if(empty($files[0])) {
			return false;
		}
		$dump = $files[0];
		if ($dump->getType() != 'sql') {
			$configs = $this->getConfigs();
			$files = [];
			foreach ($this->getFiles() as $file) {
				$files[$file->getFileName()] = $file->getContents();
			}
			foreach($configs['sms_media'] as &$media) {
				$media['raw'] = $files[$media['name']] ?? '';
			}

			$tables = [
				'sms_media',
				'sms_routing',
				'sms_dids',
				'sms_messages',
			];

			foreach($tables as $table){
				$this->addDataToTableFromArray($table, $configs[$table]);
			}
		} else {
			$dumpfile = $this->tmpdir . '/files/' . ltrim((string) $dump->getPathTo(), '/') . '/' . $dump->getFilename();
			$this->processSms($dumpfile, false);
		}
	}

	public function processLegacy($pdo, $data, $tablelist, $unknowntables){
		$files = [];
		foreach (glob($this->tmpdir."/*.sql") as $filename) {
			$files[] = $filename;
		}
		foreach($files as $file) {
			if(exec('grep '.escapeshellarg("INSERT INTO `sms_routing`")." ".$file)) {
				$this->processSms($file, true);
			}
		}
	}

	public function processSms($file, $legacy) {
		global $amp_conf;
		if($legacy) {
			// Extracting the sms module data from mysqldump
			$info = new \SplFileInfo($file);
			$filePath = $info->getPath();
			$extractedFile = $filePath."/sms.sql";
			$command = "sed -n -e '/DROP TABLE.*`sms_dids`/,/INSERT INTO `sms_routing`/p' $file > $extractedFile";
			$process = \freepbx_get_process_obj($command);
			$process->mustRun();
			$out = $process->getOutput();
			$this->log(sprintf(_("Extract sms module tables from mysqldump Done....  %s  "), $out));
			$file = $extractedFile;
		}

		$fdbuser = $this->FreePBX->Config->get('AMPDBUSER') ?: $amp_conf['AMPDBUSER'];
		$fdbpass = $this->FreePBX->Config->get('AMPDBPASS') ?: $amp_conf['AMPDBPASS'];
		$dbname = $this->FreePBX->Config->get('AMPDBNAME') ?: 'asterisk';
		$fdbpass = escapeshellarg((string) $fdbpass);

		//Restoring the sms module mysqldump
		$command = "mysql -u $fdbuser -p$fdbpass $dbname < $file";
		$process = \freepbx_get_process_obj($command);
		$process->mustRun();
		$out = $process->getOutput();
		$this->log(sprintf(_("Processing sms module Done....  %s  "), $out));
	}
}
