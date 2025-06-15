<?php
namespace FreePBX\modules\Sms;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$tables = $this->getTablenames();
		$smstables = implode(' ', $tables);
		$this->dumpTableIntoFile('sms',$smstables, false, false);
		$this->addDependency('ucp');
	}

	private function getTablenames() {
		$module = strtolower((string) $this->data['module']);
		$this->log(sprintf(_("Exporting Databases from %s"), $module));
		$dir = $this->FreePBX->Config->get('AMPWEBROOT').'/admin/modules/'.$module;
		if(!file_exists($dir.'/module.xml')) {
			return [];
		}
		$xml = simplexml_load_file($dir.'/module.xml');
		$tables = [];
		if(is_object($xml->database->table)) {
			foreach($xml->database->table as $table) {
				$tname = (string)$table->attributes()->name;
			$tables[$tname] = $tname;
			}
		}
		return $tables;
	}
}
