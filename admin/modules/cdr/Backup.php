<?php

namespace FreePBX\modules\Cdr;

use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
  public function runBackup($id,$transaction){
    $dumpOtherOptions = [];
    $backupDetails = $this->FreePBX->Backup->getAll($id);
    if (isset($backupDetails['cdrStartDate']) && isset($backupDetails['cdrEndDate'])) {
      $startDate = $backupDetails['cdrStartDate'];
      $endDate = $backupDetails['cdrEndDate'];
      $query = 'calldate between "'.$startDate.'" and "'.$endDate.'"';
      $dumpOtherOptions[] = " --where='" . $query."'";
    }

    $dumpOtherOptions[] = '--opt --skip-lock-tables --skip-triggers --no-create-info --default-character-set=utf8mb4';
    $dumpOtherOptions = implode(" ", $dumpOtherOptions);

    $fileObj = $this->dumpTableIntoFile('cdr','cdr', $dumpOtherOptions, true);
    $this->addDirectories([$fileObj->getPath()]);
    $this->addConfigs([
      'settings' => $this->dumpAdvancedSettings(),
	'kvstore' => $this->dumpKVStore()
    ]);
  }
}
